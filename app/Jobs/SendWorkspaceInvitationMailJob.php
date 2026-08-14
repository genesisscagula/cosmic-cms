<?php

namespace App\Jobs;

use App\Mail\CosmicEventMail;
use App\Models\NotificationDeliveryLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendWorkspaceInvitationMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $email,
        public string $subjectLine,
        public string $heading,
        public string $messageText,
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public ?int $userId = null,
    ) {
        $this->onQueue((string) config('cosmic-queue.queues.mail', 'mail'));
        $this->afterCommit();
    }

    public function handle(): void
    {
        $email = strtolower(trim($this->email));
        if ($email === '') return;

        $log = NotificationDeliveryLog::create([
            'user_id' => $this->userId,
            'event' => 'team_invitation',
            'recipient' => $email,
            'subject' => $this->subjectLine,
            'status' => 'sending',
        ]);

        try {
            Mail::to($email)->send(new CosmicEventMail(
                $this->subjectLine,
                $this->heading,
                $this->messageText,
                $this->actionLabel,
                $this->actionUrl,
            ));
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'failure_message' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }
}
