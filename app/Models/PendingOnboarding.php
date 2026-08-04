<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingOnboarding extends Model
{
    protected $fillable = [
        'user_id',
        'trial_generation_id',
        'workspace_id',
        'website_id',
        'selected_plan',
        'website_name',
        'website_slug',
        'industry',
        'business_description',
        'location',
        'status',
        'expires_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    public function trialGeneration()
    {
        return $this->belongsTo(TrialGeneration::class);
    }

    public function provisioning()
    {
        return $this->hasOne(WorkspaceProvisioning::class);
    }
}
