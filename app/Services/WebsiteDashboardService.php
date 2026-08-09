<?php

namespace App\Services;

use App\Models\Website;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class WebsiteDashboardService
{
    public function __construct(private readonly PreviewDeploymentService $previews) {}

    public function build(User $user, Collection $websites, array $capabilities = []): array
    {
        $cards = $websites->values()->map(function (Website $website, int $index) use ($user, $capabilities) {
            $themeSettings = is_array($website->theme_settings) ? $website->theme_settings : [];
            $settings = is_array($website->settings) ? $website->settings : [];
            $published = (int) ($website->published_pages_count ?? 0) > 0;
            $theme = (string) ($themeSettings['primary'] ?? $themeSettings['primary_color'] ?? 'midnight');
            $logoPath = $settings['logo_path'] ?? null;

            return [
                'id' => $website->id,
                'name' => $website->name ?: 'Untitled Website',
                'domain' => $website->domain ?: 'No domain connected',
                'industry' => $website->industry ?: 'Uncategorized',
                'location' => $website->location ?: null,
                'created_at' => $website->created_at?->toIso8601String(),
                'status' => $published ? 'Published' : 'Draft',
                'theme' => str($theme)->replace(['_', '-'], ' ')->title()->toString(),
                'pages_count' => (int) ($website->pages_count ?? 0),
                'published_pages_count' => (int) ($website->published_pages_count ?? 0),
                'draft_pages_count' => (int) ($website->draft_pages_count ?? 0),
                'deployment_status' => $website->last_deployed_at
                    ? 'Deployed'
                    : ($website->deployment_verified_at ? 'Connected' : 'Not connected'),
                'logo_url' => filled($logoPath) ? Storage::disk('public')->url($logoPath) : null,
                'updated_at' => $website->updated_at?->toIso8601String(),
                'updated_label' => $website->updated_at?->timezone($user->timezone ?: config('app.timezone'))->format('M j, Y'),
                'builder_url' => route('pages.index', $website),
                'preview_url' => filled($website->preview_slug) && filled($website->last_preview_deployed_at)
                    ? $this->previews->url($website)
                    : null,
                'preview_ready' => filled($website->preview_slug) && filled($website->last_preview_deployed_at),
                'settings_url' => route('dashboard', ['tab' => 'settings', 'website' => $website->id]),
                'can_transfer_ownership' => (int) $website->user_id === (int) $user->id
                    && (bool) data_get($capabilities, 'capabilities.ownership_transfer', false),
                'owner_email' => (int) $website->user_id === (int) $user->id ? $user->email : null,
                'accent_index' => $index,
            ];
        });

        $published = $cards->where('status', 'Published')->count();
        $connected = $cards->whereIn('deployment_status', ['Connected', 'Deployed'])->count();

        return [
            'mode' => ($capabilities['plan_family'] ?? 'personal') === 'agency' ? 'agency' : 'personal',
            'summary' => [
                'websites' => $cards->count(),
                'pages' => $cards->sum('pages_count'),
                'published' => $published,
                'draft' => $cards->count() - $published,
                'connected' => $connected,
                'credits' => (int) $user->credits,
            ],
            'filters' => [
                'industries' => $cards->pluck('industry')->filter()->unique()->sort()->values()->all(),
                'statuses' => [
                    ['key' => 'all', 'label' => 'All', 'count' => $cards->count()],
                    ['key' => 'published', 'label' => 'Published', 'count' => $published],
                    ['key' => 'draft', 'label' => 'Draft', 'count' => $cards->count() - $published],
                    ['key' => 'connected', 'label' => 'Connected', 'count' => $connected],
                ],
                'deployment_states' => ['all', 'deployed', 'connected', 'not_connected'],
                'activity_ranges' => ['all', '7_days', '30_days', '90_days'],
                'sorts' => ['recent', 'name', 'created', 'industry', 'status'],
            ],
            // Kept for backwards compatibility with Patch 8.2 clients.
            'industries' => $cards->pluck('industry')->filter()->unique()->sort()->values()->all(),
            'items' => $cards->all(),
        ];
    }
}
