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
        return DB::transaction(function () use ($trial, $profile, $bundlePlan, $homeGeneration): Page {
            $owner = $this->stagingOwner();
            $workspace = $this->stagingWorkspace($owner);
            $menu = collect($bundlePlan['pages'] ?? [])->map(fn (array $page) => [
                'label' => (string) $page['title'],
                'url' => (string) $page['slug'],
            ])->values()->all();
            $theme = is_array($trial->preview_theme) ? $trial->preview_theme : [];

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
                'global_footer' => [
                    'type' => 'minimal_footer',
                    'mega_enabled' => false,
                    'theme' => 'white',
                    'logo_text' => (string) $profile['business_name'],
                    'logo_image_url' => '/storage/branding/your-logo.png',
                    'copyright' => '© '.now()->year.' '.$profile['business_name'].'. All rights reserved.',
                ],
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
                    'build_status' => $isHome ? 'ready' : 'queued',
                    'built_at' => $isHome ? now()->toIso8601String() : null,
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
                'bundle_status' => count($manifestPages) > 1 ? 'queued' : 'ready',
                'bundle_error' => null,
                'sections' => $homeGeneration['sections'] ?? [],
                'generated_blocks' => $homeGeneration['blocks'] ?? [],
                'status' => 'ready',
                'error_message' => null,
            ])->save();

            return $homePage->fresh();
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
