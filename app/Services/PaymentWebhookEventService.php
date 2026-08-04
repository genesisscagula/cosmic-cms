<?php

namespace App\Services;

use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentWebhookEventService
{
    public function claim(string $provider, string $eventId, string $eventType, array $event): ?PaymentWebhookEvent
    {
        $payloadHash = hash('sha256', $this->canonicalJson($event));

        return DB::transaction(function () use ($provider, $eventId, $eventType, $event, $payloadHash) {
            $record = PaymentWebhookEvent::query()->firstOrCreate(
                ['provider' => $provider, 'event_id' => $eventId],
                [
                    'event_type' => $eventType,
                    'resource_id' => (string) data_get($event, 'resource.id', ''),
                    'status' => 'received',
                    'attempts' => 0,
                    'duplicate_count' => 0,
                    'payload_hash' => $payloadHash,
                    'payload' => $this->safePayload($event),
                    'received_at' => now(),
                    'occurred_at' => $this->parseDate($event['create_time'] ?? null),
                ],
            );

            $record = PaymentWebhookEvent::query()->lockForUpdate()->findOrFail($record->id);

            if (! hash_equals((string) $record->payload_hash, $payloadHash)) {
                $record->update([
                    'status' => 'conflict',
                    'duplicate_count' => $record->duplicate_count + 1,
                    'last_error' => 'A duplicate provider event ID was received with a different payload hash.',
                ]);

                throw new RuntimeException('Conflicting duplicate webhook payload.');
            }

            if (! $record->wasRecentlyCreated) {
                $record->increment('duplicate_count');
                $record->refresh();
            }

            if ($record->status === 'processed' || $record->status === 'ignored') {
                return null;
            }

            if (
                $record->status === 'processing'
                && $record->processing_started_at
                && $record->processing_started_at->gt(now()->subMinutes(10))
            ) {
                return null;
            }

            $record->update([
                'event_type' => $eventType,
                'resource_id' => (string) data_get($event, 'resource.id', $record->resource_id),
                'status' => 'processing',
                'attempts' => $record->attempts + 1,
                'last_error' => null,
                'processing_started_at' => now(),
                'last_attempted_at' => now(),
                'next_retry_at' => null,
            ]);

            return $record->fresh();
        });
    }

    public function processed(PaymentWebhookEvent $event): void
    {
        $event->update([
            'status' => 'processed',
            'last_error' => null,
            'processed_at' => now(),
            'next_retry_at' => null,
        ]);
    }

    public function ignored(PaymentWebhookEvent $event, string $reason = ''): void
    {
        $event->update([
            'status' => 'ignored',
            'last_error' => $reason !== '' ? mb_substr($reason, 0, 2000) : null,
            'processed_at' => now(),
            'next_retry_at' => null,
        ]);
    }

    public function failed(PaymentWebhookEvent $event, \Throwable $exception): void
    {
        $delayMinutes = min(60, 2 ** min(max($event->attempts - 1, 0), 5));

        $event->update([
            'status' => 'failed',
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            'next_retry_at' => now()->addMinutes($delayMinutes),
        ]);
    }

    private function canonicalJson(array $event): string
    {
        $normalize = function (&$value) use (&$normalize): void {
            if (! is_array($value)) return;
            if (! array_is_list($value)) ksort($value);
            foreach ($value as &$child) $normalize($child);
        };
        $normalize($event);

        return json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') return null;
        try { return Carbon::parse($value); } catch (\Throwable) { return null; }
    }

    private function safePayload(array $event): array
    {
        return array_filter([
            'id' => $event['id'] ?? null,
            'event_type' => $event['event_type'] ?? null,
            'create_time' => $event['create_time'] ?? null,
            'resource_type' => $event['resource_type'] ?? null,
            'resource' => array_filter([
                'id' => data_get($event, 'resource.id'),
                'status' => data_get($event, 'resource.status'),
                'state' => data_get($event, 'resource.state'),
                'custom_id' => data_get($event, 'resource.custom_id'),
                'invoice_id' => data_get($event, 'resource.invoice_id'),
                'billing_agreement_id' => data_get($event, 'resource.billing_agreement_id'),
                'amount' => data_get($event, 'resource.amount'),
                'billing_info' => data_get($event, 'resource.billing_info'),
                'related_ids' => data_get($event, 'resource.supplementary_data.related_ids'),
            ], static fn ($value) => $value !== null && $value !== ''),
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
