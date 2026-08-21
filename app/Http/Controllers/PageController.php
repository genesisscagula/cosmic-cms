<?php

namespace App\Http\Controllers;

use App\Services\MyBrandThemeService;
use App\Helpers\CmsHtmlCompiler;
use App\Models\Website;
use App\Models\Page;
use App\Models\TrialGeneration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\PagePublisher;
use App\Services\PreviewDeploymentService;
use App\Services\CreditService;
use App\Services\ThemePlanAccessService;
use App\Services\MediaAssetLifecycleService;
use App\Services\MediaAssetSafetyService;
use App\Services\WebsiteHealthService;
use App\Services\TrialCreditService;
use App\Support\PageStyleRegistry;
use App\Services\BlogSparkRegistry;
use App\Cosmic\Pricing\ActionPricing;
use App\Cosmic\Pricing\BlockPricingRegistry;
use App\Cosmic\Pricing\ThemePricingRegistry;
use App\Models\CosmicUnlock;
use Throwable;

class PageController extends Controller
{
    public function index(Website $website)
    {
        $this->authorize('view', $website);

        $user = request()->user();
        if ($user && app(\App\Services\WorkspaceAccessService::class)->websiteRole($user, $website) === 'website_editor') {
            $firstPage = $website->pages()->orderBy('sort_order')->orderBy('id')->firstOrFail();
            return redirect()->route('pages.builder', $firstPage);
        }

        return \Inertia\Inertia::render('Websites/Index', [
            'website' => $website,
            'pages' => $website->pages()->orderBy('parent_id')->orderBy('sort_order')->orderBy('id')->get(),
            'inquiryCount' => $website->contactSubmissions()->whereNull('archived_at')->count(),
            'recentInquiries' => $website->contactSubmissions()
                ->whereNull('archived_at')
                ->latest('received_at')
                ->limit(25)
                ->get(),
            // DIRETSO KORREKTE HANDSHAKE PACKET NGADTO SA REACT
            'globalHeaderBlock' => $website->global_header,
            'globalFooterBlock' => $website->global_footer,
            'commerce' => $this->commerceWorkspacePayload($website),
            'contentWorkspace' => ContentWorkspaceController::payload($website),
        ]);
    }

    public function store(Request $request, Website $website, CreditService $credits)
    {
        $this->authorize('update', $website);

        $request->validate([
            'title' => 'required|string|max:255',
            'page_type' => 'nullable|in:standard,blog',
            'parent_id' => 'nullable|integer',
        ]);

        $slug = Str::slug($request->title);
        $pageType = $request->input('page_type', 'standard');

        $reservedCommerceSlugs = ['shop', 'cart', 'checkout', 'account', 'order', 'product'];
        if (in_array(strtolower($slug), $reservedCommerceSlugs, true)) {
            throw ValidationException::withMessages([
                'title' => "The /{$slug} URL is reserved for Cosmic Commerce. Choose a different page title.",
            ]);
        }

        $parent = null;
        if ($request->filled('parent_id')) {
            $parent = $website->pages()->with('parent')->findOrFail($request->integer('parent_id'));
            abort_if($parent->parent?->parent_id !== null, 422, 'Pages can only be nested three levels deep.');
        }

        $reference = 'add-page-' . Str::uuid();
        $pageCost = ActionPricing::ADD_PAGE;
        if ($pageCost > 0) {
            $credits->consume(
                $request->user(),
                $pageCost,
                'Add page: ' . $request->title,
                $website,
                $reference,
                ['page_type' => $pageType],
            );
        }

        try {
            DB::transaction(function () use ($website, $request, $slug, $pageType, $parent) {
                $page = $website->pages()->create([
                    'title' => $request->title,
                    'slug' => $slug,
                    'parent_id' => $parent?->id,
                    'sort_order' => ((int) $website->pages()->where('parent_id', $parent?->id)->max('sort_order')) + 1,
                    'page_type' => $pageType,
                    'status' => 'draft',
                    'blocks' => $pageType === 'blog' ? $this->createBlogPageBlocks() : [],
                ]);

                if ($pageType === 'blog') {
                    $this->createStarterBlogPosts($website, $page);
                }
            });
        } catch (Throwable $exception) {
            if ($pageCost > 0) {
                $credits->refund(
                    $request->user(),
                    $pageCost,
                    'Refund for failed page creation',
                    $website,
                    $reference . '-refund',
                );
            }
            throw $exception;
        }

        return back()->with('success', $pageCost > 0
            ? 'Page created for ' . $pageCost . ' Cosmic Credits.'
            : 'Page created successfully.');
    }

    public function updateTitle(Request $request, Website $website, Page $page)
    {
        $this->authorize('update', $website);
        abort_unless($page->website_id === $website->id, 404);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $page->update([
            'title' => trim($validated['title']),
        ]);

        return response()->json([
            'message' => 'Page title updated successfully.',
            'page' => $page->fresh(['parent']),
        ]);
    }

    public function clonePage(Request $request, Website $website, Page $page, CreditService $credits)
    {
        $this->authorize('update', $website);
        abort_unless($page->website_id === $website->id, 404);

        $baseTitle = trim((string) ($page->title ?: 'Untitled Page'));
        $copyTitle = $this->uniquePageCopyTitle($website, $baseTitle);
        $copySlug = $this->uniquePageSlug($website, Str::slug($copyTitle) ?: 'page-copy');
        $reference = 'clone-page-' . Str::uuid();
        $pageCost = ActionPricing::ADD_PAGE;

        if ($pageCost > 0) {
            $credits->consume(
                $request->user(),
                $pageCost,
                'Clone page: ' . $baseTitle,
                $website,
                $reference,
                ['source_page_id' => $page->id, 'page_type' => $page->page_type],
            );
        }

        try {
            $copy = DB::transaction(function () use ($website, $page, $copyTitle, $copySlug) {
                $copy = $website->pages()->create([
                    'title' => $copyTitle,
                    'slug' => $copySlug,
                    'parent_id' => $page->parent_id,
                    'sort_order' => ((int) $website->pages()->where('parent_id', $page->parent_id)->max('sort_order')) + 1,
                    'page_type' => $page->page_type,
                    'page_style' => $page->page_style,
                    'blocks' => $page->blocks ?? [],
                    'status' => 'draft',
                    'published_blocks' => null,
                    'published_html' => null,
                    'published_page_style' => null,
                    'published_at' => null,
                    'last_published_at' => null,
                    'publish_error' => null,
                ]);

                if ($page->page_type === 'blog') {
                    $website->blogPosts()->where('page_id', $page->id)->orderBy('id')->get()->each(function ($post) use ($website, $copy) {
                        $baseSlug = Str::slug($post->slug ?: $post->title ?: 'post') ?: 'post';
                        $candidate = $baseSlug;
                        $suffix = 2;
                        while ($website->blogPosts()->where('slug', $candidate)->exists()) {
                            $candidate = $baseSlug . '-' . $suffix++;
                        }

                        $website->blogPosts()->create([
                            'page_id' => $copy->id,
                            'title' => $post->title,
                            'slug' => $candidate,
                            'excerpt' => $post->excerpt,
                            'content' => $post->content,
                            'category' => $post->category,
                            'tags' => $post->tags,
                            'image_url' => $post->image_url,
                            'is_featured' => $post->is_featured,
                            'status' => 'draft',
                            'published_at' => null,
                        ]);
                    });
                }

                return $copy;
            });
        } catch (Throwable $exception) {
            if ($pageCost > 0) {
                $credits->refund(
                    $request->user(),
                    $pageCost,
                    'Refund for failed page clone',
                    $website,
                    $reference . '-refund',
                );
            }
            throw $exception;
        }

        return response()->json([
            'message' => 'Page cloned successfully.',
            'page' => $copy,
            'credit_balance' => $credits->balance($request->user()),
        ]);
    }

    private function uniquePageCopyTitle(Website $website, string $baseTitle): string
    {
        $candidate = $baseTitle . ' Copy';
        $suffix = 2;

        while ($website->pages()->whereRaw('LOWER(title) = ?', [mb_strtolower($candidate)])->exists()) {
            $candidate = $baseTitle . ' Copy ' . $suffix++;
        }

        return $candidate;
    }

    private function uniquePageSlug(Website $website, string $baseSlug): string
    {
        $candidate = $baseSlug;
        $suffix = 2;

        while ($website->pages()->where('slug', $candidate)->exists()) {
            $candidate = $baseSlug . '-' . $suffix++;
        }

        return $candidate;
    }

    public function destroy(Website $website, Page $page)
    {
        $this->authorize('update', $website);

        abort_unless($page->website_id === $website->id, 404);

        // Blog posts belong to their page. Remove posts for the whole branch
        // before the database cascade removes child pages.
        $pageIds = $this->descendantPageIds($website, $page);
        $website->blogPosts()->whereIn('page_id', $pageIds)->delete();
        $page->delete();

        return response()->json([
            'message' => 'Page deleted successfully.',
        ]);
    }

    private function descendantPageIds(Website $website, Page $page): array
    {
        $allPages = $website->pages()->get(['id', 'parent_id']);
        $ids = [$page->id];

        do {
            $before = count($ids);
            foreach ($allPages as $candidate) {
                if ($candidate->parent_id !== null && in_array($candidate->parent_id, $ids, true) && ! in_array($candidate->id, $ids, true)) {
                    $ids[] = $candidate->id;
                }
            }
        } while (count($ids) !== $before);

        return $ids;
    }

