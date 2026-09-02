<?php

namespace App\Services;

use App\Models\MarketplaceCheckout;
use App\Models\MarketplaceTemplate;
use App\Models\PaymentOrder;
use App\Models\PendingOnboarding;
use App\Models\User;
use App\Support\SubscriptionStatus;
use Illuminate\Support\Str;
use RuntimeException;

class MarketplaceCheckoutService
{
    public function __construct(private readonly PlanRegistry $plans)
    {
    }

    public function publishedTemplate(string $slug): MarketplaceTemplate
    {
        $template = MarketplaceTemplate::query()
            ->published()
            ->where('slug', trim($slug))
            ->first();

        if (! $template) {
            throw new RuntimeException('The selected Marketplace website is no longer available.');
        }

        $this->assertPersonalPlan((string) $template->plan);

        return $template;
    }

    public function billingPriceCents(string $planKey): int
    {
        $plan = $this->plans->find($planKey);

        if (! is_array($plan) || ! is_numeric($plan['price_usd'] ?? null)) {
            throw new RuntimeException('The selected website plan has no active monthly price.');
        }

        return (int) round(((float) $plan['price_usd']) * 100);
    }

    public function planSummary(string $planKey): array
    {
        $plan = $this->plans->find($planKey);
        $this->assertPersonalPlan($planKey);

        if (! is_array($plan)) {
            throw new RuntimeException('The selected website plan is unavailable.');
        }

        return [
            'key' => $planKey,
            'label' => (string) ($plan['label'] ?? Str::headline($planKey)),
            'price' => (int) round((float) ($plan['price_usd'] ?? 0)),
            'price_cents' => $this->billingPriceCents($planKey),
            'currency' => 'USD',
            'credits' => (int) ($plan['credits'] ?? 0),
            'description' => (string) ($plan['description'] ?? ''),
            'capabilities' => $plan['capabilities'] ?? [],
        ];
    }

