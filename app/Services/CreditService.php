<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\Website;

/** Backward-compatible facade for the centralized account wallet. */
class CreditService
{
    public function __construct(private readonly CreditWalletService $wallet) {}

    public function balance(User $user): int { return $this->wallet->balance($user); }
    public function canAfford(User $user, int $amount): bool { return $this->wallet->canAfford($user, $amount); }

    public function consume(User $user, int $amount, string $description, ?Website $website = null, ?string $reference = null, array $metadata = []): CreditTransaction
    {
        return $this->wallet->debit($user, $amount, $description, $this->category($metadata, $description), $website, $reference, $metadata);
    }

    public function grant(User $user, int $amount, string $description, ?string $reference = null, array $metadata = []): CreditTransaction
    {
        return $this->wallet->credit($user, $amount, $description, $this->category($metadata, $description), $reference, $metadata);
    }

    public function refund(User $user, int $amount, string $description, ?Website $website = null, ?string $reference = null, array $metadata = []): CreditTransaction
    {
        return $this->wallet->refund($user, $amount, $description, 'refund', $website, $reference, $metadata);
    }

    private function category(array $metadata, string $description): string
    {
        if (!empty($metadata['category'])) return (string) $metadata['category'];
        $value = strtolower($description.' '.($metadata['product_type'] ?? ''));
        return match (true) {
            str_contains($value, 'spark') => 'sparks',
            str_contains($value, 'theme'), str_contains($value, 'style') => 'themes',
            str_contains($value, 'page'), str_contains($value, 'website'), str_contains($value, 'menu') => 'websites',
            str_contains($value, 'ai'), str_contains($value, 'generate'), str_contains($value, 'image'), str_contains($value, 'blog') => 'ai',
            str_contains($value, 'subscription'), str_contains($value, 'renewal'), str_contains($value, 'plan') => 'subscription',
            str_contains($value, 'purchase'), str_contains($value, 'top-up'), str_contains($value, 'credits') => 'purchase',
            default => 'other',
        };
    }
}
