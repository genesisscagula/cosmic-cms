<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;

class WorkspaceAccessService
{
    public const OWNER = 'owner';
    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const VIEWER = 'viewer';
    public const CLIENT = 'client';

    public function role(User $user, Workspace $workspace): ?string
    {
        if ((int) $workspace->owner_user_id === (int) $user->id) {
            return self::OWNER;
        }

        return $workspace->users()->whereKey($user->id)->value('workspace_user.role');
    }

    public function isAssigned(User $user, Website $website): bool
    {
        return $website->assignedUsers()->whereKey($user->id)->exists();
    }

    public function canView(User $user, Website $website): bool
    {
        if ((int) $website->user_id === (int) $user->id) {
            return true;
        }

        $workspace = $website->workspace;
        if (! $workspace) {
            return false;
        }

        $role = $this->role($user, $workspace);
        if ($role === self::OWNER) {
            return true;
        }

        return in_array($role, [self::ADMIN, self::EDITOR, self::VIEWER, self::CLIENT], true)
            && $this->isAssigned($user, $website);
    }

    public function canUpdate(User $user, Website $website): bool
    {
        if ((int) $website->user_id === (int) $user->id) {
            return true;
        }

        $workspace = $website->workspace;
        if (! $workspace) {
            return false;
        }

        $role = $this->role($user, $workspace);
        if ($role === self::OWNER) {
            return true;
        }

        return in_array($role, [self::ADMIN, self::EDITOR], true)
            && $this->isAssigned($user, $website);
    }

    public function canDelete(User $user, Website $website): bool
    {
        $workspace = $website->workspace;
        if (! $workspace) {
            return false;
        }

        $role = $this->role($user, $workspace);
        if ($role === self::OWNER) {
            return true;
        }

        return $role === self::ADMIN && $this->isAssigned($user, $website);
    }
}
