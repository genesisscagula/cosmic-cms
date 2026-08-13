<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentType extends Model
{
    protected $fillable = [
        'website_id', 'name', 'singular_name', 'slug', 'icon', 'description',
        'schema', 'is_system', 'sort_order', 'single_template_id', 'archive_template_id',
        'preset_key', 'schema_source', 'schema_signature', 'single_template_schema_signature', 'archive_template_schema_signature',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function website() { return $this->belongsTo(Website::class); }
    public function entries() { return $this->hasMany(ContentEntry::class); }
    public function singleTemplate() { return $this->belongsTo(SavedPageTemplate::class, 'single_template_id'); }
    public function archiveTemplate() { return $this->belongsTo(SavedPageTemplate::class, 'archive_template_id'); }
    public function templates() { return $this->hasMany(SavedPageTemplate::class); }
}
