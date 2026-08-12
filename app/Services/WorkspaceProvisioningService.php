<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\CosmicUnlock;
use App\Models\CosmicSparkFavorite;
use App\Models\CosmicTemplateFavorite;
use App\Models\PaymentOrder;
use App\Models\PendingOnboarding;
use App\Models\Page;
use App\Models\TrialGeneration;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceProvisioning;
use App\Models\WorkspaceProvisioningLog;
use App\AI\Registries\IndustryMenuRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\SubscriptionStatus;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WorkspaceProvisioningService
{
    /**
     * Continue the paid-account provisioning pipeline exactly once.
     * Patch 4.3 creates the owned workspace/site and safely transfers the trial draft.
     * Every stage is idempotent so callback/webhook retries reuse the same records.
     */
    public function start(PendingOnboarding $onboarding, PaymentOrder $order, string $source): WorkspaceProvisioning
    {
        try {
            return DB::transaction(function () use ($onboarding, $order, $source) {
                $onboarding = PendingOnboarding::query()
                    ->with(['user', 'trialGeneration'])
                    ->lockForUpdate()
                    ->findOrFail($onboarding->id);
                $order = PaymentOrder::query()->lockForUpdate()->findOrFail($order->id);

                if (! $order->fulfilled_at || $order->status !== 'paid') {
                    throw new RuntimeException('Provisioning requires a confirmed paid order.');
                }

                if ((int) $order->user_id !== (int) $onboarding->user_id) {
                    throw new RuntimeException('Payment order and onboarding account do not match.');
                }

                $provisioning = WorkspaceProvisioning::query()->firstOrCreate(
                    ['pending_onboarding_id' => $onboarding->id],
                    [
                        'user_id' => $onboarding->user_id,
                        'payment_order_id' => $order->id,
                        'status' => WorkspaceProvisioning::STATUS_PENDING,
                        'metadata' => ['created_by' => $source],
                    ],
                );

                $provisioning = WorkspaceProvisioning::query()->lockForUpdate()->findOrFail($provisioning->id);

                if ($provisioning->payment_order_id && (int) $provisioning->payment_order_id !== (int) $order->id) {
                    throw new RuntimeException('This onboarding already belongs to another payment order.');
                }

                if ($provisioning->workspace_id && $provisioning->website_id && $provisioning->trial_page_id && in_array($provisioning->status, [
                    WorkspaceProvisioning::STATUS_TRIAL_READY,
                    WorkspaceProvisioning::STATUS_ACCESS_READY,
                    WorkspaceProvisioning::STATUS_PROFILE_READY,
                    WorkspaceProvisioning::STATUS_SUBSCRIPTION_READY,
                    WorkspaceProvisioning::STATUS_COMPLETED,
                ], true)) {
                    return $provisioning->fresh(['workspace', 'website', 'trialPage']);
                }

                $provisioning->forceFill([
                    'payment_order_id' => $order->id,
                    'status' => WorkspaceProvisioning::STATUS_PROCESSING,
                    'attempts' => $provisioning->attempts + 1,
                    'started_at' => $provisioning->started_at ?? now(),
                    'failed_at' => null,
                    'next_retry_at' => null,
                    'last_error' => null,
                    'metadata' => array_merge($provisioning->metadata ?? [], [
                        'last_source' => $source,
                        'last_attempt_at' => now()->toIso8601String(),
                    ]),
                ])->save();

                $this->log($provisioning, 'attempt_started', 'processing', 'Provisioning attempt started.', [
                    'source' => $source,
                    'payment_order_id' => $order->id,
                ]);

                $workspace = $this->workspaceFor($onboarding);
                $website = $this->websiteFor($onboarding, $workspace, $provisioning);
                $trialPage = $this->transferTrialDraft($onboarding, $website, $provisioning);
                $this->provisionTrialMenuPages($onboarding, $website, $trialPage);
                app(MediaPackOwnershipService::class)->claimForWebsite($onboarding->trialGeneration, $website);
                $this->ensureOwnershipAndAccess($onboarding, $workspace, $website);
                $this->applyDefaultSettingsAndProfile($onboarding, $workspace, $website);
                $binding = $this->bindCreditsAndSubscription($onboarding, $order, $workspace, $website);
                app(PlanBuiltInSparkGrantService::class)->ensure($onboarding->user);
                $plan = config('payments.plans.'.$onboarding->selected_plan, []);

                $provisioning->forceFill([
                    'workspace_id' => $workspace->id,
                    'website_id' => $website->id,
                    'trial_page_id' => $trialPage->id,
                    'bound_plan_key' => $binding['plan_key'],
                    'bound_subscription_id' => $binding['subscription_id'],
                    'monthly_credits' => $binding['monthly_credits'],
                    'credit_balance_at_binding' => $binding['credit_balance'],
                    'subscription_bound_at' => $provisioning->subscription_bound_at ?? now(),
                    'status' => WorkspaceProvisioning::STATUS_SUBSCRIPTION_READY,
                    'last_error' => null,
                    'metadata' => array_merge($provisioning->metadata ?? [], [
                        'workspace_ready_at' => data_get($provisioning->metadata, 'workspace_ready_at', now()->toIso8601String()),
                        'website_ready_at' => data_get($provisioning->metadata, 'website_ready_at', now()->toIso8601String()),
                        'trial_transferred_at' => now()->toIso8601String(),
                        'access_ready_at' => data_get($provisioning->metadata, 'access_ready_at', now()->toIso8601String()),
                        'profile_ready_at' => data_get($provisioning->metadata, 'profile_ready_at', now()->toIso8601String()),
                        'subscription_ready_at' => now()->toIso8601String(),
                        'subscription_id' => $binding['subscription_id'],
                        'payment_provider' => $binding['provider'],
                        'monthly_credits' => $binding['monthly_credits'],
                        'credit_balance' => $binding['credit_balance'],
                        'next_billing_at' => $binding['next_billing_at'],
                        'owner_user_id' => $onboarding->user_id,
                        'workspace_role' => 'owner',
                        'trial_generation_id' => $onboarding->trial_generation_id,
                        'trial_page_id' => $trialPage->id,
                        'plan_key' => $onboarding->selected_plan,
                        'plan_name' => $plan['label'] ?? Str::headline($onboarding->selected_plan),
                        'website_limit' => $this->websiteLimit($onboarding->selected_plan),
                    ]),
                ])->save();

                $this->log($provisioning, 'attempt_succeeded', WorkspaceProvisioning::STATUS_SUBSCRIPTION_READY, 'Provisioning reached subscription-ready.', [
                    'workspace_id' => $workspace->id,
                    'website_id' => $website->id,
                    'trial_page_id' => $trialPage->id,
                ]);

                $onboarding->forceFill([
                    'workspace_id' => $workspace->id,
                    'website_id' => $website->id,
                    'status' => 'completed',
                    'completed_at' => $onboarding->completed_at ?? now(),
                    'metadata' => array_merge($onboarding->metadata ?? [], [
                        'provisioning_id' => $provisioning->id,
                        'workspace_id' => $workspace->id,
                        'website_id' => $website->id,
                        'provisioning_started_at' => data_get($onboarding->metadata, 'provisioning_started_at', now()->toIso8601String()),
                        'website_created_at' => data_get($onboarding->metadata, 'website_created_at', now()->toIso8601String()),
                        'trial_transferred' => (bool) $onboarding->trial_generation_id,
                        'trial_page_id' => $trialPage->id,
                        'trial_transferred_at' => now()->toIso8601String(),
                        'access_ready_at' => data_get($provisioning->metadata, 'access_ready_at', now()->toIso8601String()),
                        'profile_ready_at' => data_get($provisioning->metadata, 'profile_ready_at', now()->toIso8601String()),
                        'subscription_ready_at' => now()->toIso8601String(),
                        'subscription_id' => $binding['subscription_id'],
                        'payment_provider' => $binding['provider'],
                        'monthly_credits' => $binding['monthly_credits'],
                        'credit_balance' => $binding['credit_balance'],
                        'next_billing_at' => $binding['next_billing_at'],
                        'owner_user_id' => $onboarding->user_id,
                        'workspace_role' => 'owner',
                        'payment_confirmed_at' => data_get($onboarding->metadata, 'payment_confirmed_at', now()->toIso8601String()),
                        'paypal_subscription_id' => $order->external_subscription_id,
                    ]),
                ])->save();

                $provisioning->forceFill([
                    'status' => WorkspaceProvisioning::STATUS_COMPLETED,
                    'completed_at' => $provisioning->completed_at ?? now(),
                    'last_error' => null,
                    'metadata' => array_merge($provisioning->metadata ?? [], [
                        'completed_at' => now()->toIso8601String(),
                    ]),
                ])->save();

                $onboarding->user->forceFill([
                    'onboarding_status' => 'complete',
                ])->save();

                $this->log($provisioning, 'provisioning_completed', WorkspaceProvisioning::STATUS_COMPLETED, 'Workspace provisioning completed.', [
                    'workspace_id' => $workspace->id,
                    'website_id' => $website->id,
                ]);

                return $provisioning->fresh(['workspace', 'website', 'trialPage']);
            });
        } catch (Throwable $exception) {
            $this->markFailed($onboarding, $order, $source, $exception);
            throw $exception;
        }
    }

    private function workspaceFor(PendingOnboarding $onboarding): Workspace
    {
        $user = $onboarding->user;
        if (! $user) {
            throw new RuntimeException('The onboarding account no longer exists.');
        }

        $workspace = Workspace::query()->firstOrCreate(
            ['owner_user_id' => $user->id],
            [
                'name' => $onboarding->website_name.' Workspace',
                'slug' => $this->uniqueSlug($onboarding->website_slug, $user->id),
            ],
        );

        DB::table('workspace_user')->updateOrInsert(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            ['role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
        );

        return $workspace;
    }

    private function websiteFor(PendingOnboarding $onboarding, Workspace $workspace, WorkspaceProvisioning $provisioning): Website
    {
        $existingId = (int) ($provisioning->website_id ?: $onboarding->website_id ?: data_get($onboarding->metadata, 'website_id', 0));
        if ($existingId > 0) {
            $existing = Website::query()
                ->where('user_id', $onboarding->user_id)
                ->where('workspace_id', $workspace->id)
                ->find($existingId);

            if ($existing) {
                return $existing;
            }
        }

        $existing = Website::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $onboarding->user_id)
            ->oldest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $trial = $onboarding->trialGeneration;
        $menu = collect($trial?->menu_structure ?? [])
            ->map(fn ($item) => [
                'label' => (string) ($item['title'] ?? $item['label'] ?? 'Page'),
                'url' => (string) ($item['slug'] ?? $item['url'] ?? '#'),
            ])
            ->values()
            ->all();

        if ($menu === []) {
            $menu = [
                ['label' => 'Home', 'url' => 'home'],
                ['label' => 'About', 'url' => 'about'],
                ['label' => 'Services', 'url' => 'services'],
                ['label' => 'Contact', 'url' => 'contact'],
            ];
        }

        $themeSettings = $trial?->preview_theme;
        if (! is_array($themeSettings) || $themeSettings === []) {
            // Direct Pricing purchase: Midnight is the default preset and its
            // exact family palette is also registered immediately as My Brand Theme.
            $themeSettings = app(MyBrandThemeService::class)->ensureInSettings([
                'primary' => 'midnight',
                'secondary' => 'white',
                'tertiary' => 'stone',
                'auto' => true,
            ], 'midnight');
        } else {
            // Trial purchase: preserve every theme/custom-brand edit from Builder.
            $themeSettings = app(MyBrandThemeService::class)->ensureInSettings(
                $themeSettings,
                (string) data_get($themeSettings, 'custom_brand_theme.base_family', 'midnight')
            );
        }

        $entitlementSeed = (string) (
            data_get($themeSettings, 'primary') === 'my-brand'
                ? data_get($themeSettings, 'custom_brand_theme.base_family', 'midnight')
                : data_get($themeSettings, 'primary', 'midnight')
        );

        $website = Website::query()->create([
            'user_id' => $onboarding->user_id,
            'workspace_id' => $workspace->id,
            'name' => $onboarding->website_name,
            'domain' => $this->preferredDomain($onboarding->website_slug),
            'industry' => $onboarding->industry,
            'location' => $onboarding->location,
            'business_description' => $onboarding->business_description,
            'contact_email' => $onboarding->user->email,
            'api_token' => Str::random(60),
            'settings' => [
                'theme_entitlement_seed' => $entitlementSeed,
            ],
            'theme_settings' => $themeSettings,
            'global_header' => [
                'type' => 'glassmorphism_header',
                'logo_text' => $onboarding->website_name,
                'logo_image_url' => '/storage/branding/your-logo.png',
                'logo_height' => 42,
                'logo_filter_key' => data_get($trial?->preview_theme, 'primary', 'midnight'),
                'cta_label' => 'Get Started',
                'cta_url' => '#contact',
                'menu' => $menu,
            ],
            'global_footer' => [
                'type' => 'minimal_footer',
                'theme' => 'white',
                'logo_text' => $onboarding->website_name,
                'logo_image_url' => '/storage/branding/your-logo.png',
                'logo_height' => 36,
                'logo_filter_key' => data_get($trial?->preview_theme, 'primary', 'midnight'),
                'copyright' => '© '.now()->year.' '.$onboarding->website_name.'. All rights reserved.',
            ],
        ]);

        // Promote a trial logo into permanent website branding storage after the
        // paid website has an ID, then sync header/footer/custom theme references.
        if ($trial && filled($trial->logo_url)) {
            $this->syncTrialWebsiteSettings($trial, $website);
        }

        return $website->fresh();
    }

    /**
     * Move the shared trial page into the paid website, or create an equivalent
     * draft from the latest saved trial blocks when the source page is missing.
     */
    private function transferTrialDraft(
        PendingOnboarding $onboarding,
        Website $website,
        WorkspaceProvisioning $provisioning,
    ): Page {
        $trial = $onboarding->trialGeneration;

        $recordedPageId = (int) (
            $provisioning->trial_page_id
            ?: data_get($provisioning->metadata, 'trial_page_id', 0)
            ?: data_get($onboarding->metadata, 'trial_page_id', 0)
        );

        if ($recordedPageId > 0) {
            $recorded = Page::query()
                ->where('website_id', $website->id)
                ->find($recordedPageId);

            if ($recorded) {
                $this->syncTrialWebsiteSettings($trial, $website);
                $this->claimTrial($trial, $onboarding, $recorded);

                return $recorded;
            }
        }

        if ($trial?->claimed_at && (int) $trial->claimed_by_user_id !== (int) $onboarding->user_id) {
            throw new RuntimeException('This trial draft has already been claimed by another account.');
        }

        $page = null;

        if ($trial?->page_id) {
            $sourcePage = Page::query()->lockForUpdate()->find($trial->page_id);

            if ($sourcePage) {
                // A retry may find that the previous attempt already moved the page.
                if ((int) $sourcePage->website_id !== (int) $website->id) {
                    $sourcePage->forceFill(['website_id' => $website->id]);
                }

                $sourcePage->forceFill([
                    'title' => $website->name,
                    'slug' => 'home',
                    'parent_id' => null,
                    'sort_order' => 1,
                    'page_type' => $sourcePage->page_type ?: 'standard',
                    'status' => 'draft',
                    'published_blocks' => null,
                    'published_html' => null,
                    'published_at' => null,
                    'last_published_at' => null,
                    'publish_error' => null,
                ])->save();

                $page = $sourcePage;
            }
        }

        if (! $page) {
            $page = $website->pages()->where('slug', 'home')->oldest('id')->first();
        }

        if (! $page) {
            $page = $website->pages()->create([
                'title' => $website->name,
                'slug' => 'home',
                'parent_id' => null,
                'sort_order' => 1,
                'page_type' => 'standard',
                'blocks' => $trial?->generated_blocks ?? [],
                'status' => 'draft',
            ]);
        } elseif ($trial && is_array($trial->generated_blocks) && $trial->generated_blocks !== []) {
            // generated_blocks is updated whenever the trial builder saves, so it
            // is the safest fallback if the original page was partially lost.
            $page->forceFill(['blocks' => $trial->generated_blocks])->save();
        }

        $this->syncTrialWebsiteSettings($trial, $website);
        $this->claimTrial($trial, $onboarding, $page);

        return $page->fresh();
    }

    /**
     * Turn the menu promised in Trial into real owned pages after payment.
     *
     * The transferred Trial page remains the Home page. Every other local menu
     * destination is provisioned once as an intentionally empty draft so the
     * customer never lands on a 404 and can choose how to build it in Builder.
     * Existing pages are reused and never have their blocks/content overwritten.
     */
    private function provisionTrialMenuPages(
        PendingOnboarding $onboarding,
        Website $website,
        Page $homePage,
    ): void {
        $trial = $onboarding->trialGeneration;
        $structure = is_array($trial?->menu_structure) && $trial->menu_structure !== []
            ? $trial->menu_structure
            : IndustryMenuRegistry::for((string) ($onboarding->industry ?: $website->industry ?: 'default'));

        $items = collect($structure)
            ->map(function ($item, int $index): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $title = trim((string) ($item['title'] ?? $item['label'] ?? ''));
                $raw = trim((string) ($item['slug'] ?? $item['url'] ?? ''));
                $isHome = (bool) ($item['is_home'] ?? false) || $index === 0 || strtolower($raw) === 'home' || $raw === '/';

                // External/action links belong in navigation but are not CMS pages.
                if (! $isHome && ($raw === '' || $raw === '#' || str_starts_with($raw, '#') || preg_match('/^(?:https?:|mailto:|tel:)/i', $raw))) {
                    return null;
                }

                $slug = $isHome
                    ? 'home'
                    : (Str::slug(trim($raw, '/')) ?: Str::slug($title));

                if ($slug === '') {
                    return null;
                }

                return [
                    'title' => $title !== '' ? $title : Str::headline($slug),
                    'slug' => $slug,
                    'is_home' => $isHome,
                    'sort_order' => max(1, (int) ($item['sort_order'] ?? ($index + 1))),
                    'page_type' => (string) ($item['page_type'] ?? 'standard'),
                ];
            })
            ->filter()
            ->unique('slug')
            ->values();

        if ($items->isEmpty()) {
            return;
        }

        $homeItem = $items->first(fn (array $item) => $item['is_home'] || $item['slug'] === 'home');
        if ($homeItem) {
            $homePage->forceFill([
                'title' => $homeItem['title'] ?: 'Home',
                'slug' => 'home',
                'parent_id' => null,
                'sort_order' => $homeItem['sort_order'],
                'page_type' => 'standard',
            ])->save();
        }

        $provisioned = [];
        foreach ($items as $item) {
            if ($item['is_home'] || $item['slug'] === 'home') {
                continue;
            }

            $existing = $website->pages()
                ->where('slug', $item['slug'])
                ->oldest('id')
                ->first();

            if ($existing) {
                // Never replace an existing customer's work on retries.
                $provisioned[] = $existing->slug;
                continue;
            }

            $page = $website->pages()->create([
                'title' => $item['title'],
                'slug' => $item['slug'],
                'parent_id' => null,
                'sort_order' => $item['sort_order'],
                'page_type' => $item['page_type'] ?: 'standard',
                'blocks' => [],
                'status' => 'draft',
            ]);

            $provisioned[] = $page->slug;
        }

        // Keep a lightweight marker for support/QA. Builder behavior itself is
        // driven by an empty blocks array, so no database migration is required.
        $settings = is_array($website->settings) ? $website->settings : [];
        $settings['post_purchase_pages'] = [
            'source' => $trial ? 'trial_menu' : 'industry_default',
            'home_page_id' => $homePage->id,
            'unbuilt_slugs' => array_values(array_unique($provisioned)),
            'provisioned_at' => data_get($settings, 'post_purchase_pages.provisioned_at', now()->toIso8601String()),
        ];
        $website->forceFill(['settings' => $settings])->save();
    }

    private function syncTrialWebsiteSettings(?TrialGeneration $trial, Website $website): void
    {
        if (! $trial) {
            return;
        }

        // Reload the trial inside the provisioning transaction so PayPal callback /
        // webhook retries always claim the very latest Builder save, logo and theme.
        $trial->refresh();

        $menu = collect($trial->menu_structure ?? [])
            ->map(fn ($item) => [
                'label' => (string) ($item['title'] ?? $item['label'] ?? 'Page'),
                'url' => (string) ($item['slug'] ?? $item['url'] ?? '#'),
            ])
            ->filter(fn ($item) => $item['label'] !== '')
            ->values()
            ->all();

        $themeSettings = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        $themeSettings = app(MyBrandThemeService::class)->ensureInSettings(
            $themeSettings,
            (string) data_get($themeSettings, 'custom_brand_theme.base_family', 'midnight')
        );

        // Promote every branding reference used by the Trial Builder, not only
        // trial_generations.logo_url. Theme-matched/original variants otherwise
        // keep pointing at temporary /storage/trials/... files after purchase.
        $paidLogoUrl = $this->promoteTrialBrandingAsset($trial->logo_url, $website);
        foreach (['brand_original_logo_url', 'brand_active_logo_url', 'brand_favicon_url'] as $key) {
            $promoted = $this->promoteTrialBrandingAsset(data_get($themeSettings, $key), $website);
            if ($promoted) {
                $themeSettings[$key] = $promoted;
            }
        }

        $variants = (array) data_get($themeSettings, 'brand_logo_variants', []);
        foreach ($variants as $themeKey => $variantUrl) {
            $promoted = $this->promoteTrialBrandingAsset($variantUrl, $website);
            if ($promoted) {
                $variants[$themeKey] = $promoted;
            }
        }
        if ($variants !== []) {
            $themeSettings['brand_logo_variants'] = $variants;
        }

        if (is_array(data_get($themeSettings, 'custom_brand_theme'))) {
            $customTheme = (array) data_get($themeSettings, 'custom_brand_theme');
            $promotedSource = $this->promoteTrialBrandingAsset($customTheme['source_logo_url'] ?? null, $website);
            if ($promotedSource) {
                $customTheme['source_logo_url'] = $promotedSource;
            } elseif ($paidLogoUrl && filled($customTheme['source_logo_url'] ?? null)) {
                $customTheme['source_logo_url'] = $paidLogoUrl;
            }
            $themeSettings['custom_brand_theme'] = $customTheme;
        }

        // The active Trial logo is the source of truth at purchase time. This
        // preserves uploaded, AI-generated, theme-matched, cropped and restored
        // logo states exactly as the user last saw them in Trial Builder.
        $activeLogoUrl = trim((string) data_get($themeSettings, 'brand_active_logo_url', ''));
        if ($activeLogoUrl === '') {
            $activeLogoUrl = $paidLogoUrl ?: '/storage/branding/your-logo.png';
        }

        $header = $website->global_header ?? [];
        if ($menu !== []) {
            $header['menu'] = $menu;
        }
        $header['logo_text'] = $trial->logo_company_name ?: $trial->business_name ?: $website->name;
        $header['logo_image_url'] = $activeLogoUrl;
        $header['logo_height'] = max(42, (int) data_get($themeSettings, 'logo_height', $header['logo_height'] ?? 60));
        $header['logo_max_width'] = max(220, (int) data_get($themeSettings, 'logo_max_width', $header['logo_max_width'] ?? 300));
        $header['logo_filter_key'] = data_get($themeSettings, 'primary', 'midnight');
        $header['overlay_header_on_banner'] = (bool) data_get($themeSettings, 'overlay_header_on_banner', $header['overlay_header_on_banner'] ?? false);

        $footer = $website->global_footer ?? [];
        $footer['type'] = $footer['type'] ?? 'minimal_footer';
        $footer['theme'] = $footer['theme'] ?? 'white';
        $footer['logo_text'] = $header['logo_text'];
        $footer['logo_image_url'] = $activeLogoUrl;
        $footer['logo_height'] = max(36, min(56, (int) data_get($themeSettings, 'logo_height', $footer['logo_height'] ?? 48)));
        $footer['logo_filter_key'] = $header['logo_filter_key'];
        $footer['copyright'] = $footer['copyright'] ?? ('© '.now()->year.' '.$website->name.'. All rights reserved.');

        $settings = is_array($website->settings) ? $website->settings : [];
        $seed = trim((string) data_get($settings, 'theme_entitlement_seed', ''));
        if ($seed === '') {
            $trialTheme = trim((string) (
                data_get($themeSettings, 'primary') === 'my-brand'
                    ? data_get($themeSettings, 'custom_brand_theme.base_family', 'midnight')
                    : data_get($themeSettings, 'primary', 'midnight')
            ));
            $settings['theme_entitlement_seed'] = $trialTheme ?: 'midnight';
        }

        // Carry L1 brand memory and the final trial branding state into the paid
        // website. Defaults may fill missing values later, but must never rebuild
        // or replace this transferred snapshot.
        $settings['brand_memory'] = [
            'brand_prompt' => $trial->brand_prompt ?: $trial->prompt,
            'latest_user_prompt' => $trial->latest_user_prompt ?: $trial->prompt,
            'brand_context' => is_array($trial->brand_context) ? $trial->brand_context : [],
            'prompt_history' => is_array($trial->prompt_history) ? $trial->prompt_history : [],
            'source_trial_id' => $trial->id,
        ];
        $settings['trial_transfer'] = array_merge((array) data_get($settings, 'trial_transfer', []), [
            'source_trial_id' => $trial->id,
            'last_saved_at' => optional($trial->last_saved_at)?->toIso8601String(),
            'logo_source' => $trial->logo_source,
            'logo_theme_sync_state' => $trial->logo_theme_sync_state,
            'logo_theme_sync_source' => $trial->logo_theme_sync_source,
            'logo_theme_synced_theme' => $trial->logo_theme_synced_theme,
        ]);

        $website->forceFill([
            'settings' => $settings,
            'theme_settings' => $themeSettings,
            'global_header' => $header,
            'global_footer' => $footer,
        ])->save();
    }

    /**
     * Copy one trial-scoped branding asset into permanent website storage.
     * Content-addressed filenames guarantee that a later save cannot reuse a
     * stale logo from an earlier provisioning attempt.
     */
    private function promoteTrialBrandingAsset(mixed $value, Website $website): ?string
    {
        $logoUrl = trim((string) $value);
        if ($logoUrl === '') {
            return null;
        }

        $urlPath = parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl;
        $urlPath = '/'.ltrim((string) $urlPath, '/');

        if (str_starts_with($urlPath, '/storage/website-branding/')) {
            return $urlPath;
        }

        if (! str_starts_with($urlPath, '/storage/')) {
            return $logoUrl;
        }

        $source = ltrim(substr($urlPath, strlen('/storage/')), '/');
        if ($source === '' || str_contains($source, '..') || ! Storage::disk('public')->exists($source)) {
            return null;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (! in_array($extension, ['png', 'svg', 'jpg', 'jpeg', 'webp'], true)) {
            $extension = 'png';
        }

        $bytes = Storage::disk('public')->get($source);
        $fingerprint = substr(hash('sha256', $bytes), 0, 16);
        $destination = "website-branding/logos/website-{$website->id}-{$fingerprint}.{$extension}";

        if (! Storage::disk('public')->exists($destination)) {
            Storage::disk('public')->put($destination, $bytes);
        }

        return Storage::disk('public')->url($destination);
    }

    /**
     * Backward-compatible wrapper retained for any internal callers.
     */
    private function promoteTrialLogo(TrialGeneration $trial, Website $website): ?string
    {
        return $this->promoteTrialBrandingAsset($trial->logo_url, $website);
    }

    private function claimTrial(
        ?TrialGeneration $trial,
        PendingOnboarding $onboarding,
        Page $page,
    ): void {
        if (! $trial) {
            return;
        }

        if ($trial->claimed_at && (int) $trial->claimed_by_user_id !== (int) $onboarding->user_id) {
            throw new RuntimeException('This trial draft has already been claimed by another account.');
        }

        $wasUnclaimed = $trial->claimed_at === null;

        // Preserve the guest asset library when the paid workspace claims the trial.
        // updateOrCreate makes callback/webhook retries idempotent.
        foreach (collect($trial->owned_sparks ?? [])->unique() as $key) {
            CosmicUnlock::query()->updateOrCreate(
                ['user_id' => $onboarding->user_id, 'unlock_type' => 'spark', 'unlock_key' => $key],
                ['credits_paid' => max(0, (int) (SparkCatalog::find((string) $key)['credits'] ?? 0)), 'is_installed' => true],
            );
        }
        foreach (collect($trial->owned_templates ?? [])->unique() as $key) {
            CosmicUnlock::query()->updateOrCreate(
                ['user_id' => $onboarding->user_id, 'unlock_type' => 'template', 'unlock_key' => $key],
                ['credits_paid' => PageTemplateCatalog::PURCHASE_CREDITS, 'is_installed' => true],
            );
        }
        foreach (collect($trial->favorite_sparks ?? [])->unique() as $key) {
            CosmicSparkFavorite::query()->firstOrCreate(['user_id' => $onboarding->user_id, 'spark_key' => $key]);
        }
        foreach (collect($trial->favorite_templates ?? [])->unique() as $key) {
            CosmicTemplateFavorite::query()->firstOrCreate(['user_id' => $onboarding->user_id, 'template_key' => $key]);
        }

        $trial->forceFill([
            'token' => $wasUnclaimed ? (string) Str::uuid() : $trial->token,
            'page_id' => $page->id,
            'claimed_at' => $trial->claimed_at ?? now(),
            'claimed_by_user_id' => $onboarding->user_id,
            'status' => 'claimed',
            'last_saved_at' => $trial->last_saved_at ?? now(),
        ])->save();
    }


    private function ensureOwnershipAndAccess(PendingOnboarding $onboarding, Workspace $workspace, Website $website): void
    {
        if ((int) $workspace->owner_user_id !== (int) $onboarding->user_id) {
            throw new RuntimeException('Provisioned workspace ownership does not match the paid account.');
        }

        DB::table('workspace_user')->updateOrInsert(
            ['workspace_id' => $workspace->id, 'user_id' => $onboarding->user_id],
            ['role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
        );

        $website->forceFill([
            'user_id' => $onboarding->user_id,
            'workspace_id' => $workspace->id,
        ])->save();
    }

    private function applyDefaultSettingsAndProfile(
        PendingOnboarding $onboarding,
        Workspace $workspace,
        Website $website,
    ): void {
        $user = $onboarding->user;
        if (! $user) {
            throw new RuntimeException('The onboarding account no longer exists.');
        }

        $timezone = (string) (data_get($onboarding->metadata, 'timezone') ?: $user->timezone ?: 'Asia/Manila');
        $locale = (string) (data_get($onboarding->metadata, 'locale') ?: $user->locale ?: 'en');
        $phone = data_get($onboarding->metadata, 'phone');

        $user->forceFill([
            'business_name' => $onboarding->website_name,
            'location' => $onboarding->location,
            'phone' => $user->phone ?: $phone,
            'industry' => $onboarding->industry,
            'timezone' => $user->timezone ?: $timezone,
            'locale' => $user->locale ?: $locale,
            'profile_settings' => array_merge($user->profile_settings ?? [], [
                'default_workspace_id' => $workspace->id,
                'default_website_id' => $website->id,
                'onboarding_source' => $onboarding->trial_generation_id ? 'trial' : 'registration',
            ]),
            'profile_completed_at' => $user->profile_completed_at ?? now(),
        ])->save();

        $workspace->forceFill([
            'name' => $workspace->name ?: $onboarding->website_name.' Workspace',
            'settings' => array_merge($workspace->settings ?? [], [
                'timezone' => $timezone,
                'locale' => $locale,
                'industry' => $onboarding->industry,
                'default_website_id' => $website->id,
                'owner_profile_email' => $user->email,
            ]),
        ])->save();

        $website->forceFill([
            'name' => $onboarding->website_name,
            'industry' => $onboarding->industry,
            'location' => $onboarding->location,
            'business_description' => $onboarding->business_description,
            'contact_email' => $user->email,
            'contact_phone' => $user->phone,
            'timezone' => $timezone,
            'locale' => $locale,
            'settings' => array_merge($website->settings ?? [], [
                'business_name' => $onboarding->website_name,
                'business_email' => $user->email,
                'business_phone' => $user->phone,
                'industry' => $onboarding->industry,
                'location' => $onboarding->location,
                'timezone' => $timezone,
                'locale' => $locale,
                'site_status' => 'draft',
                'seo' => [
                    'site_title' => $onboarding->website_name,
                    'site_description' => Str::limit($onboarding->business_description, 160, ''),
                ],
            ]),
        ])->save();
    }

    /**
     * Bind the already-confirmed subscription and wallet to provisioned resources.
     * This stage intentionally never grants credits. Credit grants remain owned by
     * PaymentFulfillmentService and its idempotent credit ledger references.
     */
    private function bindCreditsAndSubscription(
        PendingOnboarding $onboarding,
        PaymentOrder $order,
        Workspace $workspace,
        Website $website,
    ): array {
        $user = $onboarding->user?->fresh();
        $order = $order->fresh();

        if (! $user || ! $order) {
            throw new RuntimeException('Unable to load the paid account for subscription binding.');
        }

        if ($order->product_type !== 'plan' || $order->status !== 'paid' || ! $order->fulfilled_at) {
            throw new RuntimeException('A fulfilled plan payment is required before subscription binding.');
        }

        if ((int) $order->user_id !== (int) $user->id) {
            throw new RuntimeException('The subscription does not belong to the provisioned account.');
        }

        $planKey = (string) $order->product_key;
        $plan = config('payments.plans.'.$planKey);
        if (! is_array($plan)) {
            throw new RuntimeException('The paid plan configuration is unavailable.');
        }

        if ((string) $user->plan_key !== $planKey || SubscriptionStatus::normalize((string) $user->plan_status) !== SubscriptionStatus::ACTIVE) {
            throw new RuntimeException('The account subscription is not active or does not match the paid plan.');
        }

        if ((string) $user->plan_provider !== (string) $order->provider) {
            throw new RuntimeException('The account payment provider does not match the fulfilled order.');
        }

        $subscriptionId = (string) $order->external_subscription_id;
        if ($order->provider === 'paypal' && $subscriptionId === '') {
            throw new RuntimeException('The PayPal subscription ID is missing.');
        }

        $initialCreditReference = 'payment:'.$order->provider.':'.$order->reference;
        $creditGrant = CreditTransaction::query()
            ->where('user_id', $user->id)
            ->where('reference', $initialCreditReference)
            ->where('type', 'credit')
            ->first();

        if (! $creditGrant) {
            throw new RuntimeException('The confirmed payment credit grant has not been recorded yet.');
        }

        $monthlyCredits = (int) ($plan['credits'] ?? $order->credits);
        if ($monthlyCredits < 1 || (int) $order->credits !== $monthlyCredits) {
            throw new RuntimeException('The paid order credits do not match the selected plan allowance.');
        }

        $nextBillingAt = $user->plan_renews_at?->toIso8601String();
        $binding = [
            'plan_key' => $planKey,
            'plan_name' => (string) ($plan['label'] ?? Str::headline($planKey)),
            'provider' => (string) $order->provider,
            'subscription_id' => $subscriptionId,
            'payment_order_id' => $order->id,
            'monthly_credits' => $monthlyCredits,
            'credit_balance' => (int) $user->credits,
            'initial_credit_transaction_id' => $creditGrant->id,
            'next_billing_at' => $nextBillingAt,
            'bound_at' => now()->toIso8601String(),
        ];

        $workspace->forceFill([
            'settings' => array_merge($workspace->settings ?? [], [
                'billing' => array_merge(data_get($workspace->settings, 'billing', []), $binding),
                'plan_key' => $planKey,
                'website_limit' => $this->websiteLimit($planKey),
            ]),
        ])->save();

        $website->forceFill([
            'settings' => array_merge($website->settings ?? [], [
                'subscription' => [
                    'workspace_id' => $workspace->id,
                    'plan_key' => $planKey,
                    'plan_name' => $binding['plan_name'],
                    'provider' => $binding['provider'],
                    'payment_order_id' => $order->id,
                    'subscription_id' => $subscriptionId,
                    'monthly_credits' => $monthlyCredits,
                    'next_billing_at' => $nextBillingAt,
                    'bound_at' => $binding['bound_at'],
                ],
            ]),
        ])->save();

        return $binding;
    }

    /** Retry a paid onboarding safely. All stages are idempotent. */
    public function recover(WorkspaceProvisioning $provisioning, string $source = 'manual'): WorkspaceProvisioning
    {
        $provisioning->loadMissing(['pendingOnboarding', 'paymentOrder']);

        if (! $provisioning->pendingOnboarding || ! $provisioning->paymentOrder) {
            throw new RuntimeException('Provisioning recovery is missing its onboarding or payment order.');
        }

        if ($provisioning->status === WorkspaceProvisioning::STATUS_PROCESSING
            && $provisioning->updated_at?->isAfter(now()->subMinutes(15))) {
            throw new RuntimeException('Provisioning is already running. Please wait before retrying.');
        }

        $this->log($provisioning, 'recovery_requested', $provisioning->status, 'Provisioning recovery requested.', [
            'source' => $source,
        ]);

        return $this->start($provisioning->pendingOnboarding, $provisioning->paymentOrder, $source);
    }

    /** Recover stale/failed rows that are due. */
    public function recoverDue(int $limit = 100, bool $dryRun = false): array
    {
        $rows = WorkspaceProvisioning::query()
            ->where(function ($query) {
                $query->where(function ($failed) {
                    $failed->where('status', WorkspaceProvisioning::STATUS_FAILED)
                        ->where(function ($due) {
                            $due->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
                        });
                })->orWhere(function ($stale) {
                    $stale->where('status', WorkspaceProvisioning::STATUS_PROCESSING)
                        ->where('updated_at', '<=', now()->subMinutes(15));
                });
            })
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->get();

        $result = ['found' => $rows->count(), 'recovered' => 0, 'failed' => 0, 'dry_run' => $dryRun];
        if ($dryRun) return $result;

        foreach ($rows as $row) {
            try {
                $this->recover($row, 'reconciliation');
                $result['recovered']++;
            } catch (Throwable $e) {
                report($e);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function log(WorkspaceProvisioning $provisioning, string $event, ?string $stage, ?string $message = null, array $context = [], string $level = 'info'): void
    {
        try {
            WorkspaceProvisioningLog::query()->create([
                'workspace_provisioning_id' => $provisioning->id,
                'user_id' => $provisioning->user_id,
                'level' => $level,
                'event' => $event,
                'stage' => $stage,
                'message' => $message,
                'attempt' => (int) $provisioning->attempts,
                'context' => $context,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function markFailed(PendingOnboarding $onboarding, PaymentOrder $order, string $source, Throwable $exception): void
    {
        try {
            $provisioning = WorkspaceProvisioning::query()->firstOrCreate(
                ['pending_onboarding_id' => $onboarding->id],
                [
                    'user_id' => $onboarding->user_id,
                    'payment_order_id' => $order->id,
                    'status' => WorkspaceProvisioning::STATUS_FAILED,
                ],
            );

            $attempts = max(1, (int) $provisioning->attempts);
            $delayMinutes = min(60, 2 ** min($attempts, 5));

            $provisioning->forceFill([
                'status' => WorkspaceProvisioning::STATUS_FAILED,
                'failed_at' => now(),
                'next_retry_at' => now()->addMinutes($delayMinutes),
                'last_error' => Str::limit($exception->getMessage(), 2000),
                'metadata' => array_merge($provisioning->metadata ?? [], [
                    'last_source' => $source,
                    'last_failure_at' => now()->toIso8601String(),
                ]),
            ])->save();

            $this->log($provisioning, 'attempt_failed', WorkspaceProvisioning::STATUS_FAILED, Str::limit($exception->getMessage(), 1000), [
                'source' => $source,
                'next_retry_at' => $provisioning->next_retry_at?->toIso8601String(),
            ], 'error');

            $onboarding->forceFill([
                'status' => 'provisioning_failed',
                'metadata' => array_merge($onboarding->metadata ?? [], [
                    'provisioning_id' => $provisioning->id,
                    'provisioning_error' => Str::limit($exception->getMessage(), 1000),
                ]),
            ])->save();
        } catch (Throwable $loggingFailure) {
            report($loggingFailure);
        }
    }

    private function uniqueSlug(string $preferred, int $userId): string
    {
        $base = Str::slug($preferred) ?: 'workspace-'.$userId;
        $candidate = $base.'-'.$userId;
        $counter = 2;

        while (Workspace::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$userId.'-'.$counter++;
        }

        return $candidate;
    }

    private function preferredDomain(string $slug): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! $host || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return 'https://'.$slug.'.cosmiccms.test';
        }

        return 'https://'.$slug.'.'.$host;
    }

    private function websiteLimit(string $plan): ?int
    {
        $value = data_get(config('cosmic-plans.' . $plan), 'capabilities.max_sites', 1);

        return $value === null || $value === 'unlimited' ? null : max(0, (int) $value);
    }
}
