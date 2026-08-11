<?php

namespace App\Observers;

use App\Models\CommerceOrder;
use App\Models\CommerceOrderEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CommerceOrderObserver
{
    private const AUDITED = [
        'status', 'payment_status', 'refund_status', 'refunded_minor',
        'external_checkout_id', 'external_payment_id', 'paid_at',
        'payment_recovery_attempts', 'payment_recovery_last_error',
        'payment_recovered_at', 'payment_attention_required_at',
        'tracking_carrier', 'tracking_number', 'fulfilled_at', 'cancelled_at',
        'admin_note', 'order_confirmation_sent_at', 'merchant_notification_sent_at',
        'last_customer_notification_at', 'notification_attempts',
        'notification_last_error', 'notification_attention_required_at', 'metadata',
    ];

    public function created(CommerceOrder $order): void
    {
        $this->write($order, 'order_created', 'Order created', 'Checkout created a new order record.', [
            'status' => ['before' => null, 'after' => $order->status],
            'payment_status' => ['before' => null, 'after' => $order->payment_status],
            'total_minor' => ['before' => null, 'after' => (int) $order->total_minor],
        ]);
    }

    public function updated(CommerceOrder $order): void
    {
        $changes = [];
        foreach (self::AUDITED as $field) {
            if (! $order->wasChanged($field)) continue;
            $before = $order->getOriginal($field);
            $after = $order->getAttribute($field);
            if ($field === 'metadata') {
                $metadataDiff = $this->metadataDiff($before, $after);
                if ($metadataDiff === []) continue;
                $changes[$field] = $metadataDiff;
                continue;
            }
            $changes[$field] = ['before' => $before, 'after' => $after];
        }

        if ($changes === []) return;

        [$type, $title, $description] = $this->describe($order, $changes);
        $this->write($order, $type, $title, $description, $changes);
    }

    private function describe(CommerceOrder $order, array $changes): array
    {
        if (isset($changes['payment_status'])) {
            $before = (string) data_get($changes, 'payment_status.before', '');
            $after = (string) data_get($changes, 'payment_status.after', '');
            if ($after === 'paid') return ['payment_completed', 'Payment completed', 'PayPal payment was captured and verified.'];
            if ($after === 'partially_refunded') return ['refund_recorded', 'Partial refund recorded', 'The captured payment is now partially refunded.'];
            if ($after === 'refunded') return ['refund_completed', 'Payment fully refunded', 'The captured payment has been fully refunded.'];
            if ($after === 'cancelled') return ['payment_cancelled', 'Payment checkout cancelled', 'The pending payment checkout was cancelled or expired.'];
            if ($before === 'cancelled' && $after === 'pending') return ['payment_reopened', 'Payment checkout reopened', 'A resumable checkout was reopened for another payment attempt.'];
        }

        if (isset($changes['external_checkout_id'])) return ['checkout_linked', 'PayPal checkout linked', 'The order was linked to a PayPal checkout session.'];
        if (isset($changes['external_payment_id'])) return ['payment_reference_linked', 'PayPal capture linked', 'The verified PayPal capture reference was attached to the order.'];
        if (isset($changes['payment_recovered_at'])) return ['payment_recovered', 'Payment recovered', 'An interrupted checkout was reconciled successfully.'];
        if (isset($changes['payment_attention_required_at'])) return ['payment_attention', 'Payment needs attention', 'Automatic payment recovery reached the merchant attention threshold.'];
        if (isset($changes['payment_recovery_attempts'])) return ['payment_recovery_attempt', 'Payment recovery checked', 'Cosmic checked PayPal for an interrupted or pending checkout.'];

        $metadata = $changes['metadata'] ?? [];
        if (isset($metadata['inventory_reserved_at'])) return ['inventory_reserved', 'Inventory reserved', 'Tracked stock was reserved for the active checkout.'];
        if (isset($metadata['inventory_reservations_released_at'])) return ['inventory_reservation_released', 'Inventory reservation released', 'Reserved stock was returned to checkout availability.'];
        if (isset($metadata['inventory_restocked_at'])) return ['inventory_restocked', 'Inventory restocked', 'Inventory was restored for this order.'];
        if (isset($metadata['inventory_attention_required'])) return ['inventory_attention', 'Inventory needs attention', 'The paid order could not be fully deducted from tracked inventory.'];

        if (isset($changes['tracking_number']) || isset($changes['tracking_carrier'])) return ['tracking_updated', 'Tracking updated', 'Shipment tracking details were changed.'];
        if (isset($changes['status'])) {
            $after = Str::headline((string) data_get($changes, 'status.after', $order->status));
            return ['fulfillment_status_changed', 'Fulfillment changed to '.$after, 'The merchant fulfillment state was updated.'];
        }
        if (isset($changes['fulfilled_at'])) return ['fulfillment_completed', 'Order fulfilled', 'The order fulfillment timestamp was updated.'];
        if (isset($changes['admin_note'])) return ['merchant_note_updated', 'Internal note updated', 'A merchant-only order note was changed.'];

        if (isset($changes['order_confirmation_sent_at'])) return ['customer_confirmation_sent', 'Customer confirmation sent', 'The paid-order confirmation email was delivered.'];
        if (isset($changes['merchant_notification_sent_at'])) return ['merchant_notification_sent', 'Merchant notification sent', 'The merchant order notification was delivered.'];
        if (isset($changes['last_customer_notification_at'])) return ['customer_notification_sent', 'Customer update sent', 'A customer order update email was delivered.'];
        if (isset($changes['notification_attention_required_at']) || isset($changes['notification_last_error'])) return ['notification_attention', 'Notification delivery needs attention', 'Order email delivery encountered repeated failures.'];
        if (isset($changes['notification_attempts'])) return ['notification_retry', 'Notification delivery retried', 'Cosmic retried a failed order notification.'];

        return ['order_updated', 'Order updated', 'Order state was updated.'];
    }

    private function metadataDiff(mixed $before, mixed $after): array
    {
        $before = is_array($before) ? $before : (json_decode((string) $before, true) ?: []);
        $after = is_array($after) ? $after : (json_decode((string) $after, true) ?: []);
        $keys = [
            'payment_completion_source', 'payment_completed_at', 'payment_expiry_reason',
            'payment_expired_at', 'inventory_attention_required', 'inventory_restocked_at',
            'inventory_restocked_quantity', 'refund_last_synced_at',
            'inventory_reserved_at', 'inventory_reservation_expires_at',
            'inventory_reservations_released_at',
        ];
        $diff = [];
        foreach ($keys as $key) {
            $old = Arr::get($before, $key);
            $new = Arr::get($after, $key);
            if ($old !== $new) $diff[$key] = ['before' => $old, 'after' => $new];
        }
        return $diff;
    }

    private function write(CommerceOrder $order, string $type, string $title, ?string $description, array $changes): void
    {
        $userId = Auth::id();
        $routeName = null;
        if (! app()->runningInConsole() && app()->bound('request')) {
            $routeName = request()->route()?->getName();
        }
        $source = app()->runningInConsole()
            ? 'scheduler'
            : (str_contains((string) $routeName, 'webhook') ? 'webhook' : ($userId ? 'merchant' : 'storefront'));

        CommerceOrderEvent::create([
            'website_id' => $order->website_id,
            'commerce_order_id' => $order->id,
            'actor_user_id' => $userId,
            'event_type' => $type,
            'actor_type' => $userId ? 'user' : 'system',
            'source' => $source,
            'title' => $title,
            'description' => $description,
            'changes' => $changes,
            'metadata' => [
                'route' => $routeName,
                'order_number' => $order->order_number,
            ],
        ]);
    }
}
