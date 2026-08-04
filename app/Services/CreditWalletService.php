<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditWalletService
{
    public function balance(User $user): int
    {
        return (int) $user->fresh()->credits;
    }

    public function canAfford(User $user, int $amount): bool
    {
        $this->assertPositiveAmount($amount);
        return $this->balance($user) >= $amount;
    }

    public function debit(User $user, int $amount, string $description, string $category = 'other', ?Website $website = null, ?string $reference = null, array $metadata = []): CreditTransaction
    {
        return $this->mutate($user, -$amount, 'debit', $description, $category, $website, $reference, $metadata);
    }

    public function credit(User $user, int $amount, string $description, string $category = 'other', ?string $reference = null, array $metadata = []): CreditTransaction
    {
        return $this->mutate($user, $amount, 'credit', $description, $category, null, $reference, $metadata);
    }

    public function refund(User $user, int $amount, string $description, string $category = 'refund', ?Website $website = null, ?string $reference = null, array $metadata = []): CreditTransaction
    {
        return $this->mutate($user, $amount, 'refund', $description, $category, $website, $reference, $metadata);
    }

    private function mutate(User $user, int $signedAmount, string $type, string $description, string $category, ?Website $website, ?string $reference, array $metadata): CreditTransaction
    {
        $this->assertPositiveAmount(abs($signedAmount));

        return DB::transaction(function () use ($user, $signedAmount, $type, $description, $category, $website, $reference, $metadata) {
            if ($reference) {
                $existing = CreditTransaction::query()->where('user_id', $user->id)->where('reference', $reference)->first();
                if ($existing) {
                    $user->setAttribute('credits', $existing->balance_after);
                    return $existing;
                }
            }

            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($signedAmount < 0 && (int) $lockedUser->credits < abs($signedAmount)) {
                throw new InsufficientCreditsException(abs($signedAmount), (int) $lockedUser->credits);
            }

            $lockedUser->credits = max(0, (int) $lockedUser->credits + $signedAmount);
            $lockedUser->save();

            $transaction = $lockedUser->creditTransactions()->create([
                'website_id' => $website?->id,
                'type' => $type,
                'category' => $category,
                'amount' => $signedAmount,
                'balance_after' => (int) $lockedUser->credits,
                'description' => $description,
                'reference' => $reference,
                'metadata' => $metadata ?: null,
            ]);

            $user->setAttribute('credits', $lockedUser->credits);
            return $transaction;
        });
    }

    private function assertPositiveAmount(int $amount): void
    {
        if ($amount < 1) throw new InvalidArgumentException('Credit amount must be at least 1.');
    }
}
