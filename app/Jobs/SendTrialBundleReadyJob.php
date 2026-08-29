<?php

namespace App\Jobs;

use App\Models\TrialGeneration;
use App\Services\TrialStagingPublisherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendTrialBundleReadyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900];

    public function __construct(public int $trialId)
    {
        $this->onQueue((string) config('cosmic-queue.queues.mail', 'mail'));
        $this->afterCommit();
    }

    public function handle(TrialStagingPublisherService $staging): void
    {
        $trial = TrialGeneration::query()->find($this->trialId);
        if (! $trial || blank($trial->email) || $trial->bundle_status !== 'ready' || ! $trial->page_id) return;
        if ($trial->bundle_ready_email_sent_at) return;
        if (! (bool) config('cosmic-mail.trial_mail_enabled', true)) return;

        $base = rtrim((string) config('cosmic-mail.public_url', config('app.url')), '/');
        $builderUrl = $base.'/pages/'.$trial->page_id.'/builder?token='.urlencode((string) $trial->token);
        $previewUrl = $staging->existingUrl($trial);
        if (! $previewUrl) {
            Log::warning('[TrialBundleReadyMail] Verified staging URL is not ready yet.', ['trial_id' => $trial->id]);
            $this->release(120);
            return;
        }

        try {
            Mail::send('emails.trial-bundle-ready', compact('trial','builderUrl','previewUrl'), function ($message) use ($trial): void {
                $message->to(strtolower(trim((string) $trial->email)))
                    ->from((string) config('mail.from.address', 'hello@cosmiccms.com'), (string) config('mail.from.name', 'Cosmic CMS'))
                    ->subject('Your complete Cosmic CMS website is ready');
            });
            $trial->forceFill(['bundle_ready_email_sent_at'=>now(), 'bundle_ready_email_last_error'=>null])->save();
        } catch (Throwable $e) {
            $trial->forceFill(['bundle_ready_email_last_error'=>mb_substr($e->getMessage(),0,4000)])->save();
            Log::error('[TrialBundleReadyMail] Send failed.', ['trial_id'=>$trial->id,'error'=>$e->getMessage()]);
            throw $e;
        }
    }
}
