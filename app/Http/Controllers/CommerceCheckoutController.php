<?php

namespace App\Http\Controllers;

use App\Models\CommerceOrder;
use App\Models\Website;
use App\Services\CommerceCartService;
use App\Services\CommerceOrderService;
use App\Services\CommercePayPalService;
use App\Services\CommerceOrderNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

class CommerceCheckoutController extends Controller
{
    public function localCreate(Request $request, CommerceOrderService $orders, CommercePayPalService $paypal, string $slug): RedirectResponse
    {
        return $this->create($request, $orders, $paypal, $slug);
    }

    public function subdomainCreate(Request $request, CommerceOrderService $orders, CommercePayPalService $paypal, string $preview): RedirectResponse
    {
        return $this->create($request, $orders, $paypal, $preview);
    }

    public function localReturn(Request $request, CommerceOrderService $orders, CommercePayPalService $paypal, CommerceCartService $cart, string $slug): RedirectResponse
    {
        return $this->complete($request, $orders, $paypal, $cart, $slug);
    }

    public function subdomainReturn(Request $request, CommerceOrderService $orders, CommercePayPalService $paypal, CommerceCartService $cart, string $preview): RedirectResponse
    {
        return $this->complete($request, $orders, $paypal, $cart, $preview);
    }

    public function localCancel(Request $request, CommerceOrderService $orders, string $slug): RedirectResponse
    {
        return $this->cancel($request, $orders, $slug);
    }

    public function subdomainCancel(Request $request, CommerceOrderService $orders, string $preview): RedirectResponse
    {
        return $this->cancel($request, $orders, $preview);
    }

    private function create(Request $request, CommerceOrderService $orders, CommercePayPalService $paypal, string $previewSlug): RedirectResponse
    {
        $website = $this->commerceWebsite($previewSlug);
        $countries = array_keys(config('cosmic-commerce.countries', []));
        $requiresShipping = app(CommerceCartService::class)->summary($website)['requires_shipping'];

        $data = $request->validate([
            'first_name' => ['required','string','max:120'],
            'last_name' => ['required','string','max:120'],
            'email' => ['required','email:rfc','max:255'],
            'country' => ['required', Rule::in($countries)],
            'region' => ['nullable','string','max:120'],
            'shipping_rate' => ['nullable','integer','min:1'],
            'address1' => [$requiresShipping ? 'required' : 'nullable','string','max:255'],
            'city' => [$requiresShipping ? 'required' : 'nullable','string','max:120'],
            'postal_code' => [$requiresShipping ? 'required' : 'nullable','string','max:40'],
            'coupon' => ['nullable','string','max:80'],
            'checkout_idempotency_key' => ['required','uuid'],
        ]);

        $order = $orders->createPending(
            $website,
            $data,
            strtoupper($data['country']),
            strtoupper(trim((string) ($data['region'] ?? ''))),
            isset($data['shipping_rate']) ? (int) $data['shipping_rate'] : null,
            $data['coupon'] ?? null,
            $data['checkout_idempotency_key'],
        );

        try {
            $base = $request->getSchemeAndHttpHost();
            $returnUrl = $base.$this->commerceUrl($previewSlug, '/checkout/paypal/return').'?order='.rawurlencode($order->public_id);
            $cancelUrl = $base.$this->commerceUrl($previewSlug, '/checkout/paypal/cancel').'?order='.rawurlencode($order->public_id);
            $checkout = $paypal->create($order->loadMissing('website'), $returnUrl, $cancelUrl);
            $orders->linkExternalCheckout($order, (string) $checkout['id']);
            $request->session()->forget($this->checkoutIntentSessionKey($website));

            return redirect()->away($checkout['approve_url']);
        } catch (RuntimeException $e) {
            $orders->releasePendingReservations($order);
            Log::warning('Commerce PayPal checkout creation failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            return redirect($this->checkoutReviewUrl($previewSlug, $data))
                ->withInput()
                ->withErrors(['payment' => $e->getMessage()]);
        }
    }

    private function complete(Request $request, CommerceOrderService $orders, CommercePayPalService $paypal, CommerceCartService $cart, string $previewSlug): RedirectResponse
    {
        $website = $this->commerceWebsite($previewSlug);
        $publicId = (string) $request->query('order', '');
        $paypalOrderId = (string) ($request->query('token') ?: $request->query('orderID') ?: '');
        $order = CommerceOrder::query()->where('website_id', $website->id)->where('public_id', $publicId)->firstOrFail();
        if ($paypalOrderId === '' && filled($order->external_checkout_id)) {
            $paypalOrderId = (string) $order->external_checkout_id;
        }

        if (in_array($order->payment_status, ['paid', 'partially_refunded', 'refunded'], true)) {
            $cart->clear($website);
            $request->session()->forget($this->checkoutIntentSessionKey($website));
            return redirect($this->commerceUrl($previewSlug, '/order/'.$order->public_id.'/success'));
        }

        try {
            $payload = $paypal->captureAndVerify($order, $paypalOrderId);
            $paidOrder = $orders->markPaid($order, $paypal->captureId($payload), 'return');
            app(CommerceOrderNotificationService::class)->paid($paidOrder);
            $cart->clear($website);
            $request->session()->forget($this->checkoutIntentSessionKey($website));

            return redirect($this->commerceUrl($previewSlug, '/order/'.$order->public_id.'/success'))
                ->with('commerce_success', 'Payment received. Thank you for your order.');
        } catch (RuntimeException $e) {
            Log::warning('Commerce PayPal capture failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            return redirect($this->commerceUrl($previewSlug, '/checkout'))
                ->withErrors(['payment' => $e->getMessage()]);
        }
    }

    private function cancel(Request $request, CommerceOrderService $orders, string $previewSlug): RedirectResponse
    {
        $website = $this->commerceWebsite($previewSlug);
        $publicId = (string) $request->query('order', '');
        if ($publicId !== '') {
            $order = CommerceOrder::query()
                ->where('website_id', $website->id)
                ->where('public_id', $publicId)
                ->first();
            if ($order) $orders->cancelPending($order);
        }

        $request->session()->forget($this->checkoutIntentSessionKey($website));

        return redirect($this->commerceUrl($previewSlug, '/checkout'))
            ->withErrors(['payment' => 'PayPal checkout was cancelled. Your cart is still available.']);
    }

    private function checkoutReviewUrl(string $previewSlug, array $data): string
    {
        $query = array_filter([
            'country' => strtoupper((string) ($data['country'] ?? '')),
            'region' => strtoupper(trim((string) ($data['region'] ?? ''))),
            'shipping_rate' => isset($data['shipping_rate']) ? (int) $data['shipping_rate'] : null,
            'coupon' => filled($data['coupon'] ?? null) ? strtoupper(trim((string) $data['coupon'])) : null,
        ], fn ($value) => $value !== null && $value !== '');

        $url = $this->commerceUrl($previewSlug, '/checkout');
        return $query ? $url.'?'.http_build_query($query) : $url;
    }

    private function checkoutIntentSessionKey(Website $website): string
    {
        return 'cosmic_commerce_checkout_intent.'.$website->id;
    }

    private function commerceWebsite(string $previewSlug): Website
    {
        $website = Website::query()->where('preview_slug', $previewSlug)->with('commerceSetting')->firstOrFail();
        abort_unless((bool) $website->commerceSetting?->enabled, 404);
        return $website;
    }

    private function commerceUrl(string $previewSlug, string $path): string
    {
        return config('cosmic_preview.mode') === 'local'
            ? '/preview/'.rawurlencode($previewSlug).'/'.ltrim($path, '/')
            : '/'.ltrim($path, '/');
    }
}
