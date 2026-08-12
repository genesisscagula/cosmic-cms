<?php

namespace App\Http\Controllers;

use App\Models\CommerceProduct;
use App\Models\CommerceProductCategory;
use App\Models\CommerceProductOption;
use App\Models\CommerceProductOptionValue;
use App\Models\CommerceProductVariant;
use App\Models\Page;
use App\Models\Website;
use App\Services\CommerceCapabilityService;
use App\Services\CommerceProductCategoryService;
use App\Services\CommerceProductVariationService;
use App\Services\CommerceInventoryService;
use App\Services\CommerceInstallerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommerceProductController extends Controller
{
    /** Install or repair commerce pages, optionally with an idempotent demo catalog. */
    public function installPages(Request $request, Website $website, CommerceCapabilityService $commerce, CommerceInstallerService $installer)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['plain', 'demo'])],
            'preset' => ['nullable', Rule::in(['clean', 'premium', 'editorial'])],
        ]);
        $result = $installer->install($website, ($data['mode'] ?? 'plain') === 'demo', $data['preset'] ?? 'premium');
        $message = ($data['mode'] ?? 'plain') === 'demo'
            ? "Commerce ready with {$result['preset']} Shop preset. Demo content added: {$result['demo']['products']} products, {$result['demo']['categories']} categories (existing demo items were kept)."
            : "Commerce pages installed/repaired with {$result['preset']} preset: Shop, Featured Products and Collections are ready as editable Standard Pages.";
        return redirect()->back(303)->with('success', $message);
    }

    /** Upload media used by commerce products, galleries, categories and variants. */
    public function uploadMedia(Request $request, Website $website, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());

        $data = $request->validate([
            'image' => [
                'required',
                'file',
                'max:8192',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/avif,image/heic,image/heif',
            ],
            'kind' => ['nullable', Rule::in(['featured', 'gallery', 'category', 'variation'])],
        ]);

        $file = $data['image'];
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['image' => 'The uploaded image could not be read. Please choose the file again.']);
        }

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif'];
        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages(['image' => 'Use JPG, PNG, WebP, GIF, AVIF, HEIC or HEIF images.']);
        }

        $kind = $data['kind'] ?? 'gallery';
        $filename = $kind.'-'.Str::uuid().'.'.$extension;
        $path = $file->storeAs("websites/{$website->id}/commerce", $filename, 'public');
        $relativeUrl = Storage::disk('public')->url($path);

        return response()->json([
            'url' => rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($relativeUrl, '/'),
            'path' => $path,
            'kind' => $kind,
            'mime_type' => $file->getMimeType(),
        ]);
    }

    public function updateSettings(Request $request, Website $website, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());

        $currencies = array_keys((array) config('cosmic-commerce.currencies', []));
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'currency' => ['required','string', Rule::in($currencies)],
            'paypal_receiver_email' => ['nullable', 'email:rfc', 'max:190'],
        ]);

        $settings = $commerce->settingsFor($website);
        $storeSettings = is_array($settings->settings) ? $settings->settings : [];
        $receiver = strtolower(trim((string) ($data['paypal_receiver_email'] ?? '')));
        if ($receiver === '') {
            unset($storeSettings['paypal_receiver_email']);
        } else {
            $storeSettings['paypal_receiver_email'] = $receiver;
        }

        $settings->update([
            'enabled' => (bool) $data['enabled'],
            'currency' => strtoupper($data['currency']),
            'settings' => $storeSettings,
        ]);

        return redirect()->back(303)->with('success', $settings->enabled ? 'Store enabled.' : 'Store disabled.');
    }

    public function storeCategory(Request $request, Website $website, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'parent_id' => ['nullable','integer', Rule::exists('commerce_product_categories', 'id')->where(fn ($q) => $q->where('website_id', $website->id))],
        ]);
        $base = Str::slug($data['name']) ?: 'category';
        $slug = $base;
        $n = 2;
        while ($website->commerceProductCategories()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }
        $website->commerceProductCategories()->create([
            'name' => trim($data['name']),
            'slug' => $slug,
            'parent_id' => $data['parent_id'] ?? null,
            'is_visible' => true,
            'sort_order' => ((int) $website->commerceProductCategories()->max('sort_order')) + 1,
        ]);

        return redirect()->back(303)->with('success', 'Product category created.');
    }

    public function updateCategory(Request $request, Website $website, CommerceProductCategory $category, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        abort_unless((int) $category->website_id === (int) $website->id, 404);
        $data = $request->validate([
            'name' => 'required|string|max:120', 'slug' => 'required|string|max:140', 'description' => 'nullable|string|max:2000',
            'image_url' => 'nullable|string|max:2048', 'image_alt' => 'nullable|string|max:255', 'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:2000', 'sort_order' => 'nullable|integer|min:0|max:4294967295', 'is_visible' => 'boolean',
        ]);
        $base = Str::slug($data['slug'] ?: $data['name']) ?: 'category'; $slug=$base; $n=2;
        while ($website->commerceProductCategories()->where('slug',$slug)->whereKeyNot($category->id)->exists()) $slug=$base.'-'.$n++;
        $category->update([...$data, 'name'=>trim($data['name']), 'slug'=>$slug, 'description'=>trim((string)($data['description']??'')) ?: null]);
        return redirect()->back(303)->with('success','Product category updated.');
    }

    public function destroyCategory(Request $request, Website $website, CommerceProductCategory $category, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        abort_unless((int) $category->website_id === (int) $website->id, 404);

        $name = $category->name;
        $category->delete();

        return redirect()->back(303)->with('success', "Category {$name} deleted. Product assignments were removed automatically.");
    }

    public function store(Request $request, Website $website, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());

        $data = $this->validatedProduct($request, $website);
        $this->assertCatalogSkuAvailable($website, $data['sku'] ?? null);
        $data['slug'] = $this->uniqueSlug($website, $data['slug'] ?: $data['title']);
        $data['published_at'] = $data['status'] === CommerceProduct::STATUS_PUBLISHED ? now() : null;
        $data['regular_price_minor'] = $this->moneyToMinor($request->input('regular_price'), $website);
        $data['sale_price_minor'] = $this->moneyToMinor($request->input('sale_price'), $website);
        $data['stock_status'] = app(CommerceInventoryService::class)->normalizedStatus((bool) ($data['track_inventory'] ?? false), $data['stock_quantity'] ?? null, (bool) ($data['allow_backorders'] ?? false), (string) ($data['stock_status'] ?? CommerceProduct::STOCK_IN_STOCK));

        $product = $website->commerceProducts()->create($data);
        $this->syncRelations($request, $product);

        return redirect()->back(303)->with('success', 'Product created.');
    }

    public function update(Request $request, Website $website, CommerceProduct $product, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertProductWebsite($website, $product);

        $data = $this->validatedProduct($request, $website, $product);
        $this->assertCatalogSkuAvailable($website, $data['sku'] ?? null, $product->id);
        $data['slug'] = $this->uniqueSlug($website, $data['slug'] ?: $data['title'], $product->id);
        $data['regular_price_minor'] = $this->moneyToMinor($request->input('regular_price'), $website);
        $data['sale_price_minor'] = $this->moneyToMinor($request->input('sale_price'), $website);
        $data['stock_status'] = app(CommerceInventoryService::class)->normalizedStatus((bool) ($data['track_inventory'] ?? false), $data['stock_quantity'] ?? null, (bool) ($data['allow_backorders'] ?? false), (string) ($data['stock_status'] ?? CommerceProduct::STOCK_IN_STOCK));
        if ($data['status'] === CommerceProduct::STATUS_PUBLISHED && ! $product->published_at) {
            $data['published_at'] = now();
        } elseif ($data['status'] !== CommerceProduct::STATUS_PUBLISHED) {
            $data['published_at'] = null;
        }

        $product->update($data);
        $this->syncRelations($request, $product);
        $this->syncVariantEdits($request, $website, $product);

        return redirect()->back(303)->with('success', 'Product saved.');
    }

    public function duplicate(Request $request, Website $website, CommerceProduct $product, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertProductWebsite($website, $product);

        $product->load(['categories', 'images', 'options.values', 'variants.values']);

        DB::transaction(function () use ($website, $product): void {
            $copy = $product->replicate();
            $copy->public_id = null;
            $copy->title = $product->title.' Copy';
            $copy->slug = $this->uniqueSlug($website, $copy->title);
            $copy->status = CommerceProduct::STATUS_DRAFT;
            $copy->published_at = null;
            $copy->sku = null;
            $copy->barcode = null;
            $copy->save();

            $copy->categories()->sync($product->categories->mapWithKeys(fn ($category) => [
                $category->id => [
                    'is_primary' => (bool) $category->pivot->is_primary,
                    'sort_order' => (int) $category->pivot->sort_order,
                ],
            ])->all());

            foreach ($product->images as $image) {
                $clone = $image->replicate();
                $clone->product_id = $copy->id;
                $clone->save();
            }

            $valueMap = [];
            foreach ($product->options as $option) {
                $newOption = $option->replicate();
                $newOption->product_id = $copy->id;
                $newOption->save();
                foreach ($option->values as $value) {
                    $newValue = $value->replicate();
                    $newValue->option_id = $newOption->id;
                    $newValue->save();
                    $valueMap[$value->id] = ['value_id' => $newValue->id, 'option_id' => $newOption->id];
                }
            }

            foreach ($product->variants as $variant) {
                $newVariant = $variant->replicate();
                $newVariant->public_id = null;
                $newVariant->product_id = $copy->id;
                $newVariant->sku = null;
                $newVariant->barcode = null;
                $newVariant->save();
                $attach = [];
                foreach ($variant->values as $value) {
                    if (! isset($valueMap[$value->id])) continue;
                    $mapped = $valueMap[$value->id];
                    $attach[$mapped['value_id']] = ['option_id' => $mapped['option_id']];
                }
                $newVariant->values()->sync($attach);
            }
        });

        return redirect()->back(303)->with('success', 'Product duplicated as a draft.');
    }

    public function destroy(Request $request, Website $website, CommerceProduct $product, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertProductWebsite($website, $product);
        $product->delete();

        return redirect()->back(303)->with('success', 'Product deleted.');
    }

    public function syncOptions(Request $request, Website $website, CommerceProduct $product, CommerceCapabilityService $commerce, CommerceProductVariationService $variations)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertProductWebsite($website, $product);

        $payload = $request->validate([
            'options' => 'array|max:8',
            'options.*.name' => 'required|string|max:120',
            'options.*.display_type' => ['nullable', Rule::in(['select', 'buttons', 'swatch'])],
            'options.*.values' => 'required|array|min:1|max:50',
            'options.*.values.*.label' => 'required|string|max:120',
            'options.*.values.*.swatch_hex' => ['nullable', 'string', 'max:16', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        DB::transaction(function () use ($product, $payload): void {
            $keepOptionIds = [];
            foreach (($payload['options'] ?? []) as $optionIndex => $optionData) {
                $slug = Str::slug($optionData['name']) ?: 'option-'.($optionIndex + 1);
                $option = $product->options()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => trim($optionData['name']),
                        'display_type' => $optionData['display_type'] ?? 'select',
                        'is_required' => true,
                        'sort_order' => $optionIndex,
                    ]
                );
                $keepOptionIds[] = $option->id;

                $keepValueIds = [];
                foreach ($optionData['values'] as $valueIndex => $valueData) {
                    $valueSlug = Str::slug($valueData['label']) ?: 'value-'.($valueIndex + 1);
                    $value = $option->values()->updateOrCreate(
                        ['slug' => $valueSlug],
                        [
                            'label' => trim($valueData['label']),
                            'swatch_hex' => $valueData['swatch_hex'] ?? null,
                            'is_active' => true,
                            'sort_order' => $valueIndex,
                        ]
                    );
                    $keepValueIds[] = $value->id;
                }
                $option->values()->whereNotIn('id', $keepValueIds)->update(['is_active' => false]);
            }

            $product->options()->whereNotIn('id', $keepOptionIds ?: [0])->delete();
            $product->forceFill(['type' => ($payload['options'] ?? []) === [] ? CommerceProduct::TYPE_SIMPLE : CommerceProduct::TYPE_VARIABLE])->save();
        });

        if ($product->fresh()->isVariable()) {
            $variations->syncAutomaticCombinations($product->fresh());
        } else {
            $product->variants()->update(['is_enabled' => false, 'is_default' => false]);
        }

        return redirect()->back(303)->with('success', 'Product variations regenerated.');
    }

    public function updateVariant(Request $request, Website $website, CommerceProduct $product, CommerceProductVariant $variant, CommerceCapabilityService $commerce, CommerceProductVariationService $variations)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertProductWebsite($website, $product);
        abort_unless((int) $variant->product_id === (int) $product->id && (int) $variant->website_id === (int) $website->id, 404);

        $data = $request->validate([
            'sku' => ['nullable','string','max:120', Rule::unique('commerce_product_variants', 'sku')->where(fn ($q) => $q->where('website_id', $website->id))->ignore($variant->id)],
            'regular_price' => 'nullable|numeric|min:0|max:999999999',
            'sale_price' => 'nullable|numeric|min:0|max:999999999',
            'track_inventory' => 'boolean',
            'stock_quantity' => 'nullable|integer|min:0|max:4294967295',
            'low_stock_threshold' => 'nullable|integer|min:0|max:4294967295',
            'allow_backorders' => 'boolean',
            'stock_status' => ['required', Rule::in(['in_stock','out_of_stock','backorder'])],
            'image_url' => 'nullable|string|max:2048',
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $this->assertVariantSkuAvailable($website, $data['sku'] ?? null, $variant->id);

        $trackInventory = (bool) ($data['track_inventory'] ?? false);
        $allowBackorders = (bool) ($data['allow_backorders'] ?? false);
        $stockQuantity = $data['stock_quantity'] ?? null;
        $variant->update([
            'sku' => $data['sku'] ?: null,
            'regular_price_minor' => $this->moneyToMinor($request->input('regular_price'), $website),
            'sale_price_minor' => $this->moneyToMinor($request->input('sale_price'), $website),
            'track_inventory' => $trackInventory,
            'stock_quantity' => $stockQuantity,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
            'allow_backorders' => $allowBackorders,
            'stock_status' => app(CommerceInventoryService::class)->normalizedStatus($trackInventory, $stockQuantity, $allowBackorders, (string) $data['stock_status']),
            'image_url' => $data['image_url'] ?: null,
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
        ]);

        if (($data['is_default'] ?? false) && $variant->is_enabled) {
            $variations->setDefaultVariant($product, $variant->fresh());
        }

        return redirect()->back(303)->with('success', 'Variation saved.');
    }

    public function destroyVariant(Request $request, Website $website, CommerceProduct $product, CommerceProductVariant $variant, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertProductWebsite($website, $product);
        abort_unless((int) $variant->product_id === (int) $product->id && (int) $variant->website_id === (int) $website->id, 404);

        $hasOrderHistory = DB::table('commerce_order_items')->where('commerce_product_variant_id', $variant->id)->exists();
        $hasInventoryHistory = DB::table('commerce_inventory_adjustments')->where('commerce_product_variant_id', $variant->id)->exists();

        if ($hasOrderHistory || $hasInventoryHistory) {
            $variant->forceFill(['is_enabled' => false, 'is_default' => false])->save();
            return redirect()->back(303)->with('success', 'Variation has order or inventory history, so it was disabled instead of permanently deleted.');
        }

        $label = $variant->optionLabel() ?: 'Variation';
        $variant->delete();

        return redirect()->back(303)->with('success', "{$label} deleted. Re-syncing the same option values may recreate this combination.");
    }

    public function adjustInventory(Request $request, Website $website, CommerceProduct $product, CommerceInventoryService $inventory)
    {
        $this->authorize('update', $website);
        app(CommerceCapabilityService::class)->assertPlanAllowsCommerce($request->user());
        $this->assertProductWebsite($website, $product);

        $data = $request->validate([
            'variant_id' => ['nullable', 'integer', Rule::exists('commerce_product_variants', 'id')->where(fn ($q) => $q->where('website_id', $website->id)->where('product_id', $product->id))],
            'inventory_delta' => 'required|integer|min:-1000000|max:1000000|not_in:0',
            'reason' => ['required', Rule::in(['manual', 'correction', 'import'])],
            'note' => 'nullable|string|max:500',
        ]);

        $variant = ! empty($data['variant_id']) ? CommerceProductVariant::query()->findOrFail((int) $data['variant_id']) : null;
        $inventory->adjust($website, $product, $variant, (int) $data['inventory_delta'], $data['reason'], $data['note'] ?? null, $request->user()?->id);

        return redirect()->back(303)->with('success', 'Inventory adjusted.');
    }

    private function validatedProduct(Request $request, Website $website, ?CommerceProduct $product = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['nullable','string','max:255'],
            'type' => ['required', Rule::in(['simple','variable'])],
            'fulfillment_type' => ['required', Rule::in(['physical','digital'])],
            'status' => ['required', Rule::in(['draft','published','archived'])],
            'visibility' => ['required', Rule::in(['catalog','hidden'])],
            'short_description' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:50000',
            'regular_price' => 'nullable|numeric|min:0|max:999999999',
            'sale_price' => 'nullable|numeric|min:0|max:999999999|lte:regular_price',
            'sku' => ['nullable','string','max:120', Rule::unique('commerce_products', 'sku')->where(fn ($q) => $q->where('website_id', $website->id))->ignore($product?->id)],
            'barcode' => 'nullable|string|max:120',
            'track_inventory' => 'boolean',
            'stock_quantity' => 'nullable|integer|min:0|max:4294967295',
            'low_stock_threshold' => 'nullable|integer|min:0|max:4294967295',
            'allow_backorders' => 'boolean',
            'stock_status' => ['required', Rule::in(['in_stock','out_of_stock','backorder'])],
            'weight_grams' => 'nullable|integer|min:0|max:4294967295',
            'length_mm' => 'nullable|integer|min:0|max:4294967295',
            'width_mm' => 'nullable|integer|min:0|max:4294967295',
            'height_mm' => 'nullable|integer|min:0|max:4294967295',
            'shipping_class' => 'nullable|string|max:120',
            'taxable' => 'boolean',
            'tax_class' => 'nullable|string|max:120',
            'is_featured' => 'boolean',
            'featured_image_url' => 'nullable|string|max:2048',
            'featured_image_alt' => 'nullable|string|max:255',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:2000',
            'category_ids' => 'array|max:30',
            'category_ids.*' => ['integer', Rule::exists('commerce_product_categories', 'id')->where(fn ($q) => $q->where('website_id', $website->id))],
            'primary_category_id' => ['nullable','integer', Rule::exists('commerce_product_categories', 'id')->where(fn ($q) => $q->where('website_id', $website->id))],
            'gallery' => 'array|max:24',
            'gallery.*.url' => 'required|string|max:2048',
            'gallery.*.alt_text' => 'nullable|string|max:255',
            'variants' => 'array|max:500',
            'variants.*.id' => 'required|integer',
            'variants.*.sku' => 'nullable|string|max:120',
            'variants.*.regular_price' => 'nullable|numeric|min:0|max:999999999',
            'variants.*.sale_price' => 'nullable|numeric|min:0|max:999999999',
            'variants.*.track_inventory' => 'boolean',
            'variants.*.stock_quantity' => 'nullable|integer|min:0|max:4294967295',
            'variants.*.low_stock_threshold' => 'nullable|integer|min:0|max:4294967295',
            'variants.*.allow_backorders' => 'boolean',
            'variants.*.stock_status' => ['nullable', Rule::in(['in_stock','out_of_stock','backorder'])],
            'variants.*.image_url' => 'nullable|string|max:2048',
            'variants.*.is_enabled' => 'boolean',
            'variants.*.is_default' => 'boolean',
        ]);
    }

    private function syncVariantEdits(Request $request, Website $website, CommerceProduct $product): void
    {
        if ($product->type !== 'variable') return;
        $rows = collect($request->input('variants', []));
        if ($rows->isEmpty()) return;
        $variants = $product->variants()->whereIn('id', $rows->pluck('id')->map(fn ($id) => (int) $id))->get()->keyBy('id');
        foreach ($rows as $row) {
            $variant = $variants->get((int) ($row['id'] ?? 0));
            if (! $variant) continue;
            $this->assertVariantSkuAvailable($website, $row['sku'] ?? null, $variant->id);
            $track = (bool) ($row['track_inventory'] ?? false);
            $backorders = (bool) ($row['allow_backorders'] ?? false);
            $qty = ($row['stock_quantity'] ?? '') === '' ? null : ($row['stock_quantity'] ?? null);
            $variant->update([
                'sku' => filled($row['sku'] ?? null) ? trim($row['sku']) : null,
                'regular_price_minor' => $this->moneyToMinor($row['regular_price'] ?? null, $website),
                'sale_price_minor' => $this->moneyToMinor($row['sale_price'] ?? null, $website),
                'track_inventory' => $track,
                'stock_quantity' => $qty,
                'low_stock_threshold' => ($row['low_stock_threshold'] ?? '') === '' ? null : ($row['low_stock_threshold'] ?? null),
                'allow_backorders' => $backorders,
                'stock_status' => app(CommerceInventoryService::class)->normalizedStatus($track, $qty, $backorders, (string) ($row['stock_status'] ?? 'in_stock')),
                'image_url' => filled($row['image_url'] ?? null) ? trim($row['image_url']) : null,
                'is_enabled' => (bool) ($row['is_enabled'] ?? false),
            ]);
        }
        $default = $rows->first(fn ($row) => !empty($row['is_default']) && !empty($row['is_enabled']));
        if ($default && ($variant = $variants->get((int) $default['id']))) {
            app(CommerceProductVariationService::class)->setDefaultVariant($product, $variant->fresh());
        }
    }

    private function syncRelations(Request $request, CommerceProduct $product): void
    {
        $categoryIds = collect($request->input('category_ids', []))->map(fn ($id) => (int) $id)->unique()->values();
        $primaryId = $request->filled('primary_category_id') ? (int) $request->input('primary_category_id') : null;
        if ($primaryId && ! $categoryIds->contains($primaryId)) {
            $categoryIds->push($primaryId);
        }
        $product->categories()->sync($categoryIds->mapWithKeys(fn ($id, $index) => [$id => ['is_primary' => $id === $primaryId, 'sort_order' => $index]])->all());

        $product->images()->delete();
        foreach ($request->input('gallery', []) as $index => $image) {
            if (! trim((string) ($image['url'] ?? ''))) continue;
            $product->images()->create(['url' => trim($image['url']), 'alt_text' => $image['alt_text'] ?? null, 'sort_order' => $index]);
        }
    }

    private function assertCatalogSkuAvailable(Website $website, ?string $sku, ?int $ignoreProductId = null): void
    {
        $sku = trim((string) $sku);
        if ($sku === '') return;

        $variantCollision = CommerceProductVariant::query()
            ->where('website_id', $website->id)
            ->where('sku', $sku)
            ->exists();
        if ($variantCollision) {
            throw ValidationException::withMessages(['sku' => 'This SKU is already used by a product variation on this website.']);
        }
    }

    private function assertVariantSkuAvailable(Website $website, ?string $sku, ?int $ignoreVariantId = null): void
    {
        $sku = trim((string) $sku);
        if ($sku === '') return;

        $productCollision = CommerceProduct::query()
            ->where('website_id', $website->id)
            ->where('sku', $sku)
            ->exists();
        if ($productCollision) {
            throw ValidationException::withMessages(['sku' => 'This SKU is already used by a product on this website.']);
        }
    }

    private function uniqueSlug(Website $website, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'product';
        $slug = $base;
        $suffix = 2;
        while ($website->commerceProducts()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }

    private function moneyToMinor(mixed $value, Website $website): ?int
    {
        if ($value === null || $value === '') return null;
        $currency = app(CommerceCapabilityService::class)->settingsFor($website)->currency;
        $decimals = (int) config('cosmic-commerce.currencies.'.strtoupper($currency).'.decimals', 2);
        $scale = 10 ** max(0, $decimals);

        return (int) round(((float) $value) * $scale);
    }

    private function assertProductWebsite(Website $website, CommerceProduct $product): void
    {
        abort_unless((int) $product->website_id === (int) $website->id, 404);
    }
}
