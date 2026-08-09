<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CosmicChatConversation extends Model
{
    protected $fillable = [
        'public_id', 'access_token_hash', 'status', 'visitor_name', 'visitor_email',
        'started_page', 'last_page', 'ip_hash', 'user_agent', 'message_count',
        'last_message_at', 'admin_read_at', 'archived_at', 'lead_status', 'ai_paused',
        'taken_over_at', 'lead_captured_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'admin_read_at' => 'datetime',
            'archived_at' => 'datetime',
            'ai_paused' => 'boolean',
            'taken_over_at' => 'datetime',
            'lead_captured_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CosmicChatMessage::class, 'conversation_id');
    }

    public function tokenMatches(string $token): bool
    {
        return hash_equals($this->access_token_hash, hash('sha256', $token));
    }
}
