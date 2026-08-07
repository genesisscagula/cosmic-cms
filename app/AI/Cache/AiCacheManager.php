<?php

namespace App\AI\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AiCacheManager
{
    public function remember(
        string $stage,
        array $fingerprint,
        int $ttlSeconds,
        callable $resolver,
        bool $enabled = true,
    ): array {
        $key = $this->key($stage, $fingerprint);

        if (! $enabled) {
            return [
                'value' => $resolver(),
                'cache' => 'disabled',
                'key' => $key,
            ];
        }

        try {
            $cached = Cache::get($key);
        } catch (Throwable $exception) {
            Log::warning('[AiCache] Cache read failed; resolving stage normally.', [
                'stage' => $stage,
                'message' => $exception->getMessage(),
            ]);
            $cached = null;
        }

        if ($cached !== null) {
            Log::debug('[AiCache] Cache hit.', [
                'stage' => $stage,
                'key' => $this->shortKey($key),
            ]);

            return [
                'value' => $cached,
                'cache' => 'hit',
                'key' => $key,
            ];
        }

        $value = $resolver();
        $ttl = $this->jitteredTtl($ttlSeconds);

        try {
            Cache::put($key, $value, $ttl);
        } catch (Throwable $exception) {
            Log::warning('[AiCache] Cache write failed; returning generated stage result.', [
                'stage' => $stage,
                'message' => $exception->getMessage(),
            ]);
        }

        Log::debug('[AiCache] Cache miss stored.', [
            'stage' => $stage,
            'key' => $this->shortKey($key),
            'ttl_seconds' => $ttl,
        ]);

        return [
            'value' => $value,
            'cache' => 'miss',
            'key' => $key,
        ];
    }

    public function key(string $stage, array $fingerprint): string
    {
        $payload = json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'cosmic:ai:' . $stage . ':' . hash('sha256', $payload === false ? serialize($fingerprint) : $payload);
    }

    public function normalizePrompt(string $prompt): string
    {
        $prompt = preg_replace('/\s+/', ' ', trim($prompt)) ?? trim($prompt);

        return function_exists('mb_strtolower')
            ? mb_strtolower($prompt, 'UTF-8')
            : strtolower($prompt);
    }

    public function shortKey(string $key): string
    {
        return substr(hash('sha256', $key), 0, 12);
    }

    private function jitteredTtl(int $ttlSeconds): int
    {
        $ttlSeconds = max(60, $ttlSeconds);
        $jitterPercent = max(0, min(25, (int) config('openai.ai_cache_jitter_percent', 10)));

        if ($jitterPercent === 0) {
            return $ttlSeconds;
        }

        $jitter = (int) floor($ttlSeconds * ($jitterPercent / 100));
        if ($jitter < 1) {
            return $ttlSeconds;
        }

        return max(60, $ttlSeconds + random_int(-$jitter, $jitter));
    }
}
