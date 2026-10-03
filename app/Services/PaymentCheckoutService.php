<?php

namespace App\Services;

use App\Cosmic\Pricing\CreditPackageRegistry;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentCheckoutService
{
    /**
     * Set only after a verified PayPal Sandbox request fails with cURL error 60
     * in a local/testing environment. This flag never activates in live mode.
     */
    private bool $paypalLocalTlsFallbackActive = false;

    public function __construct(
        private readonly SubscriptionManagementService $subscriptions,
        private readonly PlanRegistry $plans,
        private readonly PayPalPlanBindingService $paypalPlanBindings,
    )
    {
    }
    public function create(User $user, string $provider, string $productType, string $productKey, array $context = []): array
    {
        $product = match ($productType) {
            'credits' => CreditPackageRegistry::get($productKey),
            'plan' => $this->plans->find($productKey),
            default => null,
        };

        if (! is_array($product)) {
            throw new RuntimeException('The selected product is unavailable.');
        }

        if (! in_array($provider, ['paypal', 'paymongo'], true)) {
            throw new RuntimeException('The selected payment provider is unavailable.');
        }

        if (! (bool) config("payments.{$provider}.enabled")) {
            throw new RuntimeException(ucfirst($provider).' is not enabled.');
        }

        if ($provider === 'paymongo' && $productType !== 'credits') {
            throw new RuntimeException('Monthly plans currently use PayPal. PayMongo is available for Philippine credit top-ups.');
        }

        if ($provider === 'paypal' && $productType === 'plan') {
            $isPaidOnboarding = in_array((string) $user->onboarding_status, ['pending_payment', 'provisioning'], true);

            if ($isPaidOnboarding) {
                // Reuse only the PayPal checkout created for this exact onboarding.
                // This makes ?checkout=auto, double-clicks, refreshes and browser Back
                // idempotent without ever carrying a checkout across registrations.
                $resumable = $this->subscriptions->resumableCheckout($user, $productKey);
                $onboardingId = (int) ($context['onboarding_id'] ?? 0);
                $requestedCreditOption = (string) ($context['marketplace_credit_option_key'] ?? '');
                $resumableCreditOption = (string) data_get($resumable?->metadata, 'marketplace_credit_option_key', '');

                if (
                    $resumable
                    && $onboardingId > 0
                    && (int) data_get($resumable->metadata, 'onboarding_id', 0) === $onboardingId
                    && $requestedCreditOption === $resumableCreditOption
                ) {
                    $resumed = $this->subscriptions->resumeCheckout($user, $productKey);

                    if ($resumed) {
                        return $resumed;
                    }
                }

                // A different or stale onboarding flow must never be reused.
                $user->paymentOrders()
                    ->where('provider', 'paypal')
                    ->where('product_type', 'plan')
                    ->where('status', 'pending')
                    ->update(['status' => 'expired']);
            } else {
                $resumed = $this->subscriptions->resumeCheckout($user, $productKey);

                if ($resumed) {
                    return $resumed;
                }
            }
        }

        $currency = $provider === 'paymongo' ? 'PHP' : 'USD';
        $priceKey = $provider === 'paymongo' ? 'price_php' : 'price_usd';

        if (! isset($product[$priceKey]) || ! is_numeric($product[$priceKey])) {
            throw new RuntimeException("The selected product has no {$currency} price configured.");
        }

        $amountMinor = (int) round(((float) $product[$priceKey]) * 100);

        $previousSubscription = $productType === 'plan'
            ? $this->subscriptions->assertCanStartPlanCheckout($user, $productKey)
            : null;

        $order = PaymentOrder::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $user->id,
            'provider' => $provider,
            'product_type' => $productType,
            'product_key' => $productKey,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'credits' => (int) ($product['credits'] ?? 0),
            'status' => 'pending',
            'metadata' => array_merge(array_filter([
                'country_routed' => true,
                'previous_subscription_id' => $previousSubscription?->external_subscription_id,
                'previous_plan_key' => $previousSubscription?->product_key,
                'plan_change_type' => $previousSubscription
                    ? $this->plans->changeType((string) $previousSubscription->product_key, $productKey)
                    : null,
                'plan_switch_status' => $previousSubscription ? 'awaiting_approval' : null,
                'plan_switch_started_at' => $previousSubscription ? now()->toIso8601String() : null,
            ]), array_filter($context, static fn ($value) => $value !== null && $value !== '')),
        ]);

        try {
            return match ($provider) {
                'paypal' => $this->paypal($order, $user, $product),
                'paymongo' => $this->paymongo($order, $user, $product),
            };
        } catch (\Throwable $exception) {
            $order->update([
                'status' => 'failed',
                'metadata' => array_merge($order->metadata ?? [], [
                    'checkout_error' => $exception->getMessage(),
                ]),
            ]);

            throw $exception;
        }
    }

    private function paypal(PaymentOrder $order, User $user, array $product): array
    {
        $client = $this->paypalClient();

        if ($order->product_type === 'plan') {
            return $this->paypalSubscription($client, $order, $user, $product);
        }

        return $this->paypalOrder($client, $order, $product);
    }

    private function paypalOrder(PendingRequest $client, PaymentOrder $order, array $product): array
    {
        $amount = number_format($order->amount_minor / 100, 2, '.', '');

        $response = $this->paypalPost($client, '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->reference,
                'custom_id' => $order->reference,
                'description' => $product['label'].' Cosmic Credits',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $amount,
                ],
            ]],
            'application_context' => [
                'brand_name' => 'Cosmic CMS',
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => $this->corePaymentUrl('payments/success').'?provider=paypal&order='.$order->reference,
                'cancel_url' => $this->corePaymentUrl('payments/cancel').'?provider=paypal&order='.$order->reference,
            ],
        ]);

        $approvalUrl = collect($response->json('links', []))
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if (! $response->successful() || ! $response->json('id') || ! $approvalUrl) {
            throw new RuntimeException(
                $response->json('details.0.description')
                    ?? $response->json('message')
                    ?? 'PayPal could not create checkout.'
            );
        }

        $order->update([
            'external_checkout_id' => $response->json('id'),
            'metadata' => array_merge($order->metadata ?? [], [
                'checkout_url' => $approvalUrl,
                'checkout_created_at' => now()->toIso8601String(),
            ]),
        ]);

        return [
            'checkout_url' => $approvalUrl,
            'order_reference' => $order->reference,
            'resumed' => false,
        ];
    }

    private function paypalSubscription(
        PendingRequest $client,
        PaymentOrder $order,
        User $user,
        array $product,
    ): array {
        $planId = $this->resolvePaypalPlanId($client, $order, $product);

        $name = trim((string) $user->name);
        $nameParts = preg_split('/\s+/', $name, 2) ?: [];

        $payload = [
            'plan_id' => $planId,
            'custom_id' => $order->reference,
            'subscriber' => [
                'name' => [
                    'given_name' => $nameParts[0] ?? 'Cosmic',
                    'surname' => $nameParts[1] ?? 'Customer',
                ],
                'email_address' => $user->email,
            ],
            'application_context' => [
                'brand_name' => 'Cosmic CMS',
                'locale' => 'en-US',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $this->corePaymentUrl('payments/success').'?provider=paypal&order='.$order->reference,
                'cancel_url' => $this->corePaymentUrl('payments/cancel').'?provider=paypal&order='.$order->reference,
            ],
        ];

        $response = $this->paypalPost($client, '/v1/billing/subscriptions', $payload);

        $approvalUrl = collect($response->json('links', []))
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if (! $response->successful() || ! $response->json('id') || ! $approvalUrl) {
            throw new RuntimeException(
                $response->json('details.0.description')
                    ?? $response->json('message')
                    ?? 'PayPal could not create the subscription.'
            );
        }

        $order->update([
            'external_checkout_id' => $response->json('id'),
            'external_subscription_id' => $response->json('id'),
            'metadata' => array_merge($order->metadata ?? [], [
                'checkout_url' => $approvalUrl,
                'checkout_created_at' => now()->toIso8601String(),
                'paypal_plan_id' => $planId,
            ]),
        ]);

        return [
            'checkout_url' => $approvalUrl,
            'order_reference' => $order->reference,
            'resumed' => false,
        ];
    }


    private function resolvePaypalPlanId(
        PendingRequest $client,
        PaymentOrder $order,
        array $product,
    ): string {
        $mode = (string) config('payments.paypal.mode', 'sandbox');
        $configured = $this->paypalPlanBindings->configuredPlanId($order->product_key);
        $remembered = $this->rememberedPaypalPlanId($order);
        $candidates = array_values(array_unique(array_filter([$remembered, $configured])));
        $expectedAmount = (int) round(((float) ($product['price_usd'] ?? 0)) * 100);
        $expectedSetupFee = $this->expectedSetupFeeMinor($order);
        $variantProductId = '';

        foreach ($candidates as $candidate) {
            $response = $client->get('/v1/billing/plans/'.rawurlencode($candidate));
            $active = $response->successful()
                && strtoupper((string) $response->json('status')) === 'ACTIVE';
            $actualValue = $response->json('billing_cycles.0.pricing_scheme.fixed_price.value');
            $actualCurrency = strtoupper((string) $response->json('billing_cycles.0.pricing_scheme.fixed_price.currency_code'));
            $actualAmount = is_numeric($actualValue)
                ? (int) round(((float) $actualValue) * 100)
                : -1;
            $priceMatches = $actualCurrency === 'USD' && $actualAmount === $expectedAmount;
            $actualSetupFee = $this->paypalPlanSetupFeeMinor((array) $response->json());
            $setupFeeMatches = $actualSetupFee === $expectedSetupFee;

            if ($active && $priceMatches && $setupFeeMatches) {
                $order->update([
                    'metadata' => array_merge($order->metadata ?? [], [
                        'paypal_plan_id' => $candidate,
                        'paypal_mode' => $mode,
                        'paypal_plan_source' => $candidate === $configured ? 'configured' : 'remembered',
                        'paypal_setup_fee_minor' => $expectedSetupFee,
                    ]),
                ]);

                return $candidate;
            }

            if ($active && $priceMatches) {
                $variantProductId = trim((string) $response->json('product_id'));

                // A Marketplace credit top-up intentionally needs a plan variant
                // with a one-time setup fee. Reuse the validated product and create
                // only that pricing variant instead of mutating the base plan.
                if ($expectedSetupFee > 0 && $variantProductId !== '') {
                    continue;
                }

                // Never silently use a live base plan that carries an unexpected
                // setup fee when no top-up was requested.
                if ($mode !== 'sandbox') {
                    throw new RuntimeException(
                        'The configured PayPal plan setup fee does not match this checkout. Update the live plan binding before checkout.'
                    );
                }
            }

            if ($active && ! $priceMatches && $mode !== 'sandbox') {
                throw new RuntimeException(
                    'The configured PayPal plan price does not match the current Cosmic plan price. Update the live PayPal plan binding before checkout.'
                );
            }

            // A configured ID from the other PayPal environment commonly returns
            // RESOURCE_NOT_FOUND. In local sandbox mode we can repair this safely.
            if (! $response->successful() && ! in_array($response->status(), [404, 422], true)) {
                throw new RuntimeException(
                    $response->json('details.0.description')
                        ?? $response->json('message')
                        ?? 'PayPal could not validate the subscription plan.'
                );
            }
        }

        if ($expectedSetupFee > 0 && $variantProductId !== '') {
            return $this->createPaypalPlanForProduct(
                $client,
                $order,
                $product,
                $variantProductId,
                $expectedSetupFee,
                $mode,
                'marketplace_credit_variant',
            );
        }

        $canProvisionSandbox = $mode === 'sandbox'
            && (bool) config('payments.paypal.auto_provision_sandbox_plans', true);

        if (! $canProvisionSandbox) {
            $definition = $this->plans->definition($order->product_key);
            $suffix = $configured !== ''
                ? ' The configured plan ID is not available in the current PayPal '.$mode.' environment.'
                : '';

            throw new RuntimeException(
                'The PayPal subscription plan ID for '.$definition->key().' is not configured.'.$suffix
            );
        }

        return $this->createSandboxPaypalPlan($client, $order, $product, $expectedSetupFee);
    }

    private function rememberedPaypalPlanId(PaymentOrder $order): string
    {
        $mode = (string) config('payments.paypal.mode', 'sandbox');
        $expectedSetupFee = $this->expectedSetupFeeMinor($order);

        $previousOrders = PaymentOrder::query()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->where('product_key', $order->product_key)
            ->where('id', '!=', $order->id)
            ->latest('id')
            ->limit(30)
            ->get();

        foreach ($previousOrders as $previous) {
            $metadata = $previous->metadata ?? [];
            $planId = trim((string) data_get($metadata, 'paypal_plan_id', ''));
            $recordedMode = trim((string) data_get($metadata, 'paypal_mode', ''));
            $recordedSetupFee = (int) data_get(
                $metadata,
                'paypal_setup_fee_minor',
                data_get($metadata, 'marketplace_topup_amount_minor', 0),
            );

            if ($planId !== ''
                && ($recordedMode === '' || $recordedMode === $mode)
                && $recordedSetupFee === $expectedSetupFee) {
                return $planId;
            }
        }

        return '';
    }

    private function createSandboxPaypalPlan(
        PendingRequest $client,
        PaymentOrder $order,
        array $product,
        int $setupFeeMinor = 0,
    ): string {
        $label = trim((string) ($product['label'] ?? $order->product_key));

        $productResponse = $this->paypalPost($client, '/v1/catalogs/products', [
            'name' => 'Cosmic CMS '.$label,
            'description' => 'Cosmic CMS '.$label.' subscription',
            'type' => 'SERVICE',
            'category' => 'SOFTWARE',
        ]);

        $productId = trim((string) $productResponse->json('id'));

        if (! $productResponse->successful() || $productId === '') {
            throw new RuntimeException(
                $productResponse->json('details.0.description')
                    ?? $productResponse->json('message')
                    ?? 'PayPal could not create the sandbox subscription product.'
            );
        }

        $order->update([
            'metadata' => array_merge($order->metadata ?? [], [
                'paypal_product_id' => $productId,
                'paypal_mode' => 'sandbox',
                'paypal_product_auto_provisioned' => true,
                'paypal_product_auto_provisioned_at' => now()->toIso8601String(),
            ]),
        ]);

        return $this->createPaypalPlanForProduct(
            $client,
            $order,
            $product,
            $productId,
            $setupFeeMinor,
            'sandbox',
            'auto_provisioned',
        );
    }

    private function createPaypalPlanForProduct(
        PendingRequest $client,
        PaymentOrder $order,
        array $product,
        string $productId,
        int $setupFeeMinor,
        string $mode,
        string $source,
    ): string {
        $label = trim((string) ($product['label'] ?? $order->product_key));
        $price = number_format((float) ($product['price_usd'] ?? 0), 2, '.', '');
        $setupFee = number_format(max(0, $setupFeeMinor) / 100, 2, '.', '');
        $topUpCredits = max(0, (int) data_get($order->metadata, 'marketplace_topup_credits', 0));

        if ((float) $price <= 0) {
            throw new RuntimeException('The selected plan has an invalid PayPal subscription price.');
        }

        $suffix = $setupFeeMinor > 0
            ? ' + '.number_format($topUpCredits).' Credits'
            : '';

        $planResponse = $this->paypalPost($client, '/v1/billing/plans', [
            'product_id' => $productId,
            'name' => 'Cosmic CMS '.$label.' Monthly'.$suffix,
            'description' => $setupFeeMinor > 0
                ? 'Monthly '.$label.' subscription with a one-time Cosmic Credit top-up.'
                : 'Monthly '.$label.' subscription for Cosmic CMS.',
            'status' => 'ACTIVE',
            'billing_cycles' => [[
                'frequency' => [
                    'interval_unit' => 'MONTH',
                    'interval_count' => 1,
                ],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => $price,
                        'currency_code' => 'USD',
                    ],
                ],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee' => [
                    'value' => $setupFee,
                    'currency_code' => 'USD',
                ],
                'setup_fee_failure_action' => $setupFeeMinor > 0 ? 'CANCEL' : 'CONTINUE',
                'payment_failure_threshold' => 3,
            ],
        ]);

        $planId = trim((string) $planResponse->json('id'));

        if (! $planResponse->successful() || $planId === '') {
            throw new RuntimeException(
                $planResponse->json('details.0.description')
                    ?? $planResponse->json('message')
                    ?? 'PayPal could not create the subscription plan variant.'
            );
        }

        $order->update([
            'metadata' => array_merge($order->metadata ?? [], [
                'paypal_plan_id' => $planId,
                'paypal_product_id' => $productId,
                'paypal_mode' => $mode,
                'paypal_plan_source' => $source,
                'paypal_setup_fee_minor' => $setupFeeMinor,
                'paypal_plan_auto_provisioned' => true,
                'paypal_plan_auto_provisioned_at' => now()->toIso8601String(),
            ]),
        ]);

        return $planId;
    }

    private function expectedSetupFeeMinor(PaymentOrder $order): int
    {
        return max(0, (int) data_get($order->metadata, 'marketplace_topup_amount_minor', 0));
    }

    private function paypalPlanSetupFeeMinor(array $plan): int
    {
        $currency = strtoupper((string) data_get($plan, 'payment_preferences.setup_fee.currency_code', 'USD'));
        $value = data_get($plan, 'payment_preferences.setup_fee.value', 0);

        if ($currency !== 'USD' || ! is_numeric($value)) {
            return -1;
        }

        return (int) round(((float) $value) * 100);
    }

    private function paypalPost(PendingRequest $client, string $path, array $payload)
    {
        return $client
            ->withHeaders(['PayPal-Request-Id' => (string) Str::uuid()])
            ->post($path, $payload);
    }

    private function corePaymentUrl(string $path): string
    {
        $base = rtrim((string) config('cosmic_marketplace.core_url', config('app.url')), '/');
        return $base.'/'.ltrim($path, '/');
    }

    private function paypalClient(): PendingRequest
    {
        $clientId = (string) config('payments.paypal.client_id');
        $clientSecret = (string) config('payments.paypal.client_secret');
        $baseUrl = rtrim((string) config('payments.paypal.base_url'), '/');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('PayPal is not configured.');
        }

        $tokenResponse = $this->paypalTokenRequest($clientId, $clientSecret, $baseUrl);

        $accessToken = $tokenResponse->json('access_token');

        if (! $tokenResponse->successful() || ! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException(
                $tokenResponse->json('error_description', 'PayPal authentication failed.')
            );
        }

        return Http::withOptions($this->paypalEffectiveTlsOptions())
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($accessToken);
    }

    /**
     * Authenticate with PayPal using verified TLS first.
     *
     * Some Windows/XAMPP machines have HTTPS inspection or a locally-issued
     * root certificate that PHP/cURL does not trust even when a current Mozilla
     * CA bundle is supplied. For localhost PayPal Sandbox development only, an
     * exact cURL error 60 may be retried once without certificate verification.
     * Live/production requests are never allowed to use this fallback.
     */
    private function paypalTokenRequest(string $clientId, string $clientSecret, string $baseUrl)
    {
        if ($this->paypalShouldBypassTlsVerificationLocally()) {
            $this->paypalLocalTlsFallbackActive = true;

            Log::warning('PayPal Sandbox local TLS bypass active for localhost development only.', [
                'environment' => app()->environment(),
                'app_url' => config('app.url'),
                'paypal_mode' => config('payments.paypal.mode'),
            ]);

            return Http::withOptions(['verify' => false])
                ->asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post($baseUrl.'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);
        }

        try {
            return Http::withOptions($this->paypalTlsOptions())
                ->asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post($baseUrl.'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (ConnectionException $exception) {
            if (! $this->canUseLocalSandboxTlsFallback($exception)) {
                throw $exception;
            }

            $this->paypalLocalTlsFallbackActive = true;

            Log::warning('PayPal Sandbox TLS verification failed locally; retrying with local-only verification bypass.', [
                'environment' => app()->environment(),
                'paypal_mode' => config('payments.paypal.mode'),
                'reason' => $exception->getMessage(),
            ]);

            return Http::withOptions(['verify' => false])
                ->asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post($baseUrl.'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);
        }
    }

    private function canUseLocalSandboxTlsFallback(ConnectionException $exception): bool
    {
        if (! (bool) config('payments.paypal.allow_insecure_local_fallback', true)) {
            return false;
        }

        if (strtolower((string) config('payments.paypal.mode', 'sandbox')) !== 'sandbox') {
            return false;
        }

        if (app()->environment('production')) {
            return false;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $isLocalRuntime = app()->environment(['local', 'testing'])
            || in_array($appHost, ['localhost', '127.0.0.1', '::1'], true);

        if (! $isLocalRuntime) {
            return false;
        }

        return str_contains(strtolower($exception->getMessage()), 'curl error 60');
    }

    private function paypalEffectiveTlsOptions(): array
    {
        if ($this->paypalShouldBypassTlsVerificationLocally() || $this->paypalLocalTlsFallbackActive) {
            return ['verify' => false];
        }

        return $this->paypalTlsOptions();
    }

    /**
     * XAMPP/Windows local Sandbox mode can fail before the application receives
     * any HTTP response because the machine PHP/cURL trust store is incomplete.
     * For localhost PayPal Sandbox development only, bypass certificate
     * verification from the first request instead of waiting for cURL error 60.
     * Live mode and production are always verified.
     */
    private function paypalShouldBypassTlsVerificationLocally(): bool
    {
        if (! (bool) config('payments.paypal.allow_insecure_local_fallback', true)) {
            return false;
        }

        if (strtolower((string) config('payments.paypal.mode', 'sandbox')) !== 'sandbox') {
            return false;
        }

        if (app()->environment('production')) {
            return false;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return app()->environment(['local', 'testing'])
            || in_array($appHost, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * TLS options shared by every PayPal request.
     *
     * Prefer an explicitly configured/project CA bundle while keeping
     * certificate verification enabled. The local Sandbox fallback is handled
     * separately by paypalTokenRequest() and can never activate in live mode.
     */
    private function paypalTlsOptions(): array
    {
        $verify = filter_var(
            config('payments.paypal.ssl_verify', true),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        );

        $verify = $verify ?? true;
        $mode = strtolower((string) config('payments.paypal.mode', 'sandbox'));

        if (! $verify) {
            if ($mode === 'live' || app()->environment('production')) {
                throw new RuntimeException('PayPal SSL verification cannot be disabled in live/production mode.');
            }

            return ['verify' => false];
        }

        $candidates = array_filter([
            config('payments.paypal.ca_bundle'),
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            base_path('resources/certs/cacert.pem'),
        ], static fn ($path) => is_string($path) && trim($path) !== '');

        foreach (array_unique($candidates) as $candidate) {
            $candidate = trim((string) $candidate, " \t\n\r\0\x0B\"");

            if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) {
                return ['verify' => $candidate];
            }
        }

        return ['verify' => true];
    }

    private function paymongo(PaymentOrder $order, User $user, array $product): array
    {
        $secret = (string) config('payments.paymongo.secret');

        if ($secret === '') {
            throw new RuntimeException('PayMongo is not configured.');
        }

        $response = Http::withBasicAuth($secret, '')
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name' => $user->name,
                            'email' => $user->email,
                        ],
                        'line_items' => [[
                            'amount' => $order->amount_minor,
                            'currency' => 'PHP',
                            'name' => $product['label'].' Cosmic Credits',
                            'description' => number_format($product['credits']).' Cosmic Credits',
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => config('payments.paymongo.methods'),
                        'reference_number' => $order->reference,
                        'success_url' => route('payments.success').'?provider=paymongo&order='.$order->reference,
                        'cancel_url' => route('payments.cancel').'?provider=paypal&order='.$order->reference,
                        'description' => 'Cosmic CMS credit top-up',
                        'send_email_receipt' => true,
                        'show_line_items' => true,
                    ],
                ],
            ]);

        $url = $response->json('data.attributes.checkout_url');

        if (! $response->successful() || ! $url) {
            throw new RuntimeException(
                $response->json('errors.0.detail', 'PayMongo could not create checkout.')
            );
        }

        $order->update(['external_checkout_id' => $response->json('data.id')]);

        return [
            'checkout_url' => $url,
            'order_reference' => $order->reference,
            'resumed' => false,
        ];
    }
}
