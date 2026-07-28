<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialGeneration extends Model
{
    protected $fillable = [
        'token',
        'email',
        'business_name',
        'industry',
        'location',
        'business_description',
        'prompt',
        'sections',
        'generated_blocks',
        'status',
        'error_message',
        'ip_hash',
        'claimed_at',
        'claimed_by_user_id',
        'selected_plan',
        'plan_selected_at',
    ];

    protected $casts = [
        'sections' => 'array',
        'generated_blocks' => 'array',
        'claimed_at' => 'datetime',
        'plan_selected_at' => 'datetime',
    ];
}
