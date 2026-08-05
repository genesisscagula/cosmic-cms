<?php

namespace App\Services;

use App\Models\CosmicUnlock;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSpark;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class WorkspaceSparkLibraryService
{
    public function workspaceFor(User $user): ?Workspace
    {
        return $user->ownedWorkspaces()->first()
            ?? $user->workspaces()->orderBy('workspaces.id')->first();
    }

    public function canManage(User $user, Workspace $workspace): bool
    {
        if (! $user->hasPlanCapability('shared_sparks')) {
            return false;
        }

        return (int) $workspace->owner_user_id === (int) $user->id
            || in_array($workspace->roleFor($user), ['owner', 'admin'], true);
    }

    public function assertCanManage(User $user, Workspace $workspace): void
    {
        if (! $this->canManage($user, $workspace)) {
            throw new AuthorizationException('Your plan or workspace role does not allow Shared Agency Sparks.');
        }
    }

    public function assertOwnedAndInstalled(User $user, string $sparkKey): CosmicUnlock
    {
        $unlock = $user->cosmicUnlocks()
            ->where('unlock_type', 'spark')
            ->where('unlock_key', $sparkKey)
            ->where('is_installed', true)
            ->first();

        if (! $unlock) {
            throw ValidationException::withMessages([
                'spark' => 'Only an installed Spark from your Owned Sparks library can be shared.',
            ]);
        }

        return $unlock;
    }

    public function share(User $user, Workspace $workspace, string $sparkKey): WorkspaceSpark
    {
        $this->assertCanManage($user, $workspace);
        $this->assertOwnedAndInstalled($user, $sparkKey);

        return WorkspaceSpark::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'spark_key' => $sparkKey],
            ['shared_by_user_id' => $user->id],
        );
    }

    public function unshare(User $user, Workspace $workspace, string $sparkKey): bool
    {
        $this->assertCanManage($user, $workspace);

        return WorkspaceSpark::query()
            ->where('workspace_id', $workspace->id)
            ->where('spark_key', $sparkKey)
            ->delete() > 0;
    }

    /** @return Collection<int,string> */
    public function keysFor(User $user): Collection
    {
        $workspace = $this->workspaceFor($user);
        if (! $workspace) {
            return collect();
        }

        return $workspace->sharedSparks()->pluck('spark_key')->values();
    }

    /** @return array<string,mixed> */
    public function summary(User $user): array
    {
        $workspace = $this->workspaceFor($user);
        $enabled = $workspace !== null && $user->hasPlanCapability('shared_sparks');

        return [
            'enabled' => $enabled,
            'can_manage' => $workspace ? $this->canManage($user, $workspace) : false,
            'workspace_id' => $workspace?->id,
            'workspace_name' => $workspace?->name,
            'shared_count' => $workspace?->sharedSparks()->count() ?? 0,
            'message' => $enabled
                ? 'Shared Sparks are available to websites and members in this agency workspace.'
                : 'Upgrade to Growth Agency or Pro Agency to share Sparks across the workspace.',
        ];
    }
}
