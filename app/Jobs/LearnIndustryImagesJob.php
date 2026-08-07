<?php

namespace App\Jobs;

use App\Services\SmartImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LearnIndustryImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(
        public readonly string $industry,
        public readonly array $queries,
        public readonly ?int $trialId = null,
    ) {
        $this->onQueue('images');
    }

    public function handle(SmartImageService $images): void
    {
        // This job only grows the reusable industry folder to the exact number
        // of image slots required by the selected Sparks. It intentionally does
        // not mutate an already-open trial page or wait inside the public request.
        $images->learnIndustry($this->industry, $this->queries);
    }
}
