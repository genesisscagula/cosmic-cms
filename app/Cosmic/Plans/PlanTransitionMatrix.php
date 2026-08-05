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
        $from = $this->plans->definition($fromKey);
        $to = $this->plans->definition($toKey);
        $same = $from->key() === $to->key();
        $familyChange = $from->family() !== $to->family();
        $type = $same ? 'same' : ($to->price() > $from->price() ? 'upgrade' : 'downgrade');

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

        if ($to->family() === 'personal') {
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
