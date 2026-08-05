<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NotificationDeliveryLog extends Model
{
    protected $fillable = ['user_id','channel','event','recipient','subject','status','failure_message','sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
}
