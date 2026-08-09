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
        'guest_credits',
        'logo_url',
        'logo_company_name',
        'logo_source',
        'logo_theme_sync_state',
        'logo_theme_sync_source',
        'logo_theme_synced_theme',
        'logo_updated_at',
        'status',
        'error_message',
        'ip_hash',
        'claimed_at',
        'claimed_by_user_id',
        'selected_plan',
        'plan_selected_at',
        'email_captured_at',
        'welcome_email_sent_at',
        'welcome_email_address',
        'welcome_email_attempts',
        'welcome_email_last_attempt_at',
        'welcome_email_last_error',
        'last_saved_at',
    ];

    protected $casts = [
        'sections' => 'array',
        'generated_blocks' => 'array',
        'menu_structure' => 'array',
        'preview_theme' => 'array',
        'guest_credits' => 'integer',
        'logo_updated_at' => 'datetime',
        'claimed_at' => 'datetime',
        'plan_selected_at' => 'datetime',
        'email_captured_at' => 'datetime',
        'welcome_email_sent_at' => 'datetime',
        'welcome_email_attempts' => 'integer',
        'welcome_email_last_attempt_at' => 'datetime',
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
