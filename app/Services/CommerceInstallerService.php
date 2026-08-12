<?php

namespace App\Services;

use App\AI\Images\Providers\UnsplashProvider;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommerceInstallerService
{
    public const PAGE_DEFINITIONS = [
        ['title' => 'Cart', 'slug' => 'cart', 'sort_order' => 910],
        ['title' => 'Checkout', 'slug' => 'checkout', 'sort_order' => 911],
        ['title' => 'Account', 'slug' => 'account', 'sort_order' => 912],
        ['title' => 'Order Lookup', 'slug' => 'order', 'sort_order' => 913],
    ];

    private const SHOP_PRESETS = ['clean', 'premium', 'editorial'];

    private const DEMO_CATEGORIES = [
        'laptops' => ['Laptops', 'Portable performance for work and creativity.', 'category-laptops.jpg', 'premium modern laptop on clean desk'],
        'phones' => ['Phones', 'Modern mobile essentials for everyday life.', 'category-phones.jpg', 'modern smartphone product photography'],
        'audio' => ['Audio', 'Premium listening gear for work and play.', 'category-audio.jpg', 'premium wireless headphones product photography'],
        'accessories' => ['Accessories', 'Thoughtful add-ons for your everyday setup.', 'category-accessories.jpg', 'modern tech accessories flat lay'],
    ];

    private const DEMO_PRODUCTS = [
        ['aerobook-pro-14','AeroBook Pro 14','laptops',129900,'A lightweight performance laptop built for focused work.','aerobook-pro-14.jpg','sleek modern laptop on desk product photography'],
        ['nova-phone-x','Nova Phone X','phones',89900,'A refined smartphone with a vivid display and all-day battery.','nova-phone-x.jpg','modern smartphone isolated product photography'],
        ['pulse-studio-headphones','Pulse Studio Headphones','audio',24900,'Comfortable wireless headphones tuned for detailed listening.','pulse-studio-headphones.jpg','premium over ear headphones product photography'],
        ['orbit-smart-watch','Orbit Smart Watch','accessories',19900,'A clean everyday smartwatch for activity, alerts and routines.','orbit-smart-watch.jpg','modern smartwatch product photography'],
        ['arc-mechanical-keyboard','Arc Mechanical Keyboard','accessories',14900,'A compact mechanical keyboard with a precise, satisfying feel.','arc-mechanical-keyboard.jpg','mechanical keyboard clean desk product photography'],
        ['beam-wireless-charger','Beam Wireless Charger','accessories',5900,'A minimal fast wireless charger for desks and nightstands.','beam-wireless-charger.jpg','wireless charging pad product photography'],
        ['studioview-monitor-27','StudioView Monitor 27','accessories',39900,'A spacious 27-inch display designed for productive workspaces.','studioview-monitor-27.jpg','modern desktop monitor product photography'],
        ['nomad-laptop-sleeve','Nomad Laptop Sleeve','accessories',4900,'A protective everyday sleeve with a clean travel-ready profile.','nomad-laptop-sleeve.jpg','minimal laptop sleeve product photography'],
    ];

    public function install(Website $website, bool $withDemo = false, string $preset = 'premium'): array
    {
        return DB::transaction(function () use ($website, $withDemo, $preset): array {
            $preset = in_array($preset, self::SHOP_PRESETS, true) ? $preset : 'premium';
            $pages = $this->installPages($website, $preset, $withDemo);
            $demo = $withDemo ? $this->installDemoCatalog($website) : ['categories' => 0, 'products' => 0];
            return ['pages' => $pages, 'demo' => $demo, 'preset' => $preset];
        });
    }

    public function downloadDemoImages(bool $refresh = false): array
    {
        $provider = app(UnsplashProvider::class);
        if (! $provider->isEnabled()) {
            return ['downloaded' => 0, 'skipped' => 0, 'failed' => 0, 'enabled' => false];
        }

        $targets = [];
        foreach (self::DEMO_CATEGORIES as [$name, , $filename, $query]) {
            $targets[] = [$filename, $query, $name];
        }
        foreach (self::DEMO_PRODUCTS as [, $title, , , , $filename, $query]) {
            $targets[] = [$filename, $query, $title];
        }

        $disk = Storage::disk('public');
        $downloaded = $skipped = $failed = 0;
        foreach ($targets as [$filename, $query, $label]) {
            $path = 'cms-images/commerce-demo/'.$filename;
            if (! $refresh && $disk->exists($path) && $disk->size($path) > 0) {
                $skipped++;
                continue;
            }

            $result = $provider->search($query, ['orientation' => 'landscape']);
            if (! $result) {
                $failed++;
                continue;
            }

            $response = Http::connectTimeout(8)->timeout(45)->retry(2, 400, throw: false)
                ->get($result->url, array_merge($result->downloadParameters, ['w' => 1400, 'h' => 1000, 'q' => 84]));
            if (! $response->successful() || $response->body() === '') {
                $failed++;
                continue;
            }

            $disk->put($path, $response->body());
            if (! $disk->exists($path) || $disk->size($path) < 1024) {
                $failed++;
                continue;
            }

            $provider->trackDownload($result);
            $downloaded++;
        }

        return compact('downloaded', 'skipped', 'failed') + ['enabled' => true];
    }

    private function installPages(Website $website, string $preset, bool $withDemo): int
    {
        $count = 0;

        foreach ($this->standardStorePages($preset) as $definition) {
            $this->installStandardStorePage($website, $definition);
            $count++;
        }

        foreach (self::PAGE_DEFINITIONS as $definition) {
            $page = $website->pages()->firstOrNew(['slug' => $definition['slug']]);
            if ($page->exists && $page->page_type !== 'commerce') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'commerce_pages' => "The /{$definition['slug']} slug is already used by a standard page. Rename or remove that page, then run the installer again.",
                ]);
            }
            $runtimeBlocks = $this->runtimePageBlocks($definition['slug'], $preset);
            $page->fill([
                'title' => $definition['title'],
                'page_type' => 'commerce',
                'parent_id' => null,
                'sort_order' => $definition['sort_order'],
                'status' => 'published',
                'blocks' => $runtimeBlocks,
                'published_blocks' => $runtimeBlocks,
            ]);
            $page->save();
            $count++;
        }
        return $count;
    }

    private function runtimePageBlocks(string $slug, string $preset): array
    {
        $cartType = match ($preset) {
            'clean' => 'commerce_cart_compact',
            'editorial' => 'commerce_cart_classic',
            default => 'commerce_cart_split',
        };
        $checkoutType = match ($preset) {
            'clean' => 'commerce_checkout_express',
            'editorial' => 'commerce_checkout_classic',
            default => 'commerce_checkout_split',
        };

        if ($slug === 'cart') {
            return [[
                'type' => $cartType,
                'theme' => 'surface',
                'heading' => $cartType === 'commerce_cart_split' ? 'Review your bag' : ($cartType === 'commerce_cart_compact' ? 'Your bag' : 'Your cart'),
                'text' => $cartType === 'commerce_cart_split' ? 'Review your items, then continue securely to checkout.' : 'Review your items before checkout.',
                'checkout_label' => $cartType === 'commerce_cart_split' ? 'Secure checkout' : 'Proceed to checkout',
                'continue_label' => 'Continue shopping',
                'cosmic_runtime_visual' => true,
            ]];
        }

        if ($slug === 'checkout') {
            return [[
                'type' => $checkoutType,
                'theme' => 'surface',
                'heading' => $checkoutType === 'commerce_checkout_split' ? 'Secure checkout' : ($checkoutType === 'commerce_checkout_express' ? 'Complete your order' : 'Checkout'),
                'text' => $checkoutType === 'commerce_checkout_split' ? 'Delivery details on the left, live order summary on the right.' : 'Complete your details and review the order securely.',
                'payment_label' => $checkoutType === 'commerce_checkout_express' ? 'Complete purchase' : ($checkoutType === 'commerce_checkout_split' ? 'Pay securely' : 'Continue to payment'),
                'help_text' => 'Shipping, tax, coupons and payment stay protected by the commerce runtime.',
                'cosmic_runtime_visual' => true,
            ]];
        }

        return [];
    }

    private function standardStorePages(string $preset): array
    {
        $catalogType = match ($preset) {
            'editorial' => 'commerce_catalog_editorial',
            'clean' => 'commerce_catalog_compact',
            default => 'commerce_catalog_grid',
        };

        $shopHero = match ($preset) {
            'editorial' => [
                'type' => 'mini_hero_split', 'theme' => 'primary', 'eyebrow' => 'Shop',
                'heading' => 'Products worth discovering', 'text' => 'Browse the live catalog, compare products, and find what fits.',
                'button_label' => 'Browse products', 'button_url' => '#catalog',
                'image_url' => '/storage/cms-images/background/background-2.avif', 'image_alt' => 'Shop collection',
            ],
            'clean' => [
                'type' => 'mini_hero_minimal', 'theme' => 'primary', 'eyebrow' => 'Shop',
                'heading' => 'Find what fits', 'text' => 'A clean storefront for browsing the latest products.',
                'button_label' => 'Browse products', 'button_url' => '#catalog',
            ],
            default => [
                'type' => 'mini_hero_promo', 'theme' => 'primary', 'eyebrow' => 'Shop',
                'heading' => 'Discover something worth bringing home', 'text' => 'Explore the live catalog with a focused storefront built around your products.',
                'button_label' => 'Shop now', 'button_url' => '#catalog',
                'image_url' => '/storage/cms-images/background/background-3.avif', 'image_alt' => 'Featured shop collection',
            ],
        };

        $featuredHero = [
            'type' => 'mini_hero_minimal', 'theme' => 'primary', 'eyebrow' => 'Featured',
            'heading' => 'Featured products', 'text' => 'A curated edit of products worth a closer look.',
            'button_label' => 'Browse all products', 'button_url' => '/shop',
        ];

        $collectionsHero = [
            'type' => 'mini_hero_minimal', 'theme' => 'primary', 'eyebrow' => 'Collections',
            'heading' => 'Shop by collection', 'text' => 'Browse product categories and jump into the collection that fits.',
            'button_label' => 'View all products', 'button_url' => '/shop',
        ];

        return [
            [
                'title' => 'Shop', 'slug' => 'shop', 'sort_order' => 900,
                'blocks' => [
                    $shopHero,
                    ['type' => $catalogType, 'theme' => 'white', 'heading' => $preset === 'editorial' ? 'Curated for you' : 'Shop the catalog', 'text' => 'Search, filter and compare products from the live catalog.', 'limit' => $preset === 'clean' ? 16 : 12, 'show_toolbar' => true, 'anchor' => 'catalog'],
                    $this->benefitsBlock(),
                ],
            ],
            [
                'title' => 'Featured Products', 'slug' => 'featured-products', 'sort_order' => 901,
                'blocks' => [
                    $featuredHero,
                    ['type' => 'commerce_featured_products', 'theme' => 'white', 'heading' => 'Featured picks', 'text' => 'A curated selection worth a closer look.', 'limit' => 8],
                    [
                        'type' => 'commerce_promo_split', 'theme' => 'primary', 'eyebrow' => 'Featured collection',
                        'heading' => 'Make room for something new', 'text' => 'Highlight a seasonal collection, campaign, or standout product.',
                        'button_label' => 'Explore the collection', 'button_url' => '/collections',
                        'image_url' => '/storage/cms-images/background/background-3.avif', 'image_alt' => 'Featured collection',
                    ],
                    ['type' => 'commerce_featured_collection', 'theme' => 'surface', 'heading' => 'Featured collection', 'text' => 'Explore a focused edit from one collection.', 'category_id' => null, 'limit' => 4, 'button_label' => 'View collection'],
                    $this->benefitsBlock(),
                ],
            ],
            [
                'title' => 'Collections', 'slug' => 'collections', 'sort_order' => 902,
                'blocks' => [
                    $collectionsHero,
                    ['type' => 'commerce_categories', 'theme' => 'surface', 'heading' => 'Shop by category', 'text' => 'Browse collections and find the right product for you.'],
                    ['type' => 'commerce_featured_collection', 'theme' => 'white', 'heading' => 'Collection spotlight', 'text' => 'Choose a collection to feature a focused group of products.', 'category_id' => null, 'limit' => 6, 'button_label' => 'View collection'],
                    $this->benefitsBlock(),
                ],
            ],
        ];
    }

    private function benefitsBlock(): array
    {
        return [
            'type' => 'commerce_benefits_strip', 'theme' => 'surface', 'heading' => 'Shop with confidence',
            'benefit_1_title' => 'Secure checkout', 'benefit_1_text' => 'Protected payment flow',
            'benefit_2_title' => 'Fast delivery', 'benefit_2_text' => 'Clear shipping options',
            'benefit_3_title' => 'Easy returns', 'benefit_3_text' => 'Straightforward support',
            'benefit_4_title' => 'Here to help', 'benefit_4_text' => 'Customer care when needed',
        ];
    }

    private function installStandardStorePage(Website $website, array $definition): void
    {
        $page = $website->pages()->firstOrNew(['slug' => $definition['slug']]);

        if ($page->exists && $page->page_type !== 'standard' && $page->page_type !== 'commerce') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'commerce_pages' => "The /{$definition['slug']} slug is already used by another page type. Rename or remove that page, then run the installer again.",
            ]);
        }

        $existingBlocks = is_array($page->blocks) ? $page->blocks : [];
        $canReplaceBlocks = ! $page->exists || $page->page_type === 'commerce' || $existingBlocks === [] || $this->looksInstallerManaged($existingBlocks);
        if (! $canReplaceBlocks) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'commerce_pages' => "The /{$definition['slug']} standard page already contains custom content. Rename it or clear its blocks before running the commerce installer.",
            ]);
        }

        $page->fill([
            'title' => $definition['title'],
            'page_type' => 'standard',
            'parent_id' => null,
            'sort_order' => $definition['sort_order'],
            'status' => 'published',
            'blocks' => $definition['blocks'],
            'published_blocks' => $definition['blocks'],
        ]);
        $page->save();
    }

    private function looksInstallerManaged(array $blocks): bool
    {
        if ($blocks === []) return true;

        $known = [
            'mini_hero_minimal', 'mini_hero_split', 'mini_hero_promo',
            'commerce_categories', 'commerce_featured_products',
            'commerce_catalog_grid', 'commerce_catalog_editorial', 'commerce_catalog_compact',
            'commerce_promo_split', 'commerce_featured_collection', 'commerce_benefits_strip',
        ];

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? '');
            if ($type === '' || ! in_array($type, $known, true)) return false;
        }

        return true;
    }

    private function installDemoCatalog(Website $website): array
    {
        $base = '/storage/cms-images/commerce-demo/';
        $categoryModels = [];
        $createdCategories = 0;
        foreach (self::DEMO_CATEGORIES as $slug => [$name, $description, $image]) {
            $category = $website->commerceProductCategories()->firstOrCreate(['slug' => $slug], [
                'name' => $name, 'description' => $description, 'image_url' => $base.$image, 'image_alt' => $name,
                'seo_title' => $name, 'seo_description' => $description, 'is_visible' => true, 'sort_order' => count($categoryModels) + 1,
                'metadata' => ['cosmic_demo' => true],
            ]);
            if ($category->wasRecentlyCreated) $createdCategories++;
            if ((bool) data_get($category->metadata, 'cosmic_demo') && (blank($category->image_url) || str_contains((string) $category->image_url, '/commerce-demo/'))) {
                $category->update(['image_url' => $base.$image, 'image_alt' => $category->image_alt ?: $name]);
            }
            $categoryModels[$slug] = $category;
        }

        $createdProducts = 0;
        foreach (self::DEMO_PRODUCTS as [$slug,$title,$categorySlug,$price,$description,$image]) {
            $product = $website->commerceProducts()->firstOrCreate(['slug' => $slug], [
                'type' => 'simple', 'fulfillment_type' => 'physical', 'status' => 'published', 'visibility' => 'catalog',
                'title' => $title, 'short_description' => $description, 'description' => $description,
                'regular_price_minor' => $price, 'track_inventory' => true, 'stock_quantity' => 12, 'low_stock_threshold' => 3,
                'allow_backorders' => false, 'stock_status' => 'in_stock', 'taxable' => true, 'is_featured' => in_array($slug, ['aerobook-pro-14','nova-phone-x'], true),
                'featured_image_url' => $base.$image, 'featured_image_alt' => $title, 'seo_title' => $title, 'seo_description' => $description,
                'metadata' => ['cosmic_demo' => true], 'published_at' => now(),
            ]);
            if ($product->wasRecentlyCreated) $createdProducts++;
            if ((bool) data_get($product->metadata, 'cosmic_demo') && (blank($product->featured_image_url) || str_contains((string) $product->featured_image_url, '/commerce-demo/'))) {
                $product->update(['featured_image_url' => $base.$image, 'featured_image_alt' => $product->featured_image_alt ?: $title]);
            }
            $category = $categoryModels[$categorySlug];
            $product->categories()->syncWithoutDetaching([$category->id => ['is_primary' => true, 'sort_order' => 0]]);
        }
        return ['categories' => $createdCategories, 'products' => $createdProducts];
    }
}
