<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsitePreviewLink extends Model
{
    protected $fillable = ['workspace_id','website_id','created_by_user_id','token','label','expires_at','revoked_at','last_viewed_at','views'];
    protected $casts = ['expires_at'=>'datetime','revoked_at'=>'datetime','last_viewed_at'=>'datetime'];

    public function website() { return $this->belongsTo(Website::class); }
    public function workspace() { return $this->belongsTo(Workspace::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function isUsable(): bool { return ! $this->revoked_at && (! $this->expires_at || $this->expires_at->isFuture()); }
}
