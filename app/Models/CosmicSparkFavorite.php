<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CosmicSparkFavorite extends Model
{
    protected $fillable = ['user_id', 'spark_key'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
