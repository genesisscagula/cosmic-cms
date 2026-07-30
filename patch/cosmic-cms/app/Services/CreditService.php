<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditService
{
    public function balance(User $user): int
    {
        return (int) $user->credits;
    }

    public function canAfford(User $user, int $amount): bool
    {
        $this->assertPositiveAmount($amount);

        return $this->balance($user) >= $amount;
    }

    public function consume(
        User $user,
        int $amount,
        string $description,
        ?Website $website = null,
        ?string $reference = null,
        array $metadata = [],
    ): CreditTransaction {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $description, $website, $reference, $metadata) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ((int) $lockedUser->credits < $amount) {
                throw new InsufficientCreditsException($amount, (int) $lockedUser->credits);
            }

            $lockedUser->decrement('credits', $amount);
            $lockedUser->refresh();

            $transaction = $lockedUser->creditTransactions()->create([
                'website_id' => $website?->id,
                'type' => 'debit',
                'amount' => -$amount,
                'balance_after' => (int) $lockedUser->credits,
                'description' => $description,
                'reference' => $reference,
                'metadata' => $metadata ?: null,
            ]);

            $user->setAttribute('credits', $lockedUser->credits);

            return $transaction;
        });
    }

    public function grant(
        User $user,
        int $amount,
        string $description,
        ?string $reference = null,
        array $metadata = [],
    ): CreditTransaction {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $description, $reference, $metadata) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->increment('credits', $amount);
            $lockedUser->refresh();

            $transaction = $lockedUser->creditTransactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => (int) $lockedUser->credits,
                'description' => $description,
                'reference' => $reference,
                'metadata' => $metadata ?: null,
            ]);

            $user->setAttribute('credits', $lockedUser->credits);

            return $transaction;
        });
    }

    public function refund(
        User $user,
        int $amount,
        string $description,
        ?Website $website = null,
        ?string $reference = null,
        array $metadata = [],
    ): CreditTransaction {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $description, $website, $reference, $metadata) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->increment('credits', $amount);
            $lockedUser->refresh();

            $transaction = $lockedUser->creditTransactions()->create([
                'website_id' => $website?->id,
                'type' => 'refund',
                'amount' => $amount,
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
        if ($amount < 1) {
            throw new InvalidArgumentException('Credit amount must be at least 1.');
        }
    }
}
