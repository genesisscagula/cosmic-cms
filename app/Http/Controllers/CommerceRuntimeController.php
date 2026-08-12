<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\PreviewDeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CommerceRuntimeController extends Controller
{
    public function catalog(string $preview, PreviewDeploymentService $previews): JsonResponse
    {
        $website = Website::query()
            ->where('preview_slug', $preview)
            ->with(['commerceSetting'])
            ->firstOrFail();

        abort_unless((bool) $website->commerceSetting?->enabled, 404);

        $settings = $website->commerceSetting;
        $currency = strtoupper((string) ($settings?->currency ?: config('cosmic-commerce.default_currency', 'USD')));
        $decimals = (int) config("cosmic-commerce.currencies.$currency.decimals", 2);

        $assetUrl = fn (?string $url): string => $this->assetUrl($url);

        $products = $website->commerceProducts()
            ->with(['images', 'categories', 'options.values', 'variants.values.option'])
            ->where('status', 'published')
            ->where('visibility', '!=', 'hidden')
            ->orderByDesc('is_featured')
            ->latest('updated_at')
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'storefront_url' => $previews->url($website, 'product/'.$product->slug),
                'short_description' => $product->short_description,
                'regular_price_minor' => $product->regular_price_minor,
                'sale_price_minor' => $product->sale_price_minor,
                'track_inventory' => (bool) $product->track_inventory,
                'stock_quantity' => $product->stock_quantity,
                'allow_backorders' => (bool) $product->allow_backorders,
                'stock_status' => $product->stock_status,
                'is_featured' => (bool) $product->is_featured,
                'featured_image_url' => $assetUrl($product->featured_image_url),
                'featured_image_alt' => $product->featured_image_alt,
                'category_ids' => $product->categories->pluck('id')->map(fn ($id) => (int) $id)->values(),
                'categories' => $product->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])->values(),
                'gallery' => $product->images->map(fn ($image) => ['url' => $assetUrl($image->url), 'alt_text' => $image->alt_text])->values(),
                'options' => $product->options->map(fn ($option) => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'display_type' => $option->display_type,
                    'values' => $option->values->where('is_active', true)->map(fn ($value) => [
                        'id' => $value->id,
                        'label' => $value->label,
                        'swatch_hex' => $value->swatch_hex,
                    ])->values(),
                ])->values(),
            ])->values();

        $categories = $website->commerceProductCategories()
            ->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image_url' => $assetUrl($category->image_url),
                'storefront_url' => $previews->url($website, 'shop/category/'.$category->slug),
            ])->values();

        return response()->json([
            'currency' => $currency,
            'currency_decimals' => $decimals,
            'storefront_url' => $previews->url($website, 'shop'),
            'cart_url' => $previews->url($website, 'cart'),
            'products' => $products,
            'categories' => $categories,
        ])->header('Access-Control-Allow-Origin', '*')
          ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=120');
    }
    private function assetUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || str_starts_with($url, 'data:')) return $url;
        if (Str::startsWith($url, ['http://', 'https://', '//'])) {
            $host = strtolower((string) parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_HOST));
            if (! in_array($host, ['localhost', '127.0.0.1', '::1'], true) && ! str_ends_with($host, '.local')) return $url;
            $url = (string) parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_PATH);
        }
        // Rewrite commerce URLs created by older builds so live/export runtime does
        // not depend on a public/storage symlink either.
        if (preg_match('#^/storage/websites/(\d+)/commerce/([A-Za-z0-9._-]+)$#', $url, $match)) {
            $url = '/websites/'.$match[1].'/commerce/media/'.$match[2];
        }

        $base = rtrim((string) config('services.cosmic.asset_base_url', config('app.url')), '/');
        return $base.'/'.ltrim($url, '/');
    }
}
