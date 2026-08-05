<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    protected $fillable = ['user_id','subject_type','subject_key','consent_type','document_version','granted','metadata','ip_hash','user_agent_hash','recorded_at'];
    protected $casts = ['granted' => 'boolean', 'metadata' => 'array', 'recorded_at' => 'datetime'];
}
