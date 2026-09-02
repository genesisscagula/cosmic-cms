<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceCheckout;
use App\Models\MarketplaceTemplate;
use App\Models\PaymentOrder;
use App\Models\PendingOnboarding;
use App\Services\MarketplaceCheckoutService;
use App\Services\MarketplaceWebsiteProvisioningService;
use App\Services\PaymentCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class MarketplaceCheckoutController extends Controller
{
    public function __construct(private readonly MarketplaceCheckoutService $checkouts)
    {
    }

    public function show(Request $request, string $template): Response
    {
        abort_unless(Schema::hasTable('marketplace_templates'), 404);

        try {
            $website = $this->checkouts->publishedTemplate($template);
        } catch (Throwable) {
            abort(404);
        }

        $plan = $this->checkouts->planSummary((string) $website->plan);
        $user = $request->user();
        $pending = $user
            ? PendingOnboarding::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending_payment', 'payment_cancelled', 'payment_confirmed'])
                ->latest('id')
                ->first()
            : null;
        $intent = $pending && Schema::hasTable('marketplace_checkouts')
            ? $this->checkouts->forOnboarding($pending)
            : null;

        $coreBase = rtrim((string) config('cosmic_marketplace.core_url', config('app.url')), '/');
        $registerQuery = http_build_query([
            'plan' => $website->plan,
            'marketplace_template' => $website->slug,
            'source' => 'marketplace',
        ]);
        $loginQuery = http_build_query([
            'marketplace_template' => $website->slug,
            'source' => 'marketplace',
        ]);

        return Inertia::render('Marketplace/Checkout', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'authenticated' => (bool) $user,
            'account' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
                'plan' => $user->plan_key,
                'planStatus' => $user->plan_status,
                'onboardingStatus' => $user->onboarding_status,
            ] : null,
            'template' => $this->templatePayload($request, $website),
            'plan' => $plan,
            'checkoutIntent' => $intent ? [
                'uuid' => $intent->uuid,
                'status' => $intent->status,
            ] : null,
            'initialError' => $request->string('payment')->toString() === 'cancelled'
                ? 'Payment was cancelled. Nothing was charged, and your website selection is still saved.'
                : '',
            'registerUrl' => $coreBase.'/register?'.$registerQuery,
            'loginUrl' => $coreBase.'/login?'.$loginQuery,
            'startPath' => $this->marketplacePath($request, '/checkout/'.rawurlencode($website->slug).'/start'),
            'statusPath' => $this->marketplacePath($request, '/checkout/'.rawurlencode($website->slug).'/status'),
            'retryPath' => $this->marketplacePath($request, '/checkout/'.rawurlencode($website->slug).'/retry'),
            'marketplaceHome' => $this->marketplacePath($request, ''),
            'catalogPath' => $this->marketplacePath($request, '/templates'),
            'canonicalUrl' => rtrim((string) config('cosmic_marketplace.scheme', 'https').'://'.config('cosmic_marketplace.domain', 'marketplace.cosmiccms.com'), '/'),
        ]);
    }

    public function start(
        Request $request,
        string $template,
        PaymentCheckoutService $payments,
        MarketplaceWebsiteProvisioningService $marketplaceProvisioning,
    ): JsonResponse {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Sign in or create an account to continue.'], 401);
        }

        try {
            $website = $this->checkouts->publishedTemplate($template);
            $requiredPlan = (string) $website->plan;

            $pending = PendingOnboarding::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending_payment', 'payment_cancelled'])
                ->latest('id')
                ->first();

            if ($pending) {
                $intent = $this->checkouts->selectForPendingOnboarding($user, $pending, $website);
                $pending = $pending->fresh();
                $result = $payments->create(
                    $user,
                    'paypal',
                    'plan',
                    (string) $pending->selected_plan,
                    array_merge($this->checkouts->paymentMetadata($intent), [
                        'onboarding_id' => $pending->id,
                    ]),
                );

                $order = PaymentOrder::query()->where('reference', $result['order_reference'])->firstOrFail();
                $this->checkouts->linkPaymentOrder($intent, $order);

                return response()->json($result + ['provider' => 'paypal']);
            }

            // Existing subscribers should not be charged again when their current
            // plan already covers the selected website. Persist the exact selection
            // so Batch 6 can clone/personalize it after this handoff.
            if ($this->checkouts->userPlanCoversTemplate($user, $website)) {
                $intent = $this->checkouts->createForUserSelection(
                    $user,
                    $website,
                    (string) $user->plan_key,
                    \App\Models\MarketplaceCheckout::STATUS_SUBSCRIPTION_READY,
                );

                $provisionedWebsite = $marketplaceProvisioning->provision($intent);

                return response()->json([
                    'subscription_ready' => true,
                    'checkout_uuid' => $intent->uuid,
                    'website_id' => $provisionedWebsite->id,
                    'message' => 'Your Marketplace website is ready. Luna will help personalize it for your business.',
                    'next_url' => $marketplaceProvisioning->builderUrl($provisionedWebsite),
                ]);
            }

            // Signed-in customers on a lower, expired, or otherwise non-covering
            // plan can start the required Marketplace plan directly. The checkout
            // row carries the template through the existing PayPal fulfillment path.
            $intent = $this->checkouts->createForUserSelection($user, $website, $requiredPlan);
            $result = $payments->create(
                $user,
                'paypal',
                'plan',
                $requiredPlan,
                $this->checkouts->paymentMetadata($intent),
            );

            $order = PaymentOrder::query()->where('reference', $result['order_reference'])->firstOrFail();
            $this->checkouts->linkPaymentOrder($intent, $order);

            return response()->json($result + ['provider' => 'paypal']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage() ?: 'Unable to start Marketplace checkout.',
            ], 422);
        }
    }

    public function status(
        Request $request,
        string $template,
        MarketplaceWebsiteProvisioningService $marketplaceProvisioning,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user, 401);

        $websiteTemplate = $this->checkouts->publishedTemplate($template);
        $checkout = MarketplaceCheckout::query()
            ->where('user_id', $user->id)
            ->where('marketplace_template_id', $websiteTemplate->id)
            ->latest('id')
            ->first();

        if (! $checkout) {
            return response()->json(['status' => 'not_started']);
        }

        $websiteId = (int) data_get($checkout->metadata, 'website_id', 0);
        $website = $websiteId > 0
            ? \App\Models\Website::query()->where('user_id', $user->id)->find($websiteId)
            : null;

        return response()->json([
            'status' => $checkout->status,
            'checkout_uuid' => $checkout->uuid,
            'ready' => $checkout->status === MarketplaceCheckout::STATUS_COMPLETED && (bool) $website,
            'can_retry_provisioning' => in_array($checkout->status, [MarketplaceCheckout::STATUS_PAID, MarketplaceCheckout::STATUS_SUBSCRIPTION_READY], true),
            'next_url' => $website ? $marketplaceProvisioning->builderUrl($website) : null,
            'message' => match ($checkout->status) {
                MarketplaceCheckout::STATUS_COMPLETED => 'Your Marketplace website is ready.',
                MarketplaceCheckout::STATUS_SUBSCRIPTION_READY, MarketplaceCheckout::STATUS_PAID => 'Subscription confirmed. Website setup is ready to continue.',
                MarketplaceCheckout::STATUS_PENDING_PAYMENT => 'Waiting for PayPal subscription confirmation.',
                MarketplaceCheckout::STATUS_PAYMENT_CANCELLED => 'Payment was cancelled. Your website selection is still saved.',
                default => 'Your Marketplace checkout is in progress.',
            },
        ]);
    }

    public function retryProvisioning(
        Request $request,
        string $template,
        MarketplaceWebsiteProvisioningService $marketplaceProvisioning,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user, 401);

        $websiteTemplate = $this->checkouts->publishedTemplate($template);
        $checkout = MarketplaceCheckout::query()
            ->where('user_id', $user->id)
            ->where('marketplace_template_id', $websiteTemplate->id)
            ->latest('id')
            ->firstOrFail();

        if ($checkout->status === MarketplaceCheckout::STATUS_COMPLETED) {
            $websiteId = (int) data_get($checkout->metadata, 'website_id', 0);
            $website = \App\Models\Website::query()->where('user_id', $user->id)->find($websiteId);
            if ($website) {
                return response()->json([
                    'ready' => true,
                    'message' => 'Your Marketplace website is already ready.',
                    'next_url' => $marketplaceProvisioning->builderUrl($website),
                ]);
            }
        }

        if (! in_array($checkout->status, [MarketplaceCheckout::STATUS_PAID, MarketplaceCheckout::STATUS_SUBSCRIPTION_READY], true)) {
            return response()->json([
                'message' => 'Website setup can only be retried after the subscription is confirmed.',
            ], 409);
        }

        try {
            $website = $marketplaceProvisioning->provision($checkout);

            return response()->json([
                'ready' => true,
                'message' => 'Website setup completed. Opening Luna now.',
                'next_url' => $marketplaceProvisioning->builderUrl($website),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $checkout->refresh();
            $checkout->forceFill([
                'metadata' => array_merge($checkout->metadata ?? [], [
                    'last_provisioning_error' => $exception->getMessage(),
                    'last_provisioning_error_at' => now()->toIso8601String(),
                ]),
            ])->save();

            return response()->json([
                'message' => 'Your subscription is active, but website setup could not finish yet. You can safely retry without being charged again.',
            ], 422);
        }
    }

    private function templatePayload(Request $request, MarketplaceTemplate $template): array
    {
        $template->loadMissing('pages');
        $plan = $this->checkouts->planSummary((string) $template->plan);

        return [
            'slug' => $template->slug,
            'name' => $template->name,
            'industry' => $template->industry_label,
            'industrySlug' => $template->industry_slug,
            'style' => $template->style_slug,
            'plan' => (string) $template->plan,
            'pages' => (int) $template->page_count,
            'summary' => $template->summary,
            'description' => $template->description,
            'thumbnail' => $template->thumbnail_url,
            'features' => array_values($template->features ?? []),
            'customizable' => (bool) $template->is_customizable,
            'aiPersonalization' => (bool) $template->ai_personalization_enabled,
            'websiteCare' => (bool) $template->website_care_included,
            'price' => $plan['price'],
            'currency' => $plan['currency'],
            'detailPath' => $this->marketplacePath($request, "/templates/{$template->industry_slug}/{$template->slug}"),
            'demoPath' => $this->marketplacePath($request, "/templates/{$template->industry_slug}/{$template->slug}/demo"),
        ];
    }

    private function marketplacePath(Request $request, string $path): string
    {
        $domain = strtolower((string) config('cosmic_marketplace.domain', 'marketplace.cosmiccms.com'));
        $onMarketplaceHost = strtolower((string) $request->getHost()) === $domain;
        $path = '/'.ltrim($path, '/');
        $path = $path === '/' ? '' : $path;

        if ($onMarketplaceHost) {
            return $path === '' ? '/' : $path;
        }

        $prefix = '/'.trim((string) config('cosmic_marketplace.local_prefix', 'marketplace'), '/');
        return $prefix.$path;
    }
}
