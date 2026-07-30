<?php

namespace App\Http\Controllers;

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
        $alreadyOwned = $user->cosmicUnlocks()
            ->where('unlock_type', 'spark')
            ->where('unlock_key', $key)
            ->exists();

        if ($alreadyOwned) {
            return response()->json([
                'message' => 'This Spark is already in My Sparks.',
                'owned' => true,
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
            ]);

            return response()->json([
                'message' => $spark['name'].' added to My Sparks.',
                'owned' => true,
                'credit_balance' => (int) $transaction->balance_after,
            ]);
        });
    }

    private function payload(Request $request): array
    {
        $ownedKeys = $request->user()->cosmicUnlocks()
            ->where('unlock_type', 'spark')
            ->pluck('unlock_key');

        $sparks = collect(SparkCatalog::all())
            ->map(fn (array $spark) => [...$spark, 'owned' => $ownedKeys->contains($spark['key'])])
            ->values();

        return [
            'sparks' => $sparks,
            'categories' => $sparks->pluck('category')->unique()->values(),
            'ownedCount' => $sparks->where('owned', true)->count(),
        ];
    }
}
