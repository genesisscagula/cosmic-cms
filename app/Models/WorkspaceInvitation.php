<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceInvitation extends Model
{
    protected $fillable = ['workspace_id', 'invited_by_user_id', 'name', 'email', 'role', 'website_ids', 'token', 'status', 'expires_at', 'accepted_at'];

    protected $casts = ['website_ids' => 'array', 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function workspace() { return $this->belongsTo(Workspace::class); }
    public function invitedBy() { return $this->belongsTo(User::class, 'invited_by_user_id'); }
}
