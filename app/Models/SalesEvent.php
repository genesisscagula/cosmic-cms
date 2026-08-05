<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    public function lead()
    {
        return $this->belongsTo(ContactSubmission::class, 'contact_submission_id');
    }
}
