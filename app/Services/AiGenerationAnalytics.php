<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AiGenerationAnalytics
{
    private const METRICS = [
        'planner_requests',
        'planner_cache_hits',
        'planner_cache_misses',
        'planner_ai_successes',
        'planner_fallbacks',
        'compatibility_repairs',
        'content_requests',
        'content_cache_hits',
        'content_cache_misses',
        'content_successes',
        'content_failures',
    ];

    public function increment(string $metric, int $amount = 1): void
    {
        if (! in_array($metric, self::METRICS, true) || $amount < 1) {
            return;
        }

        $key = $this->key($metric);

        if (! Cache::has($key)) {
            Cache::forever($key, 0);
        }

        Cache::increment($key, $amount);
    }

    public function snapshot(): array
    {
        $metrics = [];

        foreach (self::METRICS as $metric) {
            $metrics[$metric] = (int) Cache::get($this->key($metric), 0);
        }

        $metrics['estimated_api_calls_saved'] =
            $metrics['planner_cache_hits'] + $metrics['content_cache_hits'];

        $metrics['planner_cache_hit_rate'] = $this->rate(
            $metrics['planner_cache_hits'],
            $metrics['planner_requests']
        );

        $metrics['content_cache_hit_rate'] = $this->rate(
            $metrics['content_cache_hits'],
            $metrics['content_requests']
        );

        return $metrics;
    }

    private function rate(int $hits, int $requests): float
    {
        return $requests > 0 ? round(($hits / $requests) * 100, 2) : 0.0;
    }

    private function key(string $metric): string
    {
        return 'cosmic:ai:analytics:' . $metric;
    }
}
