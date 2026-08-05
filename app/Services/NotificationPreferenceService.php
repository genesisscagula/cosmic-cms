<?php
namespace App\Services;
use App\Models\User;
final class NotificationPreferenceService
{
    public const DEFAULTS = [
        'billing' => true,
        'team' => true,
        'client' => true,
        'handoff' => true,
        'security' => true,
        'product_updates' => false,
    ];

    public function all(User $user): array
    {
        return array_merge(self::DEFAULTS, is_array($user->notification_preferences) ? $user->notification_preferences : []);
    }

    public function allows(User $user, string $event): bool
    {
        if ($event === 'security') return true;
        return (bool) ($this->all($user)[$event] ?? true);
    }
}
