<?php

namespace App\Http\Controllers;

use App\Models\CommerceOrder;
use App\Models\CommerceOrderRefund;
use App\Models\Website;
use App\Services\CommerceOrderNotificationService;
use App\Services\CommerceInventoryService;
use App\Services\CommercePayPalService;
use App\Services\CommercePaymentRecoveryService;
use App\Services\CommerceOrderService;
use App\Services\CommerceRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

class CommerceOrderController extends Controller
{
    public function update(Request $request, Website $website, CommerceOrder $order, CommerceOrderNotificationService $notifications): RedirectResponse
    {
        $this->authorize('update', $website);
        abort_unless($order->website_id === $website->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'processing', 'on_hold', 'completed', 'cancelled'])],
            'admin_note' => ['nullable', 'string', 'max:4000'],
            'tracking_number' => ['nullable', 'string', 'max:180'],
            'tracking_carrier' => ['nullable', 'string', 'max:120'],
            'order_updated_at' => ['nullable', 'date'],
        ]);

        $trackingChanged = false;
        $completedNow = false;

        try {
            $updatedOrder = DB::transaction(function () use ($website, $order, $data, &$trackingChanged, &$completedNow) {
                /** @var CommerceOrder $locked */
                $locked = CommerceOrder::query()
                    ->whereKey($order->id)
                    ->where('website_id', $website->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! empty($data['order_updated_at']) && $locked->updated_at) {
                    $expected = \Illuminate\Support\Carbon::parse($data['order_updated_at']);
                    if (! $locked->updated_at->equalTo($expected)) {
                        throw new RuntimeException('This order changed in the background. Refresh the order and review the latest payment, refund and fulfillment state before saving.');
                    }
                }

                if ($data['status'] === 'pending' && in_array($locked->payment_status, ['paid', 'partially_refunded', 'refunded'], true)) {
                    throw new RuntimeException('A paid order cannot be moved back to pending.');
                }
                if (in_array($data['status'], ['processing', 'completed'], true) && ! in_array($locked->payment_status, ['paid', 'partially_refunded'], true)) {
                    throw new RuntimeException('An unpaid order cannot be marked processing or completed.');
                }
                if ($data['status'] === 'cancelled' && in_array($locked->payment_status, ['paid', 'partially_refunded'], true)) {
                    throw new RuntimeException('Refund the remaining captured balance before cancelling this order.');
                }

                $previousStatus = $locked->status;
                $previousTracking = trim((string) $locked->tracking_number).'|'.trim((string) $locked->tracking_carrier);

                $updates = [
                    'status' => $data['status'],
                    'admin_note' => filled($data['admin_note'] ?? null) ? trim((string) $data['admin_note']) : null,
                    'tracking_number' => filled($data['tracking_number'] ?? null) ? trim((string) $data['tracking_number']) : null,
                    'tracking_carrier' => filled($data['tracking_carrier'] ?? null) ? trim((string) $data['tracking_carrier']) : null,
                ];

                if ($data['status'] === 'completed' && ! $locked->fulfilled_at) {
                    $updates['fulfilled_at'] = now();
                } elseif ($data['status'] !== 'completed' && $locked->fulfilled_at) {
                    $updates['fulfilled_at'] = null;
                }

                if ($data['status'] === 'cancelled' && ! $locked->cancelled_at) {
                    $updates['cancelled_at'] = now();
                } elseif ($data['status'] !== 'cancelled') {
                    $updates['cancelled_at'] = null;
                }

                $locked->forceFill($updates)->save();

                $newTracking = trim((string) $locked->tracking_number).'|'.trim((string) $locked->tracking_carrier);
                $trackingChanged = $newTracking !== $previousTracking && trim((string) $locked->tracking_number) !== '';
                $completedNow = $previousStatus !== $locked->status && $locked->status === 'completed';

                return $locked->fresh();
            }, 3);
        } catch (RuntimeException $e) {
            return redirect()->back(303)->withErrors(['status' => $e->getMessage()]);
        }

        // External mail is intentionally sent after commit so a slow/failing provider never holds the order row lock.
        if ($completedNow || $trackingChanged) {
            $notifications->fulfillment($updatedOrder, $trackingChanged);
        }

        return redirect()->back(303)->with('success', 'Order updated.');
    }

    public function markSeen(Request $request, Website $website, CommerceOrder $order, \App\Services\CommerceCapabilityService $commerce): RedirectResponse
    {
        $this->authorize('update', $website);
        abort_unless($order->website_id === $website->id, 404);

        $settings = $commerce->settingsFor($website);
        $storeSettings = is_array($settings->settings) ? $settings->settings : [];
        $seenIds = collect((array) data_get($storeSettings, 'seen_order_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->push((int) $order->id)
            ->unique()
            ->take(-500)
            ->values()
            ->all();

        $storeSettings['seen_order_ids'] = $seenIds;
        $settings->forceFill(['settings' => $storeSettings])->save();

        return redirect()->back(303);
    }

    public function restock(Request $request, Website $website, CommerceOrder $order, CommerceInventoryService $inventory): RedirectResponse
    {
        $this->authorize('update', $website);
        abort_unless($order->website_id === $website->id, 404);

        if (! in_array($order->payment_status, ['paid', 'partially_refunded', 'refunded'], true)) {
            return redirect()->back(303)->withErrors(['inventory' => 'Only paid or refunded orders can be restocked.']);
        }

        $data = $request->validate(['note' => 'nullable|string|max:500']);
        $restored = $inventory->restockOrder($order, $request->user()?->id, $data['note'] ?? 'Merchant restock');

        if ($restored === 0) {
            return redirect()->back(303)->withErrors(['inventory' => 'This order was already restocked or has no tracked inventory items.']);
        }

        return redirect()->back(303)->with('success', "Restocked {$restored} item(s).");
    }


    public function recoverPayment(Request $request, Website $website, CommerceOrder $order, CommercePaymentRecoveryService $recovery, CommerceOrderService $orders): RedirectResponse
    {
        $this->authorize('update', $website);
        abort_unless($order->website_id === $website->id, 404);

        if (! in_array($order->payment_status, ['pending', 'cancelled'], true)) {
            return redirect()->back(303)->withErrors(['payment' => 'This order no longer needs payment recovery.']);
        }

        if (! filled($order->external_checkout_id)) {
            return redirect()->back(303)->withErrors(['payment' => 'No PayPal checkout ID is linked to this order yet.']);
        }

        try {
            $orders->resetRecoverySchedule($order);
            $result = $recovery->recover($order->fresh());

            return match ($result) {
                'recovered', 'already_paid' => back()->with('success', 'Payment verified and the order is now paid.'),
                'expired' => back()->withErrors(['payment' => 'The PayPal checkout expired without a completed payment.']),
                'waiting' => back()->with('success', 'PayPal has not completed this payment yet. Recovery remains scheduled.'),
                default => back()->withErrors(['payment' => 'This order is not eligible for payment recovery.']),
            };
        } catch (RuntimeException $e) {
            Log::warning('Manual commerce payment recovery failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->back(303)->withErrors(['payment' => $e->getMessage()]);
        }
    }

    public function refund(Request $request, Website $website, CommerceOrder $order, CommercePayPalService $paypal, CommerceRefundService $refunds, CommerceOrderNotificationService $notifications): RedirectResponse
    {
        $this->authorize('update', $website);
        abort_unless($order->website_id === $website->id, 404);

        if (! in_array($order->payment_status, ['paid', 'partially_refunded'], true)) {
            return redirect()->back(303)->withErrors(['refund_amount' => 'Only captured PayPal payments can be refunded.']);
        }

        $decimals = (int) data_get(config('cosmic-commerce.currencies.'.strtoupper($order->currency)), 'decimals', 2);
        $scale = 10 ** max(0, $decimals);
        $remainingMinor = max(0, (int) $order->total_minor - (int) $order->refunded_minor);
        $remainingMajor = $remainingMinor / $scale;

        $data = $request->validate([
            'refund_amount' => ['required', 'numeric', 'gt:0', 'max:'.$remainingMajor],
            'refund_reason' => ['nullable', 'string', 'max:500'],
            'refund_request_key' => ['required', 'uuid'],
        ]);
        $amountMinor = (int) round(((float) $data['refund_amount']) * $scale);

        try {
            $payload = $paypal->refund($order->fresh(), $amountMinor, $data['refund_request_key']);
            $refundStatus = strtolower((string) data_get($payload, 'status', 'completed'));
            $externalRefundId = (string) data_get($payload, 'id', '');
            $updatedOrder = $refunds->record(
                $order->fresh(),
                $externalRefundId,
                $amountMinor,
                (string) data_get($payload, 'amount.currency_code', $order->currency),
                $refundStatus,
                $payload,
                $data['refund_reason'] ?? null,
                $data['refund_request_key'],
            );

            if ($refundStatus === 'completed') {
                $notifications->refund($updatedOrder, $amountMinor);
            }

            return redirect()->back(303)->with('success', $refundStatus === 'pending'
                ? 'PayPal accepted the refund and it is pending settlement.'
                : ($updatedOrder->refund_status === 'refunded' ? 'Full PayPal refund issued.' : 'Partial PayPal refund issued.'));
        } catch (RuntimeException $e) {
            Log::warning('Commerce PayPal refund failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            return redirect()->back(303)->withErrors(['refund_amount' => $e->getMessage()]);
        }
    }
}
