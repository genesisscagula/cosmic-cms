<?php

namespace App\Cosmic\Plans;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class PlanResolver
{
    public function __construct(private readonly PlanRegistry $plans)
    {
    }

    public function current(): PlanDefinition
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('A signed-in user is required to resolve the current Cosmic plan.');
        }

        return $this->forUser($user);
    }

    public function forUser(User|Authenticatable $user): PlanDefinition
    {
        return $this->plans->definition($user instanceof User ? $user->effectivePlanKey() : (string) ($user->plan_key ?? 'starter'));
    }

    public function forWorkspace(Workspace $workspace): PlanDefinition
    {
        $owner = $workspace->owner;

        if (! $owner instanceof User) {
            throw new RuntimeException("Workspace [{$workspace->getKey()}] has no plan-owning user.");
        }

        return $this->forUser($owner);
    }
}
