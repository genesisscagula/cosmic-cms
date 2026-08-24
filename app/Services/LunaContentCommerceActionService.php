<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\CommerceProduct;
use App\Models\CommerceProductCategory;
use App\Models\CommerceProductVariant;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LunaContentCommerceActionService
{
    public function __construct(
        private readonly CommerceCapabilityService $commerceCapabilities,
        private readonly CommerceInventoryService $inventory,
    ) {}

    /**
     * Deterministic Batch 5 executor for post and commerce data actions.
     * Returns null when the request belongs to the visual Builder planner or an AI generator.
     */
    public function apply(Website $website, ?Page $page, string $prompt, array $intent, ?int $userId = null): ?array
    {
        if (($intent['intent'] ?? '') !== 'action') return null;

        $domain = Str::lower((string) ($intent['domain'] ?? ''));
        if (! in_array($domain, ['post', 'commerce'], true)) return null;

        $action = Str::lower((string) ($intent['action'] ?? 'update'));
        $operation = Str::lower((string) ($intent['operation'] ?? 'update'));

        // AI authored long-form copy still belongs to the existing metered generators.
        if (preg_match('/\b(?:write|generate|draft)\b.*\b(?:article|blog post|full post|product description|long description)\b/i', $prompt)) {
            return null;
        }

        return $domain === 'post'
            ? $this->applyPost($website, $page, $prompt, $intent, $action, $operation)
            : $this->applyCommerce($website, $prompt, $intent, $action, $operation, $userId);
    }

    private function applyPost(Website $website, ?Page $page, string $prompt, array $intent, string $action, string $operation): ?array
    {
        $changes = $this->changes($intent);
        $target = $this->targetText($intent);
        $blogPage = ($page && $page->page_type === 'blog')
            ? $page
            : $website->pages()->where('page_type', 'blog')->orderBy('id')->first();

        if (in_array($operation, ['add', 'create'], true) || preg_match('/\b(?:add|create)\b.*\b(?:post|blog post)\b/i', $prompt)) {
            if (! $blogPage) return $this->failure('post', 'A Posts / Updates page is required before creating a post.');

            $title = $this->stringChange($changes, ['title', 'post_title', 'name']) ?: $this->extractNamedValue($prompt, ['titled', 'title', 'called', 'named']);
            if (! $title) return $this->failure('post', 'A post title is required.');

            $slug = Str::slug($this->stringChange($changes, ['slug']) ?: $title) ?: 'post';
            $slug = $this->uniquePostSlug($website, $slug);
            $status = $this->normalizedPostStatus($this->stringChange($changes, ['status']) ?: (preg_match('/\bpublish(?:ed)?\b/i', $prompt) ? 'published' : 'draft'));
            $tags = $this->arrayChange($changes, ['tags']);
            $category = $this->stringChange($changes, ['category']);

            $post = $website->blogPosts()->create([
                'page_id' => $blogPage->id,
                'title' => Str::limit($title, 180, ''),
                'slug' => $slug,
                'excerpt' => Str::limit((string) ($this->stringChange($changes, ['excerpt']) ?? ''), 500, ''),
                'content' => Str::limit((string) ($this->stringChange($changes, ['content']) ?? ''), 20000, ''),
                'category' => $category ? Str::limit($category, 80, '') : null,
                'tags' => $tags !== [] ? array_slice($tags, 0, 12) : [],
                'image_url' => $this->stringChange($changes, ['image_url', 'featured_image_url']),
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
            ]);

            return $this->success('post', [[
                'action' => 'create', 'target' => 'post', 'id' => $post->id,
                'value' => $post->title, 'verified' => true,
            ]], ['post_id' => $post->id]);
        }

        $post = $this->resolvePost($website, $intent, $prompt, $target);
        if (! $post) return $this->failure('post', 'A matching post could not be resolved.');

        if ($action === 'delete' || $operation === 'delete') {
            $id = $post->id; $title = $post->title;
            $post->delete();
            return $this->success('post', [[
                'action' => 'delete', 'target' => 'post', 'id' => $id,
                'value' => $title, 'verified' => ! BlogPost::query()->whereKey($id)->exists(),
            ]]);
        }

        $updates = [];
        foreach (['title', 'excerpt', 'content', 'category', 'image_url'] as $field) {
            $value = $this->stringChange($changes, [$field, $field === 'image_url' ? 'featured_image_url' : $field]);
            if ($value !== null) $updates[$field] = $value;
        }
        if (($tags = $this->arrayChange($changes, ['tags'])) !== []) $updates['tags'] = array_slice($tags, 0, 12);
        if (($status = $this->stringChange($changes, ['status'])) !== null) $updates['status'] = $this->normalizedPostStatus($status);
        if (($slug = $this->stringChange($changes, ['slug'])) !== null) $updates['slug'] = $this->uniquePostSlug($website, Str::slug($slug) ?: $post->slug, $post->id);

        if ($updates === []) {
            if (preg_match('/\bpublish\b/i', $prompt)) $updates['status'] = 'published';
            elseif (preg_match('/\b(?:unpublish|draft)\b/i', $prompt)) $updates['status'] = 'draft';
            if (preg_match('/\bcategory\s+(?:to|as|:)\s*["“]?([^"”]+)["”]?$/i', trim($prompt), $m)) $updates['category'] = trim($m[1]);
        }
        if ($updates === []) return null;

        if (isset($updates['title'])) $updates['title'] = Str::limit((string) $updates['title'], 180, '');
        if (isset($updates['excerpt'])) $updates['excerpt'] = Str::limit((string) $updates['excerpt'], 500, '');
        if (isset($updates['content'])) $updates['content'] = Str::limit((string) $updates['content'], 20000, '');
        if (isset($updates['category'])) $updates['category'] = Str::limit((string) $updates['category'], 80, '');

        $post->fill($updates);
        if (array_key_exists('status', $updates)) {
            $post->published_at = $updates['status'] === 'published' ? ($post->published_at ?? now()) : null;
        }
        $post->save();

        return $this->success('post', collect($updates)->map(fn ($value, $field) => [
            'action' => 'update', 'target' => 'post.'.$field, 'id' => $post->id,
            'value' => $value, 'verified' => data_get($post->fresh()->toArray(), $field) == $value,
        ])->values()->all(), ['post_id' => $post->id]);
    }

    private function applyCommerce(Website $website, string $prompt, array $intent, string $action, string $operation, ?int $userId): ?array
    {
        $owner = $website->user;
        if (! $owner || ! $this->commerceCapabilities->planAllowsCommerce($owner)) {
            return $this->failure('commerce', 'Ecommerce is unavailable on the current plan.');
        }

        $changes = $this->changes($intent);
        $target = $this->targetText($intent);

        $variantId = data_get($changes, 'variant_id');
        if (!is_numeric($variantId) && Str::contains(Str::lower((string)data_get($intent, 'target.type', '')), 'variant')) {
            $variantId = data_get($intent, 'target.id');
        }
        if (is_numeric($variantId)) {
            $variant = CommerceProductVariant::query()->where('website_id', $website->id)->whereKey((int)$variantId)->first();
            if (!$variant) return $this->failure('commerce', 'A matching product variant could not be resolved.');
            $variantUpdates = [];
            if (($sku = $this->stringChange($changes, ['sku'])) !== null) $variantUpdates['sku'] = $sku;
            if (($stock = $this->intChange($changes, ['stock_quantity', 'stock'])) !== null) $variantUpdates['stock_quantity'] = max(0, $stock);
            if (($track = $this->boolChange($changes, ['track_inventory'])) !== null) $variantUpdates['track_inventory'] = $track;
            if (($backorders = $this->boolChange($changes, ['allow_backorders'])) !== null) $variantUpdates['allow_backorders'] = $backorders;
            if (($enabled = $this->boolChange($changes, ['is_enabled', 'enabled'])) !== null) $variantUpdates['is_enabled'] = $enabled;
            $settings = $this->commerceCapabilities->settingsFor($website);
            if (($price = $this->priceMinor($changes, $prompt, $settings->currency)) !== null) $variantUpdates['regular_price_minor'] = $price;
            if (($sale = $this->priceMinor($changes, '', $settings->currency, 'sale_price')) !== null) $variantUpdates['sale_price_minor'] = $sale;
            if ($action === 'delete' || $operation === 'delete') {
                $hasHistory = DB::table('commerce_order_items')->where('commerce_product_variant_id', $variant->id)->exists()
                    || DB::table('commerce_inventory_adjustments')->where('commerce_product_variant_id', $variant->id)->exists();
                if ($hasHistory) {
                    $variant->forceFill(['is_enabled' => false, 'is_default' => false])->save();
                    return $this->success('commerce', [[
                        'action'=>'update','target'=>'commerce.variant.is_enabled','id'=>$variant->id,'value'=>false,'verified'=>true,
                    ]], ['variant_id'=>$variant->id]);
                }
                $id=$variant->id; $variant->delete();
                return $this->success('commerce', [[
                    'action'=>'delete','target'=>'commerce.variant','id'=>$id,'verified'=>!CommerceProductVariant::query()->whereKey($id)->exists(),
                ]], ['variant_id'=>$id]);
            }
            if ($variantUpdates === []) return null;
            $track=(bool)($variantUpdates['track_inventory'] ?? $variant->track_inventory);
            $qty=array_key_exists('stock_quantity',$variantUpdates)?(int)$variantUpdates['stock_quantity']:$variant->stock_quantity;
            $backorders=(bool)($variantUpdates['allow_backorders'] ?? $variant->allow_backorders);
            $variantUpdates['stock_status']=$this->inventory->normalizedStatus($track,$qty,$backorders,(string)$variant->stock_status);
            $variant->forceFill($variantUpdates)->save();
            return $this->success('commerce', collect($variantUpdates)->map(fn($value,$field)=>[
                'action'=>'update','target'=>'commerce.variant.'.$field,'id'=>$variant->id,'value'=>$value,'verified'=>true,
            ])->values()->all(), ['variant_id'=>$variant->id]);
        }

        $categoryTarget = Str::contains(Str::lower($target), ['category', 'collection'])
            || preg_match('/\b(?:add|create|update|rename|delete|remove)\b.*\b(?:product category|category|collection)\b/i', $prompt);
        $productRequest = preg_match('/\b(?:add|create|update|edit|change|delete|remove)\b.*\bproduct\b/i', $prompt);
        if ($categoryTarget && ! $productRequest) {
            return $this->applyProductCategory($website, $prompt, $intent, $action, $operation, $changes);
        }

        if (in_array($operation, ['add', 'create'], true) || preg_match('/\b(?:add|create)\b.*\bproduct\b/i', $prompt)) {
            $title = $this->stringChange($changes, ['title', 'product_title', 'name']) ?: $this->extractNamedValue($prompt, ['titled', 'title', 'called', 'named', 'product']);
            if (! $title) return $this->failure('commerce', 'A product title is required.');

            $settings = $this->commerceCapabilities->settingsFor($website);
            $priceMinor = $this->priceMinor($changes, $prompt, $settings->currency);
            $slug = $this->uniqueProductSlug($website, Str::slug($this->stringChange($changes, ['slug']) ?: $title) ?: 'product');
            $status = $this->normalizedProductStatus($this->stringChange($changes, ['status']) ?: 'draft');
            $type = Str::lower((string) ($this->stringChange($changes, ['type', 'product_type']) ?: 'simple')) === 'variable' ? 'variable' : 'simple';
            $sku = $this->stringChange($changes, ['sku']);
            if ($sku && $website->commerceProducts()->where('sku', $sku)->exists()) return $this->failure('commerce', 'That SKU is already used by another product on this website.');
            $saleMinor = $this->priceMinor($changes, '', $settings->currency, 'sale_price');
            if ($saleMinor !== null && $priceMinor !== null && $saleMinor > $priceMinor) return $this->failure('commerce', 'Sale price cannot be higher than the regular price.');

            $product = $website->commerceProducts()->create([
                'type' => $type,
                'fulfillment_type' => $this->stringChange($changes, ['fulfillment_type']) === 'digital' ? 'digital' : 'physical',
                'status' => $status,
                'visibility' => 'catalog',
                'title' => Str::limit($title, 255, ''),
                'slug' => $slug,
                'short_description' => $this->stringChange($changes, ['short_description']),
                'description' => $this->stringChange($changes, ['description']),
                'regular_price_minor' => $priceMinor,
                'sale_price_minor' => $saleMinor,
                'sku' => $sku,
                'track_inventory' => $this->boolChange($changes, ['track_inventory']) ?? false,
                'stock_quantity' => $this->intChange($changes, ['stock_quantity', 'stock']),
                'allow_backorders' => $this->boolChange($changes, ['allow_backorders']) ?? false,
                'stock_status' => CommerceProduct::STOCK_IN_STOCK,
                'featured_image_url' => $this->stringChange($changes, ['featured_image_url', 'image_url']),
                'is_featured' => $this->boolChange($changes, ['is_featured']) ?? false,
                'published_at' => $status === CommerceProduct::STATUS_PUBLISHED ? now() : null,
            ]);
            if ($product->track_inventory) {
                $product->forceFill(['stock_status' => $this->inventory->normalizedStatus(true, $product->stock_quantity, (bool) $product->allow_backorders)])->save();
            }
            $this->assignNamedCategories($website, $product, $changes);

            return $this->success('commerce', [[
                'action' => 'create', 'target' => 'commerce.product', 'id' => $product->id,
                'value' => $product->title, 'verified' => true,
            ]], ['product_id' => $product->id]);
        }

        $product = $this->resolveProduct($website, $intent, $prompt, $target);
        if (! $product) return $this->failure('commerce', 'A matching product could not be resolved.');

        if ($action === 'delete' || $operation === 'delete') {
            $id = $product->id; $title = $product->title;
            $product->delete();
            return $this->success('commerce', [[
                'action' => 'delete', 'target' => 'commerce.product', 'id' => $id,
                'value' => $title, 'verified' => ! CommerceProduct::query()->whereKey($id)->exists(),
            ]]);
        }

        $updates = [];
        $map = [
            'title' => ['title', 'product_title', 'name'], 'slug' => ['slug'], 'sku' => ['sku'],
            'short_description' => ['short_description'], 'description' => ['description'],
            'featured_image_url' => ['featured_image_url', 'image_url'], 'seo_title' => ['seo_title'],
            'seo_description' => ['seo_description'], 'shipping_class' => ['shipping_class'],
        ];
        foreach ($map as $field => $keys) if (($value = $this->stringChange($changes, $keys)) !== null) $updates[$field] = $value;
        if (($status = $this->stringChange($changes, ['status'])) !== null) $updates['status'] = $this->normalizedProductStatus($status);
        foreach ([
            'track_inventory' => ['track_inventory'], 'allow_backorders' => ['allow_backorders'],
            'is_featured' => ['is_featured'], 'taxable' => ['taxable'],
        ] as $field => $keys) if (($value = $this->boolChange($changes, $keys)) !== null) $updates[$field] = $value;
        foreach ([
            'stock_quantity' => ['stock_quantity', 'stock'], 'low_stock_threshold' => ['low_stock_threshold'],
            'weight_grams' => ['weight_grams'], 'length_mm' => ['length_mm'], 'width_mm' => ['width_mm'], 'height_mm' => ['height_mm'],
        ] as $field => $keys) if (($value = $this->intChange($changes, $keys)) !== null) $updates[$field] = $value;

        $settings = $this->commerceCapabilities->settingsFor($website);
        if (($price = $this->priceMinor($changes, $prompt, $settings->currency)) !== null) $updates['regular_price_minor'] = $price;
        if (($sale = $this->priceMinor($changes, '', $settings->currency, 'sale_price')) !== null) $updates['sale_price_minor'] = $sale;
        if (isset($updates['sku']) && $updates['sku'] !== '' && $website->commerceProducts()->where('sku', $updates['sku'])->whereKeyNot($product->id)->exists()) {
            return $this->failure('commerce', 'That SKU is already used by another product on this website.');
        }
        $effectiveRegular = $updates['regular_price_minor'] ?? $product->regular_price_minor;
        $effectiveSale = array_key_exists('sale_price_minor', $updates) ? $updates['sale_price_minor'] : $product->sale_price_minor;
        if ($effectiveSale !== null && $effectiveRegular !== null && $effectiveSale > $effectiveRegular) {
            return $this->failure('commerce', 'Sale price cannot be higher than the regular price.');
        }

        if ($updates === []) {
            if (preg_match('/\bpublish\b/i', $prompt)) $updates['status'] = CommerceProduct::STATUS_PUBLISHED;
            elseif (preg_match('/\barchive\b/i', $prompt)) $updates['status'] = CommerceProduct::STATUS_ARCHIVED;
            elseif (preg_match('/\b(?:unpublish|draft)\b/i', $prompt)) $updates['status'] = CommerceProduct::STATUS_DRAFT;
            if (preg_match('/\bstock\s+(?:to|as|:)\s*(\d+)/i', $prompt, $m)) $updates['stock_quantity'] = (int) $m[1];
        }

        $ops = [];
        if ($updates !== []) {
            DB::transaction(function () use ($product, $updates, &$ops): void {
                if (isset($updates['slug'])) $updates['slug'] = Str::slug((string) $updates['slug']) ?: $product->slug;
                if (isset($updates['title'])) $updates['title'] = Str::limit((string) $updates['title'], 255, '');
                $product->fill($updates);
                if (array_key_exists('status', $updates)) {
                    $product->published_at = $updates['status'] === CommerceProduct::STATUS_PUBLISHED ? ($product->published_at ?? now()) : null;
                }
                if (array_key_exists('stock_quantity', $updates) || array_key_exists('track_inventory', $updates) || array_key_exists('allow_backorders', $updates)) {
                    $track = (bool) ($updates['track_inventory'] ?? $product->track_inventory);
                    $qty = array_key_exists('stock_quantity', $updates) ? (int) $updates['stock_quantity'] : $product->stock_quantity;
                    $backorders = (bool) ($updates['allow_backorders'] ?? $product->allow_backorders);
                    $product->stock_status = $this->inventory->normalizedStatus($track, $qty, $backorders, (string) $product->stock_status);
                }
                $product->save();
                foreach ($updates as $field => $value) $ops[] = [
                    'action' => 'update', 'target' => 'commerce.product.'.$field,
                    'id' => $product->id, 'value' => $value, 'verified' => true,
                ];
            });
        }

        $categoryOps = $this->assignNamedCategories($website, $product, $changes);
        $ops = [...$ops, ...$categoryOps];
        if ($ops === []) return null;

        return $this->success('commerce', $ops, ['product_id' => $product->id]);
    }

    private function applyProductCategory(Website $website, string $prompt, array $intent, string $action, string $operation, array $changes): ?array
    {
        if (in_array($operation, ['add', 'create'], true) || preg_match('/\b(?:add|create)\b.*\b(?:category|collection)\b/i', $prompt)) {
            $name = $this->stringChange($changes, ['name', 'category', 'category_name']) ?: $this->extractNamedValue($prompt, ['category', 'collection', 'called', 'named']);
            if (! $name) return $this->failure('commerce', 'A category name is required.');
            $slug = Str::slug($this->stringChange($changes, ['slug']) ?: $name) ?: 'category';
            $base = $slug; $n = 2;
            while ($website->commerceProductCategories()->where('slug', $slug)->exists()) $slug = $base.'-'.$n++;
            $category = $website->commerceProductCategories()->create([
                'name' => Str::limit($name, 180, ''), 'slug' => $slug,
                'description' => $this->stringChange($changes, ['description']),
                'is_visible' => $this->boolChange($changes, ['is_visible']) ?? true,
                'sort_order' => max(0, (int) ($this->intChange($changes, ['sort_order']) ?? 0)),
            ]);
            return $this->success('commerce', [[
                'action' => 'create', 'target' => 'commerce.category', 'id' => $category->id,
                'value' => $category->name, 'verified' => true,
            ]], ['category_id' => $category->id]);
        }

        $category = $this->resolveCategory($website, $intent, $prompt);
        if (! $category) return $this->failure('commerce', 'A matching product category could not be resolved.');
        if ($action === 'delete' || $operation === 'delete') {
            $id = $category->id; $name = $category->name;
            $category->delete();
            return $this->success('commerce', [[
                'action' => 'delete', 'target' => 'commerce.category', 'id' => $id,
                'value' => $name, 'verified' => ! CommerceProductCategory::query()->whereKey($id)->exists(),
            ]]);
        }

        $updates = [];
        foreach (['name', 'description', 'seo_title', 'seo_description'] as $field) {
            if (($value = $this->stringChange($changes, [$field, $field === 'name' ? 'category_name' : $field])) !== null) $updates[$field] = $value;
        }
        if (($visible = $this->boolChange($changes, ['is_visible', 'visible'])) !== null) $updates['is_visible'] = $visible;
        if ($updates === []) return null;
        if (isset($updates['name'])) $updates['name'] = Str::limit((string) $updates['name'], 180, '');
        $category->fill($updates)->save();
        return $this->success('commerce', collect($updates)->map(fn ($value, $field) => [
            'action' => 'update', 'target' => 'commerce.category.'.$field, 'id' => $category->id,
            'value' => $value, 'verified' => true,
        ])->values()->all(), ['category_id' => $category->id]);
    }

    private function assignNamedCategories(Website $website, CommerceProduct $product, array $changes): array
    {
        $names = $this->arrayChange($changes, ['categories', 'category_names']);
        $single = $this->stringChange($changes, ['category', 'category_name']);
        if ($single) $names[] = $single;
        $names = array_values(array_unique(array_filter(array_map('trim', $names))));
        if ($names === []) return [];

        $ids = [];
        foreach ($names as $name) {
            $category = $website->commerceProductCategories()->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
            if (! $category) {
                $slugBase = Str::slug($name) ?: 'category'; $slug = $slugBase; $n = 2;
                while ($website->commerceProductCategories()->where('slug', $slug)->exists()) $slug = $slugBase.'-'.$n++;
                $category = $website->commerceProductCategories()->create(['name' => Str::limit($name, 180, ''), 'slug' => $slug, 'is_visible' => true]);
            }
            $ids[$category->id] = ['is_primary' => $ids === [], 'sort_order' => count($ids)];
        }
        $product->categories()->sync($ids);
        return [[
            'action' => 'update', 'target' => 'commerce.product.categories', 'id' => $product->id,
            'value' => $names, 'verified' => true,
        ]];
    }

    private function resolvePost(Website $website, array $intent, string $prompt, string $target): ?BlogPost
    {
        $id = data_get($intent, 'target.id');
        if (is_numeric($id)) {
            $found = $website->blogPosts()->whereKey((int) $id)->first();
            if ($found) return $found;
        }
        $label = trim((string) (data_get($intent, 'target.label') ?: data_get($intent, 'target.key')));
        foreach (array_filter([$label, $this->quoted($prompt), $target]) as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || mb_strlen($candidate) > 180) continue;
            $found = $website->blogPosts()->whereRaw('LOWER(title) = ?', [Str::lower($candidate)])->first();
            if ($found) return $found;
        }
        return null;
    }

    private function resolveProduct(Website $website, array $intent, string $prompt, string $target): ?CommerceProduct
    {
        $id = data_get($intent, 'target.id');
        if (is_numeric($id)) {
            $found = $website->commerceProducts()->whereKey((int) $id)->first();
            if ($found) return $found;
        }
        $label = trim((string) (data_get($intent, 'target.label') ?: data_get($intent, 'target.key')));
        foreach (array_filter([$label, $this->quoted($prompt), $target]) as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || mb_strlen($candidate) > 255) continue;
            $found = $website->commerceProducts()->whereRaw('LOWER(title) = ?', [Str::lower($candidate)])->first();
            if ($found) return $found;
        }
        return null;
    }

    private function resolveCategory(Website $website, array $intent, string $prompt): ?CommerceProductCategory
    {
        $id = data_get($intent, 'target.id');
        if (is_numeric($id)) {
            $found = $website->commerceProductCategories()->whereKey((int) $id)->first();
            if ($found) return $found;
        }
        $label = trim((string) (data_get($intent, 'target.label') ?: data_get($intent, 'target.key') ?: $this->quoted($prompt)));
        if ($label === '') return null;
        return $website->commerceProductCategories()->whereRaw('LOWER(name) = ?', [Str::lower($label)])->first();
    }

    private function changes(array $intent): array
    {
        $changes = is_array($intent['changes'] ?? null) ? $intent['changes'] : [];
        foreach ((array) ($intent['operations'] ?? []) as $operation) {
            if (is_array($operation) && is_array($operation['changes'] ?? null)) $changes = array_replace_recursive($changes, $operation['changes']);
        }
        return $changes;
    }

    private function stringChange(array $changes, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($changes, $key);
            if (is_scalar($value) && trim((string) $value) !== '') return trim((string) $value);
        }
        return null;
    }

    private function intChange(array $changes, array $keys): ?int
    {
        foreach ($keys as $key) { $value = data_get($changes, $key); if (is_numeric($value)) return (int) $value; }
        return null;
    }

    private function boolChange(array $changes, array $keys): ?bool
    {
        foreach ($keys as $key) {
            $value = data_get($changes, $key);
            if (is_bool($value)) return $value;
            if (is_string($value) && in_array(Str::lower($value), ['true', 'yes', 'on', 'enabled'], true)) return true;
            if (is_string($value) && in_array(Str::lower($value), ['false', 'no', 'off', 'disabled'], true)) return false;
        }
        return null;
    }

    private function arrayChange(array $changes, array $keys): array
    {
        foreach ($keys as $key) {
            $value = data_get($changes, $key);
            if (is_array($value)) return array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $value)));
            if (is_string($value) && trim($value) !== '') return array_values(array_filter(array_map('trim', explode(',', $value))));
        }
        return [];
    }

    private function priceMinor(array $changes, string $prompt, string $currency, string $key = 'price'): ?int
    {
        $value = data_get($changes, $key) ?? data_get($changes, $key.'_amount');
        if ($value === null && $key === 'price' && preg_match('/\b(?:price|regular price)\s*(?:to|as|:)?\s*(?:[A-Z]{3}|[$₱£€])?\s*([0-9]+(?:\.[0-9]{1,2})?)/i', $prompt, $m)) $value = $m[1];
        if (! is_numeric($value)) return null;
        $decimals = (int) data_get(config('cosmic-commerce.currencies.'.strtoupper($currency), []), 'decimals', 2);
        return max(0, (int) round(((float) $value) * (10 ** $decimals)));
    }

    private function targetText(array $intent): string
    {
        return trim(implode(' ', array_filter(array_map(fn ($v) => is_scalar($v) ? (string) $v : '', (array) ($intent['target'] ?? [])))));
    }

    private function quoted(string $prompt): ?string
    {
        return preg_match('/["“]([^"”]{1,255})["”]/u', $prompt, $m) ? trim($m[1]) : null;
    }

    private function extractNamedValue(string $prompt, array $markers): ?string
    {
        foreach ($markers as $marker) {
            if (preg_match('/\b'.preg_quote($marker, '/').'\b\s*(?:to|as|:)?\s*["“]([^"”]+)["”]/iu', $prompt, $m)) return trim($m[1]);
        }
        return $this->quoted($prompt);
    }

    private function uniquePostSlug(Website $website, string $base, ?int $exceptId = null): string
    {
        $slug = $base; $n = 2;
        while ($website->blogPosts()->where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) $slug = $base.'-'.$n++;
        return $slug;
    }

    private function uniqueProductSlug(Website $website, string $base, ?int $exceptId = null): string
    {
        $slug = $base; $n = 2;
        while ($website->commerceProducts()->where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) $slug = $base.'-'.$n++;
        return $slug;
    }

    private function normalizedPostStatus(string $status): string { return Str::lower($status) === 'published' ? 'published' : 'draft'; }
    private function normalizedProductStatus(string $status): string
    {
        return in_array(Str::lower($status), ['draft', 'published', 'archived'], true) ? Str::lower($status) : 'draft';
    }

    private function success(string $domain, array $ops, array $extra = []): array
    {
        return ['handled' => true, 'success' => collect($ops)->every(fn ($op) => ($op['verified'] ?? false) === true), 'domain' => $domain, 'operations' => $ops, ...$extra];
    }

    private function failure(string $domain, string $message): array
    {
        return ['handled' => true, 'success' => false, 'domain' => $domain, 'message' => $message, 'operations' => []];
    }
}
