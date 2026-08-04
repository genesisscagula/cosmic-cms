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

    public function delete(User $user, Website $website): bool
    {
        return $this->access->canDelete($user, $website);
    }
}
