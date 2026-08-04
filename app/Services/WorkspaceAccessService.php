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

        return $workspace->users()
            ->whereKey($user->id)
            ->value('workspace_user.role');
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

        return in_array($this->role($user, $workspace), [
            self::OWNER, self::ADMIN, self::EDITOR, self::VIEWER,
        ], true);
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

        return in_array($this->role($user, $workspace), [
            self::OWNER, self::ADMIN, self::EDITOR,
        ], true);
    }

    public function canDelete(User $user, Website $website): bool
    {
        $workspace = $website->workspace;

        return $workspace && in_array($this->role($user, $workspace), [
            self::OWNER, self::ADMIN,
        ], true);
    }
}
