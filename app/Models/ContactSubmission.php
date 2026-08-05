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
        'lead_status',
        'source',
        'notes',
        'received_at',
        'read_at',
        'qualified_at',
        'converted_at',
        'archived_at',
    ];

    protected $casts = [
        'fields' => 'array',
        'received_at' => 'datetime',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
        'qualified_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}
