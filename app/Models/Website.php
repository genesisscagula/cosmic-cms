<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Website extends Model
{
    // Gi-allow nato ang 'global_header' nga masulod sa mass assignment保護
    protected $fillable = [
        'name',
        'domain',
        'api_token',
        'theme_settings',
        'global_header',
        'global_footer',
        'published_theme_settings',
        'published_global_header',
        'published_global_footer',
    ];

    // Gi-automatic cast nato ang JSON string ngadto sa PHP/React Array packet
    protected $casts = [
        'theme_settings' => 'array',
        'global_header' => 'array', 
        'global_footer' => 'array',
        'published_theme_settings' => 'array',
        'published_global_header' => 'array',
        'published_global_footer' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function globalElements()
    {
        return $this->hasMany(GlobalElements::class);
    }
}
