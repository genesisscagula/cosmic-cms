<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    public function view(User $user, Website $website): bool
    {
        return $user->id === $website->user_id;
    }

    public function update(User $user, Website $website): bool
    {
        return $user->id === $website->user_id;
    }
}
