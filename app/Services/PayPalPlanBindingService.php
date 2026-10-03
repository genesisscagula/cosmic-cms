<?php

namespace App\Services;

use App\Cosmic\Plans\PlanRegistry;
use InvalidArgumentException;
use RuntimeException;

final class PayPalPlanBindingService
{
    public function __construct(private readonly PlanRegistry $plans)
    {
    }

    public function configuredPlanId(?string $planKey): string
    {
        $definition = $this->plans->definition($planKey);

        return trim((string) $definition->billingValue('paypal_plan_id', ''));
    }

    public function planId(?string $planKey): string
    {
        $definition = $this->plans->definition($planKey);
        $planId = $this->configuredPlanId($planKey);

        if ($planId === '') {
            throw new RuntimeException('The PayPal subscription plan ID for '.$definition->key().' is not configured.');
        }

        return $planId;
    }

    public function findPlanKeyByPlanId(?string $paypalPlanId): ?string
    {
        $needle = trim((string) $paypalPlanId);

        if ($needle === '') {
            return null;
        }

        foreach (array_keys($this->plans->all()) as $planKey) {
            $configured = trim((string) $this->plans->definition($planKey)->billingValue('paypal_plan_id', ''));

            if ($configured !== '' && hash_equals($configured, $needle)) {
                return $planKey;
            }
        }

        return null;
    }

    public function bindings(): array
    {
        $bindings = [];

        foreach (array_keys($this->plans->all()) as $planKey) {
            $bindings[$planKey] = trim((string) $this->plans->definition($planKey)->billingValue('paypal_plan_id', ''));
        }

        return $bindings;
    }

    public function assertUnique(): void
    {
        $used = [];

        foreach ($this->bindings() as $planKey => $planId) {
            if ($planId === '') {
                continue;
            }

            if (isset($used[$planId])) {
                throw new InvalidArgumentException("PayPal plan ID [{$planId}] is bound to both [{$used[$planId]}] and [{$planKey}].");
            }

            $used[$planId] = $planKey;
        }
    }
}
