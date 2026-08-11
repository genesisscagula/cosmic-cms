<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceOrderEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
        ];
    }

    public function order() { return $this->belongsTo(CommerceOrder::class, 'commerce_order_id'); }
    public function website() { return $this->belongsTo(Website::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
