<?php

namespace App\Jobs;

use App\Mail\BillingReceiptMail;
use App\Models\BillingTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendBillingReceiptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900];

    public function __construct(public int $billingTransactionId)
    {
        $this->onQueue((string) config('cosmic-queue.queues.mail', 'mail'));
        $this->afterCommit();
    }

    public function handle(): void
    {
        $transaction = BillingTransaction::query()->with(['user', 'paymentOrder'])->find($this->billingTransactionId);

        if (! $transaction || ! $transaction->user?->email || $transaction->status !== 'completed') {
            return;
        }

        Mail::to($transaction->user->email)->send(new BillingReceiptMail($transaction));
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
