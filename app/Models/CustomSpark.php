<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomSpark extends Model
{
    protected $fillable = ['website_id','key','name','schema','block','metadata'];
    protected $casts = ['schema'=>'array','block'=>'array','metadata'=>'array'];
    public function website(){ return $this->belongsTo(Website::class); }
}
