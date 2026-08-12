<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentEntry extends Model
{
    protected $fillable = [
        'website_id', 'content_type_id', 'title', 'slug', 'excerpt', 'content',
        'status', 'category', 'tags', 'featured_image_url', 'gallery', 'custom_fields',
        'seo_title', 'seo_description', 'og_image_url', 'is_featured', 'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'gallery' => 'array',
        'custom_fields' => 'array',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function website() { return $this->belongsTo(Website::class); }
    public function contentType() { return $this->belongsTo(ContentType::class); }
}
