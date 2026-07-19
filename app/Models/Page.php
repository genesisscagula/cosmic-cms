<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    // Gi-allow nga fields para sa page details ug ang iyang blocks bundle
    protected $fillable = ['title', 'slug', 'blocks', 'status'];

    // I-cast ang blocks nga gi-save nato as text para mahimong array diritso sa code
    protected $casts = [
        'blocks' => 'array',
    ];

    // Relasyon: Ang Page nag-depende sa iyang Website
    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}