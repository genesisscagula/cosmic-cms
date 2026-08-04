<?php

namespace App\Http\Controllers;

use App\Models\CosmicSparkFavorite;
use App\Models\CosmicUnlock;
use App\Services\CreditService;
use App\Services\SparkCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SparkController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Sparks/Index', $this->payload($request));
    }

    public function catalog(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function unlockKey(Request $request, string $key, CreditService $credits): JsonResponse
    {
        $spark = SparkCatalog::find($key);
        abort_unless($spark, 404);
        $user = $request->user();

        $existing = $user->cosmicUnlocks()
            ->where('unlock_type', 'spark')
            ->where('unlock_key', $key)
            ->first();

        if ($existing) {
            if (! $existing->is_installed) {
                $existing->update(['is_installed' => true]);
            }

            return response()->json([
                'message' => 'This Spark is already owned and has been restored to your library.',
                'owned' => true,
                'purchased' => (int) $existing->credits_paid > 0,
                'credit_balance' => (int) $user->fresh()->credits,
            ]);
        }

        return DB::transaction(function () use ($user, $spark, $key, $credits) {
            $transaction = $credits->consume(
                $user,
                $spark['credits'],
                'Unlocked Spark: '.$spark['name'],
                null,
                'spark-'.$key.'-'.uniqid(),
                ['spark_key' => $key]
            );

            CosmicUnlock::create([
                'user_id' => $user->id,
                'unlock_type' => 'spark',
                'unlock_key' => $key,
                'credits_paid' => $spark['credits'],
                'is_installed' => true,
            ]);

            return response()->json([
                'message' => $spark['name'].' added to Owned Sparks.',
                'owned' => true,
                'purchased' => (int) $spark['credits'] > 0,
                'credit_balance' => (int) $transaction->balance_after,
            ]);
        });
    }

    public function removeOwned(Request $request, string $key): JsonResponse
    {
        $unlock = $request->user()->cosmicUnlocks()
            ->where('unlock_type', 'spark')
            ->where('unlock_key', $key)
            ->first();

        if (! $unlock) {
            return response()->json(['message' => 'Spark was not found in your library.', 'removed' => false]);
        }

        $unlock->update(['is_installed' => false]);

        return response()->json([
            'message' => 'Spark removed from Owned Sparks. Purchases remain attached to your account.',
            'removed' => true,
            'purchased' => (int) $unlock->credits_paid > 0,
        ]);
    }

    public function toggleFavorite(Request $request, string $key): JsonResponse
    {
        abort_unless(SparkCatalog::find($key), 404);
        $favorite = $request->user()->sparkFavorites()->where('spark_key', $key)->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json(['favorited' => false, 'message' => 'Removed from Favorites.']);
        }

        CosmicSparkFavorite::create(['user_id' => $request->user()->id, 'spark_key' => $key]);
        return response()->json(['favorited' => true, 'message' => 'Added to Favorites.']);
    }

    private function payload(Request $request): array
    {
        $unlocks = $request->user()->cosmicUnlocks()->where('unlock_type', 'spark')->get()->keyBy('unlock_key');
        $favoriteKeys = $request->user()->sparkFavorites()->pluck('spark_key');

        $sparks = collect(SparkCatalog::all())->map(function (array $spark) use ($unlocks, $favoriteKeys) {
            $unlock = $unlocks->get($spark['key']);
            return [
                ...$spark,
                'owned' => (bool) ($unlock?->is_installed),
                'purchased' => (int) ($unlock?->credits_paid ?? 0) > 0,
                'favorited' => $favoriteKeys->contains($spark['key']),
                'is_free' => (int) ($spark['credits'] ?? 0) === 0,
                'is_premium' => (int) ($spark['credits'] ?? 0) > 0,
                'source' => (int) ($spark['credits'] ?? 0) === 0 ? 'Built-in' : 'Marketplace',
            ];
        })->values();

        return [
            'sparks' => $sparks,
            'categories' => $sparks->pluck('category')->unique()->values(),
            'ownedCount' => $sparks->where('owned', true)->count(),
            'favoriteCount' => $sparks->where('favorited', true)->count(),
            'purchasedCount' => $sparks->where('purchased', true)->count(),
        ];
    }
}
