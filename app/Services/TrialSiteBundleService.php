<?php

namespace App\Services;

use App\Models\Page;
use App\Models\TrialGeneration;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class TrialSiteBundleService
{
    public function __construct(private readonly GlobalMegaFooterService $megaFooters)
    {
    }

    /**
     * Create an isolated website for one public trial under the configured
     * staging owner. The ready Home page is persisted immediately; remaining
     * pages are safe queued drafts filled by BuildTrialSiteBundleJob.
     */
    public function create(
        TrialGeneration $trial,
        array $profile,
        array $bundlePlan,
        array $homeGeneration,
    ): Page {
        $menu = collect($bundlePlan['pages'] ?? [])->map(fn (array $page) => [
            'label' => (string) $page['title'],
            'url' => (string) $page['slug'],
        ])->values()->all();
        $theme = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        $footer = $this->megaFooters->compose(
            (string) ($trial->latest_user_prompt ?: $trial->prompt ?: $profile['business_description']),
            [
                'business_name' => (string) $profile['business_name'],
                'industry' => (string) $profile['industry'],
                'location' => (string) $profile['location'],
                'business_description' => (string) $profile['business_description'],
                'theme' => $theme,
            ],
            $menu,
            [
                'type' => 'minimal_footer',
                'theme' => 'white',
                'logo_text' => (string) $profile['business_name'],
                'logo_image_url' => '/storage/branding/your-logo.png',
                'copyright' => '© '.now()->year.' '.$profile['business_name'].'. All rights reserved.',
            ],
        );

        return DB::transaction(function () use ($trial, $profile, $bundlePlan, $homeGeneration, $menu, $theme, $footer): Page {
            $owner = $this->stagingOwner();
            $workspace = $this->stagingWorkspace($owner);

            $website = Website::query()->create([
                'user_id' => $owner->id,
                'workspace_id' => $workspace?->id,
                'name' => (string) $profile['business_name'],
                'domain' => null,
                'industry' => (string) $profile['industry'],
                'location' => (string) $profile['location'],
                'business_description' => (string) $profile['business_description'],
                'contact_email' => $owner->email,
                'api_token' => Str::random(60),
                'website_type' => 'builder',
                'settings' => [
                    'trial_generation_id' => $trial->id,
                    'trial_bundle_key' => $bundlePlan['bundle_key'] ?? null,
                    'trial_staging_owner_email' => Str::lower($owner->email),
                    'trial_staged_at' => now()->toIso8601String(),
                ],
                'theme_settings' => $theme,
                'global_header' => [
                    'type' => 'glassmorphism_header',
                    'logo_text' => (string) $profile['business_name'],
                    'logo_image_url' => '/storage/branding/your-logo.png',
                    'logo_height' => 42,
                    'logo_filter_key' => data_get($theme, 'primary', 'midnight'),
                    'cta_label' => 'Get Started',
                    'cta_url' => 'contact',
                    'menu' => $menu,
                ],
                'global_footer' => $footer,
            ]);

            $manifestPages = [];
            $homePage = null;
            foreach (array_values($bundlePlan['pages'] ?? []) as $index => $recipe) {
                $isHome = (bool) ($recipe['is_home'] ?? $index === 0);
                $page = $website->pages()->create([
                    'title' => (string) $recipe['title'],
                    'slug' => $isHome ? 'home' : (string) $recipe['slug'],
                    'parent_id' => null,
                    'sort_order' => $index + 1,
                    'page_type' => (string) ($recipe['page_type'] ?? 'standard'),
                    'blocks' => $isHome ? array_values($homeGeneration['blocks'] ?? []) : [],
                    'status' => 'draft',
                ]);

                if ($isHome) {
                    $homePage = $page;
                }
                $manifestPages[] = [
                    ...$recipe,
                    'page_id' => $page->id,
                    'build_status' => ($isHome && !empty($homeGeneration['blocks'] ?? [])) ? 'ready' : 'queued',
                    'built_at' => ($isHome && !empty($homeGeneration['blocks'] ?? [])) ? now()->toIso8601String() : null,
                ];
            }

            if (! $homePage) {
                throw new RuntimeException('The selected Luna bundle does not contain a Home page.');
            }

            $manifest = [
                ...$bundlePlan,
                'pages' => $manifestPages,
                'staging_website_id' => $website->id,
                'staging_owner_email' => Str::lower($owner->email),
            ];
            $menuStructure = collect($manifestPages)->map(fn (array $page) => [
                'title' => $page['title'],
                'slug' => $page['slug'],
                'is_home' => (bool) $page['is_home'],
                'sort_order' => (int) $page['sort_order'],
                'page_type' => (string) ($page['page_type'] ?? 'standard'),
                'page_id' => $page['page_id'],
            ])->values()->all();

            $trial->forceFill([
                'website_id' => $website->id,
                'page_id' => $homePage->id,
                'menu_structure' => $menuStructure,
                'bundle_manifest' => $manifest,
                'bundle_status' => collect($manifestPages)->every(fn (array $item) => ($item['build_status'] ?? null) === 'ready') ? 'ready' : 'queued',
                'bundle_error' => null,
                'sections' => $homeGeneration['sections'] ?? [],
                'generated_blocks' => $homeGeneration['blocks'] ?? [],
                'status' => 'ready',
                'error_message' => null,
            ])->save();

            return $homePage->fresh();
        });
    }

    /**
     * Replace an unclaimed trial's complete page architecture after the visitor
     * explicitly requests a full regeneration. Existing matching page records
     * are reused; obsolete trial-only pages are removed inside the transaction.
     */
    public function rebuild(
        TrialGeneration $trial,
        array $profile,
        array $bundlePlan,
        array $homeGeneration,
        array $theme,
    ): Page {
        $menu = collect($bundlePlan['pages'] ?? [])->map(fn (array $page) => [
            'label' => (string) $page['title'],
            'url' => (bool) ($page['is_home'] ?? false) ? 'home' : (string) $page['slug'],
        ])->values()->all();
        $currentWebsite = $trial->website_id ? Website::query()->find($trial->website_id) : null;
        $footer = $this->megaFooters->compose(
            (string) ($trial->latest_user_prompt ?: $trial->prompt ?: $profile['business_description']),
            [
                'business_name' => (string) $profile['business_name'],
                'industry' => (string) $profile['industry'],
                'location' => (string) $profile['location'],
                'business_description' => (string) $profile['business_description'],
                'theme' => $theme,
            ],
            $menu,
            is_array($currentWebsite?->global_footer) ? $currentWebsite->global_footer : [],
        );

        return DB::transaction(function () use ($trial, $profile, $bundlePlan, $homeGeneration, $theme, $footer): Page {
            $trial = TrialGeneration::query()->lockForUpdate()->findOrFail($trial->id);
            if ($trial->claimed_at || ! $trial->website_id) {
                throw new RuntimeException('Only an active staged trial can be regenerated.');
            }

            $website = Website::query()->lockForUpdate()->findOrFail($trial->website_id);
            $currentHome = Page::query()->lockForUpdate()->findOrFail($trial->page_id);
            $existingBySlug = $website->pages()->get()->keyBy('slug');
            $manifestPages = [];
            $desiredPageIds = [];

            foreach (array_values($bundlePlan['pages'] ?? []) as $index => $recipe) {
                $isHome = (bool) ($recipe['is_home'] ?? $index === 0);
                $slug = $isHome ? 'home' : (string) $recipe['slug'];
                $page = $isHome ? $currentHome : $existingBySlug->get($slug);

                if (! $page) {
                    $page = $website->pages()->create([
                        'title' => (string) $recipe['title'],
                        'slug' => $slug,
                        'parent_id' => null,
                        'sort_order' => $index + 1,
                        'page_type' => (string) ($recipe['page_type'] ?? 'standard'),
                        'blocks' => [],
                        'status' => 'draft',
                    ]);
                }

                $page->forceFill([
                    'title' => (string) $recipe['title'],
                    'slug' => $slug,
                    'parent_id' => null,
                    'sort_order' => $index + 1,
                    'page_type' => (string) ($recipe['page_type'] ?? 'standard'),
                    'blocks' => $isHome ? array_values($homeGeneration['blocks'] ?? []) : [],
                    'status' => 'draft',
                    'published_blocks' => null,
                    'published_html' => null,
                    'published_at' => null,
                    'last_published_at' => null,
                    'publish_error' => null,
                ])->save();

                $desiredPageIds[] = $page->id;
                $manifestPages[] = [
                    ...$recipe,
                    'page_id' => $page->id,
                    'build_status' => ($isHome && !empty($homeGeneration['blocks'] ?? [])) ? 'ready' : 'queued',
                    'built_at' => ($isHome && !empty($homeGeneration['blocks'] ?? [])) ? now()->toIso8601String() : null,
                    'build_error' => null,
                ];
            }

            if ($manifestPages === []) {
                throw new RuntimeException('The selected Luna bundle has no pages.');
            }

            $website->pages()->whereNotIn('id', $desiredPageIds)->delete();
            $menu = collect($manifestPages)->map(fn (array $page) => [
                'label' => (string) $page['title'],
                'url' => (string) $page['slug'],
            ])->values()->all();
            $header = is_array($website->global_header) ? $website->global_header : [];
            $header['logo_text'] = (string) $profile['business_name'];
            $header['menu'] = $menu;
            $header['cta_url'] = 'contact';
            $website->forceFill([
                'name' => (string) $profile['business_name'],
                'industry' => (string) $profile['industry'],
                'location' => (string) $profile['location'],
                'business_description' => (string) $profile['business_description'],
                'theme_settings' => $theme,
                'global_header' => $header,
                'global_footer' => $footer,
            ])->save();

            $manifest = [
                ...$bundlePlan,
                'pages' => $manifestPages,
                'staging_website_id' => $website->id,
                'staging_owner_email' => (string) data_get($trial->bundle_manifest, 'staging_owner_email'),
                'regenerated_at' => now()->toIso8601String(),
            ];
            $menuStructure = collect($manifestPages)->map(fn (array $page) => [
                'title' => $page['title'],
                'slug' => $page['slug'],
                'is_home' => (bool) $page['is_home'],
                'sort_order' => (int) $page['sort_order'],
                'page_type' => (string) ($page['page_type'] ?? 'standard'),
                'page_id' => $page['page_id'],
            ])->values()->all();

            $trial->forceFill([
                'page_id' => (int) $manifestPages[0]['page_id'],
                'menu_structure' => $menuStructure,
                'bundle_manifest' => $manifest,
                'bundle_status' => collect($manifestPages)->every(fn (array $item) => ($item['build_status'] ?? null) === 'ready') ? 'ready' : 'queued',
                'bundle_error' => null,
                'sections' => $homeGeneration['sections'] ?? [],
                'generated_blocks' => $homeGeneration['blocks'] ?? [],
            ])->save();

            return Page::query()->findOrFail((int) $manifestPages[0]['page_id']);
        });
    }

    private function stagingOwner(): User
    {
        $email = Str::lower(trim((string) config('cosmic.trial_website_owner_email')));
        if ($email === '') {
            throw new RuntimeException('TRIAL_WEBSITE_OWNER_EMAIL is not configured.');
        }

        $owner = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $owner) {
            throw new RuntimeException("The configured trial website owner [{$email}] does not exist.");
        }

        return $owner;
    }

    private function stagingWorkspace(User $owner): ?Workspace
    {
        return $owner->ownedWorkspaces()->oldest('id')->first()
            ?? $owner->workspaces()->oldest('workspaces.id')->first();
    }
}
