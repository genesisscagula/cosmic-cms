<?php

namespace App\AI\Pipeline;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Coordinates independent generation branches without pretending dependent
 * stages are parallel. Image preparation is fire-and-forget; Spark selection
 * and schema-bound content generation remain dependency ordered.
 */
class ParallelProcessingEngine
{
    /**
     * @return array{result:array, diagnostics:array}
     */
    public function execute(callable $imageBranch, callable $aiBranch): array
    {
        $startedAt = microtime(true);
        $branches = [
            'images' => $this->startIndependentBranch('images', $imageBranch),
        ];

        $aiStartedAt = microtime(true);
        try {
            $result = $aiBranch();
            $branches['ai'] = [
                'status' => 'completed',
                'elapsed_ms' => $this->elapsedMs($aiStartedAt),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            $branches['ai'] = [
                'status' => 'failed',
                'elapsed_ms' => $this->elapsedMs($aiStartedAt),
                'error' => $exception->getMessage(),
            ];

            Log::error('[ParallelAI] Required AI branch failed.', [
                'message' => $exception->getMessage(),
                'elapsed_ms' => $branches['ai']['elapsed_ms'],
            ]);

            throw $exception;
        }

        $diagnostics = [
            'mode' => 'parallel-independent-branches',
            'branches' => $branches,
            'partial_success' => $branches['images']['status'] === 'failed',
            'elapsed_ms' => $this->elapsedMs($startedAt),
        ];

        Log::info('[ParallelAI] Independent branches coordinated.', $diagnostics);

        return [
            'result' => $result,
            'diagnostics' => $diagnostics,
        ];
    }

    private function startIndependentBranch(string $name, callable $branch): array
    {
        $startedAt = microtime(true);

        try {
            $branch();

            return [
                'status' => 'dispatched',
                'elapsed_ms' => $this->elapsedMs($startedAt),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('[ParallelAI] Independent branch failed; continuing.', [
                'branch' => $name,
                'message' => $exception->getMessage(),
                'elapsed_ms' => $this->elapsedMs($startedAt),
            ]);

            return [
                'status' => 'failed',
                'elapsed_ms' => $this->elapsedMs($startedAt),
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) ((microtime(true) - $startedAt) * 1000);
    }
}
