<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleFor(User $user): ?string
    {
        if ((int) $this->owner_user_id === (int) $user->id) {
            return 'owner';
        }

        return $this->users()->whereKey($user->id)->value('workspace_user.role');
    }

    public function allows(User $user, string $permission): bool
    {
        return app(\App\Services\WorkspacePermissionService::class)->allows($user, $this, $permission);
    }

    public function permissionsFor(User $user): array
    {
        $role = $this->roleFor($user);
        return $role ? app(\App\Services\WorkspacePermissionService::class)->permissionsForRole($role) : [];
    }

    public function invitations()
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function websites()
    {
        return $this->hasMany(Website::class);
    }

    public function assignedWebsitesFor(User $user)
    {
        return $this->websites()->whereHas('assignedUsers', fn ($query) => $query->whereKey($user->id));
    }

    public function sharedSparks()
    {
        return $this->hasMany(WorkspaceSpark::class);
    }
}
