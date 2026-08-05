<?php

namespace App\Http\Controllers;

use App\Models\CosmicSparkFavorite;
use App\Models\CosmicUnlock;
use App\Services\CreditService;
use App\Services\SparkCatalog;
use App\Services\PlanEntitlementService;
use App\Services\OwnedSparkSlotService;
use App\Services\SparkAcquisitionService;
use App\Services\SparkUsageStateService;
use App\Services\WorkspaceSparkLibraryService;
use App\Models\User;
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

    public function unlockKey(Request $request, string $key, CreditService $credits, OwnedSparkSlotService $slots, SparkAcquisitionService $acquisitions): JsonResponse
    {
        $spark = SparkCatalog::find($key);
        abort_unless($spark, 404);
        $user = $request->user();

        return DB::transaction(function () use ($user, $spark, $key, $credits, $slots, $acquisitions) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $lockedUser->cosmicUnlocks()
                ->where('unlock_type', 'spark')
                ->where('unlock_key', $key)
                ->lockForUpdate()
                ->first();

            $decision = $acquisitions->decision($lockedUser, $spark, $existing);
            if (! $decision['allowed']) {
                $status = $decision['reason'] === 'insufficient_credits' ? 402 : 422;
                if ($decision['reason'] === 'spark_access_level') $status = 403;

                return response()->json([
                    'message' => $decision['message'] ?? 'This Spark cannot be added right now.',
                    'reason' => $decision['reason'],
                    'acquisition' => $decision,
                    'owned_spark_slots' => $slots->usage($lockedUser),
                ], $status);
            }

            if ($decision['action'] === 'installed') {
                return response()->json([
                    'message' => 'This Spark is already in your Owned Sparks library.',
                    'owned' => true,
                    'purchased' => (bool) $decision['purchased'],
                    'credit_balance' => $decision['balance'],
                    'owned_spark_slots' => $slots->usage($lockedUser),
                    'acquisition' => $decision,
                ]);
            }

            if ($existing) {
                $existing->update(['is_installed' => true]);
                return response()->json([
                    'message' => 'This Spark has been restored to your Owned Sparks library.',
                    'owned' => true,
                    'purchased' => (int) $existing->credits_paid > 0,
                    'credit_balance' => $decision['balance'],
                    'owned_spark_slots' => $slots->usage($lockedUser),
                    'acquisition' => $decision,
                ]);
            }

            $price = max(0, (int) ($spark['credits'] ?? 0));
            $balance = $decision['balance'];
            if ($price > 0) {
                $transaction = $credits->consume(
                    $lockedUser,
                    $price,
                    'Purchased Spark: '.$spark['name'],
                    null,
                    'spark-'.$key.'-'.uniqid(),
                    ['spark_key' => $key, 'product_type' => 'spark_purchase']
                );
                $balance = (int) $transaction->balance_after;
            }

            CosmicUnlock::create([
                'user_id' => $lockedUser->id,
                'unlock_type' => 'spark',
                'unlock_key' => $key,
                'credits_paid' => $price,
                'is_installed' => true,
            ]);

            return response()->json([
                'message' => $price > 0 ? $spark['name'].' purchased and added to Owned Sparks.' : $spark['name'].' installed for free.',
                'owned' => true,
                'purchased' => $price > 0,
                'credit_balance' => $balance,
                'owned_spark_slots' => $slots->usage($lockedUser),
                'acquisition' => $acquisitions->decision($lockedUser->fresh(), $spark),
            ]);
        }, 3);
    }

    public function removeOwned(Request $request, string $key, OwnedSparkSlotService $slots): JsonResponse
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
            'owned_spark_slots' => $slots->usage($request->user()),
        ]);
    }


    public function share(Request $request, string $key, WorkspaceSparkLibraryService $library): JsonResponse
    {
        abort_unless(SparkCatalog::find($key), 404);
        $workspace = $library->workspaceFor($request->user());
        abort_unless($workspace, 422, 'No workspace is available for Spark sharing.');

        $library->share($request->user(), $workspace, $key);

        return response()->json([
            'message' => 'Spark shared with '.$workspace->name.'.',
            'shared' => true,
            'shared_sparks' => $library->summary($request->user()),
        ]);
    }

    public function unshare(Request $request, string $key, WorkspaceSparkLibraryService $library): JsonResponse
    {
        abort_unless(SparkCatalog::find($key), 404);
        $workspace = $library->workspaceFor($request->user());
        abort_unless($workspace, 422, 'No workspace is available for Spark sharing.');

        $removed = $library->unshare($request->user(), $workspace, $key);

        return response()->json([
            'message' => $removed ? 'Spark removed from the shared workspace library.' : 'Spark was not shared.',
            'shared' => false,
            'shared_sparks' => $library->summary($request->user()),
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
        $entitlements = app(PlanEntitlementService::class);
        $unlocks = $request->user()->cosmicUnlocks()->where('unlock_type', 'spark')->get()->keyBy('unlock_key');
        $favoriteKeys = $request->user()->sparkFavorites()->pluck('spark_key');
        $ownedSparkSlots = app(OwnedSparkSlotService::class)->usage($request->user());
        $acquisitions = app(SparkAcquisitionService::class);
        $sharedLibrary = app(WorkspaceSparkLibraryService::class);
        $sharedKeys = $sharedLibrary->keysFor($request->user());
        $usageStates = app(SparkUsageStateService::class);

        $sparks = collect(SparkCatalog::all())->map(function (array $spark) use ($unlocks, $favoriteKeys, $request, $entitlements, $ownedSparkSlots, $acquisitions, $sharedKeys, $usageStates) {
            $unlock = $unlocks->get($spark['key']);
            $access = $entitlements->sparkAccess($request->user(), (string) ($spark['access_level'] ?? 'growth'));
            $previewAccess = $entitlements->sparkPreviewAccess($request->user(), (int) ($spark['catalog_index'] ?? 0));
            $acquisition = $acquisitions->decision($request->user(), $spark, $unlock);
            $shared = $sharedKeys->contains($spark['key']);
            $usageState = $usageStates->resolve($request->user(), $spark, $acquisition, $previewAccess, $shared);

            return [
                ...$spark,
                'owned' => (bool) ($unlock?->is_installed),
                'purchased' => (int) ($unlock?->credits_paid ?? 0) > 0,
                'favorited' => $favoriteKeys->contains($spark['key']),
                'shared' => $shared,
                'usage_state' => $usageState,
                'is_free' => (int) ($spark['credits'] ?? 0) === 0,
                'is_premium' => (int) ($spark['credits'] ?? 0) > 0,
                'source' => (int) ($spark['credits'] ?? 0) === 0 ? 'Built-in' : 'Marketplace',
                'can_preview' => (bool) $previewAccess['allowed'],
                'preview_access' => $previewAccess,
                'can_install' => (bool) $acquisition['allowed'],
                'acquisition' => $acquisition,
                'action_label' => $acquisition['label'],
                'locked' => ! (bool) $access['allowed'],
                'slot_blocked' => $acquisition['reason'] === 'owned_spark_limit',
                'credit_blocked' => $acquisition['reason'] === 'insufficient_credits',
                'access' => $access,
            ];
        })->values();

        return [
            'sparks' => $sparks,
            'categories' => $sparks->pluck('category')->unique()->values(),
            'ownedCount' => $sparks->where('owned', true)->count(),
            'ownedSparkSlots' => app(OwnedSparkSlotService::class)->usage($request->user()),
            'favoriteCount' => $sparks->where('favorited', true)->count(),
            'purchasedCount' => $sparks->where('purchased', true)->count(),
            'entitlements' => $entitlements->summary($request->user()),
            'sparkRegistry' => SparkCatalog::forClient(),
            'previewPolicy' => $entitlements->sparkPreviewSummary($request->user()),
            'sharedSparkLibrary' => $sharedLibrary->summary($request->user()),
        ];
    }
}
