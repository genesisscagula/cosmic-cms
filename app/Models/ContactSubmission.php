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
        'status',
        'received_at',
        'read_at',
        'archived_at',
    ];

    protected $casts = [
        'fields' => 'array',
        'received_at' => 'datetime',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}
