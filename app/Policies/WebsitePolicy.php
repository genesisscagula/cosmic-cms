<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;
use App\Services\WorkspaceAccessService;

class WebsitePolicy
{
    public function __construct(private readonly WorkspaceAccessService $access) {}

    public function view(User $user, Website $website): bool
    {
        return $this->access->canView($user, $website);
    }

    public function update(User $user, Website $website): bool
    {
        return $this->access->canUpdate($user, $website);
    }

    public function editBuilder(User $user, Website $website): bool
    {
        return $this->access->canEditBuilder($user, $website);
    }

    public function transferOwnership(User $user, Website $website): bool
    {
        return (int) $website->user_id === (int) $user->id
            && $user->hasPlanCapability('ownership_transfer');
    }

    public function delete(User $user, Website $website): bool
    {
        return $this->access->canDelete($user, $website);
    }
}
