<?php

namespace App\Jobs;

use App\Models\TrialGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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

        if (! (bool) config('cosmic-mail.trial_mail_enabled', true)) {
            Log::info('[TrialWelcomeMail] Delivery suppressed by configuration.', [
                'trial_id' => $trial->id,
                'email' => $trial->email,
                'regenerated' => $this->regenerated,
            ]);
            return;
        }

        // The initial welcome is one-time per captured email address.
        if (
            ! $this->regenerated
            && $trial->welcome_email_sent_at
            && strtolower((string) $trial->welcome_email_address) === strtolower((string) $trial->email)
        ) {
            Log::info('[TrialWelcomeMail] Duplicate initial welcome skipped.', [
                'trial_id' => $trial->id,
                'email' => $trial->email,
            ]);
            return;
        }

        $baseUrl = rtrim((string) config('cosmic-mail.public_url', config('app.url')), '/');
        $url = $baseUrl.'/pages/'.$trial->page_id.'/builder?token='.urlencode((string) $trial->token);
        $pricingUrl = $baseUrl.'/pricing?token='.urlencode((string) $trial->token);

        $expiresAt = filled($trial->email)
            ? $trial->created_at?->copy()->addDays(30)
            : $trial->created_at?->copy()->addHours(24);
        $expiryLabel = $expiresAt
            ? $expiresAt->timezone(config('app.timezone'))->format('M j, Y g:i A')
            : null;

        $subject = $this->regenerated
            ? 'Your updated Cosmic CMS website is ready'
            : 'Your Cosmic CMS website is ready 🚀';

        $recipient = trim((string) config('cosmic-mail.dev_recipient'));
        $deliveryEmail = $recipient !== '' ? $recipient : (string) $trial->email;

        $trial->forceFill([
            'welcome_email_attempts' => (int) $trial->welcome_email_attempts + 1,
            'welcome_email_last_attempt_at' => now(),
            'welcome_email_last_error' => null,
        ])->save();

        Log::info('[TrialWelcomeMail] Sending.', [
            'trial_id' => $trial->id,
            'captured_email' => $trial->email,
            'delivery_email' => $deliveryEmail,
            'regenerated' => $this->regenerated,
            'attempt' => $trial->welcome_email_attempts,
        ]);

        try {
            Mail::send('emails.trial-access', [
                'trial' => $trial,
                'url' => $url,
                'pricingUrl' => $pricingUrl,
                'regenerated' => $this->regenerated,
                'expiryLabel' => $expiryLabel,
                'expiresAt' => $expiresAt,
            ], function ($message) use ($deliveryEmail, $trial, $subject): void {
                $message
                    ->to($deliveryEmail)
                    ->from(
                        (string) config('mail.from.address', 'hello@cosmiccms.com'),
                        (string) config('mail.from.name', 'Cosmic CMS')
                    )
                    ->subject($subject);

                if ($deliveryEmail !== (string) $trial->email) {
                    $message->replyTo((string) config('mail.from.address', 'hello@cosmiccms.com'));
                }
            });

            if (! $this->regenerated) {
                $trial->forceFill([
                    'welcome_email_sent_at' => now(),
                    'welcome_email_address' => strtolower((string) $trial->email),
                    'welcome_email_last_error' => null,
                ])->save();
            }

            Log::info('[TrialWelcomeMail] Sent successfully.', [
                'trial_id' => $trial->id,
                'captured_email' => $trial->email,
                'delivery_email' => $deliveryEmail,
                'regenerated' => $this->regenerated,
            ]);
        } catch (Throwable $exception) {
            $trial->forceFill([
                'welcome_email_last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            Log::error('[TrialWelcomeMail] Send failed.', [
                'trial_id' => $trial->id,
                'captured_email' => $trial->email,
                'delivery_email' => $deliveryEmail,
                'regenerated' => $this->regenerated,
                'attempt' => $trial->welcome_email_attempts,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($trial = TrialGeneration::query()->find($this->trialId)) {
            $trial->forceFill([
                'welcome_email_last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            Log::critical('[TrialWelcomeMail] Exhausted all retries.', [
                'trial_id' => $trial->id,
                'email' => $trial->email,
                'regenerated' => $this->regenerated,
                'attempts' => $trial->welcome_email_attempts,
                'error' => $exception->getMessage(),
            ]);
        }

        report($exception);
    }
}
