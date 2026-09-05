<?php

namespace App\Http\Controllers;

use App\Exceptions\MarketplacePurchaseException;
use App\Models\MarketplaceCheckout;
use App\Models\MarketplaceTemplate;
use App\Models\Website;
use App\Services\MarketplaceAcquisitionService;
use App\Services\MarketplaceCheckoutService;
use App\Services\MarketplaceWebsiteProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class MarketplaceCheckoutController extends Controller
{
    public function __construct(
        private readonly MarketplaceCheckoutService $checkouts,
        private readonly MarketplaceAcquisitionService $acquisitions,
    ) {
    }

    public function show(Request $request, string $template): Response
    {
        $marketplaceProvisioning = app(MarketplaceWebsiteProvisioningService::class);
        abort_unless(Schema::hasTable('marketplace_templates'), 404);

        try {
            $website = $this->checkouts->publishedTemplate($template);
        } catch (Throwable) {
            abort(404);
        }

        $planConfig = (array) config('cosmic_marketplace.plans.'.(string) $website->plan, []);
        $plan = [
            'key' => (string) $website->plan,
            'label' => (string) ($planConfig['label'] ?? ucfirst((string) $website->plan)),
            'creditPrice' => (int) $website->credit_price,
            'priceUnit' => 'cosmic_credits',
        ];

        $user = $request->user();
        $intent = null;
        $purchaseState = null;
        $installedWebsiteUrl = null;

        if ($user && Schema::hasTable('marketplace_checkouts')) {
            $intent = $this->checkouts->latestForUserTemplate($user, $website);
            $access = $this->acquisitions->agencyAccess($user);

            if (($access['allowed'] ?? false) && ($request->boolean('new_install') || ! $intent)) {
                $intent = $this->checkouts->ensureAgencyIntent($user, $website, $request->boolean('new_install'));
            }

            $purchaseState = $this->acquisitions->summary($user, $website, $intent);

            if (($purchaseState['completed'] ?? false) && (int) ($purchaseState['website_id'] ?? 0) > 0) {
                $installedWebsite = Website::query()
                    ->where('user_id', $user->id)
                    ->find((int) $purchaseState['website_id']);
                $installedWebsiteUrl = $installedWebsite
                    ? $marketplaceProvisioning->builderUrl($installedWebsite)
                    : null;
            }
        }

        $coreBase = rtrim((string) config('cosmic_marketplace.core_url', config('app.url')), '/');
        $registerQuery = http_build_query([
            'plan' => 'agency_starter',
            'marketplace_template' => $website->slug,
            'source' => 'marketplace',
        ]);
        $loginQuery = http_build_query([
            'marketplace_template' => $website->slug,
            'source' => 'marketplace',
        ]);

        $initialMessage = $request->string('agency_subscription')->toString() === 'active'
            ? 'Your Agency subscription is active. Confirm the Cosmic Credit installation below to continue.'
            : '';

        return Inertia::render('Marketplace/Checkout', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'authenticated' => (bool) $user,
            'account' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
                'plan' => $user->effectivePlanKey(),
                'planStatus' => $user->plan_status,
                'onboardingStatus' => $user->onboarding_status,
            ] : null,
            'template' => $this->templatePayload($request, $website),
            'plan' => $plan,
            'purchaseMode' => 'cosmic_credits',
            'creditCheckoutReady' => (bool) ($purchaseState['can_install'] ?? false),
            'purchaseState' => $purchaseState,
            'checkoutIntent' => $intent ? [
                'uuid' => $intent->uuid,
                'status' => $intent->status,
            ] : null,
            'installedWebsiteUrl' => $installedWebsiteUrl,
            'initialError' => $request->string('payment')->toString() === 'cancelled'
                ? 'Agency subscription payment was cancelled. No Marketplace template credits were charged.'
                : '',
            'initialMessage' => $initialMessage,
            'registerUrl' => $coreBase.'/register?'.$registerQuery,
            'loginUrl' => $coreBase.'/login?'.$loginQuery,
            'startPath' => $this->marketplacePath($request, '/checkout/'.rawurlencode($website->slug).'/start'),
            'statusPath' => $this->marketplacePath($request, '/checkout/'.rawurlencode($website->slug).'/status'),
            'retryPath' => $this->marketplacePath($request, '/checkout/'.rawurlencode($website->slug).'/retry'),
            'newInstallPath' => $this->marketplacePath($request, '/checkout/'.rawurlencode($website->slug)).'?new_install=1',
            'marketplaceHome' => $this->marketplacePath($request, ''),
            'catalogPath' => $this->marketplacePath($request, '/templates'),
            'canonicalUrl' => rtrim((string) config('cosmic_marketplace.scheme', 'https').'://'.config('cosmic_marketplace.domain', 'marketplace.cosmiccms.com'), '/'),
        ]);
    }

    public function start(Request $request, string $template): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Sign in or create an Agency account to continue.'], 401);
        }

        $validated = $request->validate([
            'checkout_uuid' => ['required', 'uuid'],
            'confirmed' => ['accepted'],
        ]);

        try {
            $websiteTemplate = $this->checkouts->publishedTemplate($template);
            $result = $this->acquisitions->confirm(
                $user,
                $websiteTemplate,
                (string) $validated['checkout_uuid'],
            );
            $intent = $this->checkouts->latestForUserTemplate($user, $websiteTemplate);

            return response()->json([
                ...$result,
                'purchase_mode' => 'cosmic_credits',
                'purchase_state' => $this->acquisitions->summary($user, $websiteTemplate, $intent),
            ], ($result['provisioning_failed'] ?? false) ? 202 : 200);
        } catch (MarketplacePurchaseException $exception) {
            $websiteTemplate = isset($websiteTemplate) ? $websiteTemplate : null;
            $intent = $websiteTemplate
                ? $this->checkouts->latestForUserTemplate($user, $websiteTemplate)
                : null;
            $state = $websiteTemplate
                ? $this->acquisitions->summary($user, $websiteTemplate, $intent)
                : null;

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->reason,
                ...$exception->context,
                'purchase_state' => $state,
            ], $exception->httpStatus);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Marketplace installation could not start. No additional template credits were charged. Please refresh and try again.',
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
        $checkout = $this->checkouts->latestForUserTemplate($user, $websiteTemplate);

        if (! $checkout) {
            return response()->json([
                'status' => 'not_started',
                'purchase_state' => $this->acquisitions->summary($user, $websiteTemplate),
            ]);
        }

        $websiteId = (int) data_get($checkout->metadata, 'website_id', 0);
        $website = $websiteId > 0
            ? Website::query()->where('user_id', $user->id)->find($websiteId)
            : null;
        $state = $this->acquisitions->summary($user, $websiteTemplate, $checkout);
        $creditsCharged = (int) ($checkout->credit_transaction_id ?? 0) > 0;

        return response()->json([
            'status' => $checkout->status,
            'checkout_uuid' => $checkout->uuid,
            'ready' => $checkout->status === MarketplaceCheckout::STATUS_COMPLETED && (bool) $website,
            'can_retry_provisioning' => $creditsCharged && $checkout->status !== MarketplaceCheckout::STATUS_COMPLETED,
            'next_url' => $checkout->status === MarketplaceCheckout::STATUS_COMPLETED && $website
                ? $marketplaceProvisioning->builderUrl($website)
                : null,
            'purchase_state' => $state,
            'message' => match ($checkout->status) {
                MarketplaceCheckout::STATUS_COMPLETED => 'Your Marketplace website is ready.',
                MarketplaceCheckout::STATUS_CREDITS_CHARGED => 'Template credits were charged once. Website setup can safely resume without another charge.',
                MarketplaceCheckout::STATUS_SUBSCRIPTION_READY, MarketplaceCheckout::STATUS_PAID => $creditsCharged
                    ? 'Template credits are confirmed. Website setup can safely continue.'
                    : 'Your Agency subscription is active. Confirm the Cosmic Credit installation to continue.',
                MarketplaceCheckout::STATUS_PENDING_PAYMENT => 'Waiting for Agency subscription confirmation. The Marketplace template itself has not been charged.',
                MarketplaceCheckout::STATUS_PAYMENT_CANCELLED => 'Agency subscription payment was cancelled. No Marketplace template credits were charged.',
                default => 'Your Marketplace installation is ready for confirmation.',
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
        $checkout = $this->checkouts->latestForUserTemplate($user, $websiteTemplate);
        abort_unless($checkout, 404);

        if ($checkout->status === MarketplaceCheckout::STATUS_COMPLETED) {
            $websiteId = (int) data_get($checkout->metadata, 'website_id', 0);
            $website = Website::query()->where('user_id', $user->id)->find($websiteId);
            if ($website) {
                return response()->json([
                    'ready' => true,
                    'message' => 'Your Marketplace website is already ready. No additional credits were charged.',
                    'next_url' => $marketplaceProvisioning->builderUrl($website),
                ]);
            }
        }

        if (! $checkout->credit_transaction_id) {
            return response()->json([
                'message' => 'Confirm the Cosmic Credit template installation before resuming website setup.',
                'code' => 'template_credits_not_charged',
            ], 409);
        }

        $websiteId = (int) data_get($checkout->metadata, 'website_id', 0);
        $existingWebsite = $websiteId > 0
            ? Website::query()->where('user_id', $user->id)->find($websiteId)
            : null;

        try {
            $website = $marketplaceProvisioning->provision($checkout, $existingWebsite);

            return response()->json([
                'ready' => true,
                'message' => 'Website setup completed. Opening Luna now. No additional template credits were charged.',
                'next_url' => $marketplaceProvisioning->builderUrl($website),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $checkout->refresh();
            $checkout->forceFill([
                'metadata' => array_merge($checkout->metadata ?? [], [
                    'last_provisioning_error' => $exception->getMessage(),
                    'last_provisioning_error_at' => now()->toIso8601String(),
                    'provisioning_retry_safe' => true,
                ]),
            ])->save();

            return response()->json([
                'message' => 'Your credits were already charged once, but website setup still could not finish. Retry remains safe and will not charge again.',
                'can_retry_provisioning' => true,
            ], 422);
        }
    }

    private function templatePayload(Request $request, MarketplaceTemplate $template): array
    {
        $template->loadMissing('pages');
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
            'creditPrice' => (int) $template->credit_price,
            'priceUnit' => 'cosmic_credits',
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