    public function selectForPendingOnboarding(User $user, PendingOnboarding $onboarding, MarketplaceTemplate $template): MarketplaceCheckout
    {
        if (! in_array((string) $onboarding->status, ['pending_payment', 'payment_cancelled'], true)) {
            throw new RuntimeException('This onboarding can no longer change its Marketplace website.');
        }

        // The Marketplace template defines the monthly plan for an unpaid setup.
        // Re-selecting a website therefore safely replaces any abandoned plan
        // choice before a new PayPal subscription is created.
        $onboarding->forceFill([
            'selected_plan' => (string) $template->plan,
            'industry' => (string) $template->industry_label,
            'status' => 'pending_payment',
            'metadata' => array_merge($onboarding->metadata ?? [], [
                'checkout_source' => 'marketplace',
                'marketplace_template_id' => $template->id,
                'marketplace_template_slug' => $template->slug,
                'marketplace_template_version' => $template->version,
                'marketplace_selection_updated_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $this->createForOnboarding($user, $onboarding->fresh(), $template);
    }

    public function createForOnboarding(User $user, PendingOnboarding $onboarding, MarketplaceTemplate $template): MarketplaceCheckout
    {
        $this->assertPlanCoversTemplate((string) $onboarding->selected_plan, (string) $template->plan);

        $checkout = MarketplaceCheckout::query()
            ->where('pending_onboarding_id', $onboarding->id)
            ->whereNotIn('status', [MarketplaceCheckout::STATUS_COMPLETED, MarketplaceCheckout::STATUS_EXPIRED])
            ->latest('id')
            ->first();

        $attributes = [
            'user_id' => $user->id,
            'marketplace_template_id' => $template->id,
            'pending_onboarding_id' => $onboarding->id,
            'selected_plan' => (string) $onboarding->selected_plan,
            'status' => MarketplaceCheckout::STATUS_ACCOUNT_CREATED,
            'amount_minor' => $this->billingPriceCents((string) $onboarding->selected_plan),
            'currency' => 'USD',
            'source' => 'marketplace',
            'expires_at' => now()->addDays(7),
            'metadata' => $this->templateMetadata($template),
        ];

        if ($checkout) {
            $checkout->forceFill(array_merge($attributes, [
                'metadata' => array_merge($checkout->metadata ?? [], $attributes['metadata']),
            ]))->save();

            return $checkout->fresh(['template']);
        }

        return MarketplaceCheckout::query()->create(array_merge($attributes, [
            'uuid' => (string) Str::uuid(),
        ]))->fresh(['template']);
    }

    public function forOnboarding(PendingOnboarding $onboarding): ?MarketplaceCheckout
    {
        return MarketplaceCheckout::query()
            ->where('pending_onboarding_id', $onboarding->id)
            ->whereNotIn('status', [MarketplaceCheckout::STATUS_COMPLETED, MarketplaceCheckout::STATUS_EXPIRED])
            ->latest('id')
            ->first();
    }

    /**
     * Persist a Marketplace selection for an existing account. This keeps the
     * exact website/template version attached even when no new onboarding record
     * is required (for example, an already-active subscriber or a plan upgrade).
     */
    public function createForUserSelection(
        User $user,
        MarketplaceTemplate $template,
        string $selectedPlan,
        string $status = MarketplaceCheckout::STATUS_SELECTED,
    ): MarketplaceCheckout {
        $this->assertPersonalPlan($selectedPlan);
        $this->assertPlanCoversTemplate($selectedPlan, (string) $template->plan);

        $checkout = MarketplaceCheckout::query()
            ->where('user_id', $user->id)
            ->where('marketplace_template_id', $template->id)
            ->whereNull('pending_onboarding_id')
            ->whereNotIn('status', [MarketplaceCheckout::STATUS_COMPLETED, MarketplaceCheckout::STATUS_EXPIRED])
            ->latest('id')
            ->first();

        $attributes = [
            'user_id' => $user->id,
            'marketplace_template_id' => $template->id,
            'selected_plan' => $selectedPlan,
            'status' => $status,
            'amount_minor' => $this->billingPriceCents($selectedPlan),
            'currency' => 'USD',
            'source' => 'marketplace',
            'expires_at' => now()->addDays(7),
            'metadata' => array_merge($checkout?->metadata ?? [], $this->templateMetadata($template), [
                'existing_account' => true,
                'selection_updated_at' => now()->toIso8601String(),
            ]),
        ];

        if ($checkout) {
            $checkout->forceFill($attributes)->save();
            return $checkout->fresh(['template']);
        }

        return MarketplaceCheckout::query()->create(array_merge($attributes, [
            'uuid' => (string) Str::uuid(),
        ]))->fresh(['template']);
    }

    public function userPlanCoversTemplate(User $user, MarketplaceTemplate $template): bool
    {
        $planKey = strtolower(trim((string) $user->plan_key));

        if ($planKey === '') {
            return false;
        }

        try {
            $this->assertPlanCoversTemplate($planKey, (string) $template->plan);
        } catch (\Throwable) {
            return false;
        }

        return SubscriptionStatus::grantsAccess(
            $user->plan_status,
            (bool) $user->plan_cancel_at_period_end,
            $user->plan_renews_at,
        ) || $user->hasManualPlanEntitlement();
    }

    public function paymentMetadata(MarketplaceCheckout $checkout): array
    {
        $checkout->loadMissing('template');

        return [
            'marketplace_checkout_id' => $checkout->id,
            'marketplace_checkout_uuid' => $checkout->uuid,
            'marketplace_template_id' => $checkout->marketplace_template_id,
            'marketplace_template_slug' => $checkout->template?->slug,
            'marketplace_template_version' => $checkout->template?->version,
            'checkout_source' => 'marketplace',
        ];
    }

    public function linkPaymentOrder(MarketplaceCheckout $checkout, PaymentOrder $order): void
    {
        // A normal billing checkout may be resumed by PaymentCheckoutService. In
        // that case the PaymentOrder predates the Marketplace selection, so merge
        // the selection metadata here as well as when a fresh order is created.
        $order->forceFill([
            'metadata' => array_merge(
                $order->metadata ?? [],
                $this->paymentMetadata($checkout),
            ),
        ])->save();

        $checkout->forceFill([
            'payment_order_id' => $order->id,
            'status' => MarketplaceCheckout::STATUS_PENDING_PAYMENT,
            'metadata' => array_merge($checkout->metadata ?? [], [
                'payment_order_reference' => $order->reference,
                'payment_started_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    public function markCancelled(?PaymentOrder $order): void
    {
        $checkoutId = (int) data_get($order?->metadata, 'marketplace_checkout_id', 0);

        if ($checkoutId <= 0) {
            return;
        }

        MarketplaceCheckout::query()->whereKey($checkoutId)->update([
            'status' => MarketplaceCheckout::STATUS_PAYMENT_CANCELLED,
            'updated_at' => now(),
        ]);
    }

    public function assertPlanCoversTemplate(string $selectedPlan, string $requiredPlan): void
    {
        $this->assertPersonalPlan($selectedPlan);
        $this->assertPersonalPlan($requiredPlan);

        if ($this->plans->rank($selectedPlan) < $this->plans->rank($requiredPlan)) {
            throw new RuntimeException('This website requires the '.Str::headline($requiredPlan).' plan or higher.');
        }
    }

    private function templateMetadata(MarketplaceTemplate $template): array
    {
        return [
            'template_slug' => $template->slug,
            'template_name' => $template->name,
            'template_version' => (int) $template->version,
            'industry_slug' => $template->industry_slug,
            'industry_label' => $template->industry_label,
            'website_care_included' => (bool) $template->website_care_included,
            'ai_personalization_enabled' => (bool) $template->ai_personalization_enabled,
        ];
    }

    private function assertPersonalPlan(string $planKey): void
    {
        $plan = $this->plans->find($planKey);

        if (! is_array($plan) || ($plan['family'] ?? null) !== 'personal') {
            throw new RuntimeException('Marketplace websites require a Personal Starter, Growth, or Pro plan.');
        }
    }
}