    private function commerceWorkspacePayload(Website $website): array
    {
        $user = request()->user();
        $capabilities = app(\App\Services\CommerceCapabilityService::class);
        $allowed = $user ? $capabilities->planAllowsCommerce($user) : false;
        $settings = $allowed ? $capabilities->settingsFor($website) : $website->commerceSetting()->first();

        $currency = $settings?->currency ?: config('cosmic-commerce.default_currency', 'USD');
        $currencyDecimals = (int) config('cosmic-commerce.currencies.'.strtoupper($currency).'.decimals', 2);
        $currencyScale = 10 ** max(0, $currencyDecimals);
        $previewService = app(\App\Services\PreviewDeploymentService::class);
        $storefrontUrl = filled($website->preview_slug) ? $previewService->url($website, 'shop') : null;
        $seenOrderIds = collect((array) data_get($settings?->settings, 'seen_order_ids', []))->map(fn ($id) => (int) $id)->filter()->values()->all();

        $commerceMediaUrl = static function (?string $url) use ($website): string {
            $url = trim((string) $url);
            if ($url === '') return '';
            $path = preg_match('#^https?://#i', $url) ? (string) parse_url($url, PHP_URL_PATH) : $url;
            if (preg_match('#^/storage/websites/'.preg_quote((string) $website->id, '#').'/commerce/([A-Za-z0-9._-]+)$#', $path, $match)) {
                return route('commerce.media.show', ['website' => $website->id, 'filename' => $match[1]], false);
            }
            return $url;
        };

        $products = $website->commerceProducts()
            ->with(['images', 'categories', 'options.values', 'variants.values.option'])
            ->latest('updated_at')
            ->get()
            ->map(function (\App\Models\CommerceProduct $product) use ($currencyScale, $currencyDecimals, $previewService, $website, $commerceMediaUrl): array {
                return [
                    'id' => $product->id,
                    'public_id' => $product->public_id,
                    'title' => $product->title,
                    'slug' => $product->slug,
                    'storefront_url' => filled($website->preview_slug) && filled($product->slug)
                        ? $previewService->url($website, 'product/'.$product->slug)
                        : null,
                    'type' => $product->type,
                    'fulfillment_type' => $product->fulfillment_type,
                    'status' => $product->status,
                    'visibility' => $product->visibility,
                    'short_description' => $product->short_description,
                    'description' => $product->description,
                    'regular_price' => $product->regular_price_minor === null ? '' : number_format($product->regular_price_minor / $currencyScale, $currencyDecimals, '.', ''),
                    'sale_price' => $product->sale_price_minor === null ? '' : number_format($product->sale_price_minor / $currencyScale, $currencyDecimals, '.', ''),
                    // Builder Commerce Sparks consume the same minor-unit values used by checkout.
                    'regular_price_minor' => $product->regular_price_minor,
                    'sale_price_minor' => $product->sale_price_minor,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'track_inventory' => (bool) $product->track_inventory,
                    'stock_quantity' => $product->stock_quantity,
                    'low_stock_threshold' => $product->low_stock_threshold,
                    'is_low_stock' => (bool) ($product->track_inventory && $product->stock_quantity !== null && $product->low_stock_threshold !== null && $product->stock_quantity <= $product->low_stock_threshold),
                    'allow_backorders' => (bool) $product->allow_backorders,
                    'stock_status' => $product->stock_status,
                    'weight_grams' => $product->weight_grams,
                    'length_mm' => $product->length_mm,
                    'width_mm' => $product->width_mm,
                    'height_mm' => $product->height_mm,
                    'shipping_class' => $product->shipping_class,
                    'taxable' => (bool) $product->taxable,
                    'tax_class' => $product->tax_class ?: '',
                    'is_featured' => (bool) $product->is_featured,
                    'featured_image_url' => $commerceMediaUrl($product->featured_image_url),
                    'featured_image_alt' => $product->featured_image_alt,
                    'seo_title' => $product->seo_title,
                    'seo_description' => $product->seo_description,
                    'category_ids' => $product->categories->pluck('id')->values(),
                    'categories' => $product->categories->map(fn ($category) => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ])->values(),
                    'primary_category_id' => optional($product->categories->firstWhere('pivot.is_primary', true))->id,
                    'gallery' => $product->images->map(fn ($image) => ['url' => $commerceMediaUrl($image->url), 'alt_text' => $image->alt_text])->values(),
                    'images' => $product->images->map(fn ($image) => ['url' => $commerceMediaUrl($image->url), 'image_url' => $commerceMediaUrl($image->url), 'alt_text' => $image->alt_text])->values(),
                    'options' => $product->options->map(fn ($option) => [
                        'id' => $option->id,
                        'name' => $option->name,
                        'display_type' => $option->display_type,
                        'values' => $option->values->where('is_active', true)->map(fn ($value) => ['id' => $value->id, 'label' => $value->label, 'swatch_hex' => $value->swatch_hex])->values(),
                    ])->values(),
                    'variants' => $product->variants->map(fn ($variant) => [
                        'id' => $variant->id,
                        'label' => $variant->optionLabel(),
                        'sku' => $variant->sku,
                        'regular_price' => $variant->regular_price_minor === null ? '' : number_format($variant->regular_price_minor / $currencyScale, $currencyDecimals, '.', ''),
                        'sale_price' => $variant->sale_price_minor === null ? '' : number_format($variant->sale_price_minor / $currencyScale, $currencyDecimals, '.', ''),
                        'track_inventory' => (bool) $variant->track_inventory,
                        'stock_quantity' => $variant->stock_quantity,
                        'low_stock_threshold' => $variant->low_stock_threshold,
                        'is_low_stock' => (bool) ($variant->track_inventory && $variant->stock_quantity !== null && $variant->low_stock_threshold !== null && $variant->stock_quantity <= $variant->low_stock_threshold),
                        'allow_backorders' => (bool) $variant->allow_backorders,
                        'stock_status' => $variant->stock_status,
                        'image_url' => $commerceMediaUrl($variant->image_url),
                        'is_enabled' => (bool) $variant->is_enabled,
                        'is_default' => (bool) $variant->is_default,
                    ])->values(),
                ];
            })->values();

