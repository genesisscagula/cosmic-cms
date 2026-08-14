<?php

namespace App\Services;

use App\Helpers\CmsHtmlCompiler;
use App\Models\CommerceCustomer;
use App\Models\CommerceOrder;
use App\Models\CommerceProduct;
use App\Models\CommerceProductCategory;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CommerceStorefrontService
{
    public function __construct(
        private readonly CommerceCatalogPathService $paths,
        private readonly ThemeColorResolver $themes,
        private readonly CommerceCartService $cart,
        private readonly CommerceShippingService $shipping,
        private readonly CommerceTaxService $tax,
        private readonly CommerceCouponService $coupons,
    ) {
    }

    public function renderIfCommercePath(string $previewSlug, ?string $path, Request $request): ?Response
    {
        $normalized = trim((string) $path, '/');
        if (! $this->isCommercePath($normalized)) {
            return null;
        }

        $website = Website::query()
            ->where('preview_slug', $previewSlug)
            ->with('commerceSetting')
            ->first();

        if (! $website || ! $website->commerceSetting?->enabled) {
            return null;
        }

        if ($normalized === 'shop' || $normalized === 'shop/') {
            // Installer v2 makes /shop a normal Builder/export page populated with
            // Commerce Sparks. Let the static preview/export layer serve it when
            // present; fall back to the legacy dynamic storefront otherwise.
            $builderShop = $website->pages()->where('slug', 'shop')->where('page_type', '!=', 'commerce')->first();
            if ($builderShop) {
                return null;
            }
            return $this->shop($website, $previewSlug, $request);
        }

        if ($normalized === 'cart') {
            return $this->cart($website, $previewSlug);
        }

        if ($normalized === 'checkout') {
            return $this->checkout($website, $previewSlug, $request);
        }

        if (preg_match('#^order/([0-9a-f-]{36})/success$#i', $normalized, $matches)) {
            return $this->orderSuccess($website, $previewSlug, $matches[1]);
        }

        if ($normalized === 'account' || $normalized === 'account/login' || $normalized === 'account/register') {
            return $this->account($website, $previewSlug, $request, $normalized);
        }

        if ($normalized === 'order-lookup') {
            return $this->orderLookup($website, $previewSlug);
        }

        if (preg_match('#^order/([0-9a-f-]{36})$#i', $normalized, $matches)) {
            return $this->orderDetail($website, $previewSlug, $request, $matches[1]);
        }

        if (str_starts_with($normalized, 'shop/category/')) {
            $slug = rawurldecode(Str::after($normalized, 'shop/category/'));
            return $this->category($website, $previewSlug, $slug, $request);
        }

        if (str_starts_with($normalized, 'product/')) {
            $slug = rawurldecode(Str::after($normalized, 'product/'));
            return $this->product($website, $previewSlug, $slug, $request);
        }

        return null;
    }

    private function shop(Website $website, string $previewSlug, Request $request): Response
    {
        $products = $this->catalogProducts($website, $request);
        $categories = $this->visibleCategories($website);

        return $this->response($website, $previewSlug, 'shop', [
            'title' => 'Shop',
            'description' => 'Explore products from '.$website->name.'.',
            'products' => $products,
            'categories' => $categories,
            'activeCategory' => null,
            'breadcrumbs' => [['label' => 'Shop', 'url' => null]],
        ]);
    }


    private function cart(Website $website, string $previewSlug): Response
    {
        return $this->response($website, $previewSlug, 'cart', [
            'title' => 'Your Cart',
            'description' => 'Review the items in your cart.',
            'cart' => $this->cart->summary($website),
            'categories' => $this->visibleCategories($website),
            'breadcrumbs' => [
                ['label' => 'Shop', 'url' => $this->url($previewSlug, $this->paths->shop())],
                ['label' => 'Cart', 'url' => null],
            ],
            'cartVisual' => $this->runtimeVisual($website, 'cart', ['commerce_cart_classic', 'commerce_cart_split', 'commerce_cart_compact'], 'commerce_cart_split'),
        ]);
    }

    private function checkout(Website $website, string $previewSlug, Request $request): Response
    {
        $cart = $this->cart->summary($website);
        if ($cart['count'] < 1) {
            return $this->cart($website, $previewSlug);
        }

        $country = strtoupper((string) $request->query('country', ''));
        $rateId = $request->integer('shipping_rate') ?: null;
        $couponCode = strtoupper(trim((string) $request->query('coupon', '')));
        $coupon = $this->coupons->quote($website, $cart, $couponCode);
        $discountedCart = $coupon['cart'];
        $shipping = $cart['requires_shipping']
            ? $this->shipping->quote($website, $country, $discountedCart['subtotal_minor'], $rateId)
            : ['available' => true, 'country' => '', 'zone' => null, 'rates' => collect(), 'selected' => ['id' => null, 'name' => 'Digital delivery', 'amount_minor' => 0]];
        $shippingMinor = (int) data_get($shipping, 'selected.amount_minor', 0);
        $region = strtoupper(trim((string) $request->query('region', '')));
        $tax = $this->tax->quote($website, $discountedCart, $country, $region, $shippingMinor);
        $checkoutIdempotencyKey = $this->checkoutIdempotencyKey($website, $request);

        return $this->response($website, $previewSlug, 'checkout', [
            'title' => 'Checkout',
            'description' => 'Secure checkout for '.$website->name.'.',
            'cart' => $cart,
            'shipping' => $shipping,
            'shippingMinor' => $shippingMinor,
            'tax' => $tax,
            'coupon' => $coupon,
            'checkoutTotalMinor' => $tax['total_minor'],
            'checkoutIdempotencyKey' => $checkoutIdempotencyKey,
            'countries' => config('cosmic-commerce.countries', []),
            'categories' => $this->visibleCategories($website),
            'breadcrumbs' => [
                ['label' => 'Shop', 'url' => $this->url($previewSlug, $this->paths->shop())],
                ['label' => 'Cart', 'url' => $this->url($previewSlug, '/cart')],
                ['label' => 'Checkout', 'url' => null],
            ],
            'checkoutVisual' => $this->runtimeVisual($website, 'checkout', ['commerce_checkout_classic', 'commerce_checkout_split', 'commerce_checkout_express'], 'commerce_checkout_split'),
        ]);
    }

    private function orderSuccess(Website $website, string $previewSlug, string $publicId): Response
    {
        $order = CommerceOrder::query()
            ->where('website_id', $website->id)
            ->where('public_id', $publicId)
            ->where('payment_status', 'paid')
            ->with('items')
            ->firstOrFail();

        return $this->response($website, $previewSlug, 'order-success', [
            'title' => 'Order confirmed',
            'description' => 'Your payment was received successfully.',
            'order' => $order,
            'categories' => $this->visibleCategories($website),
            'breadcrumbs' => [
                ['label' => 'Shop', 'url' => $this->url($previewSlug, $this->paths->shop())],
                ['label' => 'Order '.$order->order_number, 'url' => null],
            ],
        ]);
    }


    private function account(Website $website, string $previewSlug, Request $request, string $mode): Response
    {
        $customer = $this->customer($website, $request);
        if (! $customer && $mode === 'account') {
            $mode = 'account/login';
        }

        $orders = $customer
            ? CommerceOrder::query()
                ->where('website_id', $website->id)
                ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower($customer->email)])
                ->withCount('items')
                ->latest()
                ->paginate(12)
            : null;

        return $this->response($website, $previewSlug, $customer ? 'account' : ($mode === 'account/register' ? 'account-register' : 'account-login'), [
            'title' => $customer ? 'My Account' : ($mode === 'account/register' ? 'Create account' : 'Customer login'),
            'description' => $customer ? 'View your order history and fulfillment status.' : 'Access your customer account.',
            'customer' => $customer,
            'orders' => $orders,
            'categories' => $this->visibleCategories($website),
            'breadcrumbs' => [
                ['label' => 'Shop', 'url' => $this->url($previewSlug, $this->paths->shop())],
                ['label' => 'Account', 'url' => null],
            ],
        ]);
    }

    private function orderLookup(Website $website, string $previewSlug): Response
    {
        return $this->response($website, $previewSlug, 'order-lookup', [
            'title' => 'Find your order',
            'description' => 'Look up a guest order using the order number and checkout email.',
            'categories' => $this->visibleCategories($website),
            'breadcrumbs' => [
                ['label' => 'Shop', 'url' => $this->url($previewSlug, $this->paths->shop())],
                ['label' => 'Order lookup', 'url' => null],
            ],
        ]);
    }

    private function orderDetail(Website $website, string $previewSlug, Request $request, string $publicId): Response
    {
        $order = CommerceOrder::query()
            ->where('website_id', $website->id)
            ->where('public_id', $publicId)
            ->with(['items', 'refunds'])
            ->firstOrFail();

        $customer = $this->customer($website, $request);
        $customerOwnsOrder = $customer && mb_strtolower($customer->email) === mb_strtolower($order->customer_email);
        $guestAllowed = (bool) $request->session()->get('cosmic_commerce_guest_orders.'.$website->id.'.'.$order->public_id, false);
        abort_unless($customerOwnsOrder || $guestAllowed, 404);

        return $this->response($website, $previewSlug, 'order-detail', [
            'title' => 'Order '.$order->order_number,
            'description' => 'Order status and fulfillment details.',
            'order' => $order,
            'customer' => $customer,
            'categories' => $this->visibleCategories($website),
            'breadcrumbs' => [
                ['label' => 'Shop', 'url' => $this->url($previewSlug, $this->paths->shop())],
                ['label' => $customer ? 'Account' : 'Order lookup', 'url' => $customer ? $this->url($previewSlug, '/account') : $this->url($previewSlug, '/order-lookup')],
                ['label' => $order->order_number, 'url' => null],
            ],
        ]);
    }

    private function customer(Website $website, Request $request): ?CommerceCustomer
    {
        $id = $request->session()->get('cosmic_commerce_customer.'.$website->id);
        if (! $id) {
            return null;
        }

        return CommerceCustomer::query()->where('website_id', $website->id)->whereKey($id)->first();
    }

    private function category(Website $website, string $previewSlug, string $slug, Request $request): Response
    {
        $category = CommerceProductCategory::query()
            ->forWebsite($website->id)
            ->visible()
            ->where('slug', $slug)
            ->firstOrFail();

        $products = $this->catalogProducts($website, $request, $category);
        $categories = $this->visibleCategories($website);
        $breadcrumbs = [['label' => 'Shop', 'url' => $this->url($previewSlug, $this->paths->shop())]];

        foreach ($this->categoryAncestors($category) as $ancestor) {
            $breadcrumbs[] = [
                'label' => $ancestor->name,
                'url' => $this->url($previewSlug, $this->paths->category($ancestor)),
            ];
        }
        $breadcrumbs[] = ['label' => $category->name, 'url' => null];

        return $this->response($website, $previewSlug, 'category', [
            'title' => $category->name,
            'description' => $category->description ?: 'Browse '.$category->name.' from '.$website->name.'.',
            'products' => $products,
            'categories' => $categories,
            'activeCategory' => $category,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    private function product(Website $website, string $previewSlug, string $slug, Request $request): Response
    {
        $product = CommerceProduct::query()
            ->forWebsite($website->id)
            ->published()
            ->where('slug', $slug)
            ->with([
                'images',
                'categories',
                'options.values',
                'variants.values.option',
            ])
            ->firstOrFail();

        if ($product->visibility === CommerceProduct::VISIBILITY_HIDDEN) {
            abort(404);
        }

        $primaryCategory = $product->primaryCategory();
        $related = $this->relatedProducts($website, $product, $primaryCategory);
        $breadcrumbs = [['label' => 'Shop', 'url' => $this->url($previewSlug, $this->paths->shop())]];
        if ($primaryCategory) {
            $breadcrumbs[] = [
                'label' => $primaryCategory->name,
                'url' => $this->url($previewSlug, $this->paths->category($primaryCategory)),
            ];
        }
        $breadcrumbs[] = ['label' => $product->title, 'url' => null];

        return $this->response($website, $previewSlug, 'product', [
            'title' => $product->seo_title ?: $product->title,
            'description' => $product->seo_description ?: ($product->short_description ?: Str::limit(strip_tags((string) $product->description), 160)),
            'product' => $product,
            'relatedProducts' => $related,
            'categories' => $this->visibleCategories($website),
            'activeCategory' => $primaryCategory,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    private function catalogProducts(Website $website, Request $request, ?CommerceProductCategory $category = null): LengthAwarePaginator
    {
        $query = CommerceProduct::query()
            ->forWebsite($website->id)
            ->published()
            ->catalogVisible()
            ->with(['images', 'categories', 'variants'])
            ->when($category, fn ($q) => $q->whereHas('categories', fn ($categories) => $categories->whereKey($category->id)))
            ->when(trim((string) $request->query('q')) !== '', function ($q) use ($request) {
                $term = trim((string) $request->query('q'));
                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', '%'.$term.'%')
                        ->orWhere('short_description', 'like', '%'.$term.'%')
                        ->orWhere('sku', 'like', '%'.$term.'%');
                });
            })
            ->when($request->query('stock') === 'in', fn ($q) => $q->where(function ($stock) {
                $stock->where('track_inventory', false)
                    ->orWhere('stock_quantity', '>', 0)
                    ->orWhere('allow_backorders', true)
                    ->orWhereHas('variants', fn ($variants) => $variants->where('is_enabled', true)->where(function ($available) {
                        $available->where('track_inventory', false)
                            ->orWhere('stock_quantity', '>', 0)
                            ->orWhere('allow_backorders', true);
                    }));
            }));

        // Product price can be inherited from variants, so sort after hydration to keep
        // simple and variable products in one accurate price order.
        $items = $query->limit(1000)->get();
        $sort = (string) $request->query('sort', 'featured');
        $items = match ($sort) {
            'price_asc' => $items->sortBy(fn (CommerceProduct $p) => $this->sortPrice($p)),
            'price_desc' => $items->sortByDesc(fn (CommerceProduct $p) => $this->sortPrice($p)),
            'title_asc' => $items->sortBy(fn (CommerceProduct $p) => mb_strtolower($p->title)),
            'title_desc' => $items->sortByDesc(fn (CommerceProduct $p) => mb_strtolower($p->title)),
            'newest' => $items->sortByDesc(fn (CommerceProduct $p) => $p->published_at?->getTimestamp() ?? $p->id),
            default => $items->sortByDesc(fn (CommerceProduct $p) => ($p->is_featured ? 10_000_000 : 0) + $p->id),
        };

        $perPage = 24;
        $page = max(1, (int) $request->query('page', 1));
        $slice = $items->values()->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    private function relatedProducts(Website $website, CommerceProduct $product, ?CommerceProductCategory $category): Collection
    {
        return CommerceProduct::query()
            ->forWebsite($website->id)
            ->published()
            ->catalogVisible()
            ->whereKeyNot($product->id)
            ->when($category, fn ($q) => $q->whereHas('categories', fn ($categories) => $categories->whereKey($category->id)))
            ->with(['images', 'variants'])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();
    }

    private function visibleCategories(Website $website): Collection
    {
        return CommerceProductCategory::query()
            ->forWebsite($website->id)
            ->visible()
            ->withCount(['products' => fn ($q) => $q->published()->catalogVisible()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function categoryAncestors(CommerceProductCategory $category): Collection
    {
        $ancestors = collect();
        $seen = [];
        $current = $category;

        while ($current->parent_id && ! isset($seen[$current->parent_id])) {
            $seen[$current->parent_id] = true;
            $parent = CommerceProductCategory::query()->whereKey($current->parent_id)->where('website_id', $category->website_id)->first();
            if (! $parent) {
                break;
            }
            $ancestors->prepend($parent);
            $current = $parent;
        }

        return $ancestors;
    }

    private function response(Website $website, string $previewSlug, string $viewMode, array $data): Response
    {
        $draftTheme = is_array($website->theme_settings) ? $website->theme_settings : [];
        $publishedTheme = is_array($website->published_theme_settings) ? $website->published_theme_settings : [];
        // Dynamic commerce routes are part of the live Builder preview, so prefer the
        // currently selected theme. Published settings remain a safe fallback for older sites.
        $theme = $draftTheme !== [] ? $draftTheme : $publishedTheme;
        $themeKey = (string) data_get($theme, 'primary', 'midnight');
        $brandPalette = data_get($theme, 'brand_palette');
        $brandPalette = is_array($brandPalette) ? $brandPalette : data_get($theme, 'custom_brand_theme.palette');
        // A saved brand palette can outlive later theme switches. Only use it when the
        // active theme is explicitly My Brand; named families such as emerald/midnight
        // must always resolve from the current family so runtime commerce stays in sync
        // with Builder, Preview and the published site shell.
        $palette = $themeKey === 'my-brand' && is_array($brandPalette)
            ? $this->normalizePalette($brandPalette, 'midnight')
            : $this->normalizePalette($this->themes->palette($themeKey), $themeKey);
        $currency = strtoupper((string) ($website->commerceSetting?->currency ?: config('cosmic-commerce.default_currency', 'USD')));
        $currencyMeta = config('cosmic-commerce.currencies.'.$currency, config('cosmic-commerce.currencies.USD'));
        $siteShell = $this->siteShell($website, $previewSlug, $themeKey);

        $payload = array_merge($data, [
            'viewMode' => $viewMode,
            'website' => $website,
            'previewSlug' => $previewSlug,
            'palette' => $palette,
            'currency' => $currency,
            'currencyMeta' => $currencyMeta,
            'useSiteShell' => in_array($viewMode, ['product', 'category', 'cart', 'checkout', 'account-login', 'account-register', 'account', 'order-lookup', 'order-detail', 'order-success'], true) && $siteShell['header'] !== '',
            'siteHeaderHtml' => $siteShell['header'],
            'siteFooterHtml' => $siteShell['footer'],
            'shopUrl' => $this->url($previewSlug, $this->paths->shop()),
            'cartUrl' => $this->url($previewSlug, '/cart'),
            'checkoutUrl' => $this->url($previewSlug, '/checkout'),
            'checkoutPayPalUrl' => $this->url($previewSlug, '/checkout/paypal'),
            'cartAddUrl' => $this->url($previewSlug, '/cart/add'),
            'cartUpdateUrl' => $this->url($previewSlug, '/cart/update'),
            'cartRemoveUrl' => $this->url($previewSlug, '/cart/remove'),
            'accountUrl' => $this->url($previewSlug, '/account'),
            'accountLoginUrl' => $this->url($previewSlug, '/account/login'),
            'accountRegisterUrl' => $this->url($previewSlug, '/account/register'),
            'accountLogoutUrl' => $this->url($previewSlug, '/account/logout'),
            'orderLookupUrl' => $this->url($previewSlug, '/order-lookup'),
            'cartCount' => $this->cart->count($website),
            'pathUrl' => fn (string $path): string => $this->url($previewSlug, $path),
            'productUrl' => fn (CommerceProduct $product): string => $this->url($previewSlug, $this->paths->product($product)),
            'categoryUrl' => fn (CommerceProductCategory $category): string => $this->url($previewSlug, $this->paths->category($category)),
            'money' => fn (?int $minor): string => $this->money($minor, $currency, $currencyMeta),
            'priceRange' => fn (CommerceProduct $product): array => $product->variantPriceRangeMinor(),
            'assetUrl' => fn (?string $url): string => $this->assetUrl($url),
        ]);

        return response()->view('commerce.storefront', $payload, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function runtimeVisual(Website $website, string $slug, array $allowedTypes, string $fallbackType): array
    {
        $page = $website->pages()->where('slug', $slug)->where('page_type', 'commerce')->first();
        $blocks = is_array($page?->published_blocks) && $page->published_blocks !== []
            ? $page->published_blocks
            : (is_array($page?->blocks) ? $page->blocks : []);
        $block = is_array($blocks[0] ?? null) ? $blocks[0] : [];
        $type = (string) ($block['type'] ?? '');
        if (! in_array($type, $allowedTypes, true)) {
            $type = $fallbackType;
        }
        $block['type'] = $type;
        return $block;
    }

    private function siteShell(Website $website, string $previewSlug, string $primaryColor): array
    {
        $header = $website->published_global_header ?? $website->global_header;
        $footer = $website->published_global_footer ?? $website->global_footer;

        if (! is_array($header) && ! is_array($footer)) {
            return ['header' => '', 'footer' => ''];
        }

        if (is_array($header)) {
            $preview = app(PreviewDeploymentService::class);
            $pageTargets = $website->pages()
                ->where('page_type', '!=', 'commerce')
                ->get()
                ->mapWithKeys(function ($page) use ($website, $preview): array {
                    $url = $preview->urlForPage($website, $page);
                    return filled($url) ? [strtolower(trim((string) $page->slug, '/')) => $url] : [];
                })
                ->all();

            $resolve = function (?string $url) use ($website, $preview, $pageTargets): string {
                $raw = trim((string) $url);
                if ($raw === '' || $raw === '#') return $raw === '' ? '#' : $raw;
                if (str_starts_with($raw, '#') || preg_match('#^(?:https?:)?//#i', $raw) || preg_match('#^(?:mailto|tel):#i', $raw)) return $raw;

                $path = trim((string) parse_url($raw, PHP_URL_PATH), '/');
                if ($path === '' || strtolower($path) === 'home') return (string) $preview->url($website);

                $first = strtolower(explode('/', $path)[0] ?? '');
                if (in_array($first, ['shop', 'product', 'cart', 'checkout', 'account', 'order', 'order-lookup'], true)) {
                    return (string) $preview->url($website, $path);
                }

                $key = strtolower($path);
                if (isset($pageTargets[$key])) return $pageTargets[$key];
                $last = strtolower(basename($path));
                if (isset($pageTargets[$last])) return $pageTargets[$last];

                return (string) $preview->url($website, $path);
            };

            $mapMenu = function (array $items) use (&$mapMenu, $resolve): array {
                return array_values(array_map(function ($item) use (&$mapMenu, $resolve) {
                    if (! is_array($item)) return $item;
                    $item['url'] = $resolve($item['url'] ?? '#');
                    $item['children'] = $mapMenu(is_array($item['children'] ?? null) ? $item['children'] : []);
                    return $item;
                }, $items));
            };

            $header['menu'] = $mapMenu(is_array($header['menu'] ?? null) ? $header['menu'] : []);
            if (array_key_exists('cta_url', $header)) $header['cta_url'] = $resolve($header['cta_url']);
        }

        $pageStyle = strtolower(trim((string) ($website->page_style ?: $website->published_page_style ?: 'auto')));
        $shellContext = ['page_style' => $pageStyle];

        return [
            'header' => is_array($header) ? CmsHtmlCompiler::compile([$header], $primaryColor, $shellContext) : '',
            'footer' => is_array($footer) ? CmsHtmlCompiler::compile([$footer], $primaryColor, $shellContext) : '',
        ];
    }

    private function normalizePalette(array $palette, string $fallbackTheme): array
    {
        $fallback = $this->themes->palette($fallbackTheme);
        $pick = function (string $key, string $fallbackHex) use ($palette): string {
            $value = strtoupper(trim((string) ($palette[$key] ?? '')));
            return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : $fallbackHex;
        };

        return [
            'primary' => $pick('primary', $fallback['primary']),
            'accent' => $pick('accent', $fallback['accent']),
            'surface' => $pick('surface', $fallback['surface']),
            'text' => $pick('text', $fallback['text']),
            'muted' => $pick('muted', '#64748B'),
            'border' => $pick('border', '#E2E8F0'),
            'background' => $pick('background', '#FFFFFF'),
        ];
    }

    private function checkoutIdempotencyKey(Website $website, Request $request): string
    {
        $sessionKey = 'cosmic_commerce_checkout_intent.'.$website->id;
        $value = (string) $request->session()->get($sessionKey, '');

        if (! Str::isUuid($value)) {
            $value = (string) Str::uuid();
            $request->session()->put($sessionKey, $value);
        }

        return $value;
    }

    private function money(?int $minor, string $currency, array $meta): string
    {
        if ($minor === null) {
            return 'Price unavailable';
        }

        $decimals = max(0, (int) ($meta['decimals'] ?? 2));
        $major = $minor / (10 ** $decimals);
        $formatted = number_format($major, $decimals, '.', ',');
        $symbol = (string) ($meta['symbol'] ?? $currency.' ');

        return $symbol.$formatted;
    }

    private function sortPrice(CommerceProduct $product): int
    {
        $range = $product->variantPriceRangeMinor();
        return $range['min'] ?? PHP_INT_MAX;
    }

    private function assetUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || str_starts_with($url, 'data:')) return $url;

        if (preg_match('#^(?:https?:)?//([^/]+)(/.*)?$#i', $url, $matches)) {
            $host = strtolower(preg_replace('/:\d+$/', '', $matches[1]) ?? $matches[1]);
            if (! in_array($host, ['localhost', '127.0.0.1', '::1'], true) && ! str_ends_with($host, '.local')) {
                return $url;
            }
            $url = $matches[2] ?? '/';
        }

        $base = rtrim((string) config('services.cosmic.asset_base_url', config('app.url')), '/');
        return $base.'/'.ltrim(str_replace('\\', '/', $url), '/');
    }

    private function url(string $previewSlug, string $path): string
    {
        $path = '/'.ltrim($path, '/');
        if (config('cosmic_preview.mode') === 'local') {
            return '/preview/'.rawurlencode($previewSlug).$path;
        }

        return $path;
    }

    private function isCommercePath(string $path): bool
    {
        return $path === 'shop'
            || $path === 'cart'
            || $path === 'checkout'
            || in_array($path, ['account', 'account/login', 'account/register', 'order-lookup'], true)
            || preg_match('#^order/[0-9a-f-]{36}(?:/success)?$#i', $path) === 1
            || str_starts_with($path, 'shop/category/')
            || str_starts_with($path, 'product/');
    }
}
