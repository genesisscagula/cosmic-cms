<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'website_id',
        'folder_id',
        'uploaded_by',
        'source',
        'kind',
        'disk',
        'path',
        'original_name',
        'filename',
        'mime_type',
        'extension',
        'size_bytes',
        'width',
        'height',
        'checksum_sha256',
        'alt_text',
        'caption',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (MediaAsset $asset) {
            if (! $asset->uuid) {
                $asset->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    public function folder()
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
