<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    // Gi-allow nga fields para sa page details ug ang iyang blocks bundle
    protected $fillable = [
        'title',
        'slug',
        'parent_id',
        'sort_order',
        'page_type',
        'blocks',
        'published_blocks',
        'published_html',
        'status',
        'published_at',
        'last_published_at',
        'publish_error',
    ];

    // I-cast ang blocks nga gi-save nato as text para mahimong array diritso sa code
    protected $casts = [
        'blocks' => 'array',
        'published_blocks' => 'array',
        'published_at' => 'datetime',
        'last_published_at' => 'datetime',
    ];

    // Relasyon: Ang Page nag-depende sa iyang Website
    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    /** The optional page directly above this page in the website tree. */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Direct children only; Cosmic intentionally supports a shallow tree. */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }
}