        return [
            'allowed' => $allowed,
            'enabled' => $allowed && (bool) ($settings?->enabled ?? false),
            'storefront_url' => $storefrontUrl,
            'runtime_urls' => [
                'shop' => $storefrontUrl,
                'cart' => filled($website->preview_slug) ? $previewService->url($website, 'cart') : null,
                'checkout' => filled($website->preview_slug) ? $previewService->url($website, 'checkout') : null,
                'account' => filled($website->preview_slug) ? $previewService->url($website, 'account') : null,
            ],
            'currency' => $currency,
            'paypal_receiver_email' => $settings ? $capabilities->configuredPaypalReceiverEmail($website) : null,
            'paypal_receiver_effective_email' => $settings ? $capabilities->paypalReceiverEmail($website) : (string) config('cosmic-commerce.default_paypal_receiver_email', config('cosmic.platform_owner_email')),
            'paypal_receiver_default_email' => (string) config('cosmic-commerce.default_paypal_receiver_email', config('cosmic.platform_owner_email')),
            'templates' => (array) data_get($settings?->settings, 'commerce_templates', []),
            'currency_decimals' => $currencyDecimals,
            'currencies' => collect(config('cosmic-commerce.currencies', []))->map(fn ($config, $code) => [
                'code' => $code,
                'symbol' => $config['symbol'] ?? $code,
                'decimals' => $config['decimals'] ?? 2,
            ])->values(),
            'pages' => collect(['shop', 'cart', 'checkout', 'account', 'order'])->map(function (string $slug) use ($website, $previewService) {
                $page = $slug === 'shop'
                    ? $website->pages()->where('slug', 'shop')->first()
                    : $website->pages()->where('page_type', 'commerce')->where('slug', $slug)->first();
                return [
                    'slug' => $slug,
                    'title' => match ($slug) {
                        'shop' => 'Shop',
                        'cart' => 'Cart',
                        'checkout' => 'Checkout',
                        'account' => 'Account',
                        default => 'Order Lookup',
                    },
                    'installed' => (bool) $page,
                    'url' => filled($website->preview_slug) ? $previewService->url($website, $slug) : null,
                ];
            })->values(),
            'products' => $products,
            'categories' => $website->commerceProductCategories()->orderBy('sort_order')->orderBy('name')->get()->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'description' => $category->description ?? '',
                'image_url' => $commerceMediaUrl($category->image_url),
                'image_alt' => $category->image_alt ?? '',
                'seo_title' => $category->seo_title ?? '',
                'seo_description' => $category->seo_description ?? '',
                'sort_order' => (int) $category->sort_order,
                'is_visible' => (bool) $category->is_visible,
                'storefront_url' => filled($website->preview_slug) && filled($category->slug)
                    ? $previewService->url($website, 'shop/category/'.$category->slug)
                    : null,
            ])->values(),
            'countries' => collect(config('cosmic-commerce.countries', []))->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values(),
            'tax_enabled' => (bool) ($settings?->tax_enabled ?? false),
            'prices_include_tax' => (bool) ($settings?->prices_include_tax ?? false),
            'tax_rules' => $website->commerceTaxRules()->get()->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'country_code' => $rule->country_code ?: '',
                'region_code' => $rule->region_code ?: '',
                'tax_class' => $rule->tax_class ?: '',
                'rate_percent' => number_format(((int) $rule->rate_basis_points) / 100, 2, '.', ''),
                'tax_shipping' => (bool) $rule->tax_shipping,
                'is_enabled' => (bool) $rule->is_enabled,
                'priority' => (int) $rule->priority,
            ])->values(),
            'coupons' => $website->commerceCoupons()->withCount('usages')->latest('updated_at')->get()->map(fn ($coupon) => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name ?: '',
                'discount_type' => $coupon->discount_type,
                'percent' => $coupon->percent_basis_points === null ? '' : number_format(((int) $coupon->percent_basis_points) / 100, 2, '.', ''),
                'fixed_amount' => $coupon->fixed_amount_minor === null ? '' : number_format(((int) $coupon->fixed_amount_minor) / $currencyScale, $currencyDecimals, '.', ''),
                'minimum_spend' => $coupon->minimum_spend_minor === null ? '' : number_format(((int) $coupon->minimum_spend_minor) / $currencyScale, $currencyDecimals, '.', ''),
                'usage_limit' => $coupon->usage_limit ?? '',
                'usage_limit_per_email' => $coupon->usage_limit_per_email ?? '',
                'usage_count' => (int) $coupon->usages_count,
                'starts_at' => $coupon->starts_at?->format('Y-m-d\TH:i'),
                'expires_at' => $coupon->expires_at?->format('Y-m-d\TH:i'),
                'is_enabled' => (bool) $coupon->is_enabled,
                'product_ids' => array_values($coupon->product_ids ?? []),
                'category_ids' => array_values($coupon->category_ids ?? []),
            ])->values(),
            'orders' => $website->commerceOrders()->with(['items.product:id,title,featured_image_url', 'refunds', 'events' => fn ($query) => $query->limit(80)])->latest('created_at')->limit(200)->get()->map(fn ($order) => [
                'id' => $order->id,
                'is_unread' => in_array($order->payment_status, ['paid', 'partially_refunded'], true) && ! in_array((int) $order->id, $seenOrderIds, true),
                'public_id' => $order->public_id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'refund_status' => $order->refund_status ?: 'none',
                'refunded_minor' => (int) $order->refunded_minor,
                'payment_provider' => $order->payment_provider,
                'external_checkout_id' => $order->external_checkout_id,
                'external_payment_id' => $order->external_payment_id,
                'currency' => $order->currency,
                'subtotal_minor' => (int) $order->subtotal_minor,
                'discount_minor' => (int) ($order->discount_minor ?? 0),
                'coupon_code' => $order->coupon_code,
                'shipping_minor' => (int) $order->shipping_minor,
                'tax_minor' => (int) $order->tax_minor,
                'total_minor' => (int) $order->total_minor,
                'shipping_method' => $order->shipping_method,
                'customer_email' => $order->customer_email,
                'customer_first_name' => $order->customer_first_name,
                'customer_last_name' => $order->customer_last_name,
                'billing_address' => $order->billing_address,
                'shipping_address' => $order->shipping_address,
                'admin_note' => $order->admin_note,
                'tracking_carrier' => $order->tracking_carrier,
                'tracking_number' => $order->tracking_number,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'payment_attempted_at' => $order->payment_attempted_at?->toIso8601String(),
                'checkout_expires_at' => $order->checkout_expires_at?->toIso8601String(),
                'payment_recovery_attempts' => (int) ($order->payment_recovery_attempts ?? 0),
                'payment_recovery_last_attempt_at' => $order->payment_recovery_last_attempt_at?->toIso8601String(),
                'payment_recovery_next_attempt_at' => $order->payment_recovery_next_attempt_at?->toIso8601String(),
                'payment_recovered_at' => $order->payment_recovered_at?->toIso8601String(),
                'payment_attention_required_at' => $order->payment_attention_required_at?->toIso8601String(),
                'payment_recovery_last_error' => $order->payment_recovery_last_error,
                'payment_completion_source' => data_get($order->metadata, 'payment_completion_source'),
                'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
                'cancelled_at' => $order->cancelled_at?->toIso8601String(),
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'order_confirmation_sent_at' => $order->order_confirmation_sent_at?->toIso8601String(),
                'merchant_notification_sent_at' => $order->merchant_notification_sent_at?->toIso8601String(),
                'last_customer_notification_at' => $order->last_customer_notification_at?->toIso8601String(),
                'notification_attempts' => (int) ($order->notification_attempts ?? 0),
                'notification_next_attempt_at' => $order->notification_next_attempt_at?->toIso8601String(),
                'notification_last_error' => $order->notification_last_error,
                'notification_attention_required_at' => $order->notification_attention_required_at?->toIso8601String(),
                'inventory_attention_required' => (bool) data_get($order->metadata, 'inventory_attention_required', false),
                'inventory_restocked_at' => data_get($order->metadata, 'inventory_restocked_at'),
                'inventory_restocked_quantity' => (int) data_get($order->metadata, 'inventory_restocked_quantity', 0),
                'refunds' => $order->refunds->map(fn ($refund) => [
                    'id' => $refund->id,
                    'external_refund_id' => $refund->external_refund_id,
                    'amount_minor' => (int) $refund->amount_minor,
                    'currency' => $refund->currency,
                    'status' => $refund->status,
                    'reason' => $refund->reason,
                    'refunded_at' => $refund->refunded_at?->toIso8601String(),
                ])->values(),
                'events' => $order->events->map(fn ($event) => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'actor_type' => $event->actor_type,
                    'source' => $event->source,
                    'title' => $event->title,
                    'description' => $event->description,
                    'changes' => $event->changes,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])->values(),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'sku' => $item->sku,
                    'option_label' => $item->option_label,
                    'image_url' => $commerceMediaUrl($item->image_url ?: $item->product?->featured_image_url),
                    'quantity' => (int) $item->quantity,
                    'unit_price_minor' => (int) $item->unit_price_minor,
                    'tax_minor' => (int) $item->tax_minor,
                    'line_total_minor' => (int) $item->line_total_minor,
                ])->values(),
            ])->values(),
            'payment_health' => [
                'pending' => $website->commerceOrders()->whereIn('payment_status', ['pending', 'cancelled'])->count(),
                'stale' => $website->commerceOrders()->whereIn('payment_status', ['pending', 'cancelled'])->where('updated_at', '<=', now()->subMinutes(max(5, (int) config('cosmic-commerce.payment_recovery.stale_after_minutes', 30))))->count(),
                'needs_attention' => $website->commerceOrders()->whereIn('payment_status', ['pending', 'cancelled'])->where(function ($query) {
                    $query->whereNotNull('payment_attention_required_at')
                        ->orWhere('payment_recovery_attempts', '>=', max(1, (int) config('cosmic-commerce.payment_recovery.attention_after_attempts', 6)));
                })->count(),
                'with_errors' => $website->commerceOrders()->whereIn('payment_status', ['pending', 'cancelled'])->whereNotNull('payment_recovery_last_error')->count(),
            ],
            'inventory' => [
                'tracked_products' => $website->commerceProducts()->where('track_inventory', true)->count(),
                'out_of_stock' => $website->commerceProducts()->where('track_inventory', true)->where('stock_quantity', '<=', 0)->where('allow_backorders', false)->count()
                    + \App\Models\CommerceProductVariant::query()->where('website_id', $website->id)->where('track_inventory', true)->where('stock_quantity', '<=', 0)->where('allow_backorders', false)->count(),
                'low_stock' => $website->commerceProducts()->where('track_inventory', true)->whereNotNull('low_stock_threshold')->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->where('stock_quantity', '>', 0)->count()
                    + \App\Models\CommerceProductVariant::query()->where('website_id', $website->id)->where('track_inventory', true)->whereNotNull('low_stock_threshold')->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->where('stock_quantity', '>', 0)->count(),
                'recent_adjustments' => \App\Models\CommerceInventoryAdjustment::query()->where('website_id', $website->id)->with(['product:id,title', 'variant:id,product_id,sku'])->latest()->limit(20)->get()->map(fn ($adjustment) => [
                    'id' => $adjustment->id,
                    'product_id' => $adjustment->commerce_product_id,
                    'variant_id' => $adjustment->commerce_product_variant_id,
                    'product_title' => $adjustment->product?->title ?: 'Deleted product',
                    'variant_sku' => $adjustment->variant?->sku,
                    'reason' => $adjustment->reason,
                    'quantity_delta' => (int) $adjustment->quantity_delta,
                    'quantity_before' => $adjustment->quantity_before,
                    'quantity_after' => $adjustment->quantity_after,
                    'note' => $adjustment->note,
                    'created_at' => $adjustment->created_at?->toIso8601String(),
                ])->values(),
            ],
            'shipping_zones' => $website->commerceShippingZones()->with('rates')->get()->map(fn ($zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'countries' => array_values((array) $zone->countries),
                'is_rest_of_world' => (bool) $zone->is_rest_of_world,
                'is_enabled' => (bool) $zone->is_enabled,
                'priority' => (int) $zone->priority,
                'rates' => $zone->rates->map(fn ($rate) => [
                    'id' => $rate->id,
                    'name' => $rate->name,
                    'rate' => number_format(((int) $rate->rate_minor) / $currencyScale, $currencyDecimals, '.', ''),
                    'free_above' => $rate->free_above_minor === null ? '' : number_format(((int) $rate->free_above_minor) / $currencyScale, $currencyDecimals, '.', ''),
                    'is_enabled' => (bool) $rate->is_enabled,
                    'sort_order' => (int) $rate->sort_order,
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * Give each Posts / updates page a useful, editable starting point.
     * They deliberately remain drafts: nothing reaches a live website until
     * the customer reviews and explicitly publishes it.
     */
    private function createStarterBlogPosts(Website $website, Page $page): void
    {
        $starters = [
            [
                'title' => 'A practical guide to getting started',
                'category' => 'Featured',
                'excerpt' => 'A useful first article that introduces your perspective and gives visitors a reason to explore more.',
                'content' => 'Use this featured article to share a helpful point of view, introduce an important update, or explain the value your business brings to customers.',
                'image_url' => '/storage/cms-images/background/background-1.avif',
                'is_featured' => true,
            ],
            [
                'title' => 'What customers should know first',
                'category' => 'Insights',
                'excerpt' => 'Answer a common question with a short, clear explanation your audience can trust.',
                'content' => 'Start with the context your customer needs, then explain the practical next step in plain language.',
                'image_url' => '/storage/cms-images/background/background-2.avif',
            ],
            [
                'title' => 'A closer look at our approach',
                'category' => 'How we work',
                'excerpt' => 'Share the process, standards, or ideas behind the work you do every day.',
                'content' => 'Describe your approach in a way that makes your service easier to understand and more credible.',
                'image_url' => '/storage/cms-images/background/background-3.avif',
            ],
            [
                'title' => 'Updates worth sharing',
                'category' => 'Updates',
                'excerpt' => 'Keep visitors informed with news, announcements, or practical changes from your team.',
                'content' => 'Use this post for an announcement, a timely update, or an important piece of information for your customers.',
                'image_url' => '/storage/cms-images/background/background-5.avif',
            ],
            [
                'title' => 'Ideas for your next step',
                'category' => 'Guides',
                'excerpt' => 'Offer a focused recommendation that helps readers take action with confidence.',
                'content' => 'Give readers a practical takeaway they can use right away, then invite them to contact you for help.',
                'image_url' => '/storage/cms-images/background/background-1.avif',
            ],
        ];

        foreach ($starters as $index => $starter) {
            $website->blogPosts()->create([
                ...$starter,
                'page_id' => $page->id,
                // blog_posts.slug is currently globally unique. A customer can
                // create more than one Posts / updates page, so fixed starter
                // titles must not reuse a slug left by an earlier blog hub.
                'slug' => $this->uniqueBlogPostSlug(
                    Str::slug($starter['title']) . '-' . ($index + 1)
                ),
                'tags' => [],
                'status' => 'draft',
            ]);
        }
    }

    /**
     * Keep seeded post slugs compatible with the existing global unique index.
     */
    private function uniqueBlogPostSlug(string $baseSlug): string
    {
        $slug = $baseSlug ?: 'post';
        $suffix = 2;

        while (\App\Models\BlogPost::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Keep a Posts / updates page focused and predictable. The primary mini
     * hero introduces the page, BlogHub contains the featured post and four
     * article cards, and the two blocks after it complete the editorial page.
     */
    private function createBlogPageBlocks(): array
    {
        // Randomization happens only here, during the initial creation of a
        // Posts / updates page. The selected layout_variant is persisted in
        // the block payload, so edit, save, publish, refresh, and theme changes
        // never re-roll the customer's composition.
        return [
            [
                'type' => 'blog_mini_hero',
                'theme' => 'primary',
                'layout_variant' => BlogSparkRegistry::randomVariant('blog_mini_hero')
                    ?? 'mini-header-01',
            ],
            [
                'type' => 'blog_hub',
                'theme' => 'editorial',
                'show_intro' => false,
                'layout_variant' => BlogSparkRegistry::randomVariant('blog_hub')
                    ?? 'blog-cards-01',
            ],
            [
                'type' => 'newsletter_cta',
                'theme' => 'primary',
                'layout_variant' => BlogSparkRegistry::randomVariant('newsletter_cta')
                    ?? 'newsletter-01',
            ],
            [
                'type' => 'latest_resources',
                'theme' => 'white',
                'layout_variant' => BlogSparkRegistry::randomVariant('latest_resources')
                    ?? 'resources-01',
            ],
        ];
    }

    public function builder(Request $request, Page $page)
    {
        $trial = $this->resolveTrialAccess($request, $page);
        $isTrialMode = $trial !== null;

        if (! $isTrialMode) {
            $this->authorize('editBuilder', $page->website);
        }

        $website = $page->website;

        // A public trial must render with the theme selected for that trial,
        // never with the shared demo website's current theme. Clone the model
        // so this request-only override cannot mutate Website #14.
        if ($isTrialMode) {
            $website = clone $website;
            $website->setAttribute('theme_settings', $trial->preview_theme ?? [
                'primary' => 'midnight',
                'secondary' => 'white',
                'tertiary' => 'stone',
                'auto' => true,
            ]);
        }

        $websiteMessaging = $page->website->pages()
            ->get(['title', 'blocks'])
            ->flatMap(function (Page $websitePage) {
                return collect($websitePage->blocks ?? [])
                    ->map(function ($block) {
                        if (! is_array($block)) {
                            return null;
                        }

                        return collect([
                            $block['tagline'] ?? null,
                            $block['eyebrow'] ?? null,
                            $block['heading'] ?? null,
                            $block['text'] ?? null,
                            $block['description'] ?? null,
                        ])
                            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                            ->implode(' ');
                    })
                    ->filter();
            })
            ->take(8)
            ->implode(' ');

        $profileContext = array_filter([
            $website->industry ? "Industry: {$website->industry}." : null,
            $website->location ? "Location: {$website->location}." : null,
            $website->business_description ? "Business description: {$website->business_description}" : null,
        ]);

        $websiteContext = trim(implode("\n", array_filter([
            'Generate professional website content for the following business.',
            "Business Name: {$website->name}",
            ...$profileContext,
            "Page: {$page->title}",
            'Language: English.',
            'Write naturally and professionally. Do not invent awards, certifications, employee names, years of experience, customer statistics, or other unverifiable facts.',
            $websiteMessaging !== '' ? "Existing website messaging: {$websiteMessaging}" : null,
        ])));

        $builderThemeAccess = null;
        if ($isTrialMode) {
            // H14: direct token Builder links must also always expose the
            // persistent My Brand Theme, including older trials created before H14.
            $themeSettings = is_array($trial->preview_theme) ? $trial->preview_theme : [];
            $seededThemeSettings = app(MyBrandThemeService::class)->ensureInSettings($themeSettings);
            if ($seededThemeSettings !== $themeSettings) {
                $trial->update(['preview_theme' => $seededThemeSettings]);
                $trial->setAttribute('preview_theme', $seededThemeSettings);
            }

            // Guest trials intentionally expose only three themes. Always include
            // the theme selected during generation so the active theme never
            // appears locked, then fill the remaining slots with curated defaults.
            $activeTrialTheme = (string) data_get($trial->preview_theme, 'primary', 'midnight');
            $savedTrialThemeKeys = data_get($trial->preview_theme, 'trial_theme_keys');
            $trialThemeKeys = is_array($savedTrialThemeKeys) && count($savedTrialThemeKeys) === 3
                ? array_values(array_unique(array_map('strval', $savedTrialThemeKeys)))
                : collect([$activeTrialTheme, 'midnight', 'emerald', 'ocean'])
                    ->filter()
                    ->unique()
                    ->take(3)
                    ->values()
                    ->all();

            if (! is_array($savedTrialThemeKeys) || $savedTrialThemeKeys !== $trialThemeKeys) {
                $themeSettings = is_array($trial->preview_theme) ? $trial->preview_theme : [];
                $themeSettings['trial_theme_keys'] = $trialThemeKeys;
                $trial->update(['preview_theme' => $themeSettings]);
                $trial->setAttribute('preview_theme', $themeSettings);
            }

            $customThemeExists = is_array(data_get($trial->preview_theme, 'custom_brand_theme'));
            $trialAccessibleThemeKeys = $customThemeExists
                ? array_values(array_unique(['my-brand', ...$trialThemeKeys]))
                : $trialThemeKeys;

            $builderThemeAccess = [
                'keys' => $trialAccessibleThemeKeys,
                'count' => count($trialThemeKeys),
                'unlimited' => false,
                'next_plan' => 'Sign up',
                'plan_key' => 'guest_trial',
                'trial' => true,
            ];
        } elseif ($request->user()) {
            $themeAccessService = app(ThemePlanAccessService::class);
            $effectivePlanKey = $request->user()->effectivePlanKey();
            $themeKeys = $themeAccessService->allowedThemeKeysForWebsite($effectivePlanKey, $website);
            $builderThemeAccess = [
                'keys' => $themeKeys,
                'count' => $themeKeys === null ? null : count($themeKeys),
                'unlimited' => $themeKeys === null,
                'next_plan' => in_array($effectivePlanKey, ['starter', 'agency_starter'], true)
                    ? 'Growth'
                    : (in_array($effectivePlanKey, ['growth', 'agency_growth'], true) ? 'Pro' : null),
                'plan_key' => $effectivePlanKey,
            ];
        }

        $websiteAccessRole = ! $isTrialMode && $request->user()
            ? app(\App\Services\WorkspaceAccessService::class)->websiteRole($request->user(), $page->website)
            : null;
        $isWebsiteEditor = $websiteAccessRole === 'website_editor';

        return Inertia::render('Websites/Builder', [
            // Builder is token-aware and intentionally lives outside the normal
            // authenticated route group, so pass theme access explicitly instead
            // of relying only on shared Inertia auth props.
            'themeAccess' => $builderThemeAccess,
            'page' => $page,
            'website' => $website,
            'commerce' => $isTrialMode
                ? ['enabled' => false, 'currency' => 'USD', 'currency_decimals' => 2, 'products' => [], 'categories' => []]
                : $this->commerceWorkspacePayload($website),
            'contentWorkspace' => $isTrialMode ? ['types' => []] : ContentWorkspaceController::payload($website),
            'previewUrl' => $isTrialMode || ! $website->last_preview_deployed_at
                ? null
                : app(PreviewDeploymentService::class)->urlForPage($website, $page),
            'previewDeployment' => $isTrialMode ? null : [
                'deployed_at' => $website->last_preview_deployed_at?->toISOString(),
                'error' => $website->preview_deployment_error,
                'ready' => filled($website->preview_slug) && filled($website->last_preview_deployed_at),
            ],
            'websitePages' => $isTrialMode
                ? collect($trial->menu_structure ?? [])
                    ->map(fn (array $menuPage, int $index) => [
                        'id' => ($menuPage['is_home'] ?? false) ? $page->id : null,
                        'title' => $menuPage['title'],
                        'slug' => $menuPage['slug'],
                        'parent_id' => null,
                        'is_home' => (bool) ($menuPage['is_home'] ?? false),
                        'sort_order' => $menuPage['sort_order'] ?? ($index + 1),
                    ])
                    ->values()
                : $website->pages()
                    ->orderBy('parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'title', 'slug', 'parent_id'])
                    ->map(fn (Page $websitePage) => [
                        'id' => $websitePage->id,
                        'title' => $websitePage->title,
                        'slug' => $websitePage->slug,
                        'parent_id' => $websitePage->parent_id,
                    ])
                    ->values(),
            'blogPosts' => $isTrialMode
                ? []
                : ($page->page_type === 'blog'
                    ? $website->blogPosts()
                        ->where('page_id', $page->id)
                        ->orderByDesc('is_featured')
                        ->latest()
                        ->get()
                    : []),
            'hasWebsiteContent' => $websiteMessaging !== '',
            'websiteContext' => $websiteContext,
            'pageStyle' => PageStyleRegistry::normalize($website->page_style ?: $page->page_style),
            'pageStyleOptions' => PageStyleRegistry::suggestions($website->industry, PageStyleRegistry::normalize($website->page_style ?: $page->page_style)),
            'trialMode' => $isTrialMode,
            'globalHeaderBlock' => $isTrialMode
                ? [
                    'type' => 'glassmorphism_header',
                    'logo_text' => $trial->business_name,
                    'logo_image_url' => $trial->logo_url ?: '/storage/branding/your-logo.png',
                    'logo_height' => max(42, (int) data_get($trial->preview_theme, 'logo_height', 60)),
                    'logo_max_width' => max(220, (int) data_get($trial->preview_theme, 'logo_max_width', 300)),
                    'logo_filter_key' => data_get($trial->preview_theme, 'primary', 'midnight'),
                    'overlay_header_on_banner' => (bool) data_get($trial->preview_theme, 'overlay_header_on_banner', false),
                    'cta_label' => 'Get Started',
                    'cta_url' => '#',
                    'menu' => collect($trial->menu_structure ?? [])
                        ->map(fn (array $menuPage) => [
                            'label' => $menuPage['title'],
                            'url' => ($menuPage['is_home'] ?? false) ? 'home' : $menuPage['slug'],
                        ])
                        ->values()
                        ->all(),
                ]
                : $website->global_header,
            'globalFooterBlock' => $isTrialMode
                ? [
                    'type' => 'minimal_footer',
                'mega_enabled' => false,
                'mega_footer' => [
                    'enabled' => false,
                    'tagline' => 'A premium information-rich footer.',
                    'primary_label' => 'Get in touch',
                    'primary_url' => '#contact',
                    'columns' => [
                        ['title' => 'Company', 'items' => [['label' => 'About us', 'url' => '#about'], ['label' => 'Careers', 'url' => '#careers'], ['label' => 'Contact', 'url' => '#contact']]],
                        ['title' => 'Services', 'items' => [['label' => 'What we do', 'url' => '#services'], ['label' => 'Solutions', 'url' => '#solutions'], ['label' => 'Pricing', 'url' => '#pricing']]],
                        ['title' => 'Resources', 'items' => [['label' => 'Insights', 'url' => '#insights'], ['label' => 'Guides', 'url' => '#guides'], ['label' => 'Updates', 'url' => '#updates']]],
                    ],
                ],
                    'theme' => 'white',
                    'logo_text' => $trial->business_name,
                    'logo_image_url' => $trial->logo_url ?: '/storage/branding/your-logo.png',
                    'logo_height' => max(36, min(56, (int) data_get($trial->preview_theme, 'logo_height', 48))),
                    'logo_filter_key' => data_get($trial->preview_theme, 'primary', 'midnight'),
                    'copyright' => '© '.now()->year.'. All rights reserved.',
                ]
                : $website->global_footer,
            'trialToken' => $trial?->token,
            'websiteMediaPack' => ! $isTrialMode ? [
                'status' => $website->mediaPack?->status ?? 'missing',
                'target' => (int) ($website->mediaPack?->target_image_count ?? 0),
            ] : null,
            'trialExperience' => $trial ? [
                'email' => $trial->email,
                'email_captured' => filled($trial->email),
                'regenerations_used' => 0,
                'regenerations_limit' => null,
                'regenerations_reset_at' => now()->addDay()->startOfDay()->toIso8601String(),
                'logo_regenerations_used' => DB::table('trial_logo_generations')
                    ->where('trial_generation_id', $trial->id)
                    ->where('action', 'regenerate')
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count(),
                'logo_regenerations_limit' => 2,
                'media_pack_status' => $trial->mediaPack?->status,
                'media_pack_target' => (int) ($trial->mediaPack?->target_image_count ?? 0),
                'logo_url' => $trial->logo_url,
                'logo_company_name' => $trial->logo_company_name,
                'logo_source' => $trial->logo_source,
                'logo_crop_confirmed' => (bool) data_get($trial->preview_theme, 'brand_logo_crop_confirmed', false),
                'logo_crop_dismissed' => (bool) data_get($trial->preview_theme, 'brand_logo_crop_dismissed', false),
                'logo_theme_sync_state' => $trial->logo_theme_sync_state ?: (filled($trial->logo_url) ? (in_array($trial->logo_source, ['ai', 'ai-theme-match', 'svg-theme-match'], true) ? 'synced' : 'logo_changed') : null),
                'logo_theme_sync_source' => $trial->logo_theme_sync_source,
                'logo_theme_synced_theme' => $trial->logo_theme_synced_theme,
                'guest_credits' => $trial ? app(TrialCreditService::class)->balance($trial) : 0,
            ] : null,
            'cosmicPricing' => [
                'balance' => $trial ? app(TrialCreditService::class)->balance($trial) : (int) ($request->user()?->fresh()?->credits ?? 0),
                'guest' => (bool) $trial,
                'actions' => ActionPricing::all(),
                'trial_actions' => [
                    'page_style' => TrialCreditService::PAGE_STYLE,
                    'regenerate_page' => TrialCreditService::REGENERATE_PAGE,
                    'generate_logo' => TrialCreditService::GENERATE_LOGO,
                    'generate_image' => TrialCreditService::GENERATE_IMAGE,
                    'match_logo_to_theme' => TrialCreditService::MATCH_LOGO_TO_THEME,
                    'match_theme_to_logo' => TrialCreditService::MATCH_THEME_TO_LOGO,
                ],
                'blocks' => BlockPricingRegistry::all(),
                'themes' => ThemePricingRegistry::all(),
            ],
            'trialCapabilities' => [
                'canNavigateAway' => ! $isTrialMode && ! $isWebsiteEditor,
                'canChangeTheme' => ! $isWebsiteEditor,
                'canGenerateAi' => ! $isTrialMode && ! $isWebsiteEditor,
                'canPublish' => ! $isTrialMode,
                'canManageBlocks' => ! $isTrialMode && ! $isWebsiteEditor,
                'canEditGlobalShell' => ! $isTrialMode && ! $isWebsiteEditor,
                'canSave' => true,
                'canPurchase' => $isTrialMode,
            ],
            'websiteAccessRole' => $websiteAccessRole,
        ]);
    }

   public function updateBlocks(\Illuminate\Http\Request $request, \App\Models\Page $page)
    {
        $this->authorize('editBuilder', $page->website);

        $validated = $request->validate([
            'blocks' => 'nullable|array',
            'global_header' => 'nullable|array',
        ]);

        $isWebsiteEditor = app(\App\Services\WorkspaceAccessService::class)->websiteRole($request->user(), $page->website) === 'website_editor';
        DB::transaction(function () use ($page, $validated, $isWebsiteEditor) {
            $page->blocks = $validated['blocks'];
            $page->save();

            if (! $isWebsiteEditor && array_key_exists('global_header', $validated)) {
                $page->website->global_header = $validated['global_header'];
                $page->website->save();
            }
        });

        return redirect()->back()->with('success', 'Page updated successfully.');
    }

    public function saveBuilder(Request $request, Page $page, CreditService $credits)
    {
        $trial = $this->resolveTrialAccess($request, $page);

        if ($trial === null) {
            $this->authorize('editBuilder', $page->website);
        }

        $rules = [
            'blocks' => ['nullable', 'array'],
            'theme_settings' => ['nullable', 'array'],
        ];
        if ($trial === null) {
            $rules['global_header'] = ['nullable', 'array'];
            $rules['global_footer'] = ['nullable', 'array'];
        } else {
            // Trial builder exposes only the global overlay-header switch. The
            // shared trial website shell must never be mutated by guest sessions.
            $rules['global_header'] = ['nullable', 'array'];
            $rules['global_header.overlay_header_on_banner'] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);
        $website = $page->website;
        $isWebsiteEditor = $trial === null
            && app(\App\Services\WorkspaceAccessService::class)->websiteRole($request->user(), $website) === 'website_editor';
        if ($isWebsiteEditor) {
            unset($validated['theme_settings'], $validated['global_header'], $validated['global_footer']);
        }

        if ($trial === null && array_key_exists('theme_settings', $validated)) {
            $requestedTheme = (string) data_get($validated, 'theme_settings.primary', '');
            $currentTheme = (string) data_get($website->theme_settings, 'primary', '');

            if ($requestedTheme !== '') {
                app(ThemePlanAccessService::class)->assertCanUse(
                    $request->user(),
                    $requestedTheme,
                    $currentTheme,
                    app(ThemePlanAccessService::class)->includedThemeKeyForWebsite($website),
                );
            }
        }

        // Overlay Header compatibility is a product rule, not a CSS guess:
        // only Premium/Balanced with non-light theme families may persist it.
        if (array_key_exists('global_header', $validated)) {
            $effectiveStyle = PageStyleRegistry::normalize($website->page_style);
            $effectiveTheme = strtolower(trim((string) (
                data_get($validated, 'theme_settings.primary')
                ?: data_get($website->theme_settings, 'primary')
                ?: 'midnight'
            )));
            $overlayCompatible = in_array($effectiveStyle, ['premium', 'balanced'], true)
                && ! in_array($effectiveTheme, ['stone', 'white'], true);
            if (! $overlayCompatible) {
                data_set($validated, 'global_header.overlay_header_on_banner', false);
            }
        }

        if ($trial !== null && array_key_exists('theme_settings', $validated)) {
            $requestedTheme = (string) data_get($validated, 'theme_settings.primary', '');
            $activeTrialTheme = (string) data_get($trial->preview_theme, 'primary', 'midnight');
            $allowedTrialThemes = data_get($trial->preview_theme, 'trial_theme_keys');

            // trial_theme_keys contains the unlocked preset themes only.
            // My Brand Theme is a separate first-class trial theme created from
            // the user's logo and must not consume/replace a preset slot.
            if (! is_array($allowedTrialThemes) || count($allowedTrialThemes) < 1) {
                $allowedTrialThemes = collect([$activeTrialTheme, 'midnight', 'emerald', 'ocean'])
                    ->filter(fn ($theme) => filled($theme) && $theme !== 'my-brand')
                    ->unique()
                    ->take(3)
                    ->values()
                    ->all();
            }

            $submittedCustomBrandTheme = data_get($validated, 'theme_settings.custom_brand_theme');
            $savedCustomBrandTheme = data_get($trial->preview_theme, 'custom_brand_theme');
            $canUseMyBrandTheme = $requestedTheme === 'my-brand'
                && (
                    is_array($submittedCustomBrandTheme)
                    || is_array($savedCustomBrandTheme)
                );

            abort_if(
                $requestedTheme !== ''
                    && ! $canUseMyBrandTheme
                    && ! in_array($requestedTheme, $allowedTrialThemes, true),
                403,
                'Sign up to unlock more themes.'
            );
        }

        DB::transaction(function () use ($page, $website, $validated, $trial) {
            $page->blocks = $validated['blocks'] ?? [];
            $page->status = 'draft';
            $page->publish_error = null;
            $page->save();

            if ($trial !== null) {
                $trialUpdate = ['generated_blocks' => $page->blocks, 'last_saved_at' => now()];
                $previewTheme = array_key_exists('theme_settings', $validated)
                    ? (array) $validated['theme_settings']
                    : (array) $trial->preview_theme;
                if (array_key_exists('global_header', $validated)) {
                    $previewTheme['overlay_header_on_banner'] = (bool) data_get($validated, 'global_header.overlay_header_on_banner', false);
                }
                $trialUpdate['preview_theme'] = $previewTheme;
                $trial->update($trialUpdate);
                return;
            }

            if (array_key_exists('global_header', $validated)) $website->global_header = $validated['global_header'];
            if (array_key_exists('global_footer', $validated)) $website->global_footer = $validated['global_footer'];
            if (array_key_exists('theme_settings', $validated)) $website->theme_settings = $validated['theme_settings'];
            $website->save();
        });

        if ($trial !== null) {
            app(MediaAssetLifecycleService::class)->queueTrialSave($trial->fresh(['mediaPack', 'page']));
        }

        return response()->json([
            'status' => 'success',
            'credits_spent' => 0,
            'credit_balance' => $trial === null ? $credits->balance($request->user()) : null,
            'page_status' => 'draft',
            'message' => 'Draft saved successfully. Theme credits are only charged when publishing.',
        ]);
    }

    public function applyPageStyle(Request $request, Page $page, CreditService $credits)
    {
        $this->authorize('update', $page->website);

        $validated = $request->validate([
            'style' => ['required', 'string', 'max:40'],
            'blocks' => ['required', 'array'],
        ]);

        abort_unless(PageStyleRegistry::exists($validated['style']), 422, 'That page style is not available.');

        $user = $request->user();
        $cost = PageStyleRegistry::CREDIT_COST;
        $reference = 'page-style-'.$page->id.'-'.now()->format('YmdHisv');

        $credits->consume(
            $user,
            $cost,
            'AI page style: '.$validated['style'],
            $page->website,
            $reference
        );

        try {
            $blocks = collect($validated['blocks'])
                ->map(function ($block) {
                    if (! is_array($block)) return $block;
                    $block['theme'] = 'auto';
                    unset($block['resolvedTheme']);
                    return $block;
                })
                ->values()
                ->all();

            DB::transaction(function () use ($page, $validated, $blocks) {
                $website = $page->website;
                $website->page_style = $validated['style'];
                $website->save();

                // Page Style is website-global. Keep legacy page columns synced so
                // preview/publish routes that still read snapshots cannot drift.
                $website->pages()->update(['page_style' => $validated['style']]);

                $page->blocks = $blocks;
                $page->status = 'draft';
                $page->publish_error = null;
                $page->save();
            });

            $globalStyle = PageStyleRegistry::normalize($page->website->fresh()->page_style);

            return response()->json([
                'status' => 'success',
                'page_style' => $globalStyle,
                'style' => ['key' => $globalStyle, ...PageStyleRegistry::all()[$globalStyle]],
                'blocks' => $blocks,
                'page_status' => 'draft',
                'credits_spent' => $cost,
                'credit_balance' => $credits->balance($user),
                'suggestions' => PageStyleRegistry::suggestions($page->website->industry, $globalStyle),
            ]);
        } catch (\Throwable $exception) {
            $credits->refund($user, $cost, 'Refund for failed AI page style', $page->website, $reference.'-refund');
            throw $exception;
        }
    }

    public function applyTrialPageStyle(Request $request, TrialGeneration $trial, Page $page, TrialCreditService $trialCredits)
    {
        abort_unless(
            $trial->status === 'ready'
            && ! $trial->claimed_at
            && (int) $trial->page_id === (int) $page->id,
            404
        );

        $expiresAt = filled($trial->email)
            ? $trial->created_at->copy()->addDays(30)
            : $trial->created_at->copy()->addHours(24);
        abort_if($expiresAt->isPast(), 410, 'This trial link has expired.');

        $validated = $request->validate([
            'style' => ['required', 'string', 'max:40'],
            'blocks' => ['required', 'array'],
        ]);

        abort_unless(PageStyleRegistry::exists($validated['style']), 422, 'That page style is not available.');
        $trialCredits->ensureCanSpend($trial, TrialCreditService::PAGE_STYLE, 'Page Style');

        $blocks = collect($validated['blocks'])
            ->map(function ($block) {
                if (! is_array($block)) return $block;
                $block['theme'] = 'auto';
                unset($block['resolvedTheme']);
                return $block;
            })
            ->values()
            ->all();

        DB::transaction(function () use ($page, $trial, $validated, $blocks) {
            $page->page_style = $validated['style'];
            $page->blocks = $blocks;
            $page->status = 'draft';
            $page->publish_error = null;
            $page->save();

            $trial->update([
                'generated_blocks' => $blocks,
                'last_saved_at' => now(),
            ]);
        });

        $balance = $trialCredits->consume($trial, TrialCreditService::PAGE_STYLE, 'page_style', ['style' => $validated['style']]);

        return response()->json([
            'status' => 'success',
            'page_style' => $page->page_style,
            'style' => ['key' => $page->page_style, ...PageStyleRegistry::all()[$page->page_style]],
            'blocks' => $blocks,
            'page_status' => 'draft',
            'credits_spent' => TrialCreditService::PAGE_STYLE,
            'credit_balance' => $balance,
            'suggestions' => PageStyleRegistry::suggestions($page->website->industry, $page->page_style),
        ]);
    }

    public function applyTrialTheme(Request $request, TrialGeneration $trial, Page $page, TrialCreditService $trialCredits)
    {
        abort_unless(
            $trial->status === 'ready' && ! $trial->claimed_at && (int) $trial->page_id === (int) $page->id,
            404
        );

        $expiresAt = filled($trial->email) ? $trial->created_at->copy()->addDays(30) : $trial->created_at->copy()->addHours(24);
        abort_if($expiresAt->isPast(), 410, 'This trial link has expired.');

        $validated = $request->validate(['theme' => ['required', 'string', 'max:40']]);
        $requestedTheme = $validated['theme'];
        $activeTheme = (string) data_get($trial->preview_theme, 'primary', 'midnight');
        $allowedThemes = data_get($trial->preview_theme, 'trial_theme_keys');
        if (! is_array($allowedThemes) || count($allowedThemes) !== 3) {
            $allowedThemes = collect([$activeTheme, 'midnight', 'emerald', 'ocean'])->filter(fn ($key) => $key !== 'my-brand')->unique()->take(3)->values()->all();
        }
        $hasCustomBrandTheme = is_array(data_get($trial->preview_theme, 'custom_brand_theme'));
        $isCustomBrandTheme = $requestedTheme === 'my-brand' && $hasCustomBrandTheme;
        abort_unless($isCustomBrandTheme || in_array($requestedTheme, $allowedThemes, true), 403, 'Sign up to unlock more themes.');

        $syncSource = (string) $request->input('sync_source', 'manual_theme_change');

        // Applying My Brand Theme after Match to Logo may target the theme that
        // is already active. We must still persist the new logo/theme sync state;
        // otherwise the Match to Logo CTA reappears forever after refresh.
        if ($requestedTheme === $activeTheme && $syncSource !== 'theme_to_logo') {
            return response()->json(['status' => 'success', 'theme' => $requestedTheme, 'credits_spent' => 0, 'credit_balance' => $trialCredits->balance($trial)]);
        }

        $cost = $isCustomBrandTheme ? 0 : ThemePricingRegistry::cost($requestedTheme);
        if ($cost > 0) {
            $trialCredits->ensureCanSpend($trial, $cost, 'Theme change');
        }

        $themeSettings = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        $themeSettings['primary'] = $requestedTheme;
        $syncUpdate = [];
        if (filled($trial->logo_url)) {
            if ($syncSource === 'theme_to_logo') {
                if ($requestedTheme === 'my-brand' && is_array(data_get($themeSettings, 'custom_brand_theme'))) {
                    $customTheme = (array) data_get($themeSettings, 'custom_brand_theme');
                    $customTheme['source_logo_url'] = $trial->logo_url;
                    $themeSettings['custom_brand_theme'] = $customTheme;
                    $themeSettings['brand_palette'] = $customTheme['palette'] ?? data_get($themeSettings, 'brand_palette', []);
                    $themeSettings['brand_source'] = 'logo';
                }

                $syncUpdate = [
                    'logo_theme_sync_state' => 'synced',
                    'logo_theme_sync_source' => 'theme_to_logo',
                    'logo_theme_synced_theme' => $requestedTheme,
                ];
            } else {
                $syncUpdate = [
                    'logo_theme_sync_state' => 'theme_changed',
                    'logo_theme_sync_source' => 'manual_theme_change',
                    'logo_theme_synced_theme' => null,
                ];
            }
        }
        $trial->update(['preview_theme' => $themeSettings, 'last_saved_at' => now(), ...$syncUpdate]);
        $balance = $cost > 0
            ? $trialCredits->consume($trial, $cost, 'change_theme', ['theme' => $requestedTheme])
            : $trialCredits->balance($trial);

        return response()->json([
            'status' => 'success',
            'theme' => $requestedTheme,
            'credits_spent' => $cost,
            'credit_balance' => $balance,
        ]);
    }

    private function resolveTrialAccess(Request $request, Page $page): ?TrialGeneration
    {
        // A trial token is a bearer credential for the generated trial page.
        // Validate it before falling back to authenticated workspace authorization
        // so opening a trial link while already signed in (for example as an
        // Agency Pro tester) does not incorrectly hit WebsitePolicy and return 403.
        $token = trim((string) $request->query('token', $request->input('token', '')));

        if ($token === '') {
            if ($request->user()) {
                return null;
            }

            abort(404);
        }

        $trial = TrialGeneration::query()
            ->where('token', $token)
            ->where('page_id', $page->id)
            ->where('status', 'ready')
            ->whereNull('claimed_at')
            ->first();

        abort_unless($trial, 404);
        $expiresAt = filled($trial->email) ? $trial->created_at->copy()->addDays(30) : $trial->created_at->copy()->addHours(24);
        abort_if($expiresAt->isPast(), 410, 'This trial link has expired.');

        return $trial;
    }

    private function hydrateSavedCustomSparksForPublish(Website $website, array $blocks): array
    {
        if (!$website->isCustom()) return $blocks;

        $keys = collect($blocks)
            ->filter(fn($block) => is_array($block) && ($block['type'] ?? '') === 'luna_custom_section' && !empty($block['custom_spark_key']))
            ->pluck('custom_spark_key')
            ->unique()
            ->values();

        if ($keys->isEmpty()) return $blocks;

        $saved = $website->customSparks()
            ->whereIn('key', $keys)
            ->get()
            ->filter(function ($spark) {
                $metadata = is_array($spark->metadata) ? $spark->metadata : [];
                return (bool) ($metadata['saved'] ?? false);
            })
            ->keyBy('key');

        return array_map(function ($block) use ($saved) {
            if (!is_array($block)) return $block;
            $key = $block['custom_spark_key'] ?? null;
            if (!$key || !$saved->has($key)) return $block;

            $source = $saved->get($key);
            $savedBlock = is_array($source->block) ? $source->block : [];
            return array_merge($block, $savedBlock, [
                'type' => 'luna_custom_section',
                'custom_spark_key' => $key,
                'custom_spark_saved' => true,
            ]);
        }, $blocks);
    }

    public function publish(Request $request, Page $page, PagePublisher $publisher, CreditService $credits, WebsiteHealthService $health)
    {
        $this->authorize('editBuilder', $page->website);

        $website = $page->website;

        // Resolve saved Custom Sparks from their canonical website library before
        // preview/live compilation. This keeps export dynamic even if the page
        // snapshot still contains the earlier free/draft version of the block.
        if ($website->isCustom()) {
            $page->blocks = $this->hydrateSavedCustomSparksForPublish($website, is_array($page->blocks) ? $page->blocks : []);
            $page->save();
        }

        $localizationQueued = app(MediaAssetLifecycleService::class)->queueWebsitePublish($website);
        if ($localizationQueued) {
            return response()->json([
                'status' => 'localizing',
                'message' => 'Securing your remote images locally before publishing.',
                'media_pack_status' => $website->mediaPack()->value('status') ?: 'queued',
                'retry_publish' => true,
            ], 202);
        }

        if (config('cosmic_media.localize_remote_images', false)) {
            $remainingRemoteUrls = app(MediaAssetSafetyService::class)->draftProviderUrls($website->fresh());
            if ($remainingRemoteUrls !== []) {
                return response()->json([
                    'status' => 'media_not_ready',
                    'message' => 'Some remote preview images are still being secured. Retry publishing in a moment.',
                    'remote_image_count' => count($remainingRemoteUrls),
                    'retry_publish' => true,
                ], 409);
            }
        }

        $themeKey = (string) data_get($website->theme_settings, 'primary', '');
        $publishedThemeKey = (string) data_get($website->published_theme_settings, 'primary', '');
        // Publishing and manual theme selection are ordinary CMS actions.
        // Credits are reserved for real AI/API work only.
        $themeCost = 0;
        $themeReference = null;

        try {
            $html = $publisher->publish($page, $website);

            DB::transaction(function () use ($page, $website, $html, $request, $themeKey, $themeCost) {
                $publishedAt = now();
                $page->published_blocks = $page->blocks ?? [];
                $page->published_page_style = $website->page_style ?: $page->page_style;
                $page->published_html = $html;
                $page->status = 'published';
                $page->published_at ??= $publishedAt;
                $page->last_published_at = $publishedAt;
                $page->publish_error = null;
                $page->save();

                $website->published_page_style = $website->page_style ?: $page->page_style;
                $website->pages()->update(['published_page_style' => $website->published_page_style]);
                $website->published_theme_settings = $website->theme_settings;
                $website->published_global_header = $website->global_header;
                $website->published_global_footer = $website->global_footer;
                $website->save();

                // Global Page Style is a website-level visual contract. Publishing any
                // page must refresh already-published standard-page HTML so local preview,
                // static preview, and connector/live output cannot retain an older style.
                $publishedStyle = PageStyleRegistry::normalize($website->published_page_style ?: $website->page_style);
                $primaryColor = (string) data_get($website->published_theme_settings ?: $website->theme_settings, 'primary', 'midnight');
                $website->pages()
                    ->where('status', 'published')
                    ->where('page_type', 'standard')
                    ->get()
                    ->each(function (Page $publishedPage) use ($publishedStyle, $primaryColor) {
                        try {
                            $snapshot = $publishedPage->published_blocks ?? $publishedPage->blocks ?? [];
                            $publishedPage->forceFill([
                                'published_page_style' => $publishedStyle,
                                'published_html' => CmsHtmlCompiler::compile($snapshot, $primaryColor, ['page_style' => $publishedStyle]),
                            ])->save();
                        } catch (Throwable $siblingCompileException) {
                            // A stale sibling page must never turn the current page publish
                            // into a 502. Keep its previous published HTML and report it.
                            report($siblingCompileException);
                        }
                    });

                if ($themeKey !== '' && $themeCost > 0) {
                    CosmicUnlock::firstOrCreate(
                        ['user_id' => $request->user()->id, 'unlock_type' => 'theme', 'unlock_key' => $themeKey],
                        ['credits_paid' => $themeCost],
                    );
                }
            });
        } catch (Throwable $exception) {
            if ($themeCost > 0) {
                $credits->refund($request->user(), $themeCost, 'Refund for failed theme publish', $website, $themeReference . '-refund');
            }

            report($exception);
            $page->publish_error = 'Publishing failed. Your previous live version is still available.';
            $page->save();

            return response()->json([
                'message' => $page->publish_error,
                'status' => $page->status,
                'credit_balance' => $credits->balance($request->user()),
            ], 502);
        }

        $previewService = app(PreviewDeploymentService::class);
        $previewUrl = $previewService->urlForPage($website->fresh(), $page->fresh());
        $previewDeploymentFailed = false;
        $previewDeploymentMessage = null;

        try {
            $previewService->deploy($website->fresh());
            $previewUrl = $previewService->urlForPage($website->fresh(), $page->fresh());
        } catch (Throwable $exception) {
            report($exception);
            $previewDeploymentFailed = true;
            $previewDeploymentMessage = 'The page was published, but the preview deployment failed. Your previous preview remains available.';
        }

        $postPublishHealth = null;
        try {
            $postPublishHealth = $health->scan($website->fresh());
        } catch (Throwable $healthException) {
            // Website Health is advisory and must never turn a successful publish
            // into a failed request. Report scanner failures for follow-up instead.
            report($healthException);
        }

        return response()->json([
            'status' => 'published',
            'published_at' => $page->published_at?->toISOString(),
            'last_published_at' => $page->last_published_at?->toISOString(),
            'preview_url' => $previewUrl,
            'preview_deployment_failed' => $previewDeploymentFailed,
            'preview_deployment_message' => $previewDeploymentMessage,
            'preview_deployed_at' => $website->fresh()->last_preview_deployed_at?->toISOString(),
            'credits_spent' => $themeCost,
            'credit_balance' => $credits->balance($request->user()),
            'health' => $postPublishHealth,
        ]);
    }

    /**
     * I-save ang Global Header Shell gikan sa Axios call sa UI Matrix.
     */
    public function saveGlobalHeader(Request $request, Website $website, CreditService $credits)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'header_block' => 'nullable|array',
        ]);

        $countMenuItems = function (array $items) use (&$countMenuItems): int {
            return collect($items)->sum(function ($item) use (&$countMenuItems) {
                if (! is_array($item)) {
                    return 0;
                }

                $children = is_array($item['children'] ?? null) ? $item['children'] : [];

                return 1 + $countMenuItems($children);
            });
        };

        $oldMenuCount = $countMenuItems((array) data_get($website->global_header, 'menu', []));
        $newMenuCount = $countMenuItems((array) data_get($validated, 'header_block.menu', []));
        $addedItems = max(0, $newMenuCount - $oldMenuCount);
        $cost = $addedItems * ActionPricing::ADD_MENU_ITEM;
        $reference = 'menu-' . Str::uuid();

        if ($cost > 0) {
            $credits->consume(
                $request->user(),
                $cost,
                'Add ' . $addedItems . ' menu item' . ($addedItems === 1 ? '' : 's'),
                $website,
                $reference,
                ['added_items' => $addedItems],
            );
        }

        try {
            $website->update([
                'global_header' => $validated['header_block'] ?? null,
                'published_global_header' => $validated['header_block'] ?? null,
            ]);
        } catch (Throwable $exception) {
            if ($cost > 0) {
                $credits->refund($request->user(), $cost, 'Refund for failed menu update', $website, $reference . '-refund');
            }
            throw $exception;
        }

        return response()->json([
            'status' => 'success',
            'credits_spent' => $cost,
            'credit_balance' => $credits->balance($request->user()),
            'message' => $cost > 0
                ? "Global header saved. {$cost} Cosmic Credit" . ($cost === 1 ? '' : 's') . ' used for new menu items.'
                : 'Global header saved. Existing menu edits are free.',
        ]);
    }

    public function saveFooter(Request $request, Website $website)
    {
        $this->authorize('update', $website);

        $request->validate([
            'footer_block' => 'nullable|array'
        ]);

        // Footer changes follow the same explicit global-shell workflow as
        // headers: save now, then deploy only when Push live update is used.
        $website->update([
            'global_footer' => $request->input('footer_block'),
            'published_global_footer' => $request->input('footer_block'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Global footer saved. Push a live update when you are ready to publish it.'
        ]);
    }

    public function update(Request $request, Page $page)
    {
        return $this->updateBlocks($request, $page);
    }


    public function debugApiHandshake($page_id, $primaryColor = 'espresso') // I-add ang $primaryColor
    {
        $page = \App\Models\Page::find($page_id);

        if (!$page) {
            return response()->json(['html' => '<p>Page Node Error</p>'], 404);
        }

        // Ipasa ang $primaryColor ngadto sa compiler
        $htmlCompiledOutput = \App\Helpers\CmsHtmlCompiler::compile($page->blocks ?? [], $primaryColor);

        return response()->json([
            'status' => 'compiled_render_online',
            'slug' => $page->slug,
            'html' => $htmlCompiledOutput
        ], 200);
    }


    // public function debugHeaderHandshake($website_id)
    // {
    //     // Query tanan data sa websites table para makita nato ang structure
    //     $allWebsites = \App\Models\Website::all();
        
    //     // Pangitaa ang target website
    //     $website = $allWebsites->where('id', $website_id)->first();

    //     if (!$website) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Website not found',
    //             'all_websites_in_db' => $allWebsites
    //         ], 404);
    //     }

    //     // I-return ang tanan nga data para ma-debug
    //     return response()->json([
    //         'status' => 'debug_mode',
    //         'target_website_id' => $website_id,
    //         'global_header_raw' => $website->global_header, // Tan-awa kung unsa gyud ang sulod
    //         'parsed_data' => json_decode($website->global_header, true),
    //         'all_websites_table' => $allWebsites
    //     ], 200);
    // }


    public function debugHeaderHandshake($website_id)
    {
        $website = \App\Models\Website::find($website_id);
        if (!$website || !$website->global_header) {
            return response()->json(['html' => '<p>Header Data Empty</p>'], 404);
        }

        // Ang imong data kay flat JSON (diretso na ang type), dili na "blocks" array
        $headerData = json_decode($website->global_header, true);
        
        // I-pass ang tibuok array ngadto sa Compiler
        $compiledHtml = \App\Helpers\CmsHtmlCompiler::compile([$headerData]);

        return response()->json([
            'status' => 'compiled_render_online',
            'html' => $compiledHtml
        ], 200);
    }


    public function getPipelinePackage(Request $request)
    {
        $serverToken = $request->header('X-Bridge-Token');
        $website = \App\Models\Website::where('api_token', $serverToken)->first();
        if (!$website) return response()->json(['message' => 'Unauthorized'], 401);

        // Kausa ra ni i-decode para sa tibuok request
        $themeSettings = json_decode($website->theme_settings, true) ?? [];
        $primaryColor = $themeSettings['primary'] ?? 'espresso';

        // 1. Compile Header
        $headerBlocks = json_decode($website->global_header, true) ?? [];
        $compiledHeader = \App\Helpers\CmsHtmlCompiler::compile($headerBlocks['blocks'] ?? [], $primaryColor);

        // 2. Compile Pages
        $pages = $website->pages()->get();
        $pagePayload = [];

        foreach ($pages as $page) {
            $pagePayload[] = [
                'slug' => $page->slug,
                'html' => \App\Helpers\CmsHtmlCompiler::compile($page->blocks ?? [], $primaryColor)
            ];
        }

        return response()->json([
            'header' => $compiledHeader,
            'pages'  => $pagePayload
        ]);
    }


    /**
     * Gitigom nga HTML compiler output collection para sa automatic deployment handshake, Bai!
     */
    public function debugApiAllPages()
    {
        // Kuhaon ang tanang pages sa database
        $pages = \App\Models\Page::all();
        
        $payload = [];
        
        foreach ($pages as $page) {
            $slug = !empty($page->slug) ? $page->slug : 'home';
            
            // I-compile ang HTML blocks gamit ang atong Helper class
            $payload[$slug] = \App\Helpers\CmsHtmlCompiler::compile($page->blocks ?? []);
        }
        
        return response()->json([
            'status' => 'compiled_render_online',
            'pages' => $payload
        ], 200);
    }

    // Sa imong Controller update-theme function
    public function updateTheme(Request $request, Website $website, CreditService $credits)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'theme_settings' => ['nullable', 'array'],
            'theme_settings.primary' => ['required', 'string', 'max:120'],
        ]);

        $theme = $validated['theme_settings']['primary'];
        $alreadyUnlocked = $request->user()->cosmicUnlocks()
            ->where('unlock_type', 'theme')
            ->where('unlock_key', $theme)
            ->exists();
        $currentTheme = (string) data_get($website->theme_settings, 'primary', '');
        app(ThemePlanAccessService::class)->assertCanUse(
            $request->user(),
            $theme,
            $currentTheme,
            app(ThemePlanAccessService::class)->includedThemeKeyForWebsite($website),
        );
        $cost = ($alreadyUnlocked || $currentTheme === $theme) ? 0 : ThemePricingRegistry::cost($theme);
        $reference = 'theme-' . Str::uuid();

        if ($cost > 0) {
            $credits->consume(
                $request->user(),
                $cost,
                'Unlock theme: ' . ThemePricingRegistry::get($theme)['label'],
                $website,
                $reference,
                ['theme' => $theme],
            );
        }

        try {
            DB::transaction(function () use ($website, $validated, $request, $theme, $cost) {
                $website->update(['theme_settings' => $validated['theme_settings']]);

                if (! $request->user()->cosmicUnlocks()->where('unlock_type', 'theme')->where('unlock_key', $theme)->exists()) {
                    CosmicUnlock::firstOrCreate(
                        [
                            'user_id' => $request->user()->id,
                            'unlock_type' => 'theme',
                            'unlock_key' => $theme,
                        ],
                        ['credits_paid' => $cost],
                    );
                }
            });
        } catch (Throwable $exception) {
            if ($cost > 0) {
                $credits->refund($request->user(), $cost, 'Refund for failed theme unlock', $website, $reference . '-refund');
            }
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'unlocked' => $cost > 0,
            'credits_spent' => $cost,
            'credit_balance' => $credits->balance($request->user()),
        ]);
    }

}
