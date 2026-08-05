<?php
namespace App\Jobs;
use App\Mail\CosmicEventMail;
use App\Models\NotificationDeliveryLog;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;
class SendCosmicEventMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3; public array $backoff = [60,300,900];
    public function __construct(public int $userId, public string $event, public string $subjectLine, public string $heading, public string $messageText, public ?string $actionLabel = null, public ?string $actionUrl = null) { $this->onQueue((string) config('cosmic-queue.queues.mail','mail')); $this->afterCommit(); }
    public function handle(NotificationPreferenceService $preferences): void
    {
        $user = User::find($this->userId); if (!$user?->email || !$preferences->allows($user, $this->event)) return;
        $log = NotificationDeliveryLog::create(['user_id'=>$user->id,'event'=>$this->event,'recipient'=>$user->email,'subject'=>$this->subjectLine,'status'=>'sending']);
        try { Mail::to($user->email)->send(new CosmicEventMail($this->subjectLine,$this->heading,$this->messageText,$this->actionLabel,$this->actionUrl)); $log->update(['status'=>'sent','sent_at'=>now()]); }
        catch (Throwable $e) { $log->update(['status'=>'failed','failure_message'=>mb_substr($e->getMessage(),0,2000)]); throw $e; }
    }
    public function failed(Throwable $e): void { report($e); }
}
