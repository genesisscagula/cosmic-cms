<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\VisualFirstFullPageBuildService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BuildVisualFirstCustomPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 720;
    public array $backoff = [15, 45];

    public function __construct(
        public string $buildId,
        public int $websiteId,
        public string $brief,
        public ?string $screenshotPath = null,
        public ?string $screenshotMime = null,
    ) {
        $this->onQueue((string) config('cosmic-queue.queues.ai_builds', 'ai'));
    }

    public function handle(VisualFirstFullPageBuildService $builder): void
    {
        $website = Website::query()->find($this->websiteId);
        if (! $website) {
            $this->store(['status' => 'failed', 'progress' => 0, 'stage' => 'Website not found.', 'message' => 'The Custom Website no longer exists.']);
            return;
        }

        $this->store(['status' => 'running', 'progress' => 3, 'stage' => 'Starting Cosmic AI…']);

        try {
            $result = $builder->build(
                $website,
                $this->brief,
                $this->screenshotPath,
                $this->screenshotMime,
                function (int $progress, string $stage): void {
                    $this->store(['status' => 'running', 'progress' => max(1, min(99, $progress)), 'stage' => $stage]);
                }
            );

            $this->store([
                'status' => 'completed',
                'progress' => 100,
                'stage' => 'Your editable page is ready.',
                'result' => $result,
            ], 120);
        } catch (Throwable $e) {
            report($e);
            $finalAttempt = $this->attempts() >= $this->tries;
            $this->store($finalAttempt ? [
                'status' => 'failed',
                'progress' => 0,
                'stage' => 'Generation stopped.',
                'message' => $e->getMessage() ?: 'Cosmic AI could not finish this page.',
            ] : [
                'status' => 'running',
                'stage' => 'Connection interrupted. Retrying automatically…',
                'message' => null,
            ], 120);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $this->store([
            'status' => 'failed',
            'progress' => 0,
            'stage' => 'Generation stopped.',
            'message' => $e->getMessage() ?: 'Cosmic AI could not finish this page.',
        ], 120);
    }

    private function store(array $patch, int $minutes = 60): void
    {
        $key = 'cosmic:custom-build:'.$this->buildId;
        $current = Cache::get($key, []);
        $current = is_array($current) ? $current : [];
        if (isset($patch['progress']) && isset($current['progress'])) {
            $terminal = in_array((string) ($patch['status'] ?? ''), ['completed', 'failed'], true);
            if (! $terminal) {
                $patch['progress'] = max((int) $current['progress'], (int) $patch['progress']);
            }
        }

        Cache::put($key, array_merge($current, $patch, [
            'build_id' => $this->buildId,
            'website_id' => $this->websiteId,
            'updated_at' => now()->toIso8601String(),
        ]), now()->addMinutes($minutes));
    }
}
