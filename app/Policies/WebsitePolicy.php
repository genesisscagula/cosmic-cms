<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    public function view(User $user, Website $website): bool
    {
        return $this->canAccess($user, $website);
    }

    public function update(User $user, Website $website): bool
    {
        return $this->canAccess($user, $website);
    }

    public function delete(User $user, Website $website): bool
    {
        // Clients may edit their assigned website, but only workspace/platform
        // owners may permanently delete it.
        return $user->isPlatformOwner()
            || $website->workspace?->owner_user_id === $user->id;
    }

    private function canAccess(User $user, Website $website): bool
    {
        if ($website->user_id === $user->id) {
            return true;
        }

        if (! $website->workspace_id) {
            return false;
        }

        return $user->isPlatformOwner()
            && $website->workspace?->owner_user_id === $user->id;
    }
}
