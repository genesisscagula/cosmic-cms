<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Website;
use App\Services\AgencyInsightsService;
use App\Services\AgencyWebsiteLimitService;
use App\Services\BulkWebsiteActionService;
use App\Services\CreditWalletService;
use App\Services\DeploymentConnectorArchive;
use App\Services\PagePublisher;
use App\Services\PlanCapabilityService;
use App\Services\PlanEntitlementService;
use App\Services\OwnedSparkSlotService;
use App\Services\SparkAcquisitionService;
use App\Services\SparkUsageStateService;
use App\Services\SparkCatalog;
use App\Services\WebsiteTemplateCatalog;
use App\Services\MyBrandThemeService;
use App\Services\WebsiteDuplicationService;
use App\Services\WebsiteOwnershipTransferService;
use App\Services\WebsiteDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class WebsiteController extends Controller
{
	public function index(Request $request)
	{
        $user = $request->user();
        $creditBalance = app(CreditWalletService::class)->balance($user);

        if ($user->isClient()) {
            $clientWebsites = Website::query()
                ->whereHas('assignedUsers', fn ($query) => $query->whereKey($user->id))
                ->with(['workspace.owner'])
                ->withCount('pages')
                ->latest('updated_at')
                ->get()
                ->map(fn (Website $website) => [
                    'id' => $website->id,
                    'name' => $website->name ?: 'Untitled Website',
                    'domain' => $website->domain,
                    'industry' => $website->industry,
                    'location' => $website->location,
                    'status' => $website->status ?? 'active',
                    'pages_count' => (int) $website->pages_count,
                    'updated_at' => $website->updated_at?->diffForHumans(),
                ]);

            $clientWorkspace = Website::query()
                ->whereHas('assignedUsers', fn ($query) => $query->whereKey($user->id))
                ->with('workspace.owner')
                ->first()?->workspace;
            $clientPortalAvailable = $clientWorkspace?->owner
                && (string) data_get(app(PlanCapabilityService::class)->forUser($clientWorkspace->owner), 'capabilities.white_label_level', 'none') === 'full';

            return Inertia::render('Client/Dashboard', [
                'websites' => $clientWebsites,
                'client' => ['name' => $user->name, 'email' => $user->email],
                'agency_portal_url' => $clientPortalAvailable ? route('agency-portal.index') : null,
            ]);
        }

        $workspaceMembership = $user->workspaces()->wherePivotIn('role', ['admin', 'editor'])->first();
        if ($workspaceMembership) {
            $assignedWebsite = Website::query()
                ->where('workspace_id', $workspaceMembership->id)
                ->whereHas('assignedUsers', fn ($query) => $query->whereKey($user->id))
                ->with(['pages' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
                ->first();

            if ($assignedWebsite) {
                if ($workspaceMembership->pivot->role === 'editor') {
                    $firstPage = $assignedWebsite->pages->first();
                    if ($firstPage) return redirect()->route('pages.builder', $firstPage);
                }

                return redirect()->route('pages.index', $assignedWebsite);
            }
        }

        $websiteQuery = Website::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('workspace', fn ($workspaceQuery) => $workspaceQuery->where('owner_user_id', $user->id))
                    ->orWhereHas('assignedUsers', fn ($memberQuery) => $memberQuery->whereKey($user->id));
            });

        $websites = (clone $websiteQuery)
            ->withCount([
                'pages',
                'pages as published_pages_count' => fn ($query) => $query->where('status', 'published'),
                'pages as draft_pages_count' => fn ($query) => $query->where('status', 'draft'),
            ])
            ->latest('updated_at')
            ->get();

        $websiteIds = $websites->pluck('id');
        $publishedWebsites = $websites->filter(fn (Website $website) => $website->published_pages_count > 0);
        $draftWebsites = $websites->reject(fn (Website $website) => $website->published_pages_count > 0);
        $planCapabilities = app(PlanCapabilityService::class)->forUser($user);
        $analyticsPeriod = (string) $request->query('analytics_period', '30d');
        $agencyInsights = app(AgencyInsightsService::class)->build($user, $planCapabilities, [
            'website_id' => $request->integer('analytics_website') ?: null,
            'days' => match ($analyticsPeriod) {
                '7d' => 7,
                '90d' => 90,
                default => 30,
            },
            'starts_at' => $analyticsPeriod === 'custom' ? $request->query('analytics_start') : null,
            'ends_at' => $analyticsPeriod === 'custom' ? $request->query('analytics_end') : null,
            'lead_website' => $request->query('lead_website', 'all'),
            'lead_status' => $request->query('lead_status', 'all'),
            'lead_source' => $request->query('lead_source', 'all'),
            'lead_query' => $request->query('lead_query'),
            'lead_start' => $request->query('lead_start'),
            'lead_end' => $request->query('lead_end'),
            'sale_website' => $request->query('sale_website', 'all'),
            'sale_status' => $request->query('sale_status', 'all'),
            'sale_source' => $request->query('sale_source', 'all'),
            'sale_start' => $request->query('sale_start'),
            'sale_end' => $request->query('sale_end'),
            'conversion_website' => $request->query('conversion_website', 'all'),
            'conversion_start' => $request->query('conversion_start'),
            'conversion_end' => $request->query('conversion_end'),
        ]);
        $websitesDashboard = app(WebsiteDashboardService::class)->build($user, $websites, $planCapabilities);
        $effectivePlanKey = $user->effectivePlanKey();
        $plan = config('payments.plans.' . $effectivePlanKey, []);
        $planLabel = $plan['label'] ?? Str::headline($effectivePlanKey);
        $signupCredits = (int) ($plan['credits'] ?? 0);
        $provider = $user->plan_provider ? Str::headline($user->plan_provider) : 'Not connected';
        $subscriptionStatus = $user->plan_status ? Str::headline(str_replace('_', ' ', $user->plan_status)) : 'Inactive';
        $nextBilling = $user->plan_renews_at?->timezone($user->timezone ?: config('app.timezone'));

        $sparkUnlocks = $user->cosmicUnlocks()
            ->where('unlock_type', 'spark')
            ->latest()
            ->get()
            ->keyBy('unlock_key');
        $favoriteKeys = $user->sparkFavorites()->pluck('spark_key');
        $ownedSparkSlots = app(OwnedSparkSlotService::class)->usage($user);
        $sharedSparkKeys = app(\App\Services\WorkspaceSparkLibraryService::class)->keysFor($user);
        $usageStates = app(SparkUsageStateService::class);

        $sparkMarketplace = collect(SparkCatalog::all())
            ->values()
            ->map(function (array $spark, int $index) use ($sparkUnlocks, $favoriteKeys, $user, $ownedSparkSlots, $sharedSparkKeys, $usageStates) {
                $entitlements = app(PlanEntitlementService::class);
                $isFeatured = (bool) ($spark['featured'] ?? false);
                $unlock = $sparkUnlocks->get($spark['key']);

                $access = $entitlements->sparkAccess($user, (string) ($spark['access_level'] ?? 'growth'));
                $previewAccess = $entitlements->sparkPreviewAccess($user, (int) ($spark['catalog_index'] ?? $index));
                $acquisition = app(SparkAcquisitionService::class)->decision($user, $spark, $unlock);
                $shared = $sharedSparkKeys->contains($spark['key']);
                $usageState = $usageStates->resolve($user, $spark, $acquisition, $previewAccess, $shared);

                return [
                    ...$spark,
                    'owned' => (bool) ($unlock?->is_installed) || $sharedSparkKeys->contains($spark['key']),
                    'shared' => $shared,
                    'usage_state' => $usageState,
                    'purchased' => (int) ($unlock?->credits_paid ?? 0) > 0,
                    'favorited' => $favoriteKeys->contains($spark['key']),
                    'credits_paid' => (int) ($unlock?->credits_paid ?? 0),
                    'is_free' => (int) ($spark['credits'] ?? 0) === 0,
                    'is_premium' => (int) ($spark['credits'] ?? 0) > 0,
                    'is_new' => $index < 8,
                    'popular' => $isFeatured || in_array($spark['category'] ?? null, ['Hero', 'Services', 'Pricing', 'Testimonials'], true),
                    'staff_pick' => $isFeatured,
                    'can_preview' => (bool) $previewAccess['allowed'],
                    'preview_access' => $previewAccess,
                    'can_install' => (bool) $acquisition['allowed'],
                    'acquisition' => $acquisition,
                    'action_label' => $acquisition['label'],
                    'locked' => ! (bool) $access['allowed'],
                    'slot_blocked' => $acquisition['reason'] === 'owned_spark_limit',
                    'credit_blocked' => $acquisition['reason'] === 'insufficient_credits',
                    'access' => $access,
                ];
            });

        $sparkCatalog = $sparkMarketplace->keyBy('key');
        $sparkLibrary = $sparkUnlocks
            ->filter(fn ($unlock) => (bool) $unlock->is_installed)
            ->map(function ($unlock) use ($sparkCatalog, $user, $favoriteKeys) {
                $spark = $sparkCatalog->get($unlock->unlock_key);
                if (! $spark) return null;

                return [
                    ...$spark,
                    'source' => 'Marketplace',
                    'credits_paid' => (int) $unlock->credits_paid,
                    'purchased' => (int) $unlock->credits_paid > 0,
                    'favorited' => $favoriteKeys->contains($unlock->unlock_key),
                    'unlocked_at' => $unlock->created_at?->toIso8601String(),
                    'unlocked_label' => $unlock->created_at?->timezone($user->timezone ?: config('app.timezone'))->format('M j, Y'),
                    'usage_count' => 0,
                ];
            })
            ->filter()
            ->values();

        $recentWebsites = $websites->take(3)->values()->map(function (Website $website, int $index) {
            $accents = [
                'from-violet-500 to-indigo-600',
                'from-emerald-500 to-teal-600',
                'from-sky-500 to-blue-700',
                'from-orange-400 to-rose-600',
            ];

            return [
                'id' => $website->id,
                'name' => $website->name ?: 'Untitled Website',
                'domain' => $website->domain ?: 'No domain connected',
                'status' => $website->published_pages_count > 0 ? 'Published' : 'Draft',
                'accent' => $accents[$index % count($accents)],
                'updated_at' => $website->updated_at?->toIso8601String(),
            ];
        });

        $activity = collect();

        foreach ($websites->take(4) as $website) {
            $activity->push([
                'id' => 'website-' . $website->id,
                'type' => 'website',
                'actor' => 'You',
                'action' => 'updated',
                'target' => $website->name ?: 'Untitled Website',
                'time' => $website->updated_at?->diffForHumans() ?? 'Recently',
                'timestamp' => $website->updated_at?->timestamp ?? 0,
            ]);
        }

        if ($websiteIds->isNotEmpty()) {
            Page::query()
                ->whereIn('website_id', $websiteIds)
                ->whereNotNull('last_published_at')
                ->with('website:id,name')
                ->latest('last_published_at')
                ->limit(4)
                ->get()
                ->each(function (Page $page) use ($activity) {
                    $activity->push([
                        'id' => 'publish-' . $page->id,
                        'type' => 'publish',
                        'actor' => 'You',
                        'action' => 'published ' . ($page->title ?: 'a page') . ' for',
                        'target' => $page->website?->name ?: 'a website',
                        'time' => $page->last_published_at?->diffForHumans() ?? 'Recently',
                        'timestamp' => $page->last_published_at?->timestamp ?? 0,
                    ]);
                });
        }

        $user->creditTransactions()
            ->latest()
            ->limit(5)
            ->get()
            ->each(function ($transaction) use ($activity) {
                $activity->push([
                    'id' => 'credit-' . $transaction->id,
                    'type' => 'credits',
                    'actor' => 'Credits',
                    'action' => $transaction->amount > 0 ? 'added' : 'used',
                    'target' => abs((int) $transaction->amount) . ' — ' . $transaction->description,
                    'time' => $transaction->created_at?->diffForHumans() ?? 'Recently',
                    'timestamp' => $transaction->created_at?->timestamp ?? 0,
                ]);
            });

        $user->billingTransactions()
            ->latest('occurred_at')
            ->limit(5)
            ->get()
            ->each(function ($transaction) use ($activity) {
                $activity->push([
                    'id' => 'billing-' . $transaction->id,
                    'type' => $transaction->status === 'completed' ? 'billing' : 'warning',
                    'actor' => Str::headline($transaction->provider),
                    'action' => $transaction->status === 'completed' ? 'processed' : 'failed',
                    'target' => Str::headline($transaction->type) . ' payment',
                    'time' => $transaction->occurred_at?->diffForHumans() ?? 'Recently',
                    'timestamp' => $transaction->occurred_at?->timestamp ?? 0,
                ]);
            });

        $user->workspaceProvisionings()
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit(3)
            ->get()
            ->each(function ($provisioning) use ($activity) {
                $activity->push([
                    'id' => 'provisioning-' . $provisioning->id,
                    'type' => 'workspace',
                    'actor' => 'Cosmic',
                    'action' => 'created',
                    'target' => 'your workspace and starter website',
                    'time' => $provisioning->completed_at?->diffForHumans() ?? 'Recently',
                    'timestamp' => $provisioning->completed_at?->timestamp ?? 0,
                ]);
            });

        $activity = $activity
            ->sortByDesc('timestamp')
            ->take(6)
            ->values()
            ->map(fn (array $item) => collect($item)->except('timestamp')->all());

        $hasWebsite = $websites->isNotEmpty();
        $hasPages = $websites->sum('pages_count') > 0;
        $hasWebsiteShell = $websites->contains(fn (Website $website) => filled($website->global_header) && filled($website->global_footer));
        $hasPublishedWebsite = $publishedWebsites->isNotEmpty();
        $workspaceCompleted = collect([$hasWebsite, $hasPages, $hasWebsiteShell, $hasPublishedWebsite])->filter()->count();
        $lastUpdatedAt = collect([
            $websites->max('updated_at'),
            $user->creditTransactions()->max('created_at'),
            $user->billingTransactions()->max('occurred_at'),
        ])->filter()->sortDesc()->first();

        $workspace = $user->ownedWorkspaces()->with(['owner:id,name,email', 'users:id,name,email'])->first()
            ?? $user->workspaces()->with(['owner:id,name,email', 'users:id,name,email'])->first();

        $workspaceWebsites = $workspace
            ? $websites->where('workspace_id', $workspace->id)->values()
            : $websites->values();

        $settingsWebsites = $websites->map(function (Website $website) {
            $settings = $website->settings ?? [];

            return [
                'id' => $website->id,
                'name' => $website->name,
                'domain' => $website->domain,
                'industry' => $website->industry,
                'location' => $website->location,
                'business_description' => $website->business_description,
                'contact_email' => $website->contact_email,
                'contact_phone' => $website->contact_phone,
                'timezone' => $website->timezone ?: 'Asia/Manila',
                'locale' => $website->locale ?: 'en',
                'business_name' => $settings['business_name'] ?? $website->name,
                'address' => $settings['address'] ?? $website->location,
                'company_name' => $settings['company_name'] ?? null,
                'owner_name' => $settings['owner_name'] ?? null,
                'registration_number' => $settings['registration_number'] ?? null,
                'vat_number' => $settings['vat_number'] ?? null,
                'logo_url' => filled($settings['logo_path'] ?? null) ? Storage::disk('public')->url($settings['logo_path']) : null,
                'favicon_url' => filled($settings['favicon_path'] ?? null) ? Storage::disk('public')->url($settings['favicon_path']) : null,
            ];
        })->values();

        $workspaceInformation = [
            'id' => $workspace?->id,
            'name' => $workspace?->name ?? (($user->business_name ?: $user->name) . ' Workspace'),
            'slug' => $workspace?->slug,
            'role' => $workspace?->roleFor($user) ?? 'owner',
            'owner' => [
                'name' => $workspace?->owner?->name ?? $user->name,
                'email' => $workspace?->owner?->email ?? $user->email,
            ],
            'created_at' => $workspace?->created_at?->toIso8601String(),
            'created_label' => $workspace?->created_at?->timezone($user->timezone ?: config('app.timezone'))->format('M j, Y'),
            'status' => $workspaceWebsites->isNotEmpty() ? 'Active' : 'Setup required',
            'members_count' => $workspace ? $workspace->users->count() : 1,
            'websites_count' => $workspaceWebsites->count(),
            'published_websites_count' => $workspaceWebsites->filter(fn (Website $website) => $website->published_pages_count > 0)->count(),
            'websites' => $workspaceWebsites->map(function (Website $website) use ($user) {
                $published = $website->published_pages_count > 0;
                $deploymentStatus = $website->last_deployed_at
                    ? 'Deployed'
                    : ($website->deployment_verified_at ? 'Connected' : 'Not connected');

                return [
                    'id' => $website->id,
                    'name' => $website->name ?: 'Untitled Website',
                    'industry' => $website->industry ?: 'Not set',
                    'location' => $website->location ?: 'Not set',
                    'domain' => $website->domain ?: 'No domain connected',
                    'status' => $published ? 'Published' : 'Draft',
                    'pages_count' => (int) $website->pages_count,
                    'published_pages_count' => (int) $website->published_pages_count,
                    'deployment_status' => $deploymentStatus,
                    'updated_at' => $website->updated_at?->timezone($user->timezone ?: config('app.timezone'))->format('M j, Y'),
                ];
            })->values(),
        ];

        $teamMemberLimit = data_get($planCapabilities, 'capabilities.team_members', 0);
        $workspaceMembers = $workspace ? $workspace->users()->orderBy('name')->get() : collect([$user]);
        $pendingInvitations = $workspace ? $workspace->invitations()->where('status', 'pending')->where(function ($query) {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->latest()->get() : collect();
        $teamUsed = max(0, $workspaceMembers->count() - 1) + $pendingInvitations->count();
        $brandingSettings = (array) data_get($workspace?->settings, 'branding', []);
        $whiteLabelLevel = (string) data_get($planCapabilities, 'capabilities.white_label_level', 'none');
        $agencyBranding = [
            'workspace_id' => $workspace?->id,
            'is_agency' => ($planCapabilities['plan_family'] ?? null) === 'agency',
            'available' => ($planCapabilities['plan_family'] ?? null) === 'agency' && $whiteLabelLevel !== 'none',
            'is_owner' => $workspace && (int) $workspace->owner_user_id === (int) $user->id,
            'level' => $whiteLabelLevel,
            'agency_name' => $brandingSettings['agency_name'] ?? $workspace?->name ?? $user->business_name ?? $user->name,
            'tagline' => $brandingSettings['tagline'] ?? null,
            'primary_color' => $brandingSettings['primary_color'] ?? '#7C3AED',
            'accent_color' => $brandingSettings['accent_color'] ?? '#22D3EE',
            'support_email' => $brandingSettings['support_email'] ?? $user->email,
            'website_url' => $brandingSettings['website_url'] ?? null,
            'logo_url' => filled($brandingSettings['logo_path'] ?? null) ? Storage::disk('public')->url($brandingSettings['logo_path']) : null,
            'can_remove_cosmic_branding' => $whiteLabelLevel !== 'none',
            'remove_cosmic_branding' => $whiteLabelLevel !== 'none' && (bool) ($brandingSettings['remove_cosmic_branding'] ?? false),
            'custom_portal_available' => $whiteLabelLevel === 'full',
            'portal_title' => $brandingSettings['portal_title'] ?? 'Welcome to your client portal',
            'portal_welcome' => $brandingSettings['portal_welcome'] ?? 'Review your websites, check publication status, and open secure previews from one place.',
            'preview_links' => $workspaceWebsites->map(function (Website $website) {
                return [
                    'website_id' => $website->id,
                    'website_name' => $website->name ?: 'Untitled Website',
                    'links' => $website->previewLinks()->latest()->get()->map(fn ($link) => [
                        'id' => $link->id,
                        'label' => $link->label,
                        'url' => route('preview-links.show', $link->token),
                        'views' => (int) $link->views,
                        'expires_at' => $link->expires_at?->toIso8601String(),
                        'revoked_at' => $link->revoked_at?->toIso8601String(),
                        'last_viewed_at' => $link->last_viewed_at?->toIso8601String(),
                        'active' => ! $link->revoked_at && (! $link->expires_at || $link->expires_at->isFuture()),
                    ])->values(),
                ];
            })->values(),
        ];

        $teamWorkspace = [
            'enabled' => $planCapabilities['plan_family'] === 'agency' && ($teamMemberLimit === null || (int) $teamMemberLimit > 0),
            'is_owner' => $workspace && (int) $workspace->owner_user_id === (int) $user->id,
            'current_role' => $workspace?->roleFor($user) ?? 'owner',
            'roles' => app(\App\Services\WorkspacePermissionService::class)->catalog(),
            'limit' => $teamMemberLimit,
            'used' => $teamUsed,
            'remaining' => $teamMemberLimit === null ? null : max(0, (int) $teamMemberLimit - $teamUsed),
            'websites' => $workspaceWebsites->map(fn (Website $website) => [
                'id' => $website->id,
                'name' => $website->name ?: 'Untitled Website',
                'domain' => $website->domain ?: 'No domain connected',
                'status' => $website->published_pages_count > 0 ? 'Published' : 'Draft',
            ])->values(),
            'members' => $workspaceMembers->map(function ($member) use ($workspace) {
                $isOwner = $workspace && (int) $workspace->owner_user_id === (int) $member->id;
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $workspace?->roleFor($member) ?? 'owner',
                    'joined_at' => $member->pivot?->created_at?->toIso8601String(),
                    'website_ids' => $isOwner
                        ? $workspace->websites()->pluck('id')->map(fn ($id) => (int) $id)->values()
                        : $member->assignedWebsites()->where('workspace_id', $workspace->id)->pluck('websites.id')->map(fn ($id) => (int) $id)->values(),
                ];
            })->values(),
            'invitations' => $pendingInvitations->map(fn ($invitation) => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'website_ids' => collect($invitation->website_ids ?? [])->map(fn ($id) => (int) $id)->values(),
                'accept_url' => route('workspace-invitations.show', $invitation->token),
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ])->values(),
        ];

	    return Inertia::render('Dashboard/Dashboard', [
		    'websites' => $websites,
            'dashboard' => [
                'stats' => [
                    [
                        'label' => 'Credits',
                        'value' => number_format($creditBalance),
                        'detail' => 'Available balance',
                        'accent' => 'bg-violet-400/10 text-violet-300',
                        'icon' => '✦',
                    ],
                    [
                        'label' => 'Current Plan',
                        'value' => $planLabel,
                        'detail' => $subscriptionStatus,
                        'accent' => 'bg-emerald-400/10 text-emerald-300',
                        'icon' => '◆',
                    ],
                    [
                        'label' => 'Included Credits',
                        'value' => number_format($signupCredits),
                        'detail' => $user->plan_key ? 'One-time credit allocation on first purchase' : 'Included with your first plan purchase',
                        'accent' => 'bg-cyan-400/10 text-cyan-300',
                        'icon' => '↻',
                    ],
                    [
                        'label' => 'Next Billing',
                        'value' => $nextBilling?->format('M j, Y') ?? '—',
                        'detail' => $user->plan_cancel_at_period_end ? 'Cancels at period end' : ($nextBilling ? $nextBilling->diffForHumans() : 'No renewal scheduled'),
                        'accent' => 'bg-amber-300/10 text-amber-200',
                        'icon' => '◷',
                    ],
                    [
                        'label' => 'Payment Provider',
                        'value' => $provider,
                        'detail' => $user->plan_provider ? 'Connected billing provider' : 'No provider connected',
                        'accent' => 'bg-sky-400/10 text-sky-300',
                        'icon' => '▣',
                    ],
                    [
                        'label' => 'Websites',
                        'value' => number_format($websites->count()),
                        'detail' => $publishedWebsites->count() . ' published · ' . $draftWebsites->count() . ' draft',
                        'accent' => 'bg-rose-400/10 text-rose-300',
                        'icon' => '◎',
                    ],
                ],
                'credit_balance' => $creditBalance,
                'plan_capabilities' => $planCapabilities,
                'websites_dashboard' => $websitesDashboard,
                'agency_insights' => $agencyInsights,
                'team_workspace' => $teamWorkspace,
                'agency_branding' => $agencyBranding,
                'subscription' => [
                    'plan_key' => $user->effectivePlanKey(),
                    'plan_label' => $planLabel,
                    'status' => $user->plan_status,
                    'status_label' => $subscriptionStatus,
                    'signup_credits' => $signupCredits,
                    'next_billing_at' => $nextBilling?->toIso8601String(),
                    'payment_provider' => $user->plan_provider,
                    'cancel_at_period_end' => (bool) $user->plan_cancel_at_period_end,
                ],
                'spark_library' => [
                    'items' => $sparkLibrary,
                    'categories' => $sparkLibrary->pluck('category')->filter()->unique()->sort()->values(),
                    'count' => $sparkLibrary->count(),
                ],
                'spark_marketplace' => [
                    'items' => $sparkMarketplace,
                    'registry' => SparkCatalog::forClient(),
                    'categories' => $sparkMarketplace->pluck('category')->filter()->unique()->sort()->values(),
                    'count' => $sparkMarketplace->count(),
                    'owned_count' => $sparkMarketplace->where('owned', true)->count(),
                    'favorite_count' => $sparkMarketplace->where('favorited', true)->count(),
                    'purchased_count' => $sparkMarketplace->where('purchased', true)->count(),
                    'owned_spark_slots' => $ownedSparkSlots,
                ],
                'recent_websites' => $recentWebsites,
                'recent_activity' => $activity,
                'workspace_progress' => [
                    'completed' => $workspaceCompleted,
                    'total' => 4,
                ],
                'workspace' => $workspaceInformation,
                'settings' => [
                    'websites' => $settingsWebsites,
                    'default_website_id' => $settingsWebsites->first()['id'] ?? null,
                ],
                'last_updated' => $lastUpdatedAt ? now()->parse($lastUpdatedAt)->diffForHumans() : null,
            ],
            'accessMode' => $user->isPlatformOwner() ? 'platform_owner' : ($user->isClient() ? 'client' : 'customer'),
		]);
	}

    public function store(Request $request, WebsiteTemplateCatalog $templates, AgencyWebsiteLimitService $websiteLimits, PlanEntitlementService $entitlements, MyBrandThemeService $myBrandThemes)
	{
	    $request->validate([
	        'name' => 'required|string|max:255',
	        'domain' => 'required|url',
	        'industry' => 'required|string|max:120',
	        'location' => 'required|string|max:255',
	        'business_description' => 'required|string|max:2000',
	        'theme_settings' => 'nullable|array', // I-validate ang array input
	        'template' => 'nullable|string',
	    ]);

	    $template = $request->input('template');

	    if ($template && ! $templates->supports($template)) {
	        return back()->withErrors(['template' => 'The selected website template is not available.']);
	    }

        if ($template) {
            $templateAccess = $entitlements->templateAccess($request->user(), $template, $templates);

            if (! $templateAccess['allowed']) {
                return back()->withErrors([
                    'template' => $templateAccess['message'] ?? 'Your current plan cannot use this template.',
                ]);
            }
        }

	    $defaults = [
	        'name' => $request->name,
	        'domain' => $request->domain,
	        'industry' => $request->input('industry'),
	        'location' => $request->input('location'),
	        'business_description' => $request->input('business_description'),
	        // Until a dedicated website settings screen is added, new live-form
	        // inquiries go to the account that created the website.
	        'contact_email' => $request->user()->email,
	        'api_token' => Str::random(60),
            'page_style' => 'balanced',
            'published_page_style' => 'balanced',
	        // Every new website starts with a persistent My Brand Theme.
	        // Blank websites seed it from Midnight; a Starter Kit replaces this
	        // seed below with an exact editable copy of the kit color family.
	        'theme_settings' => $myBrandThemes->ensureInSettings(
                (array) $request->input('theme_settings', [
                    'primary' => 'midnight',
                    'secondary' => 'white',
                    'tertiary' => 'stone',
                    'auto' => true,
                ]),
                'midnight'
            ),
	        'global_header' => [
	            'type' => 'glassmorphism_header',
	            'logo_text' => $request->name,
                'logo_image_url' => '/storage/branding/your-logo.png',
                'logo_height' => 42,
                'logo_filter_key' => 'midnight',
	            'cta_label' => 'Get Started',
	            'cta_url' => '#',
	            'menu' => [
	                ['label' => 'Home', 'url' => 'home'],
	                ['label' => 'About', 'url' => '#'],
	                ['label' => 'Services', 'url' => '#'],
	            ],
	        ],
	        'global_footer' => [
	            'type' => 'minimal_footer',
                'theme' => 'white',
	            'logo_text' => $request->name,
                'logo_image_url' => '/storage/branding/your-logo.png',
                'logo_height' => 36,
                'logo_filter_key' => 'midnight',
	            'copyright' => '© ' . now()->year . '. All rights reserved.',
	        ],
	    ];

	    $website = DB::transaction(function () use ($request, $template, $templates, $defaults, $websiteLimits, $myBrandThemes) {
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->first();

            if ($message = $websiteLimits->validationMessage($request->user())) {
                throw ValidationException::withMessages(['website_limit' => $message]);
            }

	        $templateAttributes = $template
	            ? $templates->websiteAttributes($template, $request->name)
	            : [];


            if ($template) {
                $kitFamily = (string) data_get($templateAttributes, 'theme_settings.primary', 'midnight');
                $customBrandTheme = $myBrandThemes->seedFromFamily($kitFamily);

                $templateAttributes['theme_settings'] = [
                    ...(array) ($templateAttributes['theme_settings'] ?? []),
                    'primary' => 'my-brand',
                    'secondary' => 'white',
                    'tertiary' => 'surface',
                    'auto' => true,
                    'custom_brand_theme' => $customBrandTheme,
                    'brand_palette' => $customBrandTheme['palette'],
                    'brand_source' => 'starter_kit',
                ];
            }

	        $workspace = $request->user()->ownedWorkspaces()->first();

            $website = Website::create([
                'user_id' => $request->user()->id,
                'workspace_id' => $workspace?->id,
	            ...$defaults,
	            ...$templateAttributes,
	        ]);

	        if ($template) {
	            foreach ($templates->pages($template) as $page) {
	                $website->pages()->create($page);
	            }
	        }

	        return $website;
	    });

	    return redirect()->route('pages.index', $website);
	}

    public function duplicate(
        Request $request,
        Website $website,
        AgencyWebsiteLimitService $websiteLimits,
        WebsiteDuplicationService $duplicator,
    ) {
        $this->authorize('update', $website);

        $copy = DB::transaction(function () use ($request, $website, $websiteLimits, $duplicator) {
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->first();

            if ($message = $websiteLimits->validationMessage($request->user())) {
                throw ValidationException::withMessages(['website_limit' => $message]);
            }

            $workspace = $request->user()->ownedWorkspaces()->first();

            return $duplicator->duplicate($website, $request->user(), $workspace);
        });

        return redirect()
            ->route('pages.index', $copy)
            ->with('success', "{$copy->name} was created as a draft copy.");
    }

    public function bulkAction(Request $request, BulkWebsiteActionService $bulkActions)
    {
        $user = $request->user();

        abort_unless($user->hasPlanCapability('bulk_actions'), 403, 'Bulk website actions require the Pro Agency plan.');

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:duplicate,disconnect,delete'],
            'website_ids' => ['required', 'array', 'min:1', 'max:50'],
            'website_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $websites = Website::query()
            ->whereIn('id', $validated['website_ids'])
            ->get()
            ->sortBy(fn (Website $website) => array_search($website->id, $validated['website_ids'], true))
            ->values();

        if ($websites->count() !== count($validated['website_ids'])) {
            throw ValidationException::withMessages([
                'website_ids' => 'One or more selected websites no longer exist.',
            ]);
        }

        foreach ($websites as $website) {
            $ability = $validated['action'] === 'delete' ? 'delete' : 'view';
            $this->authorize($ability, $website);
        }

        return response()->json($bulkActions->execute($user, $websites, $validated['action']));
    }

    public function transferOwnership(
        Request $request,
        Website $website,
        WebsiteOwnershipTransferService $transfers,
    ) {
        $this->authorize('transferOwnership', $website);

        $validated = $request->validate([
            'recipient_email' => ['required', 'email:rfc', 'max:254'],
        ]);

        $transfer = $transfers->transfer($website, $request->user(), $validated['recipient_email']);

        return redirect()->route('dashboard', ['tab' => 'websites'], 303)
            ->with('success', "Handoff invitation created for {$transfer->recipient_email}. Share this secure link within 7 days: ".route('website-handoffs.show', $transfer->token))
            ->with('handoff_url', route('website-handoffs.show', $transfer->token));
    }

    public function destroy(Website $website, \App\Services\PreviewDeploymentService $previews)
    {
        $this->authorize('delete', $website);

        // Remove the generated preview package before deleting the database row
        // so abandoned preview files cannot accumulate on the server.
        $previews->remove($website);
        $website->delete();

        // Inertia must follow a DELETE response with a GET request. A 302 can
        // preserve the original DELETE method and incorrectly hit /dashboard.
        return redirect()->route('dashboard', [], 303)->with('success', 'Website deleted.');
    }

    public function updateSettings(Request $request, Website $website)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'url', 'max:2048'],
            'industry' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'business_description' => ['nullable', 'string', 'max:3000'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            // Settings modal is intentionally a compact partial editor. Keep existing
            // timezone/locale when those fields are not part of this request.
            'timezone' => ['sometimes', 'string', 'max:80'],
            'locale' => ['sometimes', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'vat_number' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:3072'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);

        $settings = $website->settings ?? [];

        foreach (['business_name', 'address', 'company_name', 'owner_name', 'registration_number', 'vat_number'] as $field) {
            $settings[$field] = $validated[$field] ?? null;
        }

        foreach ([
            'logo' => ['path_key' => 'logo_path', 'directory' => 'website-branding/logos', 'remove_key' => 'remove_logo'],
            'favicon' => ['path_key' => 'favicon_path', 'directory' => 'website-branding/favicons', 'remove_key' => 'remove_favicon'],
        ] as $uploadField => $config) {
            $oldPath = $settings[$config['path_key']] ?? null;
            $shouldRemove = (bool) ($validated[$config['remove_key']] ?? false);

            if ($request->hasFile($uploadField)) {
                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }

                $settings[$config['path_key']] = $request->file($uploadField)->store($config['directory'], 'public');
            } elseif ($shouldRemove) {
                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }

                $settings[$config['path_key']] = null;
            }
        }

        $website->update([
            'name' => $validated['name'],
            'domain' => $validated['domain'] ?? null,
            'industry' => $validated['industry'] ?? null,
            'location' => $validated['location'] ?? null,
            'business_description' => $validated['business_description'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'timezone' => $validated['timezone'] ?? $website->timezone,
            'locale' => $validated['locale'] ?? $website->locale,
            'settings' => $settings,
        ]);

        // Axios callers need JSON; returning an Inertia redirect here made the compact
        // settings modal look like Save did nothing even when validation passed.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Website settings updated.',
                'website' => $website->fresh(),
            ]);
        }

        return back()->with('status', 'website-settings-updated');
    }

    public function updateProfile(Request $request, Website $website)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'industry' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:255'],
            'business_description' => ['required', 'string', 'max:2000'],
        ]);

        $website->update($validated);

        return response()->json([
            'status' => 'success',
            'website' => $website->fresh(),
        ]);
    }

    public function downloadDeploymentConnector(Website $website, DeploymentConnectorArchive $connector)
    {
        $this->authorize('update', $website);

        if (! filter_var($website->domain, FILTER_VALIDATE_URL)) {
            return back()->withErrors(['domain' => 'Add a valid website domain before downloading its deployment connector.']);
        }

        if (! $website->deployment_secret) {
            $website->deployment_secret = Str::random(64);
            $website->save();
        }

        $archivePath = $connector->create($website);

        return response()
            ->download($archivePath, 'cosmic-sync-' . Str::slug($website->name) . '.zip')
            ->deleteFileAfterSend(true);
    }

    public function verifyDeploymentConnector(Website $website)
    {
        $this->authorize('update', $website);

        if ($error = $this->verifyConnector($website)) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json([
            'status' => 'connected',
            'message' => 'Live site connector verified successfully.',
        ]);
    }

    public function pushLiveUpdate(Website $website, PagePublisher $publisher)
    {
        $this->authorize('update', $website);

        if (! $website->deployment_verified_at && $this->verifyConnector($website)) {
            return response()->json([
                'message' => $website->deployment_error ?? 'The live site connector could not be verified before pushing this update.',
            ], 422);
        }

        try {
            $package = $publisher->publishedPackage($website);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'status' => 'media_not_ready',
                'message' => $exception->getMessage(),
            ], 409);
        }

        if ($package['pages'] === []) {
            return response()->json(['message' => 'Publish at least one page before pushing a live update.'], 422);
        }

        $endpoint = rtrim((string) $website->domain, '/') . '/cosmic-sync/sync.php?action=receive_package';

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders(['X-Cosmic-Sync-Secret' => $website->deployment_secret])
                ->post($endpoint, $package);

            if (! $response->successful() || $response->json('status') !== 'success') {
                $website->update(['deployment_error' => 'The live site did not accept this update. Your existing live files were not changed.']);

                return response()->json(['message' => $website->deployment_error], 422);
            }
        } catch (\Throwable $exception) {
            report($exception);
            $website->update(['deployment_error' => 'The live site could not be reached. Your existing live files were not changed.']);

            return response()->json(['message' => $website->deployment_error], 422);
        }

        $website->update([
            'last_deployed_at' => now(),
            'deployment_error' => null,
        ]);

        return response()->json([
            'status' => 'deployed',
            'message' => 'Published pages were pushed to the live site.',
            'files' => $response->json('files', []),
        ]);
    }

    /**
     * Verify the installed connector before an explicit connection or a live
     * push. Returning an error message keeps both flows consistent.
     */
    private function verifyConnector(Website $website): ?string
    {
        if (! filter_var($website->domain, FILTER_VALIDATE_URL)) {
            return 'Add a valid website domain before connecting a live site.';
        }

        if (! $website->deployment_secret) {
            return 'Download and install this website\'s deployment connector before connecting it.';
        }

        $endpoint = rtrim((string) $website->domain, '/') . '/cosmic-sync/sync.php?action=verify';

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['X-Cosmic-Sync-Secret' => $website->deployment_secret])
                ->get($endpoint);

            if (! $response->successful() || $response->json('status') !== 'success') {
                $error = 'The deployment connector could not be verified at the configured domain.';
                $website->update([
                    'deployment_verified_at' => null,
                    'deployment_error' => $error,
                ]);

                return $error;
            }
        } catch (\Throwable $exception) {
            report($exception);

            $error = 'The deployment connector could not be reached. Check the domain, Apache, and connector folder.';
            $website->update([
                'deployment_verified_at' => null,
                'deployment_error' => $error,
            ]);

            return $error;
        }

        $website->update([
            'deployment_verified_at' => now(),
            'deployment_error' => null,
        ]);

        return null;
    }

	public function saveFooter(Request $request, Website $website)
	{
	    $this->authorize('update', $website);

	    $request->validate([
	        'footer_block' => 'required|array'
	    ]);

	    $website->global_footer = $request->footer_block;
	    $website->published_global_footer = $request->footer_block;
	    $website->save();
	    
	    return response()->json(['status' => 'success', 'data' => $website->global_footer]);
	}

    public function downloadBridge()
    {
        $zipName = 'cosmic-client-bridge.zip';
        $storageDir = storage_path('app');
        $zipPath = $storageDir . '/' . $zipName;
        
        $tempDir = $storageDir . '/temp_bridge';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Kani ang advanced, dynamic template rendering script sa client side
        $indexPhp = '<?php
		define("CMS_API_URL", "' . url('/api/v1/sync') . '");

		if (file_exists("config.php")) {
		    include "config.php";
		    
		    if (file_exists("content.json")) {
		        $data = json_decode(file_get_contents("content.json"), true);
		        $pages = $data[\'pages\'] ?? [];
		        
		        // 1. Router Logic: Tan-awon unsa nga slug ang gi-request (Default kay ang unang page)
		        $currentSlug = $_GET[\'page\'] ?? ($pages[0][\'slug\'] ?? \'home\');
		        
		        // Find current page data
		        $currentPage = null;
		        foreach ($pages as $p) {
		            if ($p[\'slug\'] === $currentSlug) {
		                $currentPage = $p;
		                break;
		            }
		        }
		        
		        // HTML Frontend Layout Template View
		        echo "<!DOCTYPE html>
		        <html lang=\"en\">
		        <head>
		            <meta charset=\"UTF-8\">
		            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
		            <title>" . htmlspecialchars($data[\'website_name\'] ?? \'Cosmic Site\') . "</title>
		            <link rel=\"preconnect\" href=\"https://fonts.bunny.net\">
		            <link href=\"https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap\" rel=\"stylesheet\">
		            <script src=\"https://cdn.tailwindcss.com\"></script>
                    <style>[data-cosmic-spark]{padding-top:50px!important;padding-bottom:50px!important}main>[data-cosmic-spark]:not(:first-child){content-visibility:auto;contain-intrinsic-size:800px}@media(min-width:640px){[data-cosmic-spark]{padding-top:80px!important;padding-bottom:80px!important}}</style>
		        </head>
		        <body class=\"bg-slate-50 text-slate-900 font-sans\">";
		        
		        // HEADER / DYNAMIC NAVIGATION BAR
		        echo "<header class=\"bg-white shadow-sm border-b border-slate-200 sticky top-0 z-50\">
		            <div class=\"max-w-6xl mx-auto px-4 py-4 flex justify-between items-center\">
		                <div class=\"font-bold text-xl text-indigo-600\">🚀 " . htmlspecialchars($data[\'website_name\']) . "</div>
		                <nav class=\"flex space-x-2\">";
		                foreach ($pages as $p) {
		                    $activeClass = ($p[\'slug\'] === $currentSlug) ? "bg-indigo-600 text-white" : "text-slate-600 hover:bg-slate-100";
		                    echo "<a href=\"?page=" . $p[\'slug\'] . "\" class=\"px-3 py-1.5 rounded-md text-sm font-medium transition {$activeClass}\">" . htmlspecialchars($p[\'title\']) . "</a>";
		                }
		                echo "<a href=\"?sync=true\" class=\"ml-4 px-3 py-1.5 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700 transition\">🔄 Sync</a>
		                </nav>
		            </div>
		        </header>";
		        
		        // DYNAMIC BLOCK COMPILER ENGINE
		        if ($currentPage) {
		            $blocks = $currentPage[\'blocks\'] ?? [];
		            foreach ($blocks as $block) {
		                if ($block[\'type\'] === \'hero\') {
		                    echo "<section class=\"py-20 text-center text-white shadow-inner\" style=\"background-color: {$block[\'bg_color\']};\">
		                        <div class=\"max-w-3xl mx-auto px-4\">
		                            <h1 class=\"text-4xl md:text-5xl font-extrabold tracking-tight mb-4\">" . htmlspecialchars($block[\'heading\']) . "</h1>
		                            <p class=\"text-lg md:text-xl text-indigo-200\">" . htmlspecialchars($block[\'subheading\']) . "</p>
		                        </div>
		                    </section>";
		                }
		                if ($block[\'type\'] === \'content\') {
		                    echo "<section class=\"py-16 max-w-3xl mx-auto px-4\">
		                        <div class=\"bg-white p-8 rounded-xl shadow-sm border border-slate-100\">
		                            <p class=\"text-lg leading-relaxed text-slate-700\">" . htmlspecialchars($block[\'text\']) . "</p>
		                        </div>
		                    </section>";
		                }
		            }
		        } else {
		            echo "<div class=\"text-center py-20\"><h1 class=\"text-2xl font-bold text-red-500\">404 - Page Not Found</h1></div>";
		        }
		        
		        echo "<footer class=\"bg-slate-800 text-slate-400 py-8 text-center text-sm border-t border-slate-700 mt-20\"><p>&copy; " . date(\'Y\') . " Powered by Cosmic Headless CMS Pipeline</p></footer></body></html>";
		        
		    } else {
		        echo "<h1>Connected to CMS!</h1><p>Use the sync button below to pull the latest website data.</p>";
		        echo "<br><a href=\"?sync=true\" style=\"padding:10px 20px; background:#10b981; color:#fff; text-decoration:none; border-radius:5px;\">🔄 Sync Content Now</a>";
		    }
		} else {
		    // Installer Form View
		    echo "
		    <div style=\"max-width:400px; margin:50px auto; font-family:Manrope, sans-serif; padding:20px; border:1px solid #ccc; border-radius:8px;\">
		        <h2>Cosmic CMS Client Bridge 🚀</h2>
		        <p style=\"font-size:13px; color:#666;\">Paste the token from your Cosmic Dashboard to sync pages and blocks.</p>
		        <form method=\"POST\">
		            <input type=\"text\" name=\"api_token\" placeholder=\"Paste CMS API Token\" style=\"width:100%; padding:8px; margin-bottom:10px;\" required><br>
		            <button type=\"submit\" style=\"width:100%; padding:10px; background:#4f46e5; color:white; border:none; border-radius:4px; cursor:pointer;\">Save & Initialize</button>
		        </form>
		    </div>";
		}

		if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["api_token"])) {
		    $configContent = "<?php
define(\"API_TOKEN\", \"" . addslashes($_POST["api_token"]) . "\");
";
		    file_put_contents("config.php", $configContent);
		    header("Location: index.php?sync=true");
		    exit;
		}

		if (isset($_GET["sync"]) && $_GET["sync"] == "true" && defined("API_TOKEN")) {
		    $ch = curl_init(CMS_API_URL);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    curl_setopt($ch, CURLOPT_HTTPHEADER, [
		        "X-Cosmic-Token: " . API_TOKEN,
		        "Accept: application/json"
		    ]);
		    $response = curl_exec($ch);
		    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		    curl_close($ch);
		    
		    if ($httpCode == 200 && $response) {
		        file_put_contents("content.json", $response);
		        echo "<script>alert(\"Sync Complete! Data updated successfully.\"); window.location.href=\"index.php\";</script>";
		    } else {
		        echo "<script>alert(\"Sync failed. Please verify your API token.\"); window.location.href=\"index.php\";</script>";
		    }
		}
		?>';

        file_put_contents($tempDir . '/index.php', $indexPhp);

        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        
        $cmd = "powershell -Command \"Compress-Archive -Path '{$tempDir}/*' -DestinationPath '{$zipPath}' -Force\"";
        exec($cmd);

        unlink($tempDir . '/index.php');
        rmdir($tempDir);

        if (file_exists($zipPath)) {
            return response()->download($zipPath)->deleteFileAfterSend(true);
        }

        return back()->with('error', 'Failed to generate ZIP file.');
    }
    public function mediaPackStatus(Request $request, Website $website)
    {
        $this->authorize('view', $website);

        $pack = $website->mediaPack;
        if (! $pack) {
            return response()->json([
                'status' => 'missing',
                'ready' => false,
                'terminal' => true,
                'image_count' => 0,
                'target_image_count' => 0,
            ]);
        }

        $status = (string) $pack->status;
        $terminal = in_array($status, ['ready', 'partial', 'failed'], true);
        $imageCount = $status === 'localizing'
            ? (int) data_get($pack->manifest, 'localized_image_count', 0)
            : (int) data_get($pack->manifest, 'image_count', 0);
        $target = (int) $pack->target_image_count;
        $ratio = $target > 0 ? min(1, $imageCount / $target) : 0;
        $progress = match ($status) {
            'pending' => 5,
            'queued' => 12,
            'downloading', 'localizing' => min(95, 20 + (int) round($ratio * 75)),
            'ready', 'partial', 'failed' => 100,
            default => 0,
        };
        $delayed = in_array($status, ['queued', 'downloading', 'localizing'], true)
            && $pack->queued_at
            && $pack->queued_at->lt(now()->subSeconds(45));

        return response()->json([
            'status' => $status,
            'ready' => in_array($status, ['ready', 'partial'], true),
            'terminal' => $terminal,
            'image_count' => $imageCount,
            'target_image_count' => $target,
            'progress' => $progress,
            'delayed' => (bool) $delayed,
            'message' => match (true) {
                $status === 'ready' => 'Images ready',
                $status === 'partial' => 'Using the best available images',
                $status === 'failed' => 'Using safe fallback images',
                $delayed => 'Images are taking longer than usual; you can keep editing',
                $status === 'localizing' => 'Securing your website images in the background',
                $status === 'downloading' => 'Optimizing images in the background',
                default => 'Preparing images',
            },
            'last_error' => $pack->last_error,
        ]);
    }

}
