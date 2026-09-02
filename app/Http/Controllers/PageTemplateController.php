<?php

namespace App\Http\Controllers;

use App\Models\CosmicTemplateFavorite;
use App\Models\CosmicUnlock;
use App\Models\User;
use App\Models\SavedPageTemplate;
use App\Services\CreditService;
use App\Services\PageTemplateCatalog;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PageTemplateController extends Controller
{
    public function catalog(Request $request, PlanEntitlementService $entitlements): JsonResponse
    {
        $purchased = $request->user()->cosmicUnlocks()->where('unlock_type', 'template')->get()->keyBy('unlock_key');
        $favorites = $request->user()->templateFavorites()->pluck('template_key');

        $marketplace = collect(PageTemplateCatalog::all())->map(function ($template) use ($purchased, $favorites, $entitlements, $request) {
            $unlock = $purchased->get($template['key']);
            // Template ownership is permanent once an unlock record exists.
            // `is_installed` is a workspace state used by Sparks and must not make a paid Template look unowned.
            $isPurchased = (bool) $unlock;
            $access = $entitlements->pageTemplateAccess($request->user(), (string) $template['key'], $template);
            $upgrade = (array) ($access['upgrade'] ?? []);
            $planLocked = ! $isPurchased && ! (bool) ($access['allowed'] ?? false);
            $upgradeUrl = $planLocked && ! empty($upgrade['plan_key'])
                ? '/credits?'.http_build_query([
                    'family' => $upgrade['family'] ?? 'personal',
                    'plan' => $upgrade['plan_key'],
                    'source' => 'template',
                ])
                : null;

            return [...$template,
                'credits' => PageTemplateCatalog::price($template['key']),
                'personalize_credits' => PageTemplateCatalog::PERSONALIZE_CREDITS,
                // Keep `owned` during the transition so existing install logic remains backwards-compatible.
                'owned' => $isPurchased,
                'purchased' => $isPurchased,
                'favorited' => $favorites->contains($template['key']),
                'source' => 'marketplace',
                'template_type' => 'page',
                'status' => 'active',
                'saved' => false,
                'plan_locked' => $planLocked,
                'can_purchase' => ! $planLocked,
                'access' => $access,
                'upgrade_url' => $upgradeUrl,
                'upgrade_label' => $planLocked
                    ? 'Upgrade to '.(string) ($upgrade['label'] ?? ucfirst((string) ($template['access_level'] ?? 'pro')))
                    : null,
            ];
        });

        // Patch 1 foundation: Saved Templates are first-class library records now.
        // Patch 2 will add the Builder action that creates these records.
        $saved = $request->user()->savedPageTemplates()
            ->where('status', SavedPageTemplate::STATUS_ACTIVE)
            ->where('template_type', SavedPageTemplate::TYPE_PAGE)
            ->latest('updated_at')
            ->get()
            ->map(fn (SavedPageTemplate $template) => $this->savedTemplatePayload($template));

        return response()->json([
            'templates' => $marketplace->concat($saved)->values(),
            'purchase_credits' => PageTemplateCatalog::PURCHASE_CREDITS,
            'personalize_credits' => PageTemplateCatalog::PERSONALIZE_CREDITS,
        ]);
    }

    public function storeSaved(Request $request): JsonResponse
    {
        $data = $request->validate([
            'website_id' => ['required', 'integer', 'exists:websites,id'],
            'page_id' => ['required', 'integer', 'exists:pages,id'],
            'name' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:500'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'blocks' => ['required', 'array', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ]);

        $website = $request->user()->websites()->whereKey($data['website_id'])->firstOrFail();
        $page = $website->pages()->whereKey($data['page_id'])->firstOrFail();

        $baseSlug = Str::slug($data['name']) ?: 'saved-template';
        $slug = $baseSlug;
        $suffix = 2;
        while ($request->user()->savedPageTemplates()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        $metadata = array_merge($data['metadata'] ?? [], [
            'source_page_id' => $page->id,
            'source_page_title' => $page->title,
            'source_page_slug' => $page->slug,
            'saved_from' => 'builder',
        ]);

        $template = $request->user()->savedPageTemplates()->create([
            'website_id' => $website->id,
            'name' => trim($data['name']),
            'slug' => $slug,
            'description' => $data['description'] ?? 'Saved from Cosmic Builder.',
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'source' => SavedPageTemplate::SOURCE_SAVED,
            'template_type' => SavedPageTemplate::TYPE_PAGE,
            'status' => SavedPageTemplate::STATUS_ACTIVE,
            'blocks' => array_values($data['blocks']),
            'metadata' => $metadata,
        ]);

        return response()->json([
            'message' => $template->name.' saved to Saved Templates.',
            'template' => [
                'id' => $template->id,
                'key' => 'saved-'.$template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'source' => $template->source,
                'template_type' => $template->template_type,
                'status' => $template->status,
            ],
        ], 201);
    }

    public function updateSaved(Request $request, SavedPageTemplate $template): JsonResponse
    {
        abort_unless($template->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $name = trim($data['name']);
        $slug = $this->uniqueSavedSlug($request, $name, $template->id);

        $template->update([
            'name' => $name,
            'slug' => $slug,
            'description' => array_key_exists('description', $data) ? $data['description'] : $template->description,
        ]);

        return response()->json([
            'message' => $template->name.' updated.',
            'template' => $this->savedTemplatePayload($template->fresh()),
        ]);
    }

    public function duplicateSaved(Request $request, SavedPageTemplate $template): JsonResponse
    {
        abort_unless($template->user_id === $request->user()->id, 404);
        abort_unless($template->status === SavedPageTemplate::STATUS_ACTIVE, 404);

        $name = $this->uniqueSavedName($request, $template->name.' Copy');
        $copy = $request->user()->savedPageTemplates()->create([
            'website_id' => $template->website_id,
            'content_type_id' => $template->content_type_id,
            'name' => $name,
            'slug' => $this->uniqueSavedSlug($request, $name),
            'description' => $template->description,
            'thumbnail_url' => $template->thumbnail_url,
            'source' => SavedPageTemplate::SOURCE_SAVED,
            'template_type' => $template->template_type ?: SavedPageTemplate::TYPE_PAGE,
            'status' => SavedPageTemplate::STATUS_ACTIVE,
            'blocks' => array_values($template->blocks ?: []),
            'markup' => $template->markup,
            'metadata' => array_merge($template->metadata ?: [], [
                'duplicated_from_template_id' => $template->id,
                'duplicated_at' => now()->toIso8601String(),
            ]),
        ]);

        return response()->json([
            'message' => $copy->name.' added to Saved Templates.',
            'template' => $this->savedTemplatePayload($copy),
        ], 201);
    }

    public function destroySaved(Request $request, SavedPageTemplate $template): JsonResponse
    {
        abort_unless($template->user_id === $request->user()->id, 404);

        $name = $template->name;
        // Archive instead of hard deleting so a future recovery/history feature can restore it safely.
        $template->update(['status' => SavedPageTemplate::STATUS_ARCHIVED]);

        return response()->json([
            'message' => $name.' removed from Saved Templates.',
        ]);
    }

    private function uniqueSavedName(Request $request, string $base): string
    {
        $name = $base;
        $suffix = 2;
        while ($request->user()->savedPageTemplates()->where('name', $name)->where('status', SavedPageTemplate::STATUS_ACTIVE)->exists()) {
            $name = $base.' '.$suffix++;
        }
        return $name;
    }

    private function uniqueSavedSlug(Request $request, string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'saved-template';
        $slug = $baseSlug;
        $suffix = 2;

        while ($request->user()->savedPageTemplates()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }

    private function savedTemplatePayload(SavedPageTemplate $template): array
    {
        $blocks = collect($template->blocks ?: [])->values();

        return [
            'key' => 'saved-'.$template->id,
            'saved_template_id' => $template->id,
            'name' => $template->name,
            'description' => $template->description ?: 'Saved from Cosmic Builder.',
            'tags' => collect($template->metadata['tags'] ?? ['Saved'])->values()->all(),
            'featured' => false,
            'sections' => $blocks->pluck('type')->filter()->values()->all(),
            'blocks' => $blocks->all(),
            'thumbnail_url' => $template->thumbnail_url,
            'credits' => 0,
            'personalize_credits' => PageTemplateCatalog::PERSONALIZE_CREDITS,
            'owned' => true,
            'purchased' => false,
            'favorited' => false,
            'source' => $template->source ?: SavedPageTemplate::SOURCE_SAVED,
            'template_type' => $template->template_type ?: SavedPageTemplate::TYPE_PAGE,
            'status' => $template->status ?: SavedPageTemplate::STATUS_ACTIVE,
            'saved' => true,
            'access_level' => 'starter',
            'access_label' => 'Saved',
            'plan_locked' => false,
            'can_purchase' => true,
            'upgrade_url' => null,
            'upgrade_label' => null,
        ];
    }

    public function unlock(Request $request, string $key, CreditService $credits, PlanEntitlementService $entitlements): JsonResponse
    {
        $template = PageTemplateCatalog::find($key); abort_unless($template, 404);
        return DB::transaction(function () use ($request, $key, $template, $credits, $entitlements) {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = $user->cosmicUnlocks()->where('unlock_type', 'template')->where('unlock_key', $key)->lockForUpdate()->first();
            if ($existing) {
                return response()->json([
                    'message' => 'Template is already purchased.',
                    'owned' => true,
                    'purchased' => true,
                    'credits_charged' => 0,
                    'credit_balance' => $credits->balance($user),
                ]);
            }

            $access = $entitlements->pageTemplateAccess($user, $key);
            if (! (bool) ($access['allowed'] ?? false)) {
                $upgrade = (array) ($access['upgrade'] ?? []);
                return response()->json([
                    'message' => $access['message'] ?? 'Upgrade your plan to purchase this Template.',
                    'reason' => 'template_access_level',
                    'required_level' => $access['required_level'] ?? ($template['access_level'] ?? 'pro'),
                    'upgrade' => $upgrade ?: null,
                    'upgrade_url' => ! empty($upgrade['plan_key'])
                        ? '/credits?'.http_build_query(['family' => $upgrade['family'] ?? 'personal', 'plan' => $upgrade['plan_key'], 'source' => 'template'])
                        : null,
                ], 403);
            }

            $price = PageTemplateCatalog::price($key);
            $tx = $credits->consume($user, $price, 'Purchased Template: '.$template['name'], null, 'template-'.$key.'-'.uniqid(), ['template_key' => $key, 'product_type' => 'template_purchase']);
            CosmicUnlock::create(['user_id' => $user->id, 'unlock_type' => 'template', 'unlock_key' => $key, 'credits_paid' => $price, 'is_installed' => true]);
            return response()->json([
                'message' => $template['name'].' purchased and added to Purchased Templates.',
                'owned' => true,
                'purchased' => true,
                'credits_charged' => $price,
                'credit_balance' => (int) $tx->balance_after,
            ]);
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
