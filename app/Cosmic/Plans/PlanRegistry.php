<?php

namespace App\Cosmic\Plans;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class PlanRegistry
{
    private const ALIASES = [
        'agency_basic' => 'agency_starter',
        'basic_agency' => 'agency_starter',
        'business' => 'agency_starter',
        'agency' => 'agency_growth',
    ];

    public function normalizeKey(?string $planKey): string
    {
        $key = strtolower(trim((string) $planKey));

        if ($key === '') {
            return 'starter';
        }

        return self::ALIASES[$key] ?? $key;
    }

    public function all(): array
    {
        return config('cosmic-plans', []);
    }

    public function get(?string $planKey): array
    {
        return $this->definition($planKey)->toArray();
    }

    public function find(?string $planKey): ?array
    {
        return $this->findDefinition($planKey)?->toArray();
    }

    public function definition(?string $planKey): PlanDefinition
    {
        $key = $this->normalizeKey($planKey);
        $plan = config("cosmic-plans.{$key}");

        if (! is_array($plan)) {
            throw new InvalidArgumentException("Unknown Cosmic plan [{$key}].");
        }

        return new PlanDefinition($key, $plan);
    }

    public function findDefinition(?string $planKey): ?PlanDefinition
    {
        try {
            return $this->definition($planKey);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function capabilities(?string $planKey): array
    {
        return $this->definition($planKey)->capabilities();
    }

    public function rank(?string $planKey): int
    {
        return $this->definition($planKey)->rank();
    }

    public function changeType(?string $fromPlanKey, ?string $toPlanKey): string
    {
        if ($this->normalizeKey($fromPlanKey) === $this->normalizeKey($toPlanKey)) {
            return 'same';
        }

        return $this->rank($toPlanKey) > $this->rank($fromPlanKey) ? 'upgrade' : 'downgrade';
    }

    public function forClient(): array
    {
        return collect($this->all())
            ->map(fn (array $plan, string $key) => Arr::only([
                'key' => $key,
                ...$plan,
            ], [
                'key', 'label', 'family', 'tier', 'rank', 'price_usd', 'credits',
                'description', 'capabilities',
            ]))
            ->all();
    }
}
