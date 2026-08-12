<?php

namespace App\Services;

use App\Models\TrialGeneration;
use Illuminate\Support\Facades\DB;

class TrialCreditService
{
    public const STARTING_BALANCE = 500;
    public const PAGE_STYLE = 20;
    public const REGENERATE_PAGE = 50;
    public const GENERATE_LOGO = 50;
    public const GENERATE_IMAGE = 50;
    public const GENERATE_TEXT = 10;
    public const MATCH_LOGO_TO_THEME = 50;
    public const MATCH_THEME_TO_LOGO = 50;

    public function balance(TrialGeneration $trial): int
    {
        return $this->ensureInitialized($trial);
    }

    public function ensureInitialized(TrialGeneration $trial): int
    {
        $fresh = $trial->fresh();
        $balance = (int) ($fresh->guest_credits ?? 0);

        // Legacy trials created before guest-credit initialization can appear as 0.
        // Only restore 500 when there is no spend history, so a genuinely depleted
        // guest balance is never reset.
        if ($balance <= 0) {
            $hasTransactions = DB::table('trial_credit_transactions')
                ->where('trial_generation_id', $fresh->id)
                ->exists();

            if (! $hasTransactions) {
                $fresh->guest_credits = self::STARTING_BALANCE;
                $fresh->save();
                $trial->setAttribute('guest_credits', self::STARTING_BALANCE);
                return self::STARTING_BALANCE;
            }
        }

        return max(0, $balance);
    }

    public function ensureCanSpend(TrialGeneration $trial, int $cost, string $label): void
    {
        $available = $this->ensureInitialized($trial);
        abort_if($available < $cost, 422, "Not enough Guest Cosmic Credits. {$label} costs {$cost} credits. Sign up to keep customizing.");
    }

    public function consume(TrialGeneration $trial, int $cost, string $action, array $metadata = []): int
    {
        $this->ensureInitialized($trial);

        return DB::transaction(function () use ($trial, $cost, $action, $metadata) {
            $locked = TrialGeneration::query()->lockForUpdate()->findOrFail($trial->id);
            $available = max(0, (int) $locked->guest_credits);
            abort_if($available < $cost, 422, "Not enough Guest Cosmic Credits. This action costs {$cost} credits. Sign up to keep customizing.");

            $balance = $available - $cost;
            $locked->guest_credits = $balance;
            $locked->save();

            DB::table('trial_credit_transactions')->insert([
                'trial_generation_id' => $locked->id,
                'action' => $action,
                'amount' => -$cost,
                'balance_after' => $balance,
                'metadata' => empty($metadata) ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $trial->setAttribute('guest_credits', $balance);
            return $balance;
        });
    }
}
