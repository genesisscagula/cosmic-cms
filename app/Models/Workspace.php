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

    public function websites()
    {
        return $this->hasMany(Website::class);
    }
}
