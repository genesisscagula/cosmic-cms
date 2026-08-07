<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaPack extends Model
{
    protected $fillable = [
        'uuid',
        'owner_type',
        'owner_id',
        'trial_generation_id',
        'website_id',
        'status',
        'target_image_count',
        'keywords',
        'manifest',
        'queued_at',
        'completed_at',
        'last_error',
    ];

    protected $casts = [
        'keywords' => 'array',
        'manifest' => 'array',
        'queued_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function trialGeneration()
    {
        return $this->belongsTo(TrialGeneration::class);
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    public function storageDirectory(): string
    {
        return 'cms-images/packs/'.$this->uuid;
    }

    public function publicBaseUrl(): string
    {
        return '/storage/'.$this->storageDirectory();
    }
}
