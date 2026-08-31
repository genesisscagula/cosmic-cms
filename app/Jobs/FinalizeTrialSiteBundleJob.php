<?php

namespace App\Jobs;

use App\Models\TrialGeneration;
use App\Services\TrialStagingPublisherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class FinalizeTrialSiteBundleJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 420;
    public array $backoff = [30, 90, 180];
    public int $uniqueFor = 1800;

    public function __construct(public int $trialId)
    {
        $this->onConnection((string) config('cosmic-queue.connections.ai_builds', 'database'));
        $this->onQueue((string) config('cosmic-queue.queues.ai_builds', 'ai'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->trialId;
    }

    public function handle(TrialStagingPublisherService $staging): void
    {
        $trial = TrialGeneration::query()->find($this->trialId);
        if (! $trial || $trial->claimed_at || $trial->status !== 'ready' || $trial->bundle_status !== 'ready') return;

        $pages = collect(data_get($trial->bundle_manifest, 'pages', []));
        if ($pages->isEmpty() || ! $pages->every(fn (array $page) => ($page['build_status'] ?? null) === 'ready')) return;

        $staging->publish($trial);
        $trial->refresh();
        $trial->forceFill(['bundle_error' => null])->save();
        SendTrialBundleReadyJob::dispatch($trial->id);
    }

    public function failed(?Throwable $exception): void
    {
        $trial = TrialGeneration::query()->find($this->trialId);
        if (! $trial || $trial->claimed_at) return;
        $trial->forceFill([
            'bundle_error' => 'Staging finalization failed: '.($exception?->getMessage() ?: 'Unknown staging error.'),
        ])->save();
    }
}
