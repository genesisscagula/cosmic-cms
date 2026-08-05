<?php

namespace App\Cosmic\Capabilities;

use App\Cosmic\Plans\PlanDefinition;
use App\Cosmic\Plans\PlanResolver;
use App\Models\User;

final class CapabilityEngine
{
    public function __construct(private readonly PlanResolver $plans)
    {
    }

    public function plan(User $user): PlanDefinition
    {
        return $this->plans->forUser($user);
    }

    public function value(User $user, string $capability, mixed $default = null): mixed
    {
        return $this->plan($user)->limit($capability, $default);
    }

    public function allows(User $user, string $capability, mixed $expected = true): bool
    {
        return $this->decide($user, $capability, $expected)->allowed;
    }

    public function decide(User $user, string $capability, mixed $expected = true): CapabilityDecision
    {
        $value = $this->value($user, $capability);
        $allowed = $this->matches($value, $expected);

        if ($allowed) {
            return CapabilityDecision::allow($capability, $value, $expected);
        }

        return CapabilityDecision::deny(
            capability: $capability,
            value: $value,
            required: $expected,
            reason: "The current plan does not include [{$capability}].",
            upgradeMessage: 'Upgrade your Cosmic plan to unlock this feature.',
        );
    }

    public function withinLimit(User $user, string $capability, int $currentUsage, int $increment = 1): CapabilityDecision
    {
        $limit = $this->value($user, $capability);

        if ($this->isUnlimitedValue($limit)) {
            return CapabilityDecision::allow($capability, $limit, $currentUsage + $increment);
        }

        $numericLimit = max(0, (int) $limit);
        $requestedUsage = max(0, $currentUsage) + max(0, $increment);

        if ($requestedUsage <= $numericLimit) {
            return CapabilityDecision::allow($capability, $numericLimit, $requestedUsage);
        }

        return CapabilityDecision::deny(
            capability: $capability,
            value: $numericLimit,
            required: $requestedUsage,
            reason: "The requested usage exceeds the [{$capability}] limit of {$numericLimit}.",
            upgradeMessage: 'Upgrade your Cosmic plan to increase this limit.',
        );
    }

    public function isUnlimited(User $user, string $capability): bool
    {
        return $this->isUnlimitedValue($this->value($user, $capability));
    }

    public function forClient(User $user): array
    {
        $plan = $this->plan($user);

        return [
            'key' => $plan->key(),
            'label' => $plan->name(),
            'family' => $plan->family(),
            'tier' => $plan->tier(),
            'rank' => $plan->rank(),
            'capabilities' => $plan->capabilities(),
        ];
    }

    private function matches(mixed $value, mixed $expected): bool
    {
        if (is_callable($expected)) {
            return (bool) $expected($value);
        }

        if ($expected === true) {
            return $value === true;
        }

        if ($expected === false) {
            return $value === false;
        }

        if (is_array($expected)) {
            return in_array($value, $expected, true);
        }

        return $value === $expected;
    }

    private function isUnlimitedValue(mixed $value): bool
    {
        return $value === null || $value === 'unlimited';
    }
}
