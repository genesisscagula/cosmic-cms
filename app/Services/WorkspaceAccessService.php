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

    public function role(User $user, Workspace $workspace): ?string
    {
        if ((int) $workspace->owner_user_id === (int) $user->id) {
            return self::OWNER;
        }

        return $workspace->users()->whereKey($user->id)->value('workspace_user.role');
    }


    public function websiteRole(User $user, Website $website): ?string
    {
        if ((int) $website->user_id === (int) $user->id) return self::OWNER;
        return $website->assignedUsers()->whereKey($user->id)->value('website_user.role');
    }

    public function canEditBuilder(User $user, Website $website): bool
    {
        return in_array($this->websiteRole($user, $website), [self::OWNER, 'website_admin', 'website_editor'], true);
    }

    public function canManageWebsite(User $user, Website $website): bool
    {
        return in_array($this->websiteRole($user, $website), [self::OWNER, 'website_admin'], true);
    }

    public function isAssigned(User $user, Website $website): bool
    {
        return $website->assignedUsers()->whereKey($user->id)->exists();
    }

    public function canView(User $user, Website $website): bool
    {
        if ($this->websiteRole($user, $website)) return true;
        $workspace = $website->workspace;
        if (! $workspace) return false;
        return $this->role($user, $workspace) === self::OWNER;
    }

    public function canUpdate(User $user, Website $website): bool
    {
        return $this->canManageWebsite($user, $website);
    }

    public function canDelete(User $user, Website $website): bool
    {
        return in_array($this->websiteRole($user, $website), [self::OWNER, 'website_admin'], true);
    }
}
