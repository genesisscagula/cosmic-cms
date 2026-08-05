<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteAnalyticsDaily extends Model
{
    protected $table = 'website_analytics_daily';
    protected $fillable = ['website_id','date','page_views','visitors','sessions','conversions','engaged_sessions','duration_seconds'];
    protected $casts = ['date' => 'date'];
    public function website() { return $this->belongsTo(Website::class); }
}
