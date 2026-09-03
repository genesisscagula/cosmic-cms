<?php

namespace App\Services;

use App\Models\MarketplaceCheckout;
use App\Models\MarketplaceTemplate;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Support\HeaderFooterVariantContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class MarketplaceWebsiteProvisioningService
{
    public function provision(MarketplaceCheckout $checkout, ?Website $existingWebsite = null): Website
    {
        return DB::transaction(function () use ($checkout, $existingWebsite) {
            $checkout = MarketplaceCheckout::query()
                ->with(['template.pages.blocks', 'template.navigationItems.page', 'user'])
                ->lockForUpdate()
                ->findOrFail($checkout->id);

            $template = $checkout->template;
            $user = $checkout->user;

            if (! $template || ! $user) {
                throw new RuntimeException('Marketplace website provisioning is missing its template or account.');
            }

            $recordedWebsiteId = (int) data_get($checkout->metadata, 'website_id', 0);
            if ($recordedWebsiteId > 0) {
                $recorded = Website::query()->where('user_id', $user->id)->find($recordedWebsiteId);
                if ($recorded) {
                    return $recorded;
                }
            }

            $workspace = $existingWebsite?->workspace ?: $this->workspaceFor($user, $template);
            $website = $existingWebsite ?: $this->createWebsite($user, $workspace, $template, $checkout);

            $this->installTemplate($website, $template);
            $this->primeLuna($website, $template, $checkout);

            $checkout->forceFill([
                'status' => MarketplaceCheckout::STATUS_COMPLETED,
                'completed_at' => $checkout->completed_at ?? now(),
                'metadata' => array_merge($checkout->metadata ?? [], [
                    'workspace_id' => $workspace->id,
                    'website_id' => $website->id,
                    'template_installed_at' => now()->toIso8601String(),
                    'luna_personalization_status' => 'ready',
                    'luna_personalization_ready_at' => now()->toIso8601String(),
                ]),
            ])->save();

            return $website->fresh(['pages']);
        });
    }

    public function builderUrl(Website $website): string
    {
        $prompt = 'Help me personalize this Marketplace website for my business. Ask me for my business name, location, services, target customers, brand preferences, and any details you need before rewriting the website content and images.';

        return route('pages.index', $website).'?'.http_build_query([
            'luna_open' => 1,
            'luna_prompt' => $prompt,
            'marketplace_setup' => 1,
        ]);
    }

    private function workspaceFor(User $user, MarketplaceTemplate $template): Workspace
    {
        $workspace = $user->ownedWorkspaces()->oldest('id')->first();
        if ($workspace) {
            return $workspace;
        }

        $base = Str::slug($template->industry_slug ?: 'website') ?: 'website';
        $slug = $base.'-'.$user->id;
        $counter = 2;
        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$user->id.'-'.$counter++;
        }

        $workspace = Workspace::query()->create([
            'owner_user_id' => $user->id,
            'name' => trim((string) $user->name) !== '' ? $user->name.' Workspace' : $template->name.' Workspace',
            'slug' => $slug,
        ]);

        DB::table('workspace_user')->updateOrInsert(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            ['role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
        );

        return $workspace;
    }

    private function createWebsite(User $user, Workspace $workspace, MarketplaceTemplate $template, MarketplaceCheckout $checkout): Website
    {
        if ($message = app(AgencyWebsiteLimitService::class)->validationMessage($user)) {
            throw new RuntimeException($message);
        }

        $baseSlug = Str::slug($template->name) ?: 'website';
        $slug = $baseSlug.'-'.$checkout->id;
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $domain = (! $host || in_array($host, ['localhost', '127.0.0.1'], true))
            ? 'https://'.$slug.'.cosmiccms.test'
            : 'https://'.$slug.'.'.$host;

        return Website::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'name' => $template->name,
            'domain' => $domain,
            'industry' => $template->industry_label,
            'business_description' => (string) ($template->summary ?: $template->description ?: ''),
            'contact_email' => $user->email,
            'api_token' => Str::random(60),
            'page_style' => 'balanced',
            'published_page_style' => 'balanced',
            'theme_settings' => app(MyBrandThemeService::class)->ensureInSettings(
                (array) ($template->theme_settings ?? ['primary' => $template->theme_key ?: 'midnight']),
                (string) ($template->theme_key ?: 'midnight'),
            ),
            'global_header' => HeaderFooterVariantContract::normalizeHeader((array) ($template->global_header ?? [])),
            'global_footer' => HeaderFooterVariantContract::normalizeFooter((array) ($template->global_footer ?? [])),
            'settings' => [
                'marketplace' => [
                    'checkout_id' => $checkout->id,
                    'template_id' => $template->id,
                    'template_slug' => $template->slug,
                    'template_version' => $template->version,
                    'source' => 'marketplace',
                ],
            ],
        ]);
    }

    private function installTemplate(Website $website, MarketplaceTemplate $template): void
    {
        $template->loadMissing(['pages.blocks', 'navigationItems.page']);
        $pageMap = [];

        foreach ($template->pages->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $templatePage) {
            $page = $website->pages()->where('slug', $templatePage->slug)->oldest('id')->first();
            $attributes = [
                'title' => $templatePage->name,
                'slug' => $templatePage->is_home ? 'home' : $templatePage->slug,
                'sort_order' => max(1, (int) $templatePage->sort_order),
                'page_type' => $templatePage->page_intent ?: 'standard',
                'page_style' => $templatePage->page_style ?: 'balanced',
                'seo_title' => (string) data_get($templatePage->seo, 'title', ''),
                'meta_description' => (string) data_get($templatePage->seo, 'description', ''),
                'og_image_url' => data_get($templatePage->seo, 'og_image_url'),
                'canonical_url' => data_get($templatePage->seo, 'canonical_url'),
                'is_indexable' => (bool) data_get($templatePage->seo, 'is_indexable', true),
                'blocks' => $templatePage->blocks->map(fn ($block) => $block->toBuilderBlock())->values()->all(),
                'status' => 'draft',
                'published_blocks' => null,
                'published_html' => null,
                'published_at' => null,
                'last_published_at' => null,
                'publish_error' => null,
            ];

            if ($page) {
                $page->forceFill($attributes)->save();
            } else {
                $page = $website->pages()->create($attributes);
            }

            $pageMap[$templatePage->id] = $page;
        }

        foreach ($template->pages as $templatePage) {
            if (! $templatePage->parent_id || ! isset($pageMap[$templatePage->id], $pageMap[$templatePage->parent_id])) {
                continue;
            }
            $pageMap[$templatePage->id]->forceFill(['parent_id' => $pageMap[$templatePage->parent_id]->id])->save();
        }

        $header = (array) ($template->global_header ?? []);
        if ($header === []) {
            $header = [
                'type' => 'classic_header',
                'logo_text' => $website->name,
                'logo_image_url' => '/storage/branding/your-logo.png',
                'logo_height' => 42,
                'cta_label' => 'Get Started',
                'cta_url' => '#contact',
            ];
        }
        $header['menu'] = $this->navigation($template);
        $header = HeaderFooterVariantContract::normalizeHeader($header) ?? $header;

        $footer = (array) ($template->global_footer ?? []);
        if ($footer === []) {
            $footer = [
                'type' => 'minimal_footer',
                'logo_text' => $website->name,
                'copyright' => '© '.now()->year.' '.$website->name.'. All rights reserved.',
            ];
        }
        $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer;

        $themeSettings = app(MyBrandThemeService::class)->ensureInSettings(
            (array) ($template->theme_settings ?? []),
            (string) ($template->theme_key ?: 'midnight'),
        );
        $settings = is_array($website->settings) ? $website->settings : [];
        $settings['marketplace'] = array_merge((array) ($settings['marketplace'] ?? []), [
            'source' => 'marketplace',
            'template_id' => $template->id,
            'template_slug' => $template->slug,
            'template_version' => $template->version,
            'design_kit' => [
                'version' => 1,
                'theme_settings' => $themeSettings,
                'header' => $header,
                'footer' => $footer,
                'header_variant' => (string) ($header['type'] ?? 'classic_header'),
                'footer_variant' => (string) data_get($footer, 'mega_footer.variant', 'classic'),
                'page_patterns' => $template->pages->map(fn ($page) => [
                    'name' => $page->name,
                    'slug' => $page->slug,
                    'page_intent' => $page->page_intent,
                    'page_style' => $page->page_style,
                    'blocks' => $page->blocks->map(fn ($block) => $block->toBuilderBlock())->values()->all(),
                ])->values()->all(),
                'captured_at' => now()->toIso8601String(),
            ],
        ]);

        $website->forceFill([
            'theme_settings' => $themeSettings,
            'global_header' => $header,
            'global_footer' => $footer,
            'settings' => $settings,
        ])->save();
    }

    private function navigation(MarketplaceTemplate $template): array
    {
        $items = $template->navigationItems->sortBy([['sort_order', 'asc'], ['id', 'asc']]);

        $build = function ($parentId = null) use (&$build, $items): array {
            return $items
                ->filter(fn ($item) => (int) ($item->parent_id ?? 0) === (int) ($parentId ?? 0))
                ->map(function ($item) use (&$build) {
                    $url = trim((string) ($item->url ?? ''));
                    if ($item->page) {
                        $url = $item->page->is_home ? 'home' : $item->page->slug;
                    }
                    $children = $build($item->id);
                    return array_filter([
                        'label' => (string) $item->label,
                        'url' => $url !== '' ? $url : '#',
                        'target' => (string) ($item->target ?: '_self'),
                        'children' => $children !== [] ? $children : null,
                    ], static fn ($value) => $value !== null);
                })
                ->values()
                ->all();
        };

        $menu = $build();
        if ($menu !== []) {
            return $menu;
        }

        return $template->pages
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn ($page) => [
                'label' => $page->name,
                'url' => $page->is_home ? 'home' : $page->slug,
            ])
            ->values()
            ->all();
    }

    private function primeLuna(Website $website, MarketplaceTemplate $template, MarketplaceCheckout $checkout): void
    {
        $settings = is_array($website->settings) ? $website->settings : [];
        $settings['marketplace'] = array_merge((array) ($settings['marketplace'] ?? []), [
            'checkout_id' => $checkout->id,
            'template_id' => $template->id,
            'template_slug' => $template->slug,
            'template_version' => $template->version,
            'installed_at' => now()->toIso8601String(),
        ]);
        $settings['luna_marketplace_onboarding'] = [
            'status' => 'ready',
            'template_name' => $template->name,
            'industry' => $template->industry_label,
            'prompt' => 'Help me personalize this Marketplace website for my business. Ask me for my business name, location, services, target customers, brand preferences, and any details you need before rewriting the website content and images.',
            'prepared_at' => now()->toIso8601String(),
        ];

        $website->forceFill(['settings' => $settings])->save();
    }
}
