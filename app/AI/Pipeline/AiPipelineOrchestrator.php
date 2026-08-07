<?php

namespace App\AI\Pipeline;

use App\AI\Analysis\VisualIntentAnalyzer;
use App\AI\Cache\AiCacheManager;
use App\AI\Generators\ContentGenerator;
use App\AI\Planners\SparkPlanner;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiPipelineOrchestrator
{
    public function __construct(
        private readonly VisualIntentAnalyzer $visualAnalyzer,
        private readonly SparkPlanner $sparkPlanner,
        private readonly AiCacheManager $cache,
    ) {
    }

    public function analyzeVisualIntent(string $prompt): array
    {
        $cached = $this->cache->remember(
            'visual-intent',
            [
                'prompt' => $this->cache->normalizePrompt($prompt),
                'model' => config('openai.visual_model'),
                'version' => '16.4.0',
            ],
            (int) config('openai.visual_cache_ttl', 86400),
            fn () => $this->runStage('visual_intent', fn () => $this->visualAnalyzer->analyze($prompt)),
            (bool) config('openai.visual_cache_enabled', true),
        );

        $result = is_array($cached['value']) ? $cached['value'] : [];
        $result['_cache'] = [
            'status' => $cached['cache'],
            'key' => $this->cache->shortKey($cached['key']),
        ];

        return $result;
    }

    public function selectSparks(string $prompt): array
    {
        return $this->runStage('spark_selection', fn () => $this->sparkPlanner->plan($prompt));
    }

    public function generateContent(string $prompt, array $sections): array
    {
        return $this->runStage('content_generation', fn () => (new ContentGenerator())->generate($prompt, $sections));
    }

    private function runStage(string $stage, callable $callback): mixed
    {
        $attempts = max(1, (int) config('openai.pipeline_stage_attempts', 2));
        $delayMs = max(0, (int) config('openai.pipeline_retry_delay_ms', 150));
        $startedAt = microtime(true);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $result = $callback();

                Log::info('[AiPipeline] Stage completed.', [
                    'stage' => $stage,
                    'attempt' => $attempt,
                    'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                ]);

                return $result;
            } catch (Throwable $exception) {
                $lastException = $exception;

                Log::warning('[AiPipeline] Stage attempt failed.', [
                    'stage' => $stage,
                    'attempt' => $attempt,
                    'attempt_limit' => $attempts,
                    'message' => $exception->getMessage(),
                ]);

                if ($attempt < $attempts && $delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $lastException;
    }
}
