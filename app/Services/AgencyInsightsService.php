<?php

namespace App\Services;

use App\Models\ContactSubmission;
use App\Models\SalesEvent;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAnalyticsDaily;
use Illuminate\Support\Collection;

class AgencyInsightsService
{
    public function build(User $user, array $planCapabilities, array $filters = []): array
    {
        $capabilities = (array) ($planCapabilities['capabilities'] ?? []);
        $family = (string) ($planCapabilities['plan_family'] ?? 'personal');
        $analyticsLevel = (string) ($capabilities['analytics_level'] ?? 'none');
        $leadsLevel = (string) ($capabilities['leads_level'] ?? 'none');
        $salesLevel = (string) ($capabilities['sales_level'] ?? 'none');
        $hasInsights = (bool) ($capabilities['agency_insights'] ?? false);

        $base = [
            'available' => $family === 'agency' && $hasInsights,
            'plan_family' => $family,
            'analytics_level' => $analyticsLevel,
            'leads_level' => $leadsLevel,
            'sales_level' => $salesLevel,
            'modules' => $this->modules($analyticsLevel, $leadsLevel, $salesLevel, $capabilities),
            'period_options' => [
                ['key' => '7d', 'label' => 'Last 7 days'],
                ['key' => '30d', 'label' => 'Last 30 days'],
                ['key' => '90d', 'label' => 'Last 90 days'],
                ['key' => 'custom', 'label' => 'Custom range'],
            ],
        ];

        if ($family !== 'agency') {
            return [
                ...$base,
                'reason' => 'Agency Insights is available on Growth Agency and Pro Agency plans.',
                'upgrade_plan' => 'agency_growth',
            ];
        }

        if (! $hasInsights) {
            return [
                ...$base,
                'reason' => 'Starter Agency includes per-website summaries. Upgrade to Growth Agency to unlock the account-wide Agency Insights dashboard.',
                'upgrade_plan' => 'agency_growth',
            ];
        }

        $websites = Website::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('workspace', function ($workspaceQuery) use ($user) {
                        $workspaceQuery->where('owner_user_id', $user->id)
                            ->orWhereHas('users', fn ($memberQuery) => $memberQuery->whereKey($user->id));
                    });
            })
            ->withCount([
                'pages',
                'pages as published_pages_count' => fn ($query) => $query->where('status', 'published'),
                'contactSubmissions as leads_count' => fn ($query) => $query->whereNull('archived_at'),
                'contactSubmissions as unread_leads_count' => fn ($query) => $query->whereNull('read_at')->whereNull('archived_at'),
            ])
            ->latest('updated_at')
            ->get();

        $websiteIds = $websites->pluck('id');
        $now = now();
        $periodStart = $now->copy()->subDays(30);
        $previousStart = $now->copy()->subDays(60);

        $leadQuery = ContactSubmission::query()->whereIn('website_id', $websiteIds);
        $leadsCurrent = (clone $leadQuery)->where('received_at', '>=', $periodStart)->count();
        $leadsPrevious = (clone $leadQuery)->whereBetween('received_at', [$previousStart, $periodStart])->count();
        $totalLeads = (clone $leadQuery)->whereNull('archived_at')->count();
        $unreadLeads = (clone $leadQuery)->whereNull('read_at')->whereNull('archived_at')->count();

        $publishedSites = $websites->filter(fn (Website $website) => (int) $website->published_pages_count > 0)->count();
        $connectedSites = $websites->filter(fn (Website $website) => $website->deployment_verified_at !== null)->count();
        $deployedSites = $websites->filter(fn (Website $website) => $website->last_deployed_at !== null)->count();

        return [
            ...$base,
            'is_aggregated' => in_array($analyticsLevel, ['aggregated', 'full_agency'], true),
            'can_view_aggregated_leads' => in_array($leadsLevel, ['aggregated', 'full_agency'], true),
            'can_view_sales' => in_array($salesLevel, ['summary', 'full_agency'], true),
            'summary' => [
                'websites' => $websites->count(),
                'published_websites' => $publishedSites,
                'connected_websites' => $connectedSites,
                'deployed_websites' => $deployedSites,
                'pages' => (int) $websites->sum('pages_count'),
                'published_pages' => (int) $websites->sum('published_pages_count'),
                'leads' => $totalLeads,
                'unread_leads' => $unreadLeads,
                'leads_last_30_days' => $leadsCurrent,
                'lead_change_percent' => $this->percentageChange($leadsPrevious, $leadsCurrent),
            ],
            'filters' => [
                'websites' => $websites->map(fn (Website $website) => [
                    'id' => $website->id,
                    'label' => $website->name ?: 'Untitled Website',
                ])->values()->all(),
                'default_website' => 'all',
                'default_period' => '30d',
            ],
            'websites' => $this->websiteRows($websites),
            'recent_leads' => $this->recentLeads($leadQuery, $leadsLevel),
            'leads_dashboard' => $this->leadsDashboard($leadQuery, $websites, $leadsLevel, $filters),
            'analytics' => app(AgencyAnalyticsService::class)->aggregate(
                $websites,
                (int) ($filters['days'] ?? 30),
                isset($filters['website_id']) ? (int) $filters['website_id'] : null,
                $filters['starts_at'] ?? null,
                $filters['ends_at'] ?? null,
            ),
            'sales' => $this->salesDashboard($websiteIds, $websites, $salesLevel, $filters),
            'conversions' => $this->conversionDashboard($leadQuery, $websiteIds, $websites, (bool) ($capabilities['conversion_reporting'] ?? false), $filters),
            'ai_insights' => $this->aiInsights($leadQuery, $websiteIds, $websites, (bool) ($capabilities['ai_insights_foundation'] ?? false)),
            'reporting_health' => $this->reportingHealth($websiteIds, $websites),
            'refreshed_at' => now()->toIso8601String(),
            'period' => [
                'key' => '30d',
                'label' => 'Last 30 days',
                'starts_at' => $periodStart->toDateString(),
                'ends_at' => $now->toDateString(),
            ],
        ];
    }

    private function modules(string $analyticsLevel, string $leadsLevel, string $salesLevel, array $capabilities): array
    {
        return [
            ['key' => 'overview', 'label' => 'Overview', 'status' => 'active', 'enabled' => true],
            ['key' => 'analytics', 'label' => 'Analytics', 'status' => 'active', 'enabled' => in_array($analyticsLevel, ['aggregated', 'full_agency'], true)],
            ['key' => 'leads', 'label' => 'Leads', 'status' => 'active', 'enabled' => in_array($leadsLevel, ['aggregated', 'full_agency'], true)],
            ['key' => 'sales', 'label' => 'Sales', 'status' => 'active', 'enabled' => in_array($salesLevel, ['summary', 'full_agency'], true)],
            ['key' => 'conversions', 'label' => 'Conversions', 'status' => (bool) ($capabilities['conversion_reporting'] ?? false) ? 'active' : 'pro', 'enabled' => (bool) ($capabilities['conversion_reporting'] ?? false)],
            ['key' => 'ai', 'label' => 'AI Insights', 'status' => (bool) ($capabilities['ai_insights_foundation'] ?? false) ? 'active' : 'pro', 'enabled' => (bool) ($capabilities['ai_insights_foundation'] ?? false)],
        ];
    }

    private function websiteRows(Collection $websites): array
    {
        return $websites->map(fn (Website $website) => [
            'id' => $website->id,
            'name' => $website->name ?: 'Untitled Website',
            'domain' => $website->domain ?: 'No domain connected',
            'industry' => $website->industry ?: 'Uncategorized',
            'status' => (int) $website->published_pages_count > 0 ? 'Published' : 'Draft',
            'deployment_status' => $website->last_deployed_at ? 'Deployed' : ($website->deployment_verified_at ? 'Connected' : 'Not connected'),
            'pages_count' => (int) $website->pages_count,
            'published_pages_count' => (int) $website->published_pages_count,
            'leads_count' => (int) $website->leads_count,
            'unread_leads_count' => (int) $website->unread_leads_count,
            'updated_at' => $website->updated_at?->toIso8601String(),
        ])->values()->all();
    }

    private function leadsDashboard($leadQuery, Collection $websites, string $leadsLevel, array $filters): array
    {
        if (! in_array($leadsLevel, ['aggregated', 'full_agency'], true)) {
            return ['available' => false, 'items' => [], 'summary' => []];
        }

        $query = clone $leadQuery;
        $query->whereNull('archived_at');

        $website = $filters['lead_website'] ?? 'all';
        $status = $filters['lead_status'] ?? 'all';
        $source = $filters['lead_source'] ?? 'all';
        $search = trim((string) ($filters['lead_query'] ?? ''));
        $start = $filters['lead_start'] ?? null;
        $end = $filters['lead_end'] ?? null;

        if ($website !== 'all' && $websites->pluck('id')->contains((int) $website)) {
            $query->where('website_id', (int) $website);
        }
        if (in_array($status, ['new', 'contacted', 'qualified', 'customer', 'lost'], true)) {
            $query->where('lead_status', $status);
        }
        if ($source !== 'all') {
            $query->where('source', $source);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }
        if ($start) {
            $query->whereDate('received_at', '>=', $start);
        }
        if ($end) {
            $query->whereDate('received_at', '<=', $end);
        }

        $all = (clone $query)->get();
        $total = $all->count();
        $customers = $all->where('lead_status', 'customer')->count();

        return [
            'available' => true,
            'has_data' => $total > 0,
            'summary' => [
                'total' => $total,
                'new' => $all->where('lead_status', 'new')->count(),
                'contacted' => $all->where('lead_status', 'contacted')->count(),
                'qualified' => $all->where('lead_status', 'qualified')->count(),
                'customers' => $customers,
                'lost' => $all->where('lead_status', 'lost')->count(),
                'conversion_rate' => $total ? round(($customers / $total) * 100, 1) : 0,
            ],
            'sources' => ContactSubmission::query()->whereIn('website_id', $websites->pluck('id'))->whereNotNull('source')->distinct()->orderBy('source')->pluck('source')->values()->all(),
            'filters' => [
                'website' => (string) $website,
                'status' => (string) $status,
                'source' => (string) $source,
                'query' => $search,
                'start' => $start,
                'end' => $end,
            ],
            'items' => (clone $query)->with('website:id,name,industry')->latest('received_at')->limit(250)->get()->map(fn (ContactSubmission $lead) => [
                'id' => $lead->id,
                'website_id' => $lead->website_id,
                'website_name' => $lead->website?->name ?: 'Website',
                'industry' => $lead->website?->industry ?: 'Uncategorized',
                'name' => $lead->name ?: 'Anonymous lead',
                'email' => $lead->email,
                'phone' => $lead->phone,
                'message' => $lead->message,
                'fields' => $lead->fields,
                'source' => $lead->source ?: 'contact_form',
                'lead_status' => $lead->lead_status ?: 'new',
                'notes' => $lead->notes,
                'received_at' => $lead->received_at?->toIso8601String(),
                'received_label' => $lead->received_at?->diffForHumans() ?? 'Recently',
                'qualified_at' => $lead->qualified_at?->toIso8601String(),
                'converted_at' => $lead->converted_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }


    private function salesDashboard(Collection $websiteIds, Collection $websites, string $salesLevel, array $filters): array
    {
        if (! in_array($salesLevel, ['summary', 'full_agency'], true)) {
            return ['available' => false, 'items' => [], 'summary' => []];
        }

        $query = SalesEvent::query()->whereIn('website_id', $websiteIds);
        $website = $filters['sale_website'] ?? 'all';
        $status = $filters['sale_status'] ?? 'all';
        $source = $filters['sale_source'] ?? 'all';
        $start = $filters['sale_start'] ?? now()->subDays(29)->toDateString();
        $end = $filters['sale_end'] ?? now()->toDateString();

        if ($website !== 'all' && $websiteIds->contains((int) $website)) {
            $query->where('website_id', (int) $website);
        }
        if (in_array($status, ['completed', 'pending', 'refunded', 'failed'], true)) {
            $query->where('status', $status);
        }
        if ($source !== 'all') {
            $query->where('source', $source);
        }
        if ($start) {
            $query->whereDate('occurred_at', '>=', $start);
        }
        if ($end) {
            $query->whereDate('occurred_at', '<=', $end);
        }

        $all = (clone $query)->get();
        $completed = $all->where('status', 'completed');
        $refunded = $all->where('status', 'refunded');
        $revenueMinor = (int) $completed->sum('amount_minor') - (int) $refunded->sum('amount_minor');
        $currency = $all->pluck('currency')->filter()->first() ?: 'USD';
        $averageOrder = $completed->count() ? (int) round($completed->sum('amount_minor') / $completed->count()) : 0;

        $daily = (clone $query)->where('status', 'completed')->get()
            ->groupBy(fn (SalesEvent $event) => $event->occurred_at?->toDateString())
            ->map(fn ($events, $date) => [
                'date' => $date,
                'orders' => $events->count(),
                'revenue_minor' => (int) $events->sum('amount_minor'),
            ])->sortKeys()->values()->all();

        $byWebsite = $completed->groupBy('website_id')->map(function ($events, $websiteId) use ($websites) {
            $site = $websites->firstWhere('id', (int) $websiteId);
            return [
                'website_id' => (int) $websiteId,
                'website_name' => $site?->name ?: 'Website',
                'orders' => $events->count(),
                'revenue_minor' => (int) $events->sum('amount_minor'),
            ];
        })->sortByDesc('revenue_minor')->values()->take(8)->all();

        return [
            'available' => true,
            'has_data' => $all->isNotEmpty(),
            'currency' => $currency,
            'summary' => [
                'revenue_minor' => $revenueMinor,
                'completed_orders' => $completed->count(),
                'pending_orders' => $all->where('status', 'pending')->count(),
                'refunded_orders' => $refunded->count(),
                'failed_orders' => $all->where('status', 'failed')->count(),
                'average_order_minor' => $averageOrder,
            ],
            'filters' => [
                'website' => (string) $website,
                'status' => (string) $status,
                'source' => (string) $source,
                'start' => $start,
                'end' => $end,
            ],
            'sources' => SalesEvent::query()->whereIn('website_id', $websiteIds)->distinct()->orderBy('source')->pluck('source')->filter()->values()->all(),
            'daily' => $daily,
            'by_website' => $byWebsite,
            'items' => (clone $query)->with('website:id,name')->latest('occurred_at')->limit(250)->get()->map(fn (SalesEvent $event) => [
                'id' => $event->id,
                'website_id' => $event->website_id,
                'website_name' => $event->website?->name ?: 'Website',
                'customer_name' => $event->customer_name ?: 'Anonymous customer',
                'customer_email' => $event->customer_email,
                'product' => $event->product ?: 'Sale',
                'amount_minor' => (int) $event->amount_minor,
                'currency' => $event->currency,
                'status' => $event->status,
                'source' => $event->source,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'occurred_label' => $event->occurred_at?->diffForHumans() ?? 'Recently',
            ])->values()->all(),
        ];
    }

    private function conversionDashboard($leadQuery, Collection $websiteIds, Collection $websites, bool $enabled, array $filters): array
    {
        if (! $enabled) {
            return ['available' => false, 'summary' => [], 'funnel' => [], 'by_website' => []];
        }

        $website = $filters['conversion_website'] ?? 'all';
        $start = $filters['conversion_start'] ?? now()->subDays(29)->toDateString();
        $end = $filters['conversion_end'] ?? now()->toDateString();
        $leadBase = clone $leadQuery;
        $saleBase = SalesEvent::query()->whereIn('website_id', $websiteIds);

        if ($website !== 'all' && $websiteIds->contains((int) $website)) {
            $leadBase->where('website_id', (int) $website);
            $saleBase->where('website_id', (int) $website);
        }
        if ($start) {
            $leadBase->whereDate('received_at', '>=', $start);
            $saleBase->whereDate('occurred_at', '>=', $start);
        }
        if ($end) {
            $leadBase->whereDate('received_at', '<=', $end);
            $saleBase->whereDate('occurred_at', '<=', $end);
        }

        $leads = $leadBase->whereNull('archived_at')->get();
        $sales = $saleBase->where('status', 'completed')->get();
        $qualified = $leads->whereIn('lead_status', ['qualified', 'customer'])->count();
        $customers = $leads->where('lead_status', 'customer')->count();
        $completedSales = $sales->count();
        $revenue = (int) $sales->sum('amount_minor');
        $leadCount = $leads->count();

        $byWebsite = $websites->map(function (Website $site) use ($leads, $sales) {
            $siteLeads = $leads->where('website_id', $site->id);
            $siteSales = $sales->where('website_id', $site->id);
            return [
                'website_id' => $site->id,
                'website_name' => $site->name ?: 'Website',
                'leads' => $siteLeads->count(),
                'qualified' => $siteLeads->whereIn('lead_status', ['qualified', 'customer'])->count(),
                'customers' => $siteLeads->where('lead_status', 'customer')->count(),
                'sales' => $siteSales->count(),
                'revenue_minor' => (int) $siteSales->sum('amount_minor'),
                'conversion_rate' => $siteLeads->count() ? round(($siteSales->count() / $siteLeads->count()) * 100, 1) : 0,
            ];
        })->filter(fn ($row) => $row['leads'] > 0 || $row['sales'] > 0)->sortByDesc('conversion_rate')->values()->all();

        return [
            'available' => true,
            'currency' => $sales->pluck('currency')->filter()->first() ?: 'USD',
            'filters' => ['website' => (string) $website, 'start' => $start, 'end' => $end],
            'summary' => [
                'leads' => $leadCount,
                'qualified' => $qualified,
                'customers' => $customers,
                'sales' => $completedSales,
                'revenue_minor' => $revenue,
                'lead_to_sale_rate' => $leadCount ? round(($completedSales / $leadCount) * 100, 1) : 0,
                'qualified_to_sale_rate' => $qualified ? round(($completedSales / $qualified) * 100, 1) : 0,
                'revenue_per_lead_minor' => $leadCount ? (int) round($revenue / $leadCount) : 0,
            ],
            'funnel' => [
                ['key' => 'leads', 'label' => 'Leads', 'value' => $leadCount],
                ['key' => 'qualified', 'label' => 'Qualified', 'value' => $qualified],
                ['key' => 'customers', 'label' => 'Customers', 'value' => $customers],
                ['key' => 'sales', 'label' => 'Completed sales', 'value' => $completedSales],
            ],
            'by_website' => $byWebsite,
        ];
    }

    private function aiInsights($leadQuery, Collection $websiteIds, Collection $websites, bool $enabled): array
    {
        if (! $enabled) {
            return ['available' => false, 'generated_at' => null, 'summary' => null, 'recommendations' => [], 'watchlist' => []];
        }

        $start = now()->subDays(29)->startOfDay();
        $previousStart = now()->subDays(59)->startOfDay();
        $previousEnd = now()->subDays(30)->endOfDay();

        $currentLeads = (clone $leadQuery)->whereNull('archived_at')->where('received_at', '>=', $start)->get();
        $previousLeads = (clone $leadQuery)->whereNull('archived_at')->whereBetween('received_at', [$previousStart, $previousEnd])->count();
        $currentSales = SalesEvent::query()->whereIn('website_id', $websiteIds)->where('status', 'completed')->where('occurred_at', '>=', $start)->get();

        $leadCount = $currentLeads->count();
        $salesCount = $currentSales->count();
        $revenue = (int) $currentSales->sum('amount_minor');
        $conversion = $leadCount ? round(($salesCount / $leadCount) * 100, 1) : 0;
        $leadChange = $this->percentageChange($previousLeads, $leadCount);

        $recommendations = [];
        if ($leadCount === 0) {
            $recommendations[] = ['priority' => 'high', 'title' => 'Start capturing lead activity', 'message' => 'No leads were recorded in the last 30 days. Verify forms on published websites and test each inquiry flow.', 'action' => 'Review website forms'];
        } elseif ($conversion < 5) {
            $recommendations[] = ['priority' => 'high', 'title' => 'Improve lead follow-up', 'message' => 'Lead-to-sale conversion is below 5%. Review response time, qualification steps, and calls to action on high-traffic websites.', 'action' => 'Open conversion report'];
        } else {
            $recommendations[] = ['priority' => 'positive', 'title' => 'Conversion foundation is working', 'message' => "The agency converted {$conversion}% of recorded leads into completed sales during the last 30 days.", 'action' => 'Compare top websites'];
        }

        if ($leadChange !== null && $leadChange <= -20) {
            $recommendations[] = ['priority' => 'medium', 'title' => 'Lead volume declined', 'message' => 'Lead volume dropped '.$leadChange.'% compared with the previous 30-day period. Check traffic sources and recently changed landing pages.', 'action' => 'Review analytics'];
        } elseif ($leadChange !== null && $leadChange >= 20) {
            $recommendations[] = ['priority' => 'positive', 'title' => 'Lead momentum increased', 'message' => 'Lead volume increased '.$leadChange.'% compared with the previous period. Identify which websites and sources drove the growth.', 'action' => 'Review lead sources'];
        }

        $websiteRows = $websites->map(function (Website $site) use ($currentLeads, $currentSales) {
            $leads = $currentLeads->where('website_id', $site->id)->count();
            $sales = $currentSales->where('website_id', $site->id)->count();
            return ['website_id' => $site->id, 'website_name' => $site->name ?: 'Website', 'leads' => $leads, 'sales' => $sales, 'conversion_rate' => $leads ? round(($sales / $leads) * 100, 1) : 0];
        })->filter(fn ($row) => $row['leads'] > 0 || $row['sales'] > 0)->values();

        $best = $websiteRows->sortByDesc('conversion_rate')->first();
        if ($best && $best['sales'] > 0) {
            $recommendations[] = ['priority' => 'info', 'title' => $best['website_name'].' leads conversion', 'message' => $best['website_name'].' currently has the strongest recorded conversion rate at '.$best['conversion_rate'].'%. Reuse its CTA and follow-up patterns where appropriate.', 'action' => 'Inspect website'];
        }

        $watchlist = $websiteRows->filter(fn ($row) => $row['leads'] >= 3 && $row['sales'] === 0)->sortByDesc('leads')->take(5)->values()->all();

        return [
            'available' => true,
            'generated_at' => now()->toIso8601String(),
            'period_label' => 'Last 30 days',
            'summary' => ['leads' => $leadCount, 'sales' => $salesCount, 'revenue_minor' => $revenue, 'conversion_rate' => $conversion, 'lead_change_percent' => $leadChange],
            'recommendations' => array_slice($recommendations, 0, 4),
            'watchlist' => $watchlist,
            'disclaimer' => 'Foundation insights use deterministic account data rules. A future AI provider can enrich explanations without changing this dashboard contract.',
        ];
    }

    private function recentLeads($leadQuery, string $leadsLevel): array
    {
        if (! in_array($leadsLevel, ['aggregated', 'full_agency'], true)) {
            return [];
        }

        return (clone $leadQuery)
            ->with('website:id,name')
            ->whereNull('archived_at')
            ->latest('received_at')
            ->limit(12)
            ->get()
            ->map(fn (ContactSubmission $lead) => [
                'id' => $lead->id,
                'website_id' => $lead->website_id,
                'website_name' => $lead->website?->name ?: 'Website',
                'name' => $lead->name ?: 'Anonymous lead',
                'email' => $lead->email,
                'phone' => $lead->phone,
                'status' => $lead->read_at ? 'Read' : 'Unread',
                'received_at' => $lead->received_at?->toIso8601String(),
                'received_label' => $lead->received_at?->diffForHumans() ?? 'Recently',
            ])->values()->all();
    }


    private function reportingHealth(Collection $websiteIds, Collection $websites): array
    {
        $total = $websites->count();
        $analyticsIds = $websiteIds->isEmpty()
            ? collect()
            : WebsiteAnalyticsDaily::query()->whereIn('website_id', $websiteIds)->distinct()->pluck('website_id');
        $leadIds = $websiteIds->isEmpty()
            ? collect()
            : ContactSubmission::query()->whereIn('website_id', $websiteIds)->distinct()->pluck('website_id');
        $salesIds = $websiteIds->isEmpty()
            ? collect()
            : SalesEvent::query()->whereIn('website_id', $websiteIds)->distinct()->pluck('website_id');

        $analyticsCount = $analyticsIds->count();
        $leadCount = $leadIds->count();
        $salesCount = $salesIds->count();
        $connectedCount = $websites->whereNotNull('deployment_verified_at')->count();

        $checks = [
            [
                'key' => 'websites',
                'label' => 'Client websites added',
                'complete' => $total > 0,
                'detail' => $total > 0 ? "{$total} website(s) available" : 'Add the first client website.',
            ],
            [
                'key' => 'domains',
                'label' => 'Publishing connections',
                'complete' => $total > 0 && $connectedCount === $total,
                'detail' => "{$connectedCount} of {$total} website(s) connected",
            ],
            [
                'key' => 'analytics',
                'label' => 'Analytics receiving data',
                'complete' => $total > 0 && $analyticsCount > 0,
                'detail' => "{$analyticsCount} of {$total} website(s) reporting traffic",
            ],
            [
                'key' => 'leads',
                'label' => 'Lead capture verified',
                'complete' => $total > 0 && $leadCount > 0,
                'detail' => "{$leadCount} of {$total} website(s) have lead data",
            ],
            [
                'key' => 'sales',
                'label' => 'Sales attribution verified',
                'complete' => $total > 0 && $salesCount > 0,
                'detail' => "{$salesCount} of {$total} website(s) have sales data",
            ],
        ];

        $completed = collect($checks)->where('complete', true)->count();
        $score = count($checks) ? (int) round(($completed / count($checks)) * 100) : 0;

        return [
            'score' => $score,
            'completed_checks' => $completed,
            'total_checks' => count($checks),
            'status' => $score === 100 ? 'ready' : ($score >= 60 ? 'in_progress' : 'setup_required'),
            'checks' => $checks,
        ];
    }

    private function percentageChange(int $previous, int $current): ?int
    {
        if ($previous === 0) {
            return $current === 0 ? 0 : null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }
}
