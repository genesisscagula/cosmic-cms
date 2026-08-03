<?php

namespace App\Services;

use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;

class PaymentWebhookEventService
{
    public function claim(string $provider, string $eventId, string $eventType, array $event): ?PaymentWebhookEvent
    {
        return DB::transaction(function () use ($provider, $eventId, $eventType, $event) {
            $record = PaymentWebhookEvent::query()->firstOrCreate(
                ['provider' => $provider, 'event_id' => $eventId],
                [
                    'event_type' => $eventType,
                    'resource_id' => (string) data_get($event, 'resource.id', ''),
                    'status' => 'received',
                    'attempts' => 0,
                    'payload_hash' => hash('sha256', json_encode($event, JSON_UNESCAPED_SLASHES) ?: ''),
                    'payload' => $this->safePayload($event),
                    'received_at' => now(),
                ],
            );

            $record = PaymentWebhookEvent::query()->lockForUpdate()->findOrFail($record->id);

            if ($record->status === 'processed') {
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
        ]);
    }

    public function failed(PaymentWebhookEvent $event, \Throwable $exception): void
    {
        $event->update([
            'status' => 'failed',
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
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
                'billing_agreement_id' => data_get($event, 'resource.billing_agreement_id'),
                'amount' => data_get($event, 'resource.amount'),
                'billing_info' => data_get($event, 'resource.billing_info'),
                'related_ids' => data_get($event, 'resource.supplementary_data.related_ids'),
            ], static fn ($value) => $value !== null && $value !== ''),
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
