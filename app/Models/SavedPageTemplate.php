<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPageTemplate extends Model
{
    public const SOURCE_SAVED = 'saved';
    public const TYPE_PAGE = 'page';
    public const TYPE_SINGLE = 'single';
    public const TYPE_ARCHIVE = 'archive';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'user_id', 'website_id', 'content_type_id', 'name', 'slug', 'description', 'thumbnail_url',
        'source', 'template_type', 'status', 'blocks', 'markup', 'metadata',
    ];

    protected $casts = [
        'blocks' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function contentType(): BelongsTo
    {
        return $this->belongsTo(ContentType::class);
    }
}
