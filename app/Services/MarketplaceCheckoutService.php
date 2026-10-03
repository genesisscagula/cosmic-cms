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

        // The template's starter/growth/pro value is a Marketplace design tier,
        // not the customer's subscription. Marketplace access itself is Agency-only.
        $this->assertTemplateTier((string) $template->plan);

        return $template;
    }

    /** @deprecated Legacy subscription recovery only; do not use for Marketplace template pricing. */
    public function billingPriceCents(string $planKey): int
    {
        $plan = $this->plans->find($planKey);

        if (! is_array($plan) || ! is_numeric($plan['price_usd'] ?? null)) {
            throw new RuntimeException('The selected website plan has no active monthly price.');
        }

        return (int) round(((float) $plan['price_usd']) * 100);
    }

    /** @deprecated Legacy subscription recovery only; Marketplace pricing uses template.credit_price. */
    public function planSummary(string $planKey): array
    {
        $plan = $this->plans->find($planKey);
        $this->assertTemplateTier($planKey);

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

        $selectedPlan = (string) $onboarding->selected_plan;
        try {
            $this->assertAgencyPlan($selectedPlan);
        } catch (\Throwable) {
            // Marketplace is an Agency feature. A Marketplace selection made while
            // an unpaid Personal onboarding is open moves that onboarding to the
            // entry Agency tier rather than using the template's design tier as a
            // PayPal plan key.
            $selectedPlan = 'agency_starter';
        }

        $onboarding->forceFill([
            'selected_plan' => $selectedPlan,
            'industry' => (string) $template->industry_label,
            'status' => 'pending_payment',
            'metadata' => array_merge($onboarding->metadata ?? [], [
                'checkout_source' => 'marketplace',
                'marketplace_template_id' => $template->id,
                'marketplace_template_slug' => $template->slug,
                'marketplace_template_version' => $template->version,
                'marketplace_template_payment_mode' => 'cosmic_credits',
                'marketplace_selection_updated_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $this->createForOnboarding($user, $onboarding->fresh(), $template);
    }

    public function createForOnboarding(User $user, PendingOnboarding $onboarding, MarketplaceTemplate $template): MarketplaceCheckout
    {
        $this->assertAgencyPlan((string) $onboarding->selected_plan);

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
            'amount_minor' => 0,
            'currency' => 'USD',
            'source' => 'marketplace',
            'expires_at' => now()->addDays(7),
            'metadata' => array_merge($this->templateMetadata($template), [
                'agency_plan_at_selection' => (string) $onboarding->selected_plan,
                'agency_subscription_required' => true,
            ]),
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

    public function latestForUserTemplate(User $user, MarketplaceTemplate $template): ?MarketplaceCheckout
    {
        return MarketplaceCheckout::query()
            ->where('user_id', $user->id)
            ->where('marketplace_template_id', $template->id)
            ->where('status', '!=', MarketplaceCheckout::STATUS_EXPIRED)
            ->latest('id')
            ->first();
    }

    /**
     * Return the current Marketplace installation intent. Completed installs are
     * deliberately reused on ordinary reload/reopen so refreshes cannot silently
     * create a new charge. A second installation requires an explicit new intent.
     */
    public function ensureAgencyIntent(User $user, MarketplaceTemplate $template, bool $forceNew = false): MarketplaceCheckout
    {
        $this->assertAgencyPlan($user->effectivePlanKey());

        if (! $forceNew) {
            $existing = $this->latestForUserTemplate($user, $template);
            if ($existing) {
                return $existing->fresh(['template', 'creditTransaction']);
            }
        }

        return MarketplaceCheckout::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'marketplace_template_id' => $template->id,
            'pending_onboarding_id' => null,
            'payment_order_id' => null,
            'credit_transaction_id' => null,
            'selected_plan' => $user->effectivePlanKey(),
            'status' => MarketplaceCheckout::STATUS_SELECTED,
            'amount_minor' => 0,
            'currency' => 'USD',
            'source' => 'marketplace',
            'expires_at' => now()->addDays(7),
            'metadata' => array_merge($this->templateMetadata($template), [
                'existing_account' => true,
                'agency_plan_at_selection' => $user->effectivePlanKey(),
                'selection_updated_at' => now()->toIso8601String(),
            ]),
        ])->fresh(['template', 'creditTransaction']);
    }

    /**
     * @deprecated Legacy subscription selection only. New Marketplace installs
     * use ensureAgencyIntent() and Cosmic Credits.
     */
    public function createForUserSelection(
        User $user,
        MarketplaceTemplate $template,
        string $selectedPlan,
        string $status = MarketplaceCheckout::STATUS_SELECTED,
    ): MarketplaceCheckout {
        $this->assertTemplateTier($selectedPlan);
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
            'amount_minor' => 0,
            'currency' => 'USD',
            'source' => 'marketplace',
            'expires_at' => now()->addDays(7),
            'metadata' => array_merge($checkout?->metadata ?? [], $this->templateMetadata($template), [
                'existing_account' => true,
                'legacy_subscription_selection' => true,
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

    /** @deprecated Marketplace is now Agency-only; kept for legacy recovery code. */
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
            'marketplace_template_payment_mode' => 'cosmic_credits',
            'checkout_source' => 'marketplace',
        ];
    }

    /**
     * Link the Agency subscription payment to a saved Marketplace selection.
     * The template itself is still deducted from the Cosmic Credit wallet later;
     * this order may also carry a one-time credit top-up as a PayPal setup fee.
     */
    public function linkPaymentOrder(MarketplaceCheckout $checkout, PaymentOrder $order): void
    {
        $order->forceFill([
            'metadata' => array_merge(
                $order->metadata ?? [],
                $this->paymentMetadata($checkout),
                ['payment_purpose' => (int) data_get($order->metadata, 'marketplace_topup_credits', 0) > 0
                    ? 'agency_subscription_with_marketplace_credits'
                    : 'agency_subscription'],
            ),
        ])->save();

        $checkout->forceFill([
            'payment_order_id' => $order->id,
            'status' => MarketplaceCheckout::STATUS_PENDING_PAYMENT,
            'metadata' => array_merge($checkout->metadata ?? [], [
                'payment_order_reference' => $order->reference,
                'agency_subscription_payment_started_at' => now()->toIso8601String(),
                'marketplace_credit_option_key' => data_get($order->metadata, 'marketplace_credit_option_key'),
                'marketplace_topup_credits' => (int) data_get($order->metadata, 'marketplace_topup_credits', 0),
                'marketplace_topup_amount_minor' => (int) data_get($order->metadata, 'marketplace_topup_amount_minor', 0),
            ]),
        ])->save();
    }

    public function markCancelled(?PaymentOrder $order): void
    {
        $checkoutId = (int) data_get($order?->metadata, 'marketplace_checkout_id', 0);

        if ($checkoutId <= 0) {
            return;
        }

        $checkout = MarketplaceCheckout::query()->find($checkoutId);
        if (! $checkout || $checkout->credit_transaction_id || $checkout->status === MarketplaceCheckout::STATUS_COMPLETED) {
            return;
        }

        $checkout->forceFill([
            'status' => MarketplaceCheckout::STATUS_PAYMENT_CANCELLED,
            'metadata' => array_merge($checkout->metadata ?? [], [
                'agency_subscription_payment_cancelled_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    public function assertPlanCoversTemplate(string $selectedPlan, string $requiredPlan): void
    {
        $this->assertTemplateTier($selectedPlan);
        $this->assertTemplateTier($requiredPlan);

        if ($this->plans->rank($selectedPlan) < $this->plans->rank($requiredPlan)) {
            throw new RuntimeException('This website requires the '.Str::headline($requiredPlan).' plan or higher.');
        }
    }

    public function assertAgencyPlan(string $planKey): void
    {
        $plan = $this->plans->find($planKey);

        if (! is_array($plan) || ($plan['family'] ?? null) !== 'agency') {
            throw new RuntimeException('Marketplace is an Agency feature. Choose an Agency Starter, Growth, or Pro plan.');
        }
    }

    private function templateMetadata(MarketplaceTemplate $template): array
    {
        return [
            'template_slug' => $template->slug,
            'template_name' => $template->name,
            'template_version' => (int) $template->version,
            'credit_price' => (int) $template->credit_price,
            'price_unit' => 'cosmic_credits',
            'industry_slug' => $template->industry_slug,
            'industry_label' => $template->industry_label,
            'website_care_included' => (bool) $template->website_care_included,
            'ai_personalization_enabled' => (bool) $template->ai_personalization_enabled,
        ];
    }

    private function assertTemplateTier(string $planKey): void
    {
        $plan = $this->plans->find($planKey);

        if (! is_array($plan) || ($plan['family'] ?? null) !== 'personal') {
            throw new RuntimeException('Marketplace template tier must be Starter, Growth, or Pro.');
        }
    }
}
