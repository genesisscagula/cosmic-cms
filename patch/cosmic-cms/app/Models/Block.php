<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    protected $fillable = ['page_id', 'type', 'content', 'sort_order'];

    protected $casts = [
        'content' => 'array', // Ang AI content (text/images) JSON i-cast nato as array
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}