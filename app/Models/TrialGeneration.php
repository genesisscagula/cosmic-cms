<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialGeneration extends Model
{
    protected $fillable = [
        'token',
        'page_id',
        'media_pack_id',
        'email',
        'business_name',
        'industry',
        'location',
        'business_description',
        'prompt',
        'sections',
        'generated_blocks',
        'menu_structure',
        'preview_theme',
        'status',
        'error_message',
        'ip_hash',
        'claimed_at',
        'claimed_by_user_id',
        'selected_plan',
        'plan_selected_at',
        'email_captured_at',
        'last_saved_at',
    ];

    protected $casts = [
        'sections' => 'array',
        'generated_blocks' => 'array',
        'menu_structure' => 'array',
        'preview_theme' => 'array',
        'claimed_at' => 'datetime',
        'plan_selected_at' => 'datetime',
        'email_captured_at' => 'datetime',
        'last_saved_at' => 'datetime',
    ];

    public function mediaPack()
    {
        return $this->belongsTo(MediaPack::class);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
