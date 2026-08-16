<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PaymentOrder;
use App\Models\PendingOnboarding;
use App\Models\TrialGeneration;
use App\Models\Website;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OnboardingWorkspaceService
{
    /**
     * Create the paid customer's workspace and website exactly once.
     * If a trial page exists, move it from the shared demo website into the
     * customer's new website while preserving its blocks and draft state.
     */
    public function finalize(PendingOnboarding $onboarding, ?PaymentOrder $order = null): Website
    {
        return DB::transaction(function () use ($onboarding, $order) {
            $onboarding = PendingOnboarding::query()
                ->with(['user', 'trialGeneration.page'])
                ->lockForUpdate()
                ->findOrFail($onboarding->id);

            $user = $onboarding->user;

            if (! $user) {
                throw new RuntimeException('The onboarding account no longer exists.');
            }

            $existingWebsiteId = (int) ($onboarding->website_id ?: data_get($onboarding->metadata, 'website_id', 0));
            if ($existingWebsiteId > 0) {
                $existing = Website::query()->where('user_id', $user->id)->find($existingWebsiteId);
                if ($existing) {
                    $this->syncProfileAndSettings($onboarding, $existing);
                    $this->markComplete($onboarding, $existing, $order);
                    return $existing;
                }
            }

            $workspace = $this->workspaceFor($onboarding);
            $website = $this->createWebsite($onboarding, $workspace);
            $this->transferTrial($onboarding->trialGeneration, $website);
            $this->ensureHomePage($onboarding, $website);
            $this->syncProfileAndSettings($onboarding, $website);
            $this->markComplete($onboarding, $website, $order);

            return $website->fresh();
        });
    }

    private function workspaceFor(PendingOnboarding $onboarding): Workspace
    {
        $user = $onboarding->user;
        $workspaceId = (int) ($onboarding->workspace_id ?: data_get($onboarding->metadata, 'workspace_id', 0));

        if ($workspaceId > 0) {
            $workspace = Workspace::query()->where('owner_user_id', $user->id)->find($workspaceId);
            if ($workspace) {
                return $workspace;
            }
        }

        $workspace = Workspace::query()->firstOrCreate(
            ['owner_user_id' => $user->id],
            [
                'name' => $onboarding->website_name.' Workspace',
                'slug' => $this->uniqueWorkspaceSlug($onboarding->website_slug, $user->id),
            ],
        );

        DB::table('workspace_user')->updateOrInsert(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            ['role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
        );

        $onboarding->update(['workspace_id' => $workspace->id]);

        return $workspace;
    }

    private function createWebsite(PendingOnboarding $onboarding, Workspace $workspace): Website
    {
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

        $themeSettings = is_array($trial?->preview_theme) && $trial->preview_theme !== []
            ? $trial->preview_theme
            : [
                'primary' => 'midnight',
                'secondary' => 'white',
                'tertiary' => 'stone',
                'auto' => true,
            ];

        // Paid onboarding must follow the same theme contract as Dashboard
        // website creation: My Brand Theme always exists. Direct/non-trial
        // purchases install Midnight; trial purchases preserve the trial family.
        $fallbackFamily = (string) data_get(
            $themeSettings,
            'custom_brand_theme.base_family',
            data_get($themeSettings, 'primary', 'midnight')
        );
        $themeSettings = app(MyBrandThemeService::class)->ensureInSettings($themeSettings, $fallbackFamily);

        $entitlementSeed = (string) (
            data_get($themeSettings, 'primary') === 'my-brand'
                ? data_get($themeSettings, 'custom_brand_theme.base_family', 'midnight')
                : data_get($themeSettings, 'primary', 'midnight')
        );

        return Website::create([
            'user_id' => $onboarding->user_id,
            'workspace_id' => $workspace->id,
            'name' => $onboarding->website_name,
            'domain' => $this->preferredDomain($onboarding->website_slug),
            'industry' => $onboarding->industry,
            'location' => $onboarding->location,
            'business_description' => $onboarding->business_description,
            'contact_email' => $onboarding->user->email,
            'api_token' => Str::random(60),
            'page_style' => 'balanced',
            'published_page_style' => 'balanced',
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
                'mega_enabled' => false,
                'mega_footer' => [
                    'enabled' => false,
                    'tagline' => 'A premium information-rich footer.',
                    'primary_label' => 'Get in touch',
                    'primary_url' => '#contact',
                    'columns' => [
                        ['title' => 'Company', 'items' => [['label' => 'About us', 'url' => '#about'], ['label' => 'Careers', 'url' => '#careers'], ['label' => 'Contact', 'url' => '#contact']]],
                        ['title' => 'Services', 'items' => [['label' => 'What we do', 'url' => '#services'], ['label' => 'Solutions', 'url' => '#solutions'], ['label' => 'Pricing', 'url' => '#pricing']]],
                        ['title' => 'Resources', 'items' => [['label' => 'Insights', 'url' => '#insights'], ['label' => 'Guides', 'url' => '#guides'], ['label' => 'Updates', 'url' => '#updates']]],
                    ],
                ],
                'theme' => 'white',
                'logo_text' => $onboarding->website_name,
                'logo_image_url' => '/storage/branding/your-logo.png',
                'logo_height' => 36,
                'logo_filter_key' => data_get($trial?->preview_theme, 'primary', 'midnight'),
                'copyright' => '© '.now()->year.' '.$onboarding->website_name.'. All rights reserved.',
            ],
        ]);
    }

    private function transferTrial(?TrialGeneration $trial, Website $website): void
    {
        if (! $trial || $trial->claimed_at) {
            return;
        }

        if ($trial->page_id) {
            Page::query()->whereKey($trial->page_id)->update([
                'website_id' => $website->id,
                'title' => $website->name,
                'slug' => 'home',
                'parent_id' => null,
                'sort_order' => 1,
                'status' => 'draft',
            ]);
        }

        $trial->update([
            'claimed_at' => now(),
            'claimed_by_user_id' => $website->user_id,
            'status' => 'claimed',
        ]);
    }

    private function ensureHomePage(PendingOnboarding $onboarding, Website $website): void
    {
        if ($website->pages()->exists()) {
            return;
        }

        $trial = $onboarding->trialGeneration;

        $website->pages()->create([
            'title' => $onboarding->website_name,
            'slug' => 'home',
            'parent_id' => null,
            'sort_order' => 1,
            'page_type' => 'standard',
            'blocks' => $trial?->generated_blocks ?? [],
            'status' => 'draft',
        ]);
    }

    /**
     * Keep the account profile and website settings aligned with the details
     * collected during paid onboarding. This is intentionally idempotent so a
     * callback and webhook can both safely finalize the same onboarding.
     */
    private function syncProfileAndSettings(PendingOnboarding $onboarding, Website $website): void
    {
        $user = $onboarding->user;

        $user->forceFill([
            'location' => $onboarding->location,
        ])->save();

        $settings = is_array($website->settings) ? $website->settings : [];
        $seed = trim((string) data_get($settings, 'theme_entitlement_seed', ''));
        if ($seed === '') {
            $trialTheme = trim((string) data_get($onboarding->trialGeneration?->preview_theme, 'primary', ''));
            if ($trialTheme !== '') {
                $settings['theme_entitlement_seed'] = $trialTheme;
            }
        }

        if ($onboarding->trialGeneration) {
            $trial = $onboarding->trialGeneration;
            $settings['brand_memory'] = [
                'brand_prompt' => $trial->brand_prompt ?: $trial->prompt,
                'latest_user_prompt' => $trial->latest_user_prompt ?: $trial->prompt,
                'brand_context' => is_array($trial->brand_context) ? $trial->brand_context : [],
                'prompt_history' => is_array($trial->prompt_history) ? $trial->prompt_history : [],
                'source_trial_id' => $trial->id,
            ];
        }

        $website->forceFill([
            'name' => $onboarding->website_name,
            'domain' => $this->preferredDomain($onboarding->website_slug),
            'industry' => $onboarding->industry,
            'location' => $onboarding->location,
            'business_description' => $onboarding->business_description,
            'contact_email' => $user->email,
            // Existing/recovered websites may have been created before the
            // seed existed. Backfill it exactly once from the linked trial.
            'settings' => $settings,
        ])->save();
    }

    private function markComplete(PendingOnboarding $onboarding, Website $website, ?PaymentOrder $order): void
    {
        $metadata = array_merge($onboarding->metadata ?? [], [
            'workspace_id' => $website->workspace_id,
            'website_id' => $website->id,
            'trial_transferred' => (bool) $onboarding->trial_generation_id,
            'paypal_subscription_id' => $order?->external_subscription_id
                ?: data_get($onboarding->metadata, 'paypal_subscription_id'),
            'workspace_initialized_at' => now()->toIso8601String(),
        ]);

        $onboarding->update([
            'workspace_id' => $website->workspace_id,
            'website_id' => $website->id,
            'status' => 'completed',
            'completed_at' => $onboarding->completed_at ?? now(),
            'metadata' => $metadata,
        ]);

        $onboarding->user->update(['onboarding_status' => 'complete']);
    }

    private function uniqueWorkspaceSlug(string $preferred, int $userId): string
    {
        $base = Str::slug($preferred) ?: 'workspace-'.$userId;
        $slug = $base.'-'.$userId;
        $counter = 2;

        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$userId.'-'.$counter++;
        }

        return $slug;
    }

    private function preferredDomain(string $slug): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! $host || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return 'https://'.$slug.'.cosmiccms.test';
        }

        return 'https://'.$slug.'.'.$host;
    }
}
