<?php

namespace App\Http\Controllers;

use App\Models\TrialGeneration;
use App\Services\PageTemplateCatalog;
use App\Services\SparkCatalog;
use App\Services\TrialCreditService;
use App\Services\TrialLibraryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrialAssetLibraryController extends Controller
{
    private function trial(string $token): TrialGeneration
    {
        return TrialGeneration::query()->where('token', $token)->where('status', 'ready')->whereNull('claimed_at')->firstOrFail();
    }

    public function templates(string $token, TrialLibraryAccessService $access): JsonResponse
    {
        $trial = $this->trial($token);
        $allowed = $access->templateKeys($trial);

        $templates = collect(PageTemplateCatalog::all())
            ->sortBy(function (array $template, int $index) use ($allowed) {
                $position = array_search($template['key'], $allowed, true);
                return $position === false ? 10000 + $index : $position;
            })
            ->map(function (array $template) use ($allowed) {
                $isUnlocked = in_array($template['key'], $allowed, true);

                return [...$template,
                    'credits' => $isUnlocked ? 0 : (int) ($template['credits'] ?? 0),
                    'personalize_credits' => PageTemplateCatalog::PERSONALIZE_CREDITS,
                    'owned' => $isUnlocked,
                    'purchased' => false,
                    'source' => $isUnlocked ? 'trial_curated' : 'trial_locked',
                    'template_type' => 'page',
                    'status' => 'active',
                    'saved' => false,
                    'favorited' => false,
                    'trial_curated' => $isUnlocked,
                    'trial_locked' => ! $isUnlocked,
                    'can_preview' => true,
                    'can_install' => $isUnlocked,
                ];
            })
            ->values();

        return response()->json([
            'templates' => $templates,
            'guest' => true,
            'curated' => true,
            'credit_balance' => app(TrialCreditService::class)->balance($trial),
        ]);
    }

    public function unlockTemplate(string $token, string $key, TrialCreditService $credits, TrialLibraryAccessService $access): JsonResponse
    {
        abort_unless(PageTemplateCatalog::find($key), 404); $trial = $this->trial($token);
        abort_unless($access->allowsTemplate($trial, $key), 404);
        return DB::transaction(function () use ($trial, $key, $credits) {
            $locked = TrialGeneration::query()->lockForUpdate()->findOrFail($trial->id); $owned = collect($locked->owned_templates ?? []);
            if ($owned->contains($key)) return response()->json(['owned' => true, 'message' => 'Template is already owned.', 'credit_balance' => $credits->balance($locked)]);
            $balance = $credits->balance($locked);
            $locked->forceFill(['owned_templates' => $owned->push($key)->unique()->values()->all()])->save();
            return response()->json(['owned' => true, 'message' => 'Template is available in your curated trial library.', 'credit_balance' => $balance]);
        }, 3);
    }

    public function favoriteTemplate(string $token, string $key, TrialLibraryAccessService $access): JsonResponse
    {
        $trial = $this->trial($token);
        abort_unless(PageTemplateCatalog::find($key) && $access->allowsTemplate($trial, $key), 404);
        return response()->json(['favorited' => false, 'message' => 'Favorites are available after signup.']);
    }

    public function sparks(string $token, TrialLibraryAccessService $access): JsonResponse
    {
        $trial = $this->trial($token);
        $allowed = $access->sparkKeys($trial);

        $sparks = collect(SparkCatalog::all())
            ->sortBy(function (array $spark, int $index) use ($allowed) {
                $position = array_search($spark['key'], $allowed, true);
                return $position === false ? 10000 + $index : $position;
            })
            ->map(function (array $spark) use ($allowed) {
                $isUnlocked = in_array($spark['key'], $allowed, true);

                return [...$spark,
                    'credits' => $isUnlocked ? 0 : (int) ($spark['credits'] ?? 0),
                    'owned' => $isUnlocked,
                    'purchased' => false,
                    'favorited' => false,
                    'shared' => false,
                    'can_preview' => true,
                    'can_install' => $isUnlocked,
                    'trial_curated' => $isUnlocked,
                    'trial_locked' => ! $isUnlocked,
                    'usage_state' => $isUnlocked
                        ? ['actionLabel' => 'Add to Page']
                        : ['action' => 'signup', 'actionLabel' => 'Sign up to unlock'],
                ];
            })
            ->values();

        return response()->json([
            'sparks' => $sparks,
            'guest' => true,
            'curated' => true,
            'credit_balance' => app(TrialCreditService::class)->balance($trial),
        ]);
    }

    public function unlockSpark(string $token, string $key, TrialCreditService $credits, TrialLibraryAccessService $access): JsonResponse
    {
        $spark = SparkCatalog::find($key); abort_unless($spark, 404); $trial = $this->trial($token);
        abort_unless($access->allowsSpark($trial, $key), 404);
        return DB::transaction(function () use ($trial, $key, $spark, $credits) {
            $locked = TrialGeneration::query()->lockForUpdate()->findOrFail($trial->id); $owned = collect($locked->owned_sparks ?? []);
            if ($owned->contains($key)) return response()->json(['owned' => true, 'message' => 'Spark is already owned.', 'credit_balance' => $credits->balance($locked)]);
            $price = 0; $balance = $credits->balance($locked);
            $locked->forceFill(['owned_sparks' => $owned->push($key)->unique()->values()->all()])->save();
            return response()->json(['owned' => true, 'message' => 'Spark is available in your curated trial library.', 'credit_balance' => $balance]);
        }, 3);
    }

    public function favoriteSpark(string $token, string $key, TrialLibraryAccessService $access): JsonResponse
    {
        $trial = $this->trial($token);
        abort_unless(SparkCatalog::find($key) && $access->allowsSpark($trial, $key), 404);
        return response()->json(['favorited' => false, 'message' => 'Favorites are available after signup.']);
    }

    private function toggle(TrialGeneration $trial, string $field, string $key): JsonResponse
    {
        $items = collect($trial->{$field} ?? []); $favorited = ! $items->contains($key);
        $items = $favorited ? $items->push($key)->unique() : $items->reject(fn ($v) => $v === $key);
        $trial->forceFill([$field => $items->values()->all()])->save();
        return response()->json(['favorited' => $favorited, 'message' => $favorited ? 'Added to Favorites.' : 'Removed from Favorites.']);
    }
}
