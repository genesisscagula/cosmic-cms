<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentType extends Model
{
    protected $fillable = [
        'website_id', 'name', 'singular_name', 'slug', 'icon', 'description',
        'schema', 'is_system', 'sort_order',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function website() { return $this->belongsTo(Website::class); }
    public function entries() { return $this->hasMany(ContentEntry::class); }
}
