<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $fillable = [
        'website_id',
        'name',
        'email',
        'phone',
        'message',
        'fields',
        'received_at',
    ];

    protected $casts = [
        'fields' => 'array',
        'received_at' => 'datetime',
    ];

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}
