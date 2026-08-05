<?php

namespace App\Services;

use App\Models\WebsiteAnalyticsDaily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AgencyAnalyticsService
{
    public function aggregate(
        Collection $websites,
        int $days = 30,
        ?int $websiteId = null,
        ?string $startsAt = null,
        ?string $endsAt = null,
    ): array {
        $availableIds = $websites->pluck('id')->map(fn ($id) => (int) $id);
        $selectedWebsiteId = $websiteId && $availableIds->contains($websiteId) ? $websiteId : null;
        $ids = $selectedWebsiteId ? collect([$selectedWebsiteId]) : $availableIds;

        [$start, $end, $periodKey] = $this->resolvePeriod($days, $startsAt, $endsAt);
        $periodDays = $start->diffInDays($end) + 1;

        $rows = $ids->isEmpty()
            ? collect()
            : WebsiteAnalyticsDaily::query()
                ->whereIn('website_id', $ids)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get();

        $sum = static fn (Collection $collection, string $key): int => (int) $collection->sum($key);
        $pageViews = $sum($rows, 'page_views');
        $visitors = $sum($rows, 'visitors');
        $sessions = $sum($rows, 'sessions');
        $conversions = $sum($rows, 'conversions');
        $engaged = $sum($rows, 'engaged_sessions');
        $duration = $sum($rows, 'duration_seconds');

        $series = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dateString = $date->toDateString();
            $dateRows = $rows->where('date', $dateString);
            $series[] = [
                'date' => $dateString,
                'page_views' => $sum($dateRows, 'page_views'),
                'visitors' => $sum($dateRows, 'visitors'),
                'sessions' => $sum($dateRows, 'sessions'),
                'conversions' => $sum($dateRows, 'conversions'),
            ];
        }

        $bySite = $rows->groupBy('website_id')->map(function (Collection $siteRows, $id) use ($websites, $sum) {
            $website = $websites->firstWhere('id', (int) $id);
            $siteSessions = $sum($siteRows, 'sessions');
            $siteConversions = $sum($siteRows, 'conversions');

            return [
                'website_id' => (int) $id,
                'website_name' => $website?->name ?: 'Website',
                'domain' => $website?->domain ?: null,
                'page_views' => $sum($siteRows, 'page_views'),
                'visitors' => $sum($siteRows, 'visitors'),
                'sessions' => $siteSessions,
                'conversions' => $siteConversions,
                'conversion_rate' => $siteSessions ? round(($siteConversions / $siteSessions) * 100, 1) : 0,
            ];
        })->sortByDesc('page_views')->values()->all();

        return [
            'available' => true,
            'has_data' => $rows->isNotEmpty(),
            'period_key' => $periodKey,
            'period_days' => $periodDays,
            'starts_at' => $start->toDateString(),
            'ends_at' => $end->toDateString(),
            'selected_website_id' => $selectedWebsiteId,
            'summary' => [
                'page_views' => $pageViews,
                'visitors' => $visitors,
                'sessions' => $sessions,
                'conversions' => $conversions,
                'conversion_rate' => $sessions ? round(($conversions / $sessions) * 100, 1) : 0,
                'engagement_rate' => $sessions ? round(($engaged / $sessions) * 100, 1) : 0,
                'average_session_seconds' => $sessions ? (int) round($duration / $sessions) : 0,
            ],
            'series' => $series,
            'top_websites' => $bySite,
        ];
    }

    private function resolvePeriod(int $days, ?string $startsAt, ?string $endsAt): array
    {
        if ($startsAt && $endsAt) {
            try {
                $start = CarbonImmutable::parse($startsAt)->startOfDay();
                $end = CarbonImmutable::parse($endsAt)->endOfDay();

                if ($start->lte($end)) {
                    // Protect the dashboard from accidentally requesting unbounded datasets.
                    if ($start->diffInDays($end) > 365) {
                        $start = $end->subDays(365)->startOfDay();
                    }

                    return [$start, $end, 'custom'];
                }
            } catch (\Throwable) {
                // Fall through to a supported preset.
            }
        }

        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($days - 1)->startOfDay();

        return [$start, $end, $days . 'd'];
    }
}
