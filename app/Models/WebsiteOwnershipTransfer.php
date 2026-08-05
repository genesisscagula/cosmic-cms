<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteOwnershipTransfer extends Model
{
    protected $fillable = [
        'website_id', 'from_user_id', 'to_user_id', 'initiated_by_user_id',
        'from_workspace_id', 'to_workspace_id', 'recipient_email', 'token', 'status', 'metadata', 'expires_at', 'accepted_at', 'cancelled_at', 'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function website() { return $this->belongsTo(Website::class); }
    public function fromUser() { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser() { return $this->belongsTo(User::class, 'to_user_id'); }
    public function initiatedBy() { return $this->belongsTo(User::class, 'initiated_by_user_id'); }

    public function isAcceptableBy(?User $user): bool
    {
        return $user !== null
            && $this->status === 'pending'
            && (int) $this->to_user_id === (int) $user->id
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
