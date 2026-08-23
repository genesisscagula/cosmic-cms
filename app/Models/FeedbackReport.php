<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackReport extends Model
{
    public const CATEGORIES = ['bug', 'suggestion', 'feedback'];

    public const STATUSES = ['new', 'reviewing', 'resolved', 'closed'];

    protected $fillable = [
        'user_id',
        'website_id',
        'page_id',
        'trial_generation_id',
        'category',
        'status',
        'description',
        'reporter_name',
        'reporter_email',
        'source_url',
        'context',
        'user_agent',
        'ip_hash',
        'screenshot_path',
        'screenshot_original_name',
        'screenshot_mime',
        'screenshot_size',
        'admin_notes',
        'viewed_at',
        'resolved_at',
    ];

    protected $casts = [
        'context' => 'array',
        'screenshot_size' => 'integer',
        'viewed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function trialGeneration()
    {
        return $this->belongsTo(TrialGeneration::class);
    }
}
