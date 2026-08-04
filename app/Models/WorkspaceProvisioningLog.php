<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceProvisioningLog extends Model
{
    protected $fillable = [
        'workspace_provisioning_id', 'user_id', 'level', 'event', 'stage',
        'message', 'attempt', 'context', 'occurred_at',
    ];

    protected $casts = [
        'attempt' => 'integer',
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function provisioning()
    {
        return $this->belongsTo(WorkspaceProvisioning::class, 'workspace_provisioning_id');
    }
}
