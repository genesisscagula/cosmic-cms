<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $fillable = ['website_id', 'page_id', 'title', 'slug', 'excerpt', 'content', 'category', 'tags', 'image_url', 'is_featured', 'status', 'published_at'];

    protected $casts = ['tags' => 'array', 'is_featured' => 'boolean', 'published_at' => 'datetime'];

    public function website() { return $this->belongsTo(Website::class); }
    public function page() { return $this->belongsTo(Page::class); }
}
