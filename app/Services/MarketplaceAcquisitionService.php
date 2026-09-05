<?php

namespace App\Services;

use App\Exceptions\MarketplacePurchaseException;
use App\Models\CreditTransaction;
use App\Models\MarketplaceCheckout;
use App\Models\MarketplaceTemplate;
use App\Models\User;
use App\Models\Website;
use App\Support\SubscriptionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class MarketplaceAcquisitionService
{
    public function __construct(
        private readonly CreditWalletService $wallet,
        private readonly AgencyWebsiteLimitService $websiteLimits,
        private readonly PlanRegistry $plans,
        private readonly MarketplaceWebsiteProvisioningService $provisioning,
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(User $user, MarketplaceTemplate $template, ?MarketplaceCheckout $checkout = null): array
    {
        $freshUser = $user->fresh() ?: $user;
        $access = $this->agencyAccess($freshUser);
        $limit = $this->websiteLimits->summary($freshUser);
        $existingWebsite = $this->existingWebsite($freshUser, $checkout);
        $slotRequired = $existingWebsite ? 0 : 1;
        $slotAvailable = $slotRequired === 0 || (bool) ($limit['can_create'] ?? false);
        $balance = $this->wallet->balance($freshUser);
        $required = max(1, (int) data_get($checkout?->metadata, 'credit_price', $template->credit_price));
        $creditTransactionId = (int) ($checkout?->credit_transaction_id ?? 0);
        $completed = $checkout?->status === MarketplaceCheckout::STATUS_COMPLETED && (bool) $existingWebsite;
        $creditsCharged = $creditTransactionId > 0;
        $missing = $creditsCharged ? 0 : max(0, $required - $balance);
        $balanceAfterInstallation = $creditsCharged ? $balance : max(0, $balance - $required);
        $canRetryProvisioning = $creditsCharged && ! $completed;

        return [
            'access' => $access,
            'website_limit' => [
                ...$limit,
                'slot_required' => $slotRequired,
                'slot_available' => $slotAvailable,
                'existing_reserved_website' => (bool) $existingWebsite,
            ],
            'credit_balance' => $balance,
            'required_credits' => $required,
            'missing_credits' => $missing,
            'balance_after_installation' => $balanceAfterInstallation,
            'has_enough_credits' => $missing === 0,
            'credits_charged' => $creditsCharged,
            'credit_transaction_id' => $creditTransactionId ?: null,
            'checkout_status' => $checkout?->status,
            'checkout_uuid' => $checkout?->uuid,
            'completed' => $completed,
            'can_retry_provisioning' => $canRetryProvisioning,
            'website_id' => $existingWebsite?->id,
            'can_install' => (bool) ($access['allowed'] ?? false)
                && $slotAvailable
                && $missing === 0
                && ! $creditsCharged
                && ! $completed,
            'add_credits_url' => route('credits.index', [
                'source' => 'marketplace-template',
            ]),
            'upgrade_url' => route('credits.index', [
                'family' => 'agency',
                'source' => 'marketplace-website-limit',
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public function confirm(
        User $user,
        MarketplaceTemplate $template,
        string $checkoutUuid,
    ): array {
        $reservation = DB::transaction(function () use ($user, $template, $checkoutUuid) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $checkout = MarketplaceCheckout::query()
                ->with(['template', 'creditTransaction'])
                ->where('uuid', $checkoutUuid)
                ->where('user_id', $lockedUser->id)
                ->where('marketplace_template_id', $template->id)
                ->lockForUpdate()
                ->first();

            if (! $checkout) {
                throw new MarketplacePurchaseException(
                    'invalid_checkout',
                    'This Marketplace installation session is no longer valid. Refresh the page and try again.',
                    [],
                    409,
                );
            }

            $recordedWebsite = $this->existingWebsite($lockedUser, $checkout);
            if ($checkout->status === MarketplaceCheckout::STATUS_COMPLETED && $recordedWebsite) {
                return [
                    'checkout_id' => $checkout->id,
                    'website_id' => $recordedWebsite->id,
                    'already_completed' => true,
                ];
            }

            $access = $this->agencyAccess($lockedUser);
            if (! ($access['allowed'] ?? false)) {
                $reason = ($access['is_agency_plan'] ?? false)
                    ? 'agency_subscription_inactive'
                    : 'agency_plan_required';

                throw new MarketplacePurchaseException(
                    $reason,
                    (string) ($access['message'] ?? 'An active Agency plan is required to install Marketplace templates.'),
                    ['access' => $access],
                    403,
                );
            }

            // The Studio create flow also locks the owning user before checking
            // AgencyWebsiteLimitService. Holding the same lock here serializes
            // Studio + Marketplace creation against the one shared website quota.
            if (! $recordedWebsite) {
                $limit = $this->websiteLimits->summary($lockedUser);
                if (! ($limit['can_create'] ?? false)) {
                    throw new MarketplacePurchaseException(
                        'website_limit_reached',
                        'Website limit reached. Upgrade your Agency plan before installing another Marketplace website.',
                        ['website_limit' => $limit],
                        422,
                    );
                }
            }

            $required = max(1, (int) data_get($checkout->metadata, 'credit_price', $template->credit_price));
            $reference = 'marketplace-install:'.$checkout->uuid;
            $transaction = $checkout->creditTransaction;

            // Recover an idempotent debit if a previous request created the ledger
            // row but the checkout relationship was not yet refreshed in memory.
            if (! $transaction) {
                $transaction = CreditTransaction::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('reference', $reference)
                    ->first();
            }

            if (! $transaction) {
                $available = (int) $lockedUser->credits;
                if ($available < $required) {
                    throw new MarketplacePurchaseException(
                        'insufficient_credits',
                        'You do not have enough Cosmic Credits to install this template.',
                        [
                            'credit_balance' => $available,
                            'required_credits' => $required,
                            'missing_credits' => $required - $available,
                        ],
                        422,
                    );
                }

                $transaction = $this->wallet->debit(
                    $lockedUser,
                    $required,
                    'Marketplace template installation: '.$template->name,
                    'websites',
                    $recordedWebsite,
                    $reference,
                    [
                        'product_type' => 'marketplace_template',
                        'category' => 'websites',
                        'marketplace_checkout_id' => $checkout->id,
                        'marketplace_checkout_uuid' => $checkout->uuid,
                        'marketplace_template_id' => $template->id,
                        'marketplace_template_slug' => $template->slug,
                        'marketplace_template_version' => (int) $template->version,
                        'credit_price' => $required,
                        'price_unit' => 'cosmic_credits',
                    ],
                );
            }

            $metadata = array_merge($checkout->metadata ?? [], [
                'credit_transaction_id' => $transaction->id,
                'credit_reference' => $reference,
                'credit_price' => $required,
                'price_unit' => 'cosmic_credits',
                'credit_balance_after' => (int) $transaction->balance_after,
                'credits_charged_at' => data_get($checkout->metadata, 'credits_charged_at', now()->toIso8601String()),
                'agency_plan_at_install' => $lockedUser->effectivePlanKey(),
            ]);

            $checkout->forceFill([
                'credit_transaction_id' => $transaction->id,
                'status' => MarketplaceCheckout::STATUS_CREDITS_CHARGED,
                'paid_at' => $checkout->paid_at ?? now(),
                'metadata' => $metadata,
            ])->save();

            // Reserve the Website in the same database transaction as the debit.
            // If record creation fails, both the reservation and credit debit roll
            // back. Once committed, template installation can safely be retried
            // without another Marketplace charge.
            if (! $recordedWebsite) {
                $recordedWebsite = $this->provisioning->reserveWebsite($checkout->fresh(), $lockedUser);
                $checkout->refresh();
            }

            if ((int) $transaction->website_id !== (int) $recordedWebsite->id) {
                $transaction->forceFill([
                    'website_id' => $recordedWebsite->id,
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'website_id' => $recordedWebsite->id,
                    ]),
                ])->save();
            }

            $checkout->forceFill([
                'metadata' => array_merge($checkout->metadata ?? [], [
                    'website_id' => $recordedWebsite->id,
                    'website_reserved_at' => data_get($checkout->metadata, 'website_reserved_at', now()->toIso8601String()),
                ]),
            ])->save();

            return [
                'checkout_id' => $checkout->id,
                'website_id' => $recordedWebsite->id,
                'already_completed' => false,
                'credit_transaction_id' => $transaction->id,
                'credit_balance' => (int) $transaction->balance_after,
                'credits_charged' => abs((int) $transaction->amount),
            ];
        });

        $checkout = MarketplaceCheckout::query()->findOrFail($reservation['checkout_id']);
        $website = Website::query()
            ->where('user_id', $user->id)
            ->findOrFail($reservation['website_id']);

        if ($reservation['already_completed']) {
            return [
                'ready' => true,
                'already_completed' => true,
                'message' => 'This Marketplace installation is already complete. No additional credits were charged.',
                'next_url' => $this->provisioning->builderUrl($website),
                'credit_balance' => $this->wallet->balance($user),
                'checkout_uuid' => $checkout->uuid,
            ];
        }

        try {
            $website = $this->provisioning->provision($checkout->fresh(), $website);

            return [
                'ready' => true,
                'message' => 'Template installed successfully. Opening Luna and the Builder now.',
                'next_url' => $this->provisioning->builderUrl($website),
                'credit_balance' => (int) ($reservation['credit_balance'] ?? $this->wallet->balance($user)),
                'credits_charged' => (int) ($reservation['credits_charged'] ?? 0),
                'checkout_uuid' => $checkout->uuid,
            ];
        } catch (Throwable $exception) {
            report($exception);

            $checkout->refresh();
            $checkout->forceFill([
                'metadata' => array_merge($checkout->metadata ?? [], [
                    'last_provisioning_error' => Str::limit($exception->getMessage(), 1000),
                    'last_provisioning_error_at' => now()->toIso8601String(),
                    'provisioning_retry_safe' => true,
                ]),
            ])->save();

            return [
                'ready' => false,
                'provisioning_failed' => true,
                'can_retry_provisioning' => true,
                'message' => 'Your template credits were charged once, but website setup did not finish. Resume setup safely without another charge.',
                'credit_balance' => (int) ($reservation['credit_balance'] ?? $this->wallet->balance($user)),
                'credits_charged' => (int) ($reservation['credits_charged'] ?? 0),
                'checkout_uuid' => $checkout->uuid,
            ];
        }
    }

    /** @return array<string, mixed> */
    public function agencyAccess(User $user): array
    {
        $plan = $this->plans->find($user->effectivePlanKey());
        $isAgency = is_array($plan) && ($plan['family'] ?? null) === 'agency';
        $hasSubscriptionAccess = $user->isPlatformOwner()
            || $user->hasManualPlanEntitlement()
            || SubscriptionStatus::grantsAccess(
                $user->plan_status,
                (bool) $user->plan_cancel_at_period_end,
                $user->plan_renews_at,
            );
        $allowed = $isAgency && $hasSubscriptionAccess;

        return [
            'allowed' => $allowed,
            'is_agency_plan' => $isAgency,
            'subscription_active' => $hasSubscriptionAccess,
            'plan_key' => $user->effectivePlanKey(),
            'plan_label' => (string) ($plan['label'] ?? Str::headline($user->effectivePlanKey())),
            'plan_status' => SubscriptionStatus::normalize($user->plan_status),
            'message' => $allowed
                ? 'Your Agency plan can use Marketplace templates.'
                : ($isAgency
                    ? 'Your Agency subscription is not active. Restore or renew access before installing a Marketplace template.'
                    : 'Marketplace is an Agency feature. Choose an Agency Starter, Growth, or Pro plan to install templates.'),
        ];
    }

    private function existingWebsite(User $user, ?MarketplaceCheckout $checkout): ?Website
    {
        if (! $checkout) {
            return null;
        }

        $websiteId = (int) data_get($checkout->metadata, 'website_id', 0);
        if ($websiteId <= 0) {
            return null;
        }

        return Website::query()
            ->where('user_id', $user->id)
            ->find($websiteId);
    }
}
