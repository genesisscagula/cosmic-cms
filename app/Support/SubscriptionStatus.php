<?php

namespace App\Support;

final class SubscriptionStatus
{
    public const PENDING = 'pending';
    public const ACTIVE = 'active';
    public const PAST_DUE = 'past_due';
    public const SUSPENDED = 'suspended';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    public const ALL = [
        self::PENDING,
        self::ACTIVE,
        self::PAST_DUE,
        self::SUSPENDED,
        self::CANCELLED,
        self::EXPIRED,
    ];

    public static function normalize(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return match ($status) {
            'active', 'approved', 'paid', 'completed' => self::ACTIVE,
            'approval_pending', 'pending', 'pending_payment', 'created' => self::PENDING,
            'past_due', 'payment_failed', 'failed' => self::PAST_DUE,
            'suspended' => self::SUSPENDED,
            'cancelled', 'canceled' => self::CANCELLED,
            'expired', 'inactive' => self::EXPIRED,
            default => self::PENDING,
        };
    }

    public static function badge(?string $status, bool $cancelAtPeriodEnd = false): array
    {
        $status = self::normalize($status);

        if ($cancelAtPeriodEnd && $status === self::CANCELLED) {
            return ['label' => 'Cancelled', 'tone' => 'cancelled'];
        }

        return match ($status) {
            self::ACTIVE => ['label' => 'Active', 'tone' => 'active'],
            self::PENDING => ['label' => 'Pending Payment', 'tone' => 'pending'],
            self::PAST_DUE => ['label' => 'Payment Failed', 'tone' => 'danger'],
            self::SUSPENDED => ['label' => 'Suspended', 'tone' => 'warning'],
            self::CANCELLED => ['label' => 'Cancelled', 'tone' => 'cancelled'],
            self::EXPIRED => ['label' => 'Expired', 'tone' => 'expired'],
        };
    }

    public static function grantsAccess(?string $status, bool $cancelAtPeriodEnd, mixed $renewsAt): bool
    {
        $status = self::normalize($status);

        if ($status === self::ACTIVE) {
            return true;
        }

        return $status === self::CANCELLED
            && $cancelAtPeriodEnd
            && $renewsAt
            && $renewsAt->isFuture();
    }
}
