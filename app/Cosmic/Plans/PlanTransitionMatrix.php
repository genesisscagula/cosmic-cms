<?php

namespace App\Cosmic\Plans;

use App\Models\User;
use RuntimeException;

class PlanTransitionMatrix
{
    public function __construct(private readonly PlanRegistry $plans)
    {
    }

    public function decide(?string $fromKey, ?string $toKey): PlanTransitionDecision
    {
        // A null/blank current plan means the customer has not activated a paid
        // subscription yet. PlanRegistry intentionally falls back blank keys to
        // Starter for feature resolution, but transition validation must not use
        // that fallback or first-time Starter checkout becomes Starter -> Starter.
        $hasCurrentPlan = trim((string) $fromKey) !== '';
        $from = $hasCurrentPlan
            ? $this->plans->definition($fromKey)
            : $this->plans->definition($toKey);
        $to = $this->plans->definition($toKey);
        $same = $hasCurrentPlan && $from->key() === $to->key();
        $familyChange = $hasCurrentPlan && $from->family() !== $to->family();
        $type = ! $hasCurrentPlan
            ? 'new'
            : ($same ? 'same' : ($to->price() > $from->price() ? 'upgrade' : 'downgrade'));

        $warnings = [];
        $requirements = [];

        if ($familyChange) {
            $warnings[] = $to->family() === 'agency'
                ? 'This changes the workspace from Personal to Agency.'
                : 'This changes the workspace from Agency to Personal.';
        }

        if ($type === 'downgrade') {
            $warnings[] = 'Features and limits above the target plan will be locked after the current billing period.';
            $requirements[] = 'usage_within_target_limits';
        }

        if ($hasCurrentPlan && $to->family() === 'personal') {
            $requirements[] = 'single_website_only';
            $requirements[] = 'no_team_members';
        }

        return new PlanTransitionDecision(
            from: $from,
            to: $to,
            type: $type,
            allowed: ! $same,
            familyChange: $familyChange,
            warnings: array_values(array_unique($warnings)),
            requirements: array_values(array_unique($requirements)),
            reason: $same ? 'This is already the current plan.' : null,
        );
    }

    public function forUser(User $user, string $toKey): PlanTransitionDecision
    {
        return $this->decide($user->plan_key, $toKey);
    }

    public function assertAllowed(User $user, string $toKey): PlanTransitionDecision
    {
        $decision = $this->forUser($user, $toKey);

        if (! $decision->allowed) {
            throw new RuntimeException($decision->reason ?? 'This plan change is not allowed.');
        }

        return $decision;
    }

    public function matrix(?string $currentKey): array
    {
        return collect($this->plans->all())
            ->keys()
            ->map(fn (string $target) => $this->decide($currentKey, $target)->toArray())
            ->values()
            ->all();
    }
}
