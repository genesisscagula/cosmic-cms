<?php

namespace App\Http\Controllers;

use App\Models\TrialGeneration;
use App\Services\PageTemplateCatalog;
use App\Services\SparkCatalog;
use App\Services\TrialCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrialAssetLibraryController extends Controller
{
    private function trial(string $token): TrialGeneration
    {
        return TrialGeneration::query()->where('token', $token)->where('status', 'ready')->whereNull('claimed_at')->firstOrFail();
    }

    public function templates(string $token): JsonResponse
    {
        $trial = $this->trial($token);
        $owned = collect($trial->owned_templates ?? []);
        $favorites = collect($trial->favorite_templates ?? []);
        return response()->json(['templates' => collect(PageTemplateCatalog::all())->map(fn ($t) => [...$t,
            'credits' => PageTemplateCatalog::PURCHASE_CREDITS, 'personalize_credits' => PageTemplateCatalog::PERSONALIZE_CREDITS,
            'owned' => $owned->contains($t['key']), 'purchased' => $owned->contains($t['key']),
            'source' => 'marketplace', 'template_type' => 'page', 'status' => 'active', 'saved' => false, 'favorited' => $favorites->contains($t['key']),
        ])->values(), 'guest' => true, 'credit_balance' => app(TrialCreditService::class)->balance($trial)]);
    }

    public function unlockTemplate(string $token, string $key, TrialCreditService $credits): JsonResponse
    {
        abort_unless(PageTemplateCatalog::find($key), 404); $trial = $this->trial($token);
        return DB::transaction(function () use ($trial, $key, $credits) {
            $locked = TrialGeneration::query()->lockForUpdate()->findOrFail($trial->id); $owned = collect($locked->owned_templates ?? []);
            if ($owned->contains($key)) return response()->json(['owned' => true, 'message' => 'Template is already owned.', 'credit_balance' => $credits->balance($locked)]);
            $balance = $credits->consume($locked, PageTemplateCatalog::PURCHASE_CREDITS, 'Purchased Trial Template', ['template_key' => $key]);
            $locked->forceFill(['owned_templates' => $owned->push($key)->unique()->values()->all()])->save();
            return response()->json(['owned' => true, 'message' => 'Template added to your trial library.', 'credit_balance' => $balance]);
        }, 3);
    }

    public function favoriteTemplate(string $token, string $key): JsonResponse
    {
        abort_unless(PageTemplateCatalog::find($key), 404); return $this->toggle($this->trial($token), 'favorite_templates', $key);
    }

    public function sparks(string $token): JsonResponse
    {
        $trial = $this->trial($token); $owned = collect($trial->owned_sparks ?? []); $favorites = collect($trial->favorite_sparks ?? []);
        $sparks = collect(SparkCatalog::all())->map(fn ($s) => [...$s, 'owned' => $owned->contains($s['key']), 'purchased' => $owned->contains($s['key']),
            'favorited' => $favorites->contains($s['key']), 'shared' => false, 'can_preview' => true, 'can_install' => true,
            'usage_state' => ['actionLabel' => $owned->contains($s['key']) ? 'Add to Page' : ((int)$s['credits'] === 0 ? 'Add Free Spark' : 'Add to Owned')],
        ])->values();
        return response()->json(['sparks' => $sparks, 'guest' => true, 'credit_balance' => app(TrialCreditService::class)->balance($trial)]);
    }

    public function unlockSpark(string $token, string $key, TrialCreditService $credits): JsonResponse
    {
        $spark = SparkCatalog::find($key); abort_unless($spark, 404); $trial = $this->trial($token);
        return DB::transaction(function () use ($trial, $key, $spark, $credits) {
            $locked = TrialGeneration::query()->lockForUpdate()->findOrFail($trial->id); $owned = collect($locked->owned_sparks ?? []);
            if ($owned->contains($key)) return response()->json(['owned' => true, 'message' => 'Spark is already owned.', 'credit_balance' => $credits->balance($locked)]);
            $price = max(0, (int) ($spark['credits'] ?? 0)); $balance = $price ? $credits->consume($locked, $price, 'Purchased Trial Spark', ['spark_key' => $key]) : $credits->balance($locked);
            $locked->forceFill(['owned_sparks' => $owned->push($key)->unique()->values()->all()])->save();
            return response()->json(['owned' => true, 'message' => $price ? 'Spark added to your trial library.' : 'Free Spark added to your trial library.', 'credit_balance' => $balance]);
        }, 3);
    }

    public function favoriteSpark(string $token, string $key): JsonResponse
    {
        abort_unless(SparkCatalog::find($key), 404); return $this->toggle($this->trial($token), 'favorite_sparks', $key);
    }

    private function toggle(TrialGeneration $trial, string $field, string $key): JsonResponse
    {
        $items = collect($trial->{$field} ?? []); $favorited = ! $items->contains($key);
        $items = $favorited ? $items->push($key)->unique() : $items->reject(fn ($v) => $v === $key);
        $trial->forceFill([$field => $items->values()->all()])->save();
        return response()->json(['favorited' => $favorited, 'message' => $favorited ? 'Added to Favorites.' : 'Removed from Favorites.']);
    }
}
