<?php

namespace App\Jobs;

use App\Models\TrialGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTrialAccessLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900];

    public function __construct(public int $trialId, public bool $regenerated = false)
    {
        $this->onQueue((string) config('cosmic-queue.queues.mail', 'mail'));
        $this->afterCommit();
    }

    public function handle(): void
    {
        $trial = TrialGeneration::query()->find($this->trialId);

        if (! $trial || blank($trial->email) || ! $trial->page_id) {
            return;
        }

        $url = route('pages.builder', ['page' => $trial->page_id, 'token' => $trial->token]);
        $subject = $this->regenerated ? 'Your updated Cosmic CMS landing page is ready' : 'Your Cosmic CMS landing page is ready';
        Mail::send('emails.trial-access', [
            'trial' => $trial,
            'url' => $url,
            'regenerated' => $this->regenerated,
        ], function ($message) use ($trial, $subject): void {
            $message->to($trial->email)->subject($subject);
        });
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
