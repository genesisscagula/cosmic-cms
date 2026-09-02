<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceTemplateBlock extends Model
{
    protected $fillable = [
        'marketplace_template_page_id', 'spark_key', 'sort_order', 'content', 'settings', 'metadata',
    ];

    protected $casts = [
        'content' => 'array',
        'settings' => 'array',
        'metadata' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTemplatePage::class, 'marketplace_template_page_id');
    }

    public function toBuilderBlock(): array
    {
        return array_filter([
            'type' => $this->spark_key,
            ...(is_array($this->content) ? $this->content : []),
            ...(is_array($this->settings) ? $this->settings : []),
            '_marketplace' => [
                'block_id' => $this->id,
                'template_page_id' => $this->marketplace_template_page_id,
                ...(is_array($this->metadata) ? $this->metadata : []),
            ],
        ], static fn ($value) => $value !== null);
    }
}
