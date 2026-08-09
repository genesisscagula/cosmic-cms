<?php

namespace App\Http\Controllers;

use App\Models\CosmicTemplateFavorite;
use App\Models\CosmicUnlock;
use App\Models\User;
use App\Services\CreditService;
use App\Services\PageTemplateCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PageTemplateController extends Controller
{
    public function catalog(Request $request): JsonResponse
    {
        $owned = $request->user()->cosmicUnlocks()->where('unlock_type', 'template')->get()->keyBy('unlock_key');
        $favorites = $request->user()->templateFavorites()->pluck('template_key');
        $templates = collect(PageTemplateCatalog::all())->map(function ($template) use ($owned, $favorites) {
            $unlock = $owned->get($template['key']);
            return [...$template,
                'credits' => PageTemplateCatalog::PURCHASE_CREDITS,
                'personalize_credits' => PageTemplateCatalog::PERSONALIZE_CREDITS,
                'owned' => (bool) ($unlock?->is_installed),
                'purchased' => (int) ($unlock?->credits_paid ?? 0) > 0,
                'favorited' => $favorites->contains($template['key']),
            ];
        })->values();
        return response()->json(['templates' => $templates, 'purchase_credits' => PageTemplateCatalog::PURCHASE_CREDITS, 'personalize_credits' => PageTemplateCatalog::PERSONALIZE_CREDITS]);
    }

    public function unlock(Request $request, string $key, CreditService $credits): JsonResponse
    {
        $template = PageTemplateCatalog::find($key); abort_unless($template, 404);
        return DB::transaction(function () use ($request, $key, $template, $credits) {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = $user->cosmicUnlocks()->where('unlock_type', 'template')->where('unlock_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if (!$existing->is_installed) $existing->update(['is_installed' => true]);
                return response()->json(['message' => 'Template is already owned.', 'owned' => true, 'credit_balance' => $credits->balance($user)]);
            }
            $price = PageTemplateCatalog::PURCHASE_CREDITS;
            $tx = $credits->consume($user, $price, 'Purchased Template: '.$template['name'], null, 'template-'.$key.'-'.uniqid(), ['template_key' => $key, 'product_type' => 'template_purchase']);
            CosmicUnlock::create(['user_id' => $user->id, 'unlock_type' => 'template', 'unlock_key' => $key, 'credits_paid' => $price, 'is_installed' => true]);
            return response()->json(['message' => $template['name'].' purchased and added to Owned Templates.', 'owned' => true, 'credit_balance' => (int) $tx->balance_after]);
        }, 3);
    }

    public function toggleFavorite(Request $request, string $key): JsonResponse
    {
        abort_unless(PageTemplateCatalog::find($key), 404);
        $favorite = $request->user()->templateFavorites()->where('template_key', $key)->first();
        if ($favorite) { $favorite->delete(); return response()->json(['favorited' => false, 'message' => 'Removed from Favorites.']); }
        CosmicTemplateFavorite::create(['user_id' => $request->user()->id, 'template_key' => $key]);
        return response()->json(['favorited' => true, 'message' => 'Added to Favorites.']);
    }
}
