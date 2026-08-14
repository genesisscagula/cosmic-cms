<?php

namespace App\Helpers;

use App\Support\PageStyleRegistry;

class CmsHtmlCompiler
{
    private static ?array $themeCatalog = null;
    private static string $currentPageStyle = 'auto';

    private static function themeCatalog(): array
    {
        if (self::$themeCatalog !== null) {
            return self::$themeCatalog;
        }

        $catalog = json_decode(
            file_get_contents(resource_path('theme/theme-families.json')),
            true
        );

        return self::$themeCatalog = is_array($catalog) ? $catalog : [];
    }

    private static function getTheme($key)
    {
        $catalog = self::themeCatalog();
        $themes = $catalog['families'] ?? [];
        $compilerThemeIds = $catalog['compilerThemeIds'] ?? [];

        if (in_array($key, $compilerThemeIds, true) && isset($themes[$key])) {
            $theme = $themes[$key];
        } else {
            $theme = $themes['midnight'] ?? $themes['amber'];
        }

        // Premium Sparks use semantic aliases so Builder and static export share
        // the same white / surface / primary vocabulary. Older theme catalog
        // entries expose `card` instead of `surface`, and do not include the
        // convenience `soft` / `strongText` keys used by some premium layouts.
        // Normalise them here so publishing never turns harmless missing-key
        // warnings into Laravel ErrorExceptions.
        $theme['surface'] ??= $theme['card'] ?? $theme['bg'] ?? 'bg-white';
        $theme['card'] ??= $theme['surface'];
        $theme['soft'] ??= $theme['card'];
        $theme['strongText'] ??= $theme['text'] ?? 'text-slate-950';
        $theme['sub'] ??= $theme['text'] ?? 'text-slate-600';
        $theme['border'] ??= 'border-slate-200';
        $theme['bg'] ??= 'bg-white';
        $theme['text'] ??= 'text-slate-950';

        return $theme;
    }

    /**
     * Blend the active media theme with slate for a premium branded overlay.
     * Colored themes keep most of their identity; neutral/dark families lean
     * further into slate. White/surface/stone are handled separately as a wash.
     */
    private static function mediaOverlayColor(string $resolvedTheme, string $primaryColor): string
    {
        if (self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true)) {
            return '#ffffff';
        }

        $catalog = self::themeCatalog();
        $themes = $catalog['families'] ?? [];
        $themeKey = isset($themes[$resolvedTheme]) ? $resolvedTheme : $primaryColor;
        $theme = $themes[$themeKey] ?? $themes['midnight'] ?? [];
        $primaryHex = (string) ($theme['palette']['background'] ?? '#243447');
        $neutralDarkThemes = ['midnight', 'obsidian', 'void', 'charcoal', 'asphalt', 'navy', 'slate', 'dark'];
        $primaryWeight = in_array($themeKey, $neutralDarkThemes, true) ? 0.35 : 0.72;

        return self::blendHexColors($primaryHex, '#020617', $primaryWeight);
    }

    private static function blendHexColors(string $primaryHex, string $neutralHex, float $primaryWeight): string
    {
        $normalize = static function (string $hex): string {
            $hex = trim($hex);
            if (preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
                return strtolower($hex);
            }
            if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $hex, $matches)) {
                return '#' . strtolower($matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3]);
            }
            return '#243447';
        };

        $primary = $normalize($primaryHex);
        $neutral = $normalize($neutralHex);
        $weight = max(0.0, min(1.0, $primaryWeight));
        $channels = [];

        foreach ([1, 3, 5] as $offset) {
            $a = hexdec(substr($primary, $offset, 2));
            $b = hexdec(substr($neutral, $offset, 2));
            $channels[] = (int) round(($a * $weight) + ($b * (1 - $weight)));
        }

        return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
    }

    /**
     * Static sites live outside Laravel's public directory, so relative CMS
     * storage paths must resolve back to the CMS asset host.
     */
    private static function staticAssetUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '' || str_starts_with($url, 'data:')) {
            return $url;
        }

        // Exported websites must never retain local development hosts or Windows paths.
        // Convert local absolute URLs back to their public path, then resolve them against
        // the configured Cosmic CMS asset host used by deployed static websites.
        if (preg_match('#^(?:https?:)?//([^/]+)(/.*)?$#i', $url, $matches)) {
            $host = strtolower(preg_replace('/:\d+$/', '', $matches[1]) ?? $matches[1]);
            $path = $matches[2] ?? '/';
            $localHosts = ['localhost', '127.0.0.1', '::1'];

            if (in_array($host, $localHosts, true)
                || str_ends_with($host, '.local')
                || str_contains($host, 'skyrocket-claude')) {
                $url = $path;
            } else {
                return $url;
            }
        }

        // Normalize accidental Windows public/storage paths copied into block data.
        $url = preg_replace('#^[A-Za-z]:[\\/].*?[\\/]public[\\/]#', '/', $url) ?? $url;
        $url = str_replace('\\', '/', $url);

        // Commerce uploads now use a routed public media endpoint. Rewrite URLs saved
        // by older builds so exported HTML is also independent of public/storage.
        if (preg_match('#^/storage/websites/(\d+)/commerce/([A-Za-z0-9._-]+)$#', $url, $match)) {
            $url = '/websites/'.$match[1].'/commerce/media/'.$match[2];
        }

        $baseUrl = rtrim((string) config('services.cosmic.asset_base_url', config('app.url')), '/');

        return $baseUrl . '/' . ltrim($url, '/');
    }

    /**
     * Final safety pass for image-bearing HTML attributes and CSS URLs. This catches
     * nested block data that may bypass staticAssetUrl() while leaving normal links alone.
     */
    private static function normalizePublishedAssetUrls(string $html): string
    {
        $normalize = static fn (string $url): string => self::staticAssetUrl(html_entity_decode($url, ENT_QUOTES));

        $html = preg_replace_callback(
            '/\b(src|poster)=([' . "\"'" . '])([^' . "\"'" . ']+)\2/i',
            static function (array $matches) use ($normalize): string {
                $url = $matches[3];
                if (! preg_match('#^(?:https?:)?//#i', $url)
                    && ! preg_match('#^(?:[A-Za-z]:[\\/])#', $url)
                    && ! preg_match('#^/storage(?:/|$)#i', $url)
                    && ! preg_match('#^/websites/\d+/(?:media-library/files|commerce/media)/#i', $url)) {
                    return $matches[0];
                }

                $normalized = e($normalize($url));
                return $matches[1] . '=' . $matches[2] . $normalized . $matches[2];
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/url\(([' . "\"'" . ']?)([^)' . "\"'" . ']+)\1\)/i',
            static function (array $matches) use ($normalize): string {
                $url = trim($matches[2]);
                if (! preg_match('#^(?:https?:)?//#i', $url)
                    && ! preg_match('#^(?:[A-Za-z]:[\\/])#', $url)
                    && ! preg_match('#^/storage(?:/|$)#i', $url)
                    && ! preg_match('#^/websites/\d+/(?:media-library/files|commerce/media)/#i', $url)) {
                    return $matches[0];
                }

                return "url('" . e($normalize($url)) . "')";
            },
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * Convert supported public video links into privacy-friendly background embeds.
     * Self-hosted video URLs intentionally return null and continue through <video>.
     */
    private static function backgroundVideoEmbedUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '/')) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = (string) parse_url($url, PHP_URL_PATH);
        $videoId = null;

        if ($host === 'youtu.be') {
            $videoId = trim($path, '/');
        } elseif ($host === 'youtube.com' || $host === 'm.youtube.com') {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $videoId = $query['v'] ?? null;

            if (!$videoId && preg_match('#/(?:embed|shorts)/([^/?]+)#', $path, $matches)) {
                $videoId = $matches[1];
            }
        }

        if (!empty($videoId) && preg_match('/^[A-Za-z0-9_-]{6,}$/', (string) $videoId)) {
            $videoId = rawurlencode($videoId);

            return "https://www.youtube-nocookie.com/embed/{$videoId}?autoplay=1&mute=1&loop=1&playlist={$videoId}&controls=0&playsinline=1&rel=0&modestbranding=1";
        }

        if ($host === 'vimeo.com' || str_ends_with($host, '.vimeo.com')) {
            if (preg_match('#/(\d+)#', $path, $matches)) {
                $videoId = $matches[1];

                return "https://player.vimeo.com/video/{$videoId}?autoplay=1&muted=1&loop=1&background=1&title=0&byline=0&portrait=0";
            }
        }

        return null;
    }

    /**
     * Keep published contact forms compatible with older fixed-field blocks while
     * allowing the Builder and AI to provide a safe, structured field list.
     */
    private static function contactFields(mixed $fields): array
    {
        $defaults = [
            ['id' => 'name', 'name' => 'name', 'type' => 'text', 'label' => 'Name', 'placeholder' => 'Your name', 'required' => true],
            ['id' => 'email', 'name' => 'email', 'type' => 'email', 'label' => 'Email', 'placeholder' => 'you@example.com', 'required' => true],
            ['id' => 'phone', 'name' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'placeholder' => 'Your phone number', 'required' => false],
            ['id' => 'message', 'name' => 'message', 'type' => 'textarea', 'label' => 'How can we help?', 'placeholder' => 'Tell us a little about your project', 'required' => true],
        ];

        $source = is_array($fields) && $fields !== [] ? $fields : $defaults;
        $allowedTypes = ['text', 'email', 'tel', 'textarea', 'select', 'radio', 'checkbox', 'date'];
        $normalized = [];

        foreach (array_slice($source, 0, 8) as $index => $field) {
            if (! is_array($field)) {
                continue;
            }

            $type = in_array($field['type'] ?? null, $allowedTypes, true) ? $field['type'] : 'text';
            $label = trim((string) ($field['label'] ?? 'Field ' . ($index + 1)));
            $name = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', (string) ($field['name'] ?? $label)));
            $name = trim($name, '_') ?: 'field_' . ($index + 1);
            $name = substr($name, 0, 64);
            $options = is_array($field['options'] ?? null)
                ? array_values(array_filter(array_map(static fn ($option) => trim(strip_tags((string) $option)), $field['options']), static fn ($option) => $option !== ''))
                : [];

            $normalized[] = [
                'name' => $name,
                'type' => $type,
                'label' => $label,
                'placeholder' => trim((string) ($field['placeholder'] ?? '')),
                'required' => (bool) ($field['required'] ?? false),
                'options' => array_slice($options, 0, 8),
            ];
        }

        return $normalized ?: $defaults;
    }

    private static function contactFieldsMarkup(array $fields, array $theme, string $inputClasses, string $nativeColorScheme): string
    {
        $markup = '';

        foreach ($fields as $field) {
            $name = e($field['name']);
            $label = e($field['label']);
            $placeholder = e($field['placeholder']);
            $required = $field['required'] ? ' required' : '';
            $requiredMark = $field['required'] ? "<span class='ml-1 text-rose-400'>*</span>" : '';

            if ($field['type'] === 'textarea') {
                $markup .= "<label class='block text-sm font-semibold {$theme['text']}'>{$label}{$requiredMark}<textarea name='{$name}'{$required} class='mt-2 min-h-32 w-full resize-y rounded-xl border px-4 py-3 text-sm outline-none {$inputClasses}' placeholder='{$placeholder}'></textarea></label>";
                continue;
            }

            if ($field['type'] === 'select') {
                // Native selects need explicit surface-aware colors. Avoid the old hard-coded
                // blue control which looked detached from both white and primary sections.
                $isDarkControl = $nativeColorScheme === 'dark';
                $selectBackground = $isDarkControl ? 'rgba(15,23,42,.38)' : '#ffffff';
                $selectColor = $isDarkControl ? '#f8fafc' : '#0f172a';
                $optionBackground = $isDarkControl ? '#0f172a' : '#ffffff';
                $optionColor = $isDarkControl ? '#f8fafc' : '#0f172a';
                $placeholderColor = $isDarkControl ? '#cbd5e1' : '#64748b';
                $options = "<option value='' disabled selected style='background-color:{$optionBackground};color:{$placeholderColor}'>" . e($field['placeholder'] ?: 'Select an option') . '</option>';
                foreach ($field['options'] ?: ['Option one', 'Option two'] as $option) {
                    $option = e($option);
                    $options .= "<option value='{$option}' style='background-color:{$optionBackground};color:{$optionColor}'>{$option}</option>";
                }
                $selectStyle = "color-scheme:{$nativeColorScheme};background-color:{$selectBackground};color:{$selectColor}";
                $markup .= "<label class='block text-sm font-semibold {$theme['text']}'>{$label}{$requiredMark}<select name='{$name}'{$required} data-cosmic-contact-select style='{$selectStyle}' class='mt-2 h-12 w-full rounded-xl border px-4 text-sm outline-none {$inputClasses}'>{$options}</select></label>";
                continue;
            }

            if ($field['type'] === 'radio') {
                $options = '';
                foreach ($field['options'] ?: ['Option one', 'Option two'] as $option) {
                    $option = e($option);
                    $options .= "<label class='inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium {$theme['border']}'><input type='radio' name='{$name}' value='{$option}'{$required}>{$option}</label>";
                }
                $markup .= "<fieldset class='text-sm font-semibold {$theme['text']}'><legend>{$label}{$requiredMark}</legend><div class='mt-3 flex flex-wrap gap-3'>{$options}</div></fieldset>";
                continue;
            }

            if ($field['type'] === 'checkbox') {
                if ($field['options']) {
                    $options = '';
                    foreach ($field['options'] as $option) {
                        $option = e($option);
                        $options .= "<label class='flex items-center gap-2 text-sm font-medium {$theme['sub']}'><input class='h-4 w-4 rounded border-slate-400 text-violet-500' type='checkbox' name='{$name}[]' value='{$option}'>{$option}</label>";
                    }
                    $markup .= "<fieldset class='text-sm font-semibold {$theme['text']}'><legend>{$label}{$requiredMark}</legend><div class='mt-3 space-y-2'>{$options}</div></fieldset>";
                    continue;
                }
                $markup .= "<label class='flex items-start gap-3 text-sm font-medium {$theme['text']}'><input class='mt-1 h-4 w-4 rounded border-slate-400 text-violet-500' type='checkbox' name='{$name}' value='yes'{$required}><span>{$label}{$requiredMark}</span></label>";
                continue;
            }

            $nativeControlStyle = $field['type'] === 'date' ? " style='color-scheme:{$nativeColorScheme}'" : '';
            $nativeControlAction = $field['type'] === 'date' ? " onclick='if (this.showPicker) { this.showPicker(); }'" : '';
            $markup .= "<label class='block text-sm font-semibold {$theme['text']}'>{$label}{$requiredMark}<input name='{$name}' type='{$field['type']}'{$required}{$nativeControlStyle}{$nativeControlAction} class='mt-2 h-12 w-full rounded-xl border px-4 text-sm outline-none {$inputClasses}' placeholder='{$placeholder}'></label>";
        }

        return $markup;
    }

    /** Compile one published blog post into the same static visual system as the Builder. */
    public static function compileBlogPost(array $post, string $primaryColor = null): string
    {
        $theme = self::getTheme($primaryColor ?: 'midnight');
        $title = e($post['title'] ?? 'Untitled article');
        $category = e($post['category'] ?? 'Article');
        $excerpt = e($post['excerpt'] ?? '');
        $content = trim((string) ($post['content'] ?? ''));
        $content = $content !== '' ? nl2br(e($content)) : $excerpt;
        $image = e(self::staticAssetUrl($post['image_url'] ?? '/storage/cms-images/background/background-1.avif'));
        $tags = is_array($post['tags'] ?? null) ? array_slice($post['tags'], 0, 8) : [];
        $tagMarkup = collect($tags)
            ->map(fn ($tag) => "<span class='rounded-full border px-3 py-1 text-xs font-medium {$theme['border']} {$theme['sub']}'>" . e((string) $tag) . '</span>')
            ->implode('');

        return "<article class='px-6 py-16 sm:px-8 lg:px-12 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-4xl'><a href='blog/' class='text-sm font-semibold {$theme['sub']} hover:underline'>← Back to articles</a><p class='mt-12 text-xs font-semibold uppercase tracking-[0.28em] {$theme['sub']}'>{$category}</p><h1 class='mt-4 text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl {$theme['text']}'>{$title}</h1><p class='mt-6 max-w-3xl text-lg leading-8 {$theme['sub']}'>{$excerpt}</p><img src='{$image}' alt='{$title}' width='1200' height='600' loading='lazy' decoding='async' class='mt-10 aspect-[16/8] w-full rounded-3xl object-cover'><div class='mt-8 flex flex-wrap gap-2'>{$tagMarkup}</div><div class='mt-10 max-w-3xl text-base leading-8 {$theme['sub']}'>{$content}</div></div></article>";
    }


    private static function commerceMoney(?int $minor, string $currency, int $decimals): string
    {
        if ($minor === null) return '—';
        $value = $minor / (10 ** max(0, $decimals));
        return $currency . ' ' . number_format($value, $decimals, '.', ',');
    }

    private static function commerceSparkHtml(string $type, array $block, array $context, array $theme): string
    {
        $commerce = is_array($context['commerce'] ?? null) ? $context['commerce'] : [];
        $products = array_values(array_filter((array) ($commerce['products'] ?? []), fn ($p) => is_array($p)));
        $categories = array_values(array_filter((array) ($commerce['categories'] ?? []), fn ($c) => is_array($c)));
        $currency = (string) ($commerce['currency'] ?? 'USD');
        $decimals = (int) ($commerce['currency_decimals'] ?? 2);
        $runtimeEndpoint = (string) ($context['commerce_runtime_endpoint'] ?? '');
        $boundId = (int) ($block['product_id'] ?? 0);
        $product = collect($products)->first(fn ($p) => (int) ($p['id'] ?? 0) === $boundId) ?: ($products[0] ?? null);
        $rootId = 'cosmic-commerce-' . substr(sha1($type . '|' . json_encode($block) . '|' . uniqid('', true)), 0, 12);
        $heading = e((string) ($block['heading'] ?? match ($type) {
            'commerce_product_grid' => 'Featured products',
            'commerce_catalog_grid' => 'Shop the catalog',
            'commerce_catalog_editorial' => 'Curated for you',
            'commerce_catalog_compact' => 'Browse all products',
            'commerce_categories' => 'Shop by category',
            'commerce_featured_products' => 'Featured picks',
            'commerce_featured_collection' => 'Featured collection',
            'commerce_promo_split' => 'A standout offer for the season',
            'commerce_benefits_strip' => 'Shop with confidence',
            'commerce_cart_classic' => 'Your cart',
            'commerce_cart_split' => 'Review your bag',
            'commerce_cart_compact' => 'Cart summary',
            'commerce_checkout_classic' => 'Checkout',
            'commerce_checkout_split' => 'Secure checkout',
            'commerce_checkout_express' => 'Express checkout',
            'commerce_product_gallery' => 'Product gallery',
            'commerce_variation_selector' => 'Choose your options',
            'commerce_related_products' => 'You may also like',
            default => 'Commerce',
        }));
        $text = e((string) ($block['text'] ?? ''));
        $limit = max(1, min(24, (int) ($block['limit'] ?? 8)));
        $price = static fn ($p) => self::commerceMoney(isset($p['sale_price_minor']) && $p['sale_price_minor'] !== null ? (int) $p['sale_price_minor'] : (isset($p['regular_price_minor']) ? (int) $p['regular_price_minor'] : null), $currency, $decimals);
        $img = static fn ($p) => e(self::staticAssetUrl((string) (($p['featured_image_url'] ?? '') ?: (($p['gallery'][0]['url'] ?? '') ?: '/storage/cms-images/background/background-1.avif'))));
        $productUrl = static fn ($p) => e((string) ($p['storefront_url'] ?? '#'));

        // Commerce Sparks intentionally use inline semantic palette variables instead
        // of Tailwind classes generated from PHP. Builder already renders from these
        // exact palette values; using CSS vars here guarantees Preview/Export parity
        // even when Tailwind has never seen a dynamic theme class such as bg-[#243447].
        $palette = is_array($theme['palette'] ?? null) ? $theme['palette'] : [];
        $background = e((string) ($palette['background'] ?? '#243447'));
        $surface = e((string) ($palette['surface'] ?? $palette['background'] ?? '#30475E'));
        $textColor = e((string) ($palette['text'] ?? '#F8FAFC'));
        $muted = e((string) ($palette['muted'] ?? ($textColor === '#F8FAFC' ? '#CBD5E1' : '#64748B')));
        $accent = e((string) ($palette['accent'] ?? '#60A5FA'));
        $border = e((string) ($palette['border'] ?? 'rgba(148,163,184,.28)'));
        $vars = "--commerce-bg:{$background};--commerce-surface:{$surface};--commerce-text:{$textColor};--commerce-muted:{$muted};--commerce-accent:{$accent};--commerce-border:{$border};background:var(--commerce-bg);color:var(--commerce-text)";
        $cardStyle = "background:var(--commerce-surface);color:var(--commerce-text);border-color:var(--commerce-border)";
        $body = '';

        if (in_array($type, ['commerce_catalog_grid', 'commerce_catalog_editorial', 'commerce_catalog_compact'], true)) {
            $items = array_slice($products, 0, $limit);
            $categoryOptions = "<option value=''>All categories</option>";
            foreach ($categories as $category) {
                $categoryOptions .= "<option value='".e((string) ($category['id'] ?? ''))."'>".e((string) ($category['name'] ?? ''))."</option>";
            }
            $commerceDarkControls = in_array(strtolower($textColor), ['#f8fafc', '#ffffff', '#fff', 'white'], true);
            $commerceControlScheme = $commerceDarkControls ? 'dark' : 'light';
            $commerceOptionBg = $surface;
            $commerceOptionColor = $textColor;
            $categoryOptions = str_replace('<option ', "<option style='background-color:{$commerceOptionBg};color:{$commerceOptionColor}' ", $categoryOptions);
            $sortOptions = "<option value='featured' style='background-color:{$commerceOptionBg};color:{$commerceOptionColor}'>Featured</option><option value='newest' style='background-color:{$commerceOptionBg};color:{$commerceOptionColor}'>Newest</option><option value='price_asc' style='background-color:{$commerceOptionBg};color:{$commerceOptionColor}'>Price: low to high</option><option value='price_desc' style='background-color:{$commerceOptionBg};color:{$commerceOptionColor}'>Price: high to low</option><option value='name' style='background-color:{$commerceOptionBg};color:{$commerceOptionColor}'>Name</option>";
            $commerceSelectStyle = "border-color:var(--commerce-border);color:var(--commerce-text);background:var(--commerce-surface);color-scheme:{$commerceControlScheme}";
            $toolbar = !array_key_exists('show_toolbar', $block) || $block['show_toolbar'] !== false
                ? "<div data-commerce-toolbar class='mb-7 grid gap-3 rounded-2xl border p-3 sm:grid-cols-[1fr_auto_auto]' style='{$cardStyle}'><input data-catalog-search type='search' placeholder='Search products' class='min-h-11 rounded-xl border bg-transparent px-4 text-sm outline-none' style='border-color:var(--commerce-border);color:var(--commerce-text)'><select data-catalog-category class='min-h-11 rounded-xl border px-3 text-sm font-semibold' style='{$commerceSelectStyle}'>{$categoryOptions}</select><select data-catalog-sort class='min-h-11 rounded-xl border px-3 text-sm font-semibold' style='{$commerceSelectStyle}'>{$sortOptions}</select></div>"
                : '';
            $cards = '';
            foreach ($items as $index => $p) {
                $title = e((string) ($p['title'] ?? ''));
                $url = $productUrl($p);
                $image = $img($p);
                $priceText = $price($p);
                $categoryNames = [];
                foreach ((array) ($p['category_ids'] ?? []) as $categoryId) {
                    foreach ($categories as $category) {
                        if ((int) ($category['id'] ?? 0) === (int) $categoryId) $categoryNames[] = (string) ($category['name'] ?? '');
                    }
                }
                $categoryText = e(implode(' · ', array_filter($categoryNames)) ?: 'Catalog product');
                $categoryIds = e(implode(',', array_map('intval', (array) ($p['category_ids'] ?? []))));
                $searchText = e(strtolower((string) ($p['title'] ?? '')));
                $priceMinor = (int) (($p['sale_price_minor'] ?? $p['regular_price_minor'] ?? 0));
                $featured = !empty($p['is_featured']) ? '1' : '0';
                $attrs = "data-catalog-product data-title='{$searchText}' data-category-ids='{$categoryIds}' data-price='{$priceMinor}' data-id='".e((string)($p['id']??0))."' data-featured='{$featured}'";
                if ($type === 'commerce_catalog_editorial') {
                    $span = $index % 5 === 0 ? 'lg:col-span-7' : 'lg:col-span-5';
                    $aspect = $index % 5 === 0 ? 'aspect-[16/10]' : 'aspect-[4/3]';
                    $cards .= "<a {$attrs} href='{$url}' class='group overflow-hidden rounded-[26px] border {$span}' style='{$cardStyle}'><img src='{$image}' alt='{$title}' class='{$aspect} w-full object-cover transition duration-500 group-hover:scale-[1.02]'><div class='p-6 sm:p-7'><p class='text-[10px] font-bold uppercase tracking-[0.18em]' style='color:var(--commerce-muted)'>{$categoryText}</p><div class='mt-2 flex items-end justify-between gap-5'><h3 class='text-xl font-semibold tracking-[-0.03em]'>{$title}</h3><p class='shrink-0 text-base font-bold'>{$priceText}</p></div></div></a>";
                } elseif ($type === 'commerce_catalog_compact') {
                    $stock = !empty($p['track_inventory']) ? (((int) ($p['stock_quantity'] ?? 0) > 0) ? e((string)$p['stock_quantity']).' in stock' : (!empty($p['allow_backorders']) ? 'Backorder' : 'Out of stock')) : '';
                    $cards .= "<a {$attrs} href='{$url}' class='grid grid-cols-[72px_1fr_auto] items-center gap-4 p-3.5 transition hover:bg-black/5' style='border-color:var(--commerce-border)'><img src='{$image}' alt='{$title}' class='h-[72px] w-[72px] rounded-xl object-cover'><div class='min-w-0'><h3 class='truncate text-sm font-semibold'>{$title}</h3><p class='mt-1 truncate text-xs' style='color:var(--commerce-muted)'>{$categoryText}</p></div><div class='text-right'><p class='text-sm font-bold'>{$priceText}</p>".($stock?"<p class='mt-1 text-[11px]' style='color:var(--commerce-muted)'>{$stock}</p>":'')."</div></a>";
                } else {
                    $badge = !empty($p['is_featured']) ? "<span class='rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide' style='background:color-mix(in srgb,var(--commerce-accent) 12%,transparent);color:var(--commerce-accent)'>Featured</span>" : '';
                    $cards .= "<a {$attrs} href='{$url}' class='group overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl' style='{$cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)'><img src='{$image}' alt='{$title}' class='aspect-[4/5] w-full object-cover transition duration-500 group-hover:scale-[1.025]'><div class='p-5'><h3 class='text-[16px] font-semibold tracking-[-0.02em]'>{$title}</h3><div class='mt-3 flex items-center justify-between gap-3'><p class='text-[15px] font-bold'>{$priceText}</p>{$badge}</div></div></a>";
                }
            }
            $layoutClass = $type === 'commerce_catalog_editorial'
                ? 'grid gap-6 md:grid-cols-2 lg:grid-cols-12'
                : ($type === 'commerce_catalog_compact' ? 'divide-y rounded-2xl border' : 'grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4');
            $layoutStyle = $type === 'commerce_catalog_compact' ? " style='{$cardStyle}'" : '';
            $body = $toolbar."<div data-commerce-body data-catalog-layout='{$type}' class='{$layoutClass}'{$layoutStyle}>{$cards}</div><p data-catalog-empty class='mt-5 hidden text-sm' style='color:var(--commerce-muted)'>No products match this catalog view.</p>";
        } elseif ($type === 'commerce_product_grid') {
            $items = array_slice(array_values(array_filter($products, fn ($p) => !($block['featured_only'] ?? false) || !empty($p['is_featured']))), 0, $limit);
            foreach ($items as $p) {
                $stock = !empty($p['track_inventory']) ? (((int) ($p['stock_quantity'] ?? 0) > 0) ? e((string) $p['stock_quantity']).' in stock' : (!empty($p['allow_backorders']) ? 'Available on backorder' : 'Out of stock')) : '';
                $body .= "<a href='".$productUrl($p)."' class='group block overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl' style='{$cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)'><img src='".$img($p)."' alt='".e((string) ($p['featured_image_alt'] ?? $p['title'] ?? ''))."' class='aspect-square w-full object-cover'><div class='p-5'><h3 class='text-[16px] font-semibold tracking-[-0.02em]'>".e((string) ($p['title'] ?? ''))."</h3><div class='mt-3 flex items-center justify-between gap-3'><p class='text-[15px] font-bold'>".$price($p)."</p>".($stock ? "<span class='rounded-full px-2.5 py-1 text-[11px] font-semibold' style='background:color-mix(in srgb,var(--commerce-accent) 12%,transparent);color:var(--commerce-accent)'>{$stock}</span>" : '')."</div></div></a>";
            }
            $body = "<div data-commerce-body class='grid gap-5 sm:grid-cols-2 lg:grid-cols-4'>{$body}</div>";
        } elseif ($type === 'commerce_featured_products') {
            $featured = array_values(array_filter($products, fn ($p) => !empty($p['is_featured'])));
            $items = array_slice($featured ?: $products, 0, $limit);
            foreach ($items as $p) {
                $body .= "<a href='".$productUrl($p)."' class='group block overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl' style='{$cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)'><img src='".$img($p)."' alt='".e((string) ($p['featured_image_alt'] ?? $p['title'] ?? ''))."' class='aspect-[4/5] w-full object-cover'><div class='p-5'><h3 class='text-[16px] font-semibold tracking-[-0.02em]'>".e((string) ($p['title'] ?? ''))."</h3><p class='mt-2 text-[15px] font-bold'>".$price($p)."</p></div></a>";
            }
            $body = "<div data-commerce-body class='grid gap-5 sm:grid-cols-2 lg:grid-cols-4'>{$body}</div>";
        } elseif ($type === 'commerce_featured_collection') {
            $categoryId = (int) ($block['category_id'] ?? ($categories[0]['id'] ?? 0));
            $category = collect($categories)->first(fn ($c) => (int) ($c['id'] ?? 0) === $categoryId) ?: ($categories[0] ?? null);
            $items = $category ? array_values(array_filter($products, fn ($p) => in_array((int) ($category['id'] ?? 0), array_map('intval', (array) ($p['category_ids'] ?? [])), true))) : [];
            foreach (array_slice($items, 0, $limit) as $p) {
                $body .= "<a href='".$productUrl($p)."' class='group block overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl' style='{$cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)'><img src='".$img($p)."' alt='".e((string) ($p['featured_image_alt'] ?? $p['title'] ?? ''))."' class='aspect-[4/5] w-full object-cover'><div class='p-5'><h3 class='text-[16px] font-semibold tracking-[-0.02em]'>".e((string) ($p['title'] ?? ''))."</h3><p class='mt-2 text-[15px] font-bold'>".$price($p)."</p></div></a>";
            }
            $collectionUrl = e((string) ($category['storefront_url'] ?? '#'));
            $buttonLabel = e((string) ($block['button_label'] ?? 'View collection'));
            $body = "<div data-commerce-body class='grid gap-5 sm:grid-cols-2 lg:grid-cols-4'>{$body}</div>".($category ? "<a href='{$collectionUrl}' class='mt-7 inline-flex min-h-11 items-center justify-center rounded-xl border px-5 py-2.5 text-sm font-bold' style='{$cardStyle}'>{$buttonLabel}</a>" : '');
        } elseif ($type === 'commerce_promo_split') {
            $eyebrow = e((string) ($block['eyebrow'] ?? 'Limited collection'));
            $promoHeading = e((string) ($block['heading'] ?? 'A standout offer for the season'));
            $promoText = e((string) ($block['text'] ?? 'Pair a strong message with a product-led visual and a clear next step.'));
            $buttonLabel = e((string) ($block['button_label'] ?? 'Shop the collection'));
            $buttonUrl = e((string) ($block['button_url'] ?? '/shop'));
            $promoImage = e(self::staticAssetUrl((string) ($block['image_url'] ?? '/storage/cms-images/background/background-3.avif')));
            $promoAlt = e((string) ($block['image_alt'] ?? $promoHeading));
            $body = "<div class='grid overflow-hidden rounded-[30px] border lg:grid-cols-[1.02fr_.98fr]' style='{$cardStyle}'><div class='flex flex-col justify-center p-7 sm:p-10 lg:p-12'><p class='text-[11px] font-bold uppercase tracking-[0.2em]' style='color:var(--commerce-accent)'>{$eyebrow}</p><h2 class='mt-4 text-3xl font-semibold tracking-[-0.04em] sm:text-4xl'>{$promoHeading}</h2><p class='mt-4 max-w-xl text-[15px] leading-7' style='color:var(--commerce-muted)'>{$promoText}</p><div><a href='{$buttonUrl}' class='mt-7 inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-2.5 text-sm font-bold' style='background:var(--commerce-accent);color:var(--commerce-bg)'>{$buttonLabel}</a></div></div><img src='{$promoImage}' alt='{$promoAlt}' class='h-full min-h-[300px] w-full object-cover'></div>";
        } elseif ($type === 'commerce_benefits_strip') {
            $benefitMarkup = '';
            for ($i = 1; $i <= 4; $i++) {
                $benefitTitle = e((string) ($block["benefit_{$i}_title"] ?? ''));
                if ($benefitTitle === '') continue;
                $benefitText = e((string) ($block["benefit_{$i}_text"] ?? ''));
                $benefitMarkup .= "<div class='flex gap-3'><div class='mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-black' style='background:color-mix(in srgb,var(--commerce-accent) 12%,transparent);color:var(--commerce-accent)'>✓</div><div><h3 class='text-sm font-bold'>{$benefitTitle}</h3><p class='mt-1 text-xs leading-5' style='color:var(--commerce-muted)'>{$benefitText}</p></div></div>";
            }
            $benefitsHeading = e((string) ($block['heading'] ?? 'Shop with confidence'));
            $body = "<div class='rounded-[26px] border px-6 py-7 sm:px-8' style='{$cardStyle}'><h2 class='mb-6 text-xl font-semibold tracking-[-0.03em]'>{$benefitsHeading}</h2><div class='grid gap-5 sm:grid-cols-2 lg:grid-cols-4'>{$benefitMarkup}</div></div>";
        } elseif (in_array($type, ['commerce_cart_classic', 'commerce_cart_split', 'commerce_cart_compact'], true)) {
            $shopUrl = e((string) ($commerce['runtime_urls']['shop'] ?? '#'));
            $checkoutUrl = e((string) ($commerce['runtime_urls']['checkout'] ?? '#'));
            $continueLabel = e((string) ($block['continue_label'] ?? ($type === 'commerce_cart_split' ? 'Keep shopping' : ($type === 'commerce_cart_compact' ? 'Back to shop' : 'Continue shopping'))));
            $checkoutLabel = e((string) ($block['checkout_label'] ?? ($type === 'commerce_cart_split' ? 'Secure checkout' : ($type === 'commerce_cart_compact' ? 'Checkout' : 'Proceed to checkout'))));
            $preview = array_slice($products, 0, $type === 'commerce_cart_compact' ? 3 : 2);
            $previewSubtotal = 0;
            $lines = '';
            foreach ($preview as $index => $p) {
                $qty = $index + 1;
                $unitMinor = isset($p['sale_price_minor']) && $p['sale_price_minor'] !== null ? (int) $p['sale_price_minor'] : (int) ($p['regular_price_minor'] ?? 0);
                $previewSubtotal += $unitMinor * $qty;
                $linePrice = self::commerceMoney($unitMinor * $qty, $currency, $decimals);
                $title = e((string) ($p['title'] ?? 'Product'));
                $image = $img($p);
                if ($type === 'commerce_cart_compact') {
                    $lines .= "<div class='grid grid-cols-[64px_1fr_auto] items-center gap-3 p-3'><img src='{$image}' alt='{$title}' class='h-16 w-16 rounded-xl object-cover'><div class='min-w-0'><h3 class='truncate text-sm font-bold'>{$title}</h3><p class='mt-1 text-xs' style='color:var(--commerce-muted)'>Qty {$qty} · Demo preview</p></div><strong class='text-sm'>{$linePrice}</strong></div>";
                } else {
                    $lines .= "<div class='grid grid-cols-[82px_1fr_auto] items-center gap-4 rounded-2xl border p-3.5' style='{$cardStyle}'><img src='{$image}' alt='{$title}' class='h-[82px] w-[82px] rounded-xl object-cover'><div class='min-w-0'><h3 class='truncate text-sm font-bold'>{$title}</h3><p class='mt-1 text-xs' style='color:var(--commerce-muted)'>Qty {$qty} · Demo preview</p></div><strong class='text-sm'>{$linePrice}</strong></div>";
                }
            }
            if ($lines === '') $lines = "<div class='rounded-2xl border border-dashed p-8 text-center text-sm' style='border-color:var(--commerce-border);color:var(--commerce-muted)'>Live cart items will appear here on the protected Cart route.</div>";
            $subtotal = self::commerceMoney($previewSubtotal, $currency, $decimals);
            $summary = "<aside class='rounded-[26px] border p-6' style='{$cardStyle}'><p class='text-[11px] font-bold uppercase tracking-[0.18em]' style='color:var(--commerce-muted)'>Order summary</p><div class='mt-5 flex items-center justify-between text-sm'><span style='color:var(--commerce-muted)'>Preview subtotal</span><strong>{$subtotal}</strong></div><div class='mt-3 flex items-center justify-between text-sm'><span style='color:var(--commerce-muted)'>Shipping & tax</span><span>At checkout</span></div><div class='my-5 border-t' style='border-color:var(--commerce-border)'></div><div class='flex items-center justify-between'><strong>Estimated total</strong><strong class='text-xl'>{$subtotal}</strong></div><a href='{$checkoutUrl}' class='mt-6 block w-full rounded-xl px-4 py-3 text-center text-sm font-bold' style='background:var(--commerce-accent);color:var(--commerce-bg)'>{$checkoutLabel}</a><p class='mt-3 text-center text-[11px] leading-5' style='color:var(--commerce-muted)'>Layout preview only. Live cart quantities, coupons and totals remain controlled by the commerce runtime.</p></aside>";
            $continue = "<a href='{$shopUrl}' class='mt-5 inline-flex text-sm font-bold' style='color:var(--commerce-accent)'>← {$continueLabel}</a>";
            if ($type === 'commerce_cart_split') {
                $body = "<div class='grid gap-8 lg:grid-cols-[1.2fr_.8fr] lg:items-start'><div><div class='space-y-3'>{$lines}</div>{$continue}</div>{$summary}</div>";
            } elseif ($type === 'commerce_cart_compact') {
                $body = "<div class='grid gap-5 lg:grid-cols-[1fr_320px]'><div class='divide-y rounded-2xl border' style='border-color:var(--commerce-border);background:var(--commerce-surface)'>{$lines}</div>{$summary}</div>{$continue}";
            } else {
                $body = "<div class='grid gap-7 lg:grid-cols-[1fr_360px]'><div><div class='space-y-3'>{$lines}</div>{$continue}</div>{$summary}</div>";
            }
        } elseif (in_array($type, ['commerce_checkout_classic', 'commerce_checkout_split', 'commerce_checkout_express'], true)) {
            $items = array_slice($products, 0, 2);
            $previewSubtotal = 0;
            $lines = '';
            foreach ($items as $index => $p) {
                $qty = $index + 1;
                $unitMinor = isset($p['sale_price_minor']) && $p['sale_price_minor'] !== null ? (int) $p['sale_price_minor'] : (int) ($p['regular_price_minor'] ?? 0);
                $previewSubtotal += $unitMinor * $qty;
                $title = e((string) ($p['title'] ?? 'Product'));
                $lines .= "<div class='flex items-center gap-3'><img src='".$img($p)."' alt='{$title}' class='h-12 w-12 rounded-xl object-cover'><div class='min-w-0 flex-1'><p class='truncate text-sm font-semibold'>{$title}</p><p class='text-[11px]' style='color:var(--commerce-muted)'>Qty {$qty}</p></div><strong class='text-xs'>".self::commerceMoney($unitMinor * $qty, $currency, $decimals)."</strong></div>";
            }
            if ($lines === '') $lines = "<p class='text-sm' style='color:var(--commerce-muted)'>Live order items appear at checkout.</p>";
            $subtotal = self::commerceMoney($previewSubtotal, $currency, $decimals);
            $paymentLabel = e((string) ($block['payment_label'] ?? ($type === 'commerce_checkout_split' ? 'Pay securely' : ($type === 'commerce_checkout_express' ? 'Complete purchase' : 'Continue to payment'))));
            $helpText = e((string) ($block['help_text'] ?? 'Secure checkout powered by the commerce runtime.'));
            $field = static fn (string $label, string $placeholder, bool $wide = false) => "<label class='".($wide?'sm:col-span-2':'')."'><span class='mb-1.5 block text-[11px] font-bold uppercase tracking-[0.13em]' style='color:var(--commerce-muted)'>".e($label)."</span><div class='min-h-11 rounded-xl border px-3.5 py-3 text-sm' style='border-color:var(--commerce-border);background:var(--commerce-surface);color:var(--commerce-muted)'>".e($placeholder)."</div></label>";
            $compact = $type === 'commerce_checkout_express';
            $customer = "<div class='rounded-[26px] border ".($compact?'p-4 sm:p-5':'p-5 sm:p-6')."' style='{$cardStyle}'><div class='flex items-center justify-between gap-4'><h3 class='text-base font-bold'>Customer details</h3><span class='text-[10px] font-bold uppercase tracking-[0.14em]' style='color:var(--commerce-accent)'>Runtime bound</span></div><div class='mt-5 grid gap-3 sm:grid-cols-2'>".$field('First name','Alex').$field('Last name','Morgan').$field('Email','alex@example.com',true).$field('Country','Select country').$field('Region','State / region').(!$compact?$field('Street address','123 Commerce Street',true):'')."</div></div>";
            $delivery = "<div class='rounded-[26px] border p-5 sm:p-6' style='{$cardStyle}'><h3 class='text-base font-bold'>Delivery & promo</h3><div class='mt-5 grid gap-3 sm:grid-cols-2'>".$field('Shipping','Calculated by destination').$field('Promo code','Enter code')."</div><p class='mt-4 text-xs leading-5' style='color:var(--commerce-muted)'>Live checkout recalculates shipping, coupons and tax on the protected runtime route.</p></div>";
            $summary = "<aside class='rounded-[26px] border p-5 sm:p-6' style='{$cardStyle}'><div class='flex items-center justify-between'><h3 class='text-base font-bold'>Order summary</h3><span class='text-[10px] font-bold uppercase tracking-[0.14em]' style='color:var(--commerce-accent)'>Preview</span></div><div class='mt-5 space-y-3'>{$lines}</div><div class='my-5 border-t' style='border-color:var(--commerce-border)'></div><div class='flex items-center justify-between text-sm'><span style='color:var(--commerce-muted)'>Preview subtotal</span><strong>{$subtotal}</strong></div><div class='mt-3 flex items-center justify-between text-sm'><span style='color:var(--commerce-muted)'>Shipping & tax</span><span>Calculated live</span></div><div class='my-5 border-t' style='border-color:var(--commerce-border)'></div><div class='flex items-center justify-between'><strong>Total</strong><strong class='text-xl'>{$subtotal}</strong></div><div class='mt-6 rounded-xl px-4 py-3 text-center text-sm font-bold' style='background:var(--commerce-accent);color:var(--commerce-bg)'>{$paymentLabel}</div><p class='mt-3 text-center text-[11px] leading-5' style='color:var(--commerce-muted)'>{$helpText}</p></aside>";
            if ($type === 'commerce_checkout_split') {
                $body = "<div class='grid gap-8 lg:grid-cols-[1.12fr_.88fr] lg:items-start'><div><div class='space-y-5'>{$customer}{$delivery}</div></div>{$summary}</div>";
            } elseif ($type === 'commerce_checkout_express') {
                $body = "<div class='mx-auto max-w-5xl'><div class='grid gap-5 lg:grid-cols-[1fr_330px]'>{$customer}{$summary}</div></div>";
            } else {
                $body = "<div class='grid gap-6 lg:grid-cols-[1fr_360px]'><div class='space-y-5'>{$customer}{$delivery}</div>{$summary}</div>";
            }
        } elseif ($type === 'commerce_categories') {
            foreach ($categories as $c) {
                $body .= "<a href='".e((string) ($c['storefront_url'] ?? '#'))."' class='block rounded-2xl border p-6' style='{$cardStyle}'><div class='text-lg font-bold'>".e((string) ($c['name'] ?? ''))."</div><p class='mt-2 text-sm' style='color:var(--commerce-muted)'>".e((string) ($c['description'] ?? 'Explore this collection'))."</p></a>";
            }
            $body = "<div data-commerce-body class='grid gap-4 sm:grid-cols-2 lg:grid-cols-3'>{$body}</div>";
        } elseif ($type === 'commerce_price') {
            $body = $product ? "<div data-commerce-body class='rounded-2xl border p-6' style='{$cardStyle}'><p class='text-xs font-semibold uppercase tracking-widest' style='color:var(--commerce-muted)'>".e((string) ($block['label'] ?? 'Price'))."</p><div class='mt-2 text-4xl font-black'>".$price($product)."</div></div>" : "<div data-commerce-body>No published product</div>";
        } elseif ($type === 'commerce_product_gallery') {
            $images = [];
            if ($product) {
                if (!empty($product['featured_image_url'])) $images[] = $product['featured_image_url'];
                foreach ((array) ($product['gallery'] ?? []) as $g) if (!empty($g['url'])) $images[] = $g['url'];
            }
            $images = array_values(array_unique($images));
            $main = $images[0] ?? '/storage/cms-images/background/background-1.avif';
            $thumbs=''; foreach(array_slice($images,1,4) as $u) $thumbs.="<img src='".e(self::staticAssetUrl($u))."' alt='' class='aspect-square w-full rounded-xl object-cover'>";
            $body = "<div data-commerce-body class='grid gap-4 md:grid-cols-[2fr_1fr]'><img src='".e(self::staticAssetUrl($main))."' alt='".e((string) ($product['title'] ?? ''))."' class='aspect-square w-full rounded-2xl object-cover'><div class='grid grid-cols-2 gap-3'>{$thumbs}</div></div>";
        } elseif ($type === 'commerce_variation_selector') {
            $options=''; foreach ((array) ($product['options'] ?? []) as $o) { $vals=''; foreach((array)($o['values']??[]) as $v) $vals.="<span class='rounded-xl border px-4 py-2 text-sm font-semibold' style='border-color:var(--commerce-border);background:var(--commerce-surface)'>".e((string)($v['label']??''))."</span>"; $options.="<div><p class='mb-2 text-sm font-semibold'>".e((string)($o['name']??''))."</p><div class='flex flex-wrap gap-2'>{$vals}</div></div>"; }
            $body = "<div data-commerce-body class='space-y-5'>{$options}</div>";
        } elseif ($type === 'commerce_related_products') {
            $categoryIds = array_map('intval', (array) ($product['category_ids'] ?? []));
            $related = array_values(array_filter($products, fn($p) => (int)($p['id']??0)!==(int)($product['id']??0) && (!$categoryIds || array_intersect($categoryIds, array_map('intval',(array)($p['category_ids']??[]))))));
            foreach(array_slice($related,0,$limit) as $p) $body.="<a href='".$productUrl($p)."' class='block overflow-hidden rounded-2xl border' style='{$cardStyle}'><img src='".$img($p)."' alt='' class='aspect-square w-full object-cover'><div class='p-4'><b>".e((string)($p['title']??''))."</b><p class='mt-1 text-sm'>".$price($p)."</p></div></a>";
            $body = "<div data-commerce-body class='grid gap-5 sm:grid-cols-2 lg:grid-cols-4'>{$body}</div>";
        }

        $config = e(json_encode(['type'=>$type,'block'=>$block,'endpoint'=>$runtimeEndpoint,'product_id'=>$boundId], JSON_UNESCAPED_SLASHES));
        $script = '';
        if ($runtimeEndpoint !== '') {
            $runtimeJs = <<<'JS'
(function(){
  const root=document.getElementById(__ROOT_ID__); if(!root) return;
  const cfg=JSON.parse(root.dataset.commerceConfig||'{}');
  const esc=(v)=>String(v??'').replace(/[&<>"']/g,(c)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const money=(minor,data)=>{ if(minor===null||minor===undefined||minor==='') return '—'; const d=Number(data.currency_decimals??2); return String(data.currency||'USD')+' '+(Number(minor)/(10**d)).toFixed(d); };
  const image=(p)=>p?.featured_image_url||p?.gallery?.[0]?.url||'/storage/cms-images/background/background-1.avif';
  const price=(p,data)=>money(p?.sale_price_minor??p?.regular_price_minor,data);
  const products=(data)=>(data.products||[]);
  const selected=(data)=>products(data).find(p=>Number(p.id)===Number(cfg.product_id))||products(data)[0]||null;
  const body=root.querySelector('[data-commerce-body]'); if(!body) return;
  const cardStyle='background:var(--commerce-surface);color:var(--commerce-text);border-color:var(--commerce-border)';
  const render=(data)=>{
    const type=cfg.type, block=cfg.block||{}, list=products(data), product=selected(data), limit=Math.max(1,Number(block.limit||8));
    if(['commerce_catalog_grid','commerce_catalog_editorial','commerce_catalog_compact'].includes(type)){
      const cats=data.categories||[];
      const catName=(p)=>{const ids=(p.category_ids||[]).map(Number); return cats.filter(c=>ids.includes(Number(c.id))).map(c=>c.name).join(' · ')||'Catalog product';};
      const attrs=(p)=>`data-catalog-product data-title="${esc(String(p.title||'').toLowerCase())}" data-category-ids="${esc((p.category_ids||[]).join(','))}" data-price="${Number(p.sale_price_minor??p.regular_price_minor??0)}" data-id="${Number(p.id||0)}" data-featured="${p.is_featured?1:0}"`;
      const renderCard=(p,i)=>{
        if(type==='commerce_catalog_editorial') return `<a ${attrs(p)} href="${esc(p.storefront_url||'#')}" class="group overflow-hidden rounded-[26px] border ${i%5===0?'lg:col-span-7':'lg:col-span-5'}" style="${cardStyle}"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="${i%5===0?'aspect-[16/10]':'aspect-[4/3]'} w-full object-cover"><div class="p-6 sm:p-7"><p class="text-[10px] font-bold uppercase tracking-[0.18em]" style="color:var(--commerce-muted)">${esc(catName(p))}</p><div class="mt-2 flex items-end justify-between gap-5"><h3 class="text-xl font-semibold tracking-[-0.03em]">${esc(p.title)}</h3><p class="shrink-0 text-base font-bold">${esc(price(p,data))}</p></div></div></a>`;
        if(type==='commerce_catalog_compact') return `<a ${attrs(p)} href="${esc(p.storefront_url||'#')}" class="grid grid-cols-[72px_1fr_auto] items-center gap-4 p-3.5 transition hover:bg-black/5"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="h-[72px] w-[72px] rounded-xl object-cover"><div class="min-w-0"><h3 class="truncate text-sm font-semibold">${esc(p.title)}</h3><p class="mt-1 truncate text-xs" style="color:var(--commerce-muted)">${esc(catName(p))}</p></div><div class="text-right"><p class="text-sm font-bold">${esc(price(p,data))}</p>${p.track_inventory?`<p class="mt-1 text-[11px]" style="color:var(--commerce-muted)">${Number(p.stock_quantity||0)>0?esc(p.stock_quantity)+' in stock':(p.allow_backorders?'Backorder':'Out of stock')}</p>`:''}</div></a>`;
        return `<a ${attrs(p)} href="${esc(p.storefront_url||'#')}" class="group overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style="${cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="aspect-[4/5] w-full object-cover"><div class="p-5"><h3 class="text-[16px] font-semibold tracking-[-0.02em]">${esc(p.title)}</h3><div class="mt-3 flex items-center justify-between gap-3"><p class="text-[15px] font-bold">${esc(price(p,data))}</p>${p.is_featured?`<span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide" style="background:color-mix(in srgb,var(--commerce-accent) 12%,transparent);color:var(--commerce-accent)">Featured</span>`:''}</div></div></a>`;
      };
      body.innerHTML=list.slice(0,limit).map(renderCard).join('');
      const search=root.querySelector('[data-catalog-search]'), category=root.querySelector('[data-catalog-category]'), sort=root.querySelector('[data-catalog-sort]'), empty=root.querySelector('[data-catalog-empty]');
      const apply=()=>{let rows=[...body.querySelectorAll('[data-catalog-product]')], q=String(search?.value||'').trim().toLowerCase(), cat=String(category?.value||''); rows.forEach(el=>el.hidden=!!((q&&!String(el.dataset.title||'').includes(q))||(cat&&!String(el.dataset.categoryIds||'').split(',').includes(cat)))); const visible=rows.filter(el=>!el.hidden); const mode=sort?.value||'featured'; visible.sort((a,b)=>mode==='price_asc'?Number(a.dataset.price)-Number(b.dataset.price):mode==='price_desc'?Number(b.dataset.price)-Number(a.dataset.price):mode==='name'?String(a.dataset.title).localeCompare(String(b.dataset.title)):mode==='newest'?Number(b.dataset.id)-Number(a.dataset.id):Number(b.dataset.featured)-Number(a.dataset.featured)); visible.forEach(el=>body.appendChild(el)); if(empty) empty.classList.toggle('hidden',visible.length>0);};
      search?.addEventListener('input',apply); category?.addEventListener('change',apply); sort?.addEventListener('change',apply); apply();
    } else if(type==='commerce_product_grid'){
      body.innerHTML=list.filter(p=>!block.featured_only||p.is_featured).slice(0,limit).map(p=>`<a href="${esc(p.storefront_url||'#')}" class="group block overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style="${cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="aspect-square w-full object-cover"><div class="p-5"><h3 class="text-[16px] font-semibold tracking-[-0.02em]">${esc(p.title)}</h3><div class="mt-3 flex items-center justify-between gap-3"><p class="text-[15px] font-bold">${esc(price(p,data))}</p>${p.track_inventory?`<span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background:color-mix(in srgb,var(--commerce-accent) 12%,transparent);color:var(--commerce-accent)">${Number(p.stock_quantity||0)>0?esc(p.stock_quantity)+' in stock':(p.allow_backorders?'Available on backorder':'Out of stock')}</span>`:''}</div></div></a>`).join('');
    } else if(type==='commerce_featured_products'){
      const featured=list.filter(p=>p.is_featured); const items=(featured.length?featured:list).slice(0,limit);
      body.innerHTML=items.map(p=>`<a href="${esc(p.storefront_url||'#')}" class="group block overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style="${cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="aspect-[4/5] w-full object-cover"><div class="p-5"><h3 class="text-[16px] font-semibold tracking-[-0.02em]">${esc(p.title)}</h3><p class="mt-2 text-[15px] font-bold">${esc(price(p,data))}</p></div></a>`).join('');
    } else if(type==='commerce_featured_collection'){
      const cats=data.categories||[], cat=cats.find(c=>Number(c.id)===Number(block.category_id))||cats[0]||null;
      body.innerHTML=cat?list.filter(p=>(p.category_ids||[]).map(Number).includes(Number(cat.id))).slice(0,limit).map(p=>`<a href="${esc(p.storefront_url||'#')}" class="group block overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style="${cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="aspect-[4/5] w-full object-cover"><div class="p-5"><h3 class="text-[16px] font-semibold tracking-[-0.02em]">${esc(p.title)}</h3><p class="mt-2 text-[15px] font-bold">${esc(price(p,data))}</p></div></a>`).join(''):'';
    } else if(type==='commerce_categories'){
      body.innerHTML=(data.categories||[]).map(c=>`<a href="${esc(c.storefront_url||'#')}" class="block rounded-2xl border p-6" style="${cardStyle}"><div class="text-lg font-bold">${esc(c.name)}</div><p class="mt-2 text-sm" style="color:var(--commerce-muted)">${esc(c.description||'Explore this collection')}</p></a>`).join('');
    } else if(type==='commerce_price'){
      body.innerHTML=product?`<div class="rounded-2xl border p-6" style="${cardStyle}"><p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--commerce-muted)">${esc(block.label||'Price')}</p><div class="mt-2 text-4xl font-black">${esc(price(product,data))}</div></div>`:'No published product';
    } else if(type==='commerce_product_gallery'){
      if(!product){body.innerHTML='No published product';return;} const imgs=[image(product),...(product.gallery||[]).map(g=>g.url)].filter((v,i,a)=>v&&a.indexOf(v)===i); body.innerHTML=`<img src="${esc(imgs[0]||'')}" alt="${esc(product.title||'')}" class="aspect-square w-full rounded-2xl object-cover"><div class="grid grid-cols-2 gap-3">${imgs.slice(1,5).map(u=>`<img src="${esc(u)}" alt="" class="aspect-square w-full rounded-xl object-cover">`).join('')}</div>`;
    } else if(type==='commerce_variation_selector'){
      body.innerHTML=product?(product.options||[]).map(o=>`<div><p class="mb-2 text-sm font-semibold">${esc(o.name)}</p><div class="flex flex-wrap gap-2">${(o.values||[]).map(v=>`<span class="rounded-xl border px-4 py-2 text-sm font-semibold" style="border-color:var(--commerce-border);background:var(--commerce-surface)">${esc(v.label)}</span>`).join('')}</div></div>`).join(''):'No published product';
    } else if(type==='commerce_related_products'){
      if(!product){body.innerHTML='';return;} const cats=new Set((product.category_ids||[]).map(Number)); body.innerHTML=list.filter(p=>Number(p.id)!==Number(product.id)&&(!cats.size||(p.category_ids||[]).some(id=>cats.has(Number(id))))).slice(0,limit).map(p=>`<a href="${esc(p.storefront_url||'#')}" class="block overflow-hidden rounded-2xl border" style="${cardStyle}"><img src="${esc(image(p))}" alt="" class="aspect-square w-full object-cover"><div class="p-4"><b>${esc(p.title)}</b><p class="mt-1 text-sm">${esc(price(p,data))}</p></div></a>`).join('');
    }
    root.dataset.commerceLive='1';
  };
  fetch(cfg.endpoint,{headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():Promise.reject(r.status)).then(data=>{window.CosmicCommerceRuntime=window.CosmicCommerceRuntime||{};window.CosmicCommerceRuntime[cfg.endpoint]=data;render(data);}).catch(()=>{root.dataset.commerceLive='0';});
})();
JS;
            $runtimeJs = str_replace('__ROOT_ID__', json_encode($rootId), $runtimeJs);
            $script = '<script>'.$runtimeJs.'</script>';
        }

        $outerHead = in_array($type, ['commerce_promo_split', 'commerce_benefits_strip'], true) ? '' : "<div class='mb-8 max-w-2xl'><h2 class='text-3xl font-semibold tracking-[-0.035em] md:text-4xl'>{$heading}</h2>".($text!==''?"<p class='mt-3 max-w-xl text-[15px] leading-7' style='color:var(--commerce-muted)'>{$text}</p>":'')."</div>";
        return "<section id='{$rootId}' data-cosmic-commerce-spark='".e($type)."' data-commerce-config='{$config}' class='w-full px-6 py-16 md:px-12 lg:py-20' style='{$vars};font-family:Manrope,ui-sans-serif,system-ui,sans-serif'><style>#{$rootId} h1,#{$rootId} h2,#{$rootId} h3,#{$rootId} h4{font-family:Manrope,ui-sans-serif,system-ui,sans-serif;font-weight:700}</style><div class='mx-auto max-w-7xl'>{$outerHead}{$body}</div></section>{$script}";
    }

   public static function compile(array $blocks, string $primaryColor = null, array $context = []): string
    {
        $html = "";
        self::$currentPageStyle = PageStyleRegistry::normalize($context['page_style'] ?? null);
    
        // 2. Mapping


        foreach ($blocks as $index => $block) {

            // Track only the HTML emitted by this Spark so we can attach its
            // semantic resolved theme to the first section without touching
            // nested sections emitted by other Sparks. Static overlay headers
            // use this marker at runtime to decide whether the first Spark is PRIMARY.
            $fragmentStart = strlen($html);

            $pattern = PageStyleRegistry::pattern(self::$currentPageStyle);

            $blockTheme = $block['theme'] ?? "auto";

            // Clean owns the complete light section rhythm. Historical explicit
            // Spark theme values must not re-introduce primary/double-white runs.
            if (self::$currentPageStyle === 'clean' || $blockTheme === "auto") {
                $blockTheme = $pattern[$index % count($pattern)];
            }

            switch ($blockTheme) {

                case "primary":
                    $selectedThemeName = $primaryColor;
                    break;

                case "white":
                    $selectedThemeName = "white";
                    break;

                case "surface":
                    $selectedThemeName = "stone";
                    break;

                default:
                    $selectedThemeName = $primaryColor;
                    break;
            }

            $theme = self::getTheme($selectedThemeName);


            $type = $block['type'] ?? '';


            $stoneTheme = self::getTheme('stone');

            switch ($type) {
                case 'commerce_product_grid':
                case 'commerce_catalog_grid':
                case 'commerce_catalog_editorial':
                case 'commerce_catalog_compact':
                case 'commerce_categories':
                case 'commerce_product_gallery':
                case 'commerce_price':
                case 'commerce_variation_selector':
                case 'commerce_related_products':
                case 'commerce_featured_products':
                case 'commerce_featured_collection':
                case 'commerce_promo_split':
                case 'commerce_benefits_strip':
                case 'commerce_cart_classic':
                case 'commerce_cart_split':
                case 'commerce_cart_compact':
                case 'commerce_checkout_classic':
                case 'commerce_checkout_split':
                case 'commerce_checkout_express':
                    $html .= self::commerceSparkHtml($type, $block, $context, $theme);
                    break;

                case 'newsletter_cta':
                // Editorial newsletter callouts remain neutral so they pair
                // with the white Blog Hub regardless of the website theme.
                $eyebrow = e($block['eyebrow'] ?? 'Stay in the loop');
                $heading = e($block['heading'] ?? 'Get weekly insights');
                $text = e($block['text'] ?? 'Practical ideas, useful updates, and new resources delivered occasionally.');
                $placeholder = e($block['placeholder'] ?? 'Your email address');
                $buttonLabel = e($block['button_label'] ?? 'Subscribe');
                $disclaimer = e($block['disclaimer'] ?? 'No spam. Unsubscribe anytime.');
                $html .= "<section class='bg-[#fcfcfb] px-6 py-14 sm:px-8 lg:px-12 lg:py-20'><div class='mx-auto max-w-7xl'><div class='rounded-3xl border border-slate-800 bg-slate-950 px-6 py-10 text-white shadow-[0_24px_70px_rgba(15,23,42,0.16)] sm:px-10 lg:flex lg:items-center lg:justify-between lg:gap-12 lg:px-14 lg:py-12'><div class='max-w-2xl'><p class='text-xs font-semibold uppercase tracking-[0.28em] text-violet-200'>{$eyebrow}</p><h2 class='mt-4 text-3xl font-bold leading-[1.05] tracking-tight sm:text-4xl'>{$heading}</h2><p class='mt-4 max-w-xl text-base leading-7 text-slate-300'>{$text}</p></div><form class='mt-8 w-full max-w-md lg:mt-0' onsubmit='return false'><div class='flex flex-col gap-3 sm:flex-row'><input type='email' aria-label='Email address' placeholder='{$placeholder}' class='min-h-[50px] flex-1 rounded-xl border border-white/15 bg-white/10 px-4 text-sm text-white placeholder:text-slate-400 outline-none'><button type='submit' class='min-h-[50px] rounded-xl bg-white px-6 text-sm font-bold text-slate-950'>{$buttonLabel}</button></div><p class='mt-3 text-xs text-slate-400'>{$disclaimer}</p></form></div></div></section>";
                break;

                case 'latest_resources':
                $eyebrow = e($block['eyebrow'] ?? 'Keep exploring');
                $heading = e($block['heading'] ?? 'Latest resources');
                $text = e($block['text'] ?? 'Helpful next reads for visitors who want to learn more.');
                $resources = is_array($block['resources'] ?? null) ? array_slice($block['resources'], 0, 2) : [
                    ['eyebrow' => 'Guide', 'title' => 'A practical checklist for your next step', 'text' => 'A concise starting point for making a clearer, more confident decision.', 'cta_label' => 'Read the guide', 'cta_url' => '#'],
                    ['eyebrow' => 'Resource', 'title' => 'Questions worth asking before you begin', 'text' => 'Use this focused resource to prepare for a better conversation with your team.', 'cta_label' => 'Explore resource', 'cta_url' => '#'],
                ];
                $resourceMarkup = '';
                foreach ($resources as $resource) {
                    if (!is_array($resource)) continue;
                    $resourceMarkup .= "<article class='group rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg sm:p-8'><p class='text-[11px] font-semibold uppercase tracking-[0.22em] text-violet-700'>" . e($resource['eyebrow'] ?? 'Resource') . "</p><h3 class='mt-4 text-2xl font-bold leading-tight tracking-tight text-slate-900'>" . e($resource['title'] ?? '') . "</h3><p class='mt-4 text-sm leading-6 text-slate-600'>" . e($resource['text'] ?? '') . "</p><a href='" . e($resource['cta_url'] ?? '#') . "' class='mt-7 inline-flex text-sm font-semibold text-slate-900 underline decoration-slate-300 underline-offset-4 transition group-hover:decoration-slate-900'>" . e($resource['cta_label'] ?? 'Read more') . "</a></article>";
                }
                $html .= "<section class='bg-[#fcfcfb] px-6 py-16 sm:px-8 lg:px-12 lg:py-24'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><p class='text-xs font-semibold uppercase tracking-[0.28em] text-slate-500'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight text-slate-900 sm:text-5xl lg:text-[3.75rem]'>{$heading}</h2><p class='mt-5 max-w-2xl text-base leading-7 text-slate-600'>{$text}</p></div><div class='mt-10 grid gap-5 md:grid-cols-2'>{$resourceMarkup}</div></div></section>";
                break;


                case 'content_grid_classic':
                case 'content_grid_editorial':
                case 'content_grid_compact':
                case 'content_featured_entry':
                case 'content_latest_entries':
                case 'content_events_grid':
                $contentTypes = is_array($context['content_types'] ?? null) ? $context['content_types'] : [];
                $sourceSlug = (string) ($block['content_type_slug'] ?? ($type === 'content_events_grid' ? 'events' : 'blog'));
                $contentType = null;
                foreach ($contentTypes as $candidate) {
                    if (is_array($candidate) && (string) ($candidate['slug'] ?? '') === $sourceSlug) { $contentType = $candidate; break; }
                }
                if (!$contentType && isset($contentTypes[0]) && is_array($contentTypes[0])) $contentType = $contentTypes[0];
                $entries = is_array($contentType['entries'] ?? null) ? $contentType['entries'] : [];
                $categoryFilter = trim((string) ($block['category'] ?? ''));
                $tagFilter = trim((string) ($block['tag'] ?? ''));
                $entries = array_values(array_filter($entries, function ($entry) use ($categoryFilter, $tagFilter, $type, $block) {
                    if (!is_array($entry)) return false;
                    if ($categoryFilter !== '' && (string) ($entry['category'] ?? '') !== $categoryFilter) return false;
                    if ($tagFilter !== '' && !in_array($tagFilter, is_array($entry['tags'] ?? null) ? $entry['tags'] : [], true)) return false;
                    if (($type === 'content_events_grid' || !empty($block['upcoming_only'])) && !empty($entry['custom_fields']['start_date'])) {
                        $eventTs = strtotime((string) $entry['custom_fields']['start_date']);
                        if ($eventTs && $eventTs < strtotime('today')) return false;
                    }
                    return true;
                }));
                if (!empty($block['featured_only'])) {
                    $featuredEntries = array_values(array_filter($entries, fn ($entry) => !empty($entry['is_featured'])));
                    if ($featuredEntries !== []) $entries = $featuredEntries;
                }
                $sort = (string) ($block['sort'] ?? 'newest');
                usort($entries, function ($a, $b) use ($sort) {
                    if ($sort === 'title') return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
                    if ($sort === 'event_date') return strcmp((string) ($a['custom_fields']['start_date'] ?? ''), (string) ($b['custom_fields']['start_date'] ?? ''));
                    $at = strtotime((string) ($a['published_at'] ?? $a['updated_at'] ?? '')) ?: 0;
                    $bt = strtotime((string) ($b['published_at'] ?? $b['updated_at'] ?? '')) ?: 0;
                    return $sort === 'oldest' ? ($at <=> $bt) : ($bt <=> $at);
                });
                $entries = array_slice($entries, 0, min(24, max(1, (int) ($block['limit'] ?? 6))));
                $columns = min(4, max(1, (int) ($block['columns'] ?? 3)));
                $eyebrow = e($block['eyebrow'] ?? 'Latest stories');
                $heading = e($block['heading'] ?? 'Fresh from our updates');
                $text = e($block['text'] ?? 'Explore the latest articles, events, projects, and updates.');
                $entryMarkup = '';
                foreach ($entries as $entry) {
                    $title = e($entry['title'] ?? 'Untitled'); $excerpt = e($entry['excerpt'] ?? '');
                    $category = e($entry['category'] ?? ($contentType['singular_name'] ?? 'Update'));
                    $url = e($entry['url'] ?? ('/' . $sourceSlug . '/' . ($entry['slug'] ?? '')));
                    $image = e(self::staticAssetUrl($entry['featured_image_url'] ?? '/storage/cms-images/background/background-1.avif'));
                    if ($type === 'content_events_grid') {
                        $start = (string) ($entry['custom_fields']['start_date'] ?? '');
                        $month = $start ? strtoupper(date('M', strtotime($start))) : 'EVENT'; $day = $start ? date('d', strtotime($start)) : '—';
                        $venue = e($entry['custom_fields']['venue'] ?? 'Upcoming event');
                        $registration = e($entry['custom_fields']['registration_url'] ?? $url);
                        $entryMarkup .= "<article class='rounded-2xl border p-6 {$theme['card']} {$theme['border']}'><div class='flex gap-4'><div class='min-w-16 rounded-xl border p-3 text-center {$theme['border']}'><div class='text-xs font-bold uppercase {$theme['sub']}'>{$month}</div><div class='text-2xl font-bold {$theme['text']}'>{$day}</div></div><div><p class='text-xs font-bold uppercase tracking-[.18em] {$theme['sub']}'>{$venue}</p><h3 class='mt-2 text-xl font-bold {$theme['text']}'>{$title}</h3></div></div><p class='mt-4 text-sm leading-6 {$theme['sub']}'>{$excerpt}</p><a href='{$registration}' class='mt-5 inline-flex font-bold {$theme['text']}'>" . (!empty($entry['custom_fields']['registration_url']) ? 'Register' : 'View event') . " →</a></article>";
                    } elseif ($type === 'content_grid_compact' || $type === 'content_latest_entries') {
                        $entryMarkup .= "<article class='grid gap-4 border-b p-5 last:border-b-0 sm:grid-cols-[96px_1fr_auto] sm:items-center {$theme['border']}'><img src='{$image}' alt='' class='h-20 w-24 rounded-xl object-cover'><div><p class='text-[10px] font-bold uppercase tracking-[.18em] {$theme['sub']}'>{$category}</p><h3 class='mt-1 text-lg font-bold {$theme['text']}'>{$title}</h3><p class='mt-1 text-sm {$theme['sub']}'>{$excerpt}</p></div><a href='{$url}' class='text-sm font-bold {$theme['text']}'>View →</a></article>";
                    } elseif ($type === 'content_grid_editorial') {
                        $entryMarkup .= "<article class='overflow-hidden rounded-2xl border {$theme['card']} {$theme['border']}'><a href='{$url}'><img src='{$image}' alt='{$title}' class='h-64 w-full object-cover'></a><div class='p-6'><p class='text-[11px] font-bold uppercase tracking-[.2em] {$theme['sub']}'>{$category}</p><h3 class='mt-3 text-2xl font-bold tracking-tight {$theme['text']}'><a href='{$url}'>{$title}</a></h3><p class='mt-4 text-sm leading-6 {$theme['sub']}'>{$excerpt}</p><a href='{$url}' class='mt-5 inline-flex text-sm font-bold {$theme['text']}'>Explore story →</a></div></article>";
                    } else {
                        $entryMarkup .= "<article class='overflow-hidden rounded-2xl border shadow-sm {$theme['card']} {$theme['border']}'><a href='{$url}'><img src='{$image}' alt='{$title}' class='h-52 w-full object-cover'></a><div class='p-5'><p class='text-[11px] font-bold uppercase tracking-[.2em] {$theme['sub']}'>{$category}</p><h3 class='mt-3 text-xl font-bold {$theme['text']}'><a href='{$url}'>{$title}</a></h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$excerpt}</p><a href='{$url}' class='mt-5 inline-flex text-sm font-bold {$theme['text']}'>Read more →</a></div></article>";
                    }
                }
                if ($entryMarkup === '') $entryMarkup = "<div class='rounded-2xl border border-dashed p-8 text-center {$theme['border']} {$theme['sub']}'>No published entries match this Spark yet.</div>";
                $loopGridClass = $columns === 1 ? 'grid gap-5' : ($columns === 2 ? 'grid gap-5 md:grid-cols-2' : ($columns === 4 ? 'grid gap-5 sm:grid-cols-2 xl:grid-cols-4' : 'grid gap-5 sm:grid-cols-2 lg:grid-cols-3'));
                $gridClass = $type === 'content_events_grid' ? 'grid gap-5 md:grid-cols-2 lg:grid-cols-3' : (($type === 'content_grid_compact' || $type === 'content_latest_entries') ? "rounded-2xl border {$theme['card']} {$theme['border']}" : $loopGridClass);
                $html .= "<section class='px-6 py-16 sm:px-8 lg:px-12 lg:py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-9 max-w-3xl'><p class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-3 text-3xl font-bold tracking-tight sm:text-4xl {$theme['text']}'>{$heading}</h2><p class='mt-3 text-base leading-7 {$theme['sub']}'>{$text}</p></div><div class='{$gridClass}'>{$entryMarkup}</div></div></section>";
                break;

                case 'mini_hero_minimal':
                case 'mini_hero_split':
                case 'mini_hero_promo':
                $eyebrow = e($block['eyebrow'] ?? 'Explore more');
                $heading = e($block['heading'] ?? 'A focused page for what matters next');
                $text = e($block['text'] ?? 'Use a compact hero to introduce this page without taking over the whole screen.');
                $buttonLabel = e($block['button_label'] ?? 'Explore');
                $buttonUrl = e($block['button_url'] ?? '#');
                $image = e(self::staticAssetUrl($block['image_url'] ?? '/storage/cms-images/background/background-2.avif'));
                $imageAlt = e($block['image_alt'] ?? $block['heading'] ?? 'Featured page image');
                $cta = $buttonLabel !== '' ? "<a href='{$buttonUrl}' class='mt-7 inline-flex min-h-11 items-center justify-center rounded-xl border px-5 py-2.5 text-sm font-bold transition hover:-translate-y-0.5 {$theme['card']} {$theme['text']} {$theme['border']}'>{$buttonLabel}</a>" : '';

                if ($type === 'mini_hero_split') {
                    $html .= "<section class='border-b px-6 py-10 sm:px-8 sm:py-12 lg:px-12 lg:py-14 {$theme['bg']} {$theme['border']}'><div class='mx-auto grid max-w-7xl items-center gap-8 lg:grid-cols-[1.05fr_.95fr] lg:gap-12'><div class='max-w-2xl'><p class='text-xs font-bold uppercase tracking-[0.24em] {$theme['sub']}'>{$eyebrow}</p><h1 class='mt-3 text-4xl font-semibold leading-[1.02] tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h1><p class='mt-4 text-base leading-7 {$theme['sub']}'>{$text}</p>{$cta}</div><div class='overflow-hidden rounded-[1.75rem] border p-2 shadow-xl {$theme['card']} {$theme['border']}'><img src='{$image}' alt='{$imageAlt}' class='h-56 w-full rounded-[1.35rem] object-cover sm:h-64 lg:h-72'></div></div></section>";
                } elseif ($type === 'mini_hero_promo') {
                    $html .= "<section class='border-b px-6 py-10 sm:px-8 lg:px-12 lg:py-12 {$theme['bg']} {$theme['border']}'><div class='mx-auto max-w-7xl'><div class='relative overflow-hidden rounded-[2rem] border p-7 shadow-xl sm:p-9 lg:p-11 {$theme['card']} {$theme['border']}'><div class='absolute inset-y-0 right-0 hidden w-[38%] lg:block'><img src='{$image}' alt='' class='h-full w-full object-cover opacity-20'></div><div class='relative max-w-3xl'><p class='text-xs font-bold uppercase tracking-[0.24em] {$theme['sub']}'>{$eyebrow}</p><h1 class='mt-3 text-3xl font-semibold leading-[1.04] tracking-tight sm:text-4xl lg:text-5xl {$theme['text']}'>{$heading}</h1><p class='mt-4 max-w-2xl text-base leading-7 {$theme['sub']}'>{$text}</p>{$cta}</div></div></div></section>";
                } else {
                    $html .= "<section class='relative overflow-hidden border-b px-6 py-14 sm:px-8 sm:py-16 lg:px-12 lg:py-20 {$theme['bg']} {$theme['border']}'><div class='pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full opacity-10 blur-3xl {$theme['card']}'></div><div class='relative mx-auto max-w-7xl'><div class='max-w-3xl'><p class='text-xs font-bold uppercase tracking-[0.24em] {$theme['sub']}'>{$eyebrow}</p><h1 class='mt-3 text-4xl font-semibold leading-[1.02] tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h1><p class='mt-4 max-w-2xl text-base leading-7 {$theme['sub']}'>{$text}</p>{$cta}</div></div></section>";
                }
                break;

                case 'blog_mini_hero':
                $eyebrow = e($block['eyebrow'] ?? 'Latest insights');
                $heading = e($block['heading'] ?? 'Ideas for building a better business');
                $text = e($block['text'] ?? 'Practical notes, useful perspectives, and updates from our team.');
                $html .= "<section class='relative overflow-hidden border-b px-6 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-24 {$theme['bg']} {$theme['border']}'><div class='pointer-events-none absolute -right-24 -top-28 h-72 w-72 rounded-full {$theme['card']} opacity-10 blur-3xl'></div><div class='relative mx-auto max-w-7xl'><div class='max-w-3xl'><p class='text-xs font-semibold uppercase tracking-[0.28em] {$theme['sub']}'>{$eyebrow}</p><h1 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h1><p class='mt-5 max-w-2xl text-base leading-7 sm:text-lg {$theme['sub']}'>{$text}</p></div></div></section>";
                break;

                case 'blog_hub':
                // Editorial hubs are intentionally neutral. Unlike surrounding
                // hero/supporting sections, their reading surface never inherits
                // the website's primary color.
                $theme = self::getTheme('white');
                $eyebrow = e($block['eyebrow'] ?? 'Latest insights');
                $heading = e($block['heading'] ?? 'Ideas for building a better business');
                $text = e($block['text'] ?? 'Practical notes, useful perspectives, and updates from our team.');
                $showIntro = ($block['show_intro'] ?? true) !== false;
                $featured = is_array($block['featured'] ?? null) ? $block['featured'] : [];
                $featuredCategory = e($featured['category'] ?? 'Featured article');
                $featuredTitle = e($featured['title'] ?? 'A clearer way to plan your next project');
                $featuredExcerpt = e($featured['excerpt'] ?? 'Thoughtful guidance for turning a good idea into a focused, useful website.');
                $featuredImage = e(self::staticAssetUrl($featured['image_url'] ?? '/storage/cms-images/background/background-1.avif'));
                $featuredCta = e($featured['cta_label'] ?? 'Read article');
                $featuredUrl = e($featured['url'] ?? '#');
                $posts = is_array($block['posts'] ?? null) ? array_slice($block['posts'], 0, 4) : [
                    ['category' => 'Strategy', 'title' => 'Start with the problem worth solving', 'excerpt' => 'A simple framework for making your first website decisions clearer.', 'image_url' => '/storage/cms-images/background/background-2.avif'],
                    ['category' => 'Design', 'title' => 'Consistency earns customer trust', 'excerpt' => 'A focused visual system helps every page feel more credible.', 'image_url' => '/storage/cms-images/background/background-3.avif'],
                    ['category' => 'Updates', 'title' => 'What a publish-ready website needs', 'excerpt' => 'The details that help you go from draft to a confident launch.', 'image_url' => '/storage/cms-images/background/background-5.avif'],
                    ['category' => 'Growth', 'title' => 'Make your next update easier to manage', 'excerpt' => 'Keep content and customer questions organized.', 'image_url' => '/storage/cms-images/background/background-1.avif'],
                ];
                // The deployment package provides only published database posts.
                // Keep the starter cards for legacy/static previews with no context.
                if (array_key_exists('blog_posts', $context)) {
                    $contextPosts = is_array($context['blog_posts']) ? $context['blog_posts'] : [];
                    $featuredIndex = null;

                    foreach ($contextPosts as $index => $post) {
                        if (is_array($post) && !empty($post['is_featured'])) {
                            $featuredIndex = $index;
                            break;
                        }
                    }

                    if ($featuredIndex !== null) {
                        $featured = $contextPosts[$featuredIndex];
                        unset($contextPosts[$featuredIndex]);
                    } else {
                        $featured = array_shift($contextPosts);
                    }

                    $posts = array_slice(array_values($contextPosts), 0, 4);

                    if (is_array($featured)) {
                        $featuredCategory = e($featured['category'] ?? 'Featured article');
                        $featuredTitle = e($featured['title'] ?? 'Untitled article');
                        $featuredExcerpt = e($featured['excerpt'] ?? '');
                        $featuredImage = e(self::staticAssetUrl($featured['image_url'] ?? '/storage/cms-images/background/background-1.avif'));
                        $featuredCta = 'Read article';
                        $featuredUrl = e($featured['url'] ?? '#');
                    }
                }

                $postMarkup = '';
                foreach ($posts as $post) {
                    if (!is_array($post)) continue;
                    $postUrl = e($post['url'] ?? '#');
                    $postMarkup .= "<article class='overflow-hidden rounded-2xl border {$theme['border']} {$theme['card']}'><img src='" . e(self::staticAssetUrl($post['image_url'] ?? '/storage/cms-images/background/background-1.avif')) . "' alt='" . e($post['title'] ?? 'Article image') . "' width='640' height='352' loading='lazy' decoding='async' class='h-44 w-full object-cover'><div class='p-5'><p class='text-[11px] font-semibold uppercase tracking-[0.2em] {$theme['sub']}'>" . e($post['category'] ?? 'Article') . "</p><h3 class='mt-3 text-lg font-bold leading-snug {$theme['text']}'>" . e($post['title'] ?? '') . "</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>" . e($post['excerpt'] ?? '') . "</p><a href='{$postUrl}' class='mt-4 inline-block text-sm font-semibold {$theme['text']} hover:underline'>Read article</a></div></article>";
                }
                $introMarkup = $showIntro
                    ? "<div class='max-w-3xl'><p class='text-xs font-semibold uppercase tracking-[0.28em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-2xl text-base leading-7 {$theme['sub']}'>{$text}</p></div>"
                    : '';
                $featuredSpacing = $showIntro ? 'mt-12' : '';
                $html .= "<section class='px-6 py-16 sm:px-8 lg:px-12 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'>{$introMarkup}<article class='{$featuredSpacing} grid overflow-hidden rounded-3xl border {$theme['border']} {$theme['card']} md:grid-cols-2'><img src='{$featuredImage}' alt='{$featuredTitle}' width='960' height='640' loading='lazy' decoding='async' class='min-h-[260px] h-full w-full object-cover'><div class='flex min-h-[260px] flex-col justify-center p-7 sm:p-10'><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$featuredCategory}</p><h3 class='mt-4 text-3xl font-bold tracking-tight {$theme['text']}'>{$featuredTitle}</h3><p class='mt-4 text-base leading-7 {$theme['sub']}'>{$featuredExcerpt}</p><a href='{$featuredUrl}' class='mt-7 text-sm font-semibold {$theme['text']} hover:underline'>{$featuredCta}</a></div></article><div class='mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-4'>{$postMarkup}</div></div></section>";
                break;

                case 'hero_centered_cta':
                $tagline = e($block['tagline'] ?? 'LOREM IPSUM DOLOR');
                $heading = e($block['heading'] ?? '');
                $subheading = e($block['subheading'] ?? $block['text'] ?? '');
                $btnLabel = e($block['button_label'] ?? 'Get Started');
                $btnUrl = e($block['button_url'] ?? '#');
                // Contact submit is always a branded primary action, including on light/surface forms.
                $primaryTheme = self::getTheme($primaryColor);
                $buttonClasses = "{$primaryTheme['bg']} text-white hover:opacity-90";
                $html .= "
                <section class='relative flex min-h-[500px] w-full items-center overflow-hidden border-b px-7 py-20 text-center sm:min-h-[560px] sm:px-10 sm:py-24 lg:min-h-[620px] lg:px-12 lg:py-28 {$theme['bg']} {$theme['border']}'>
                    <div class='pointer-events-none absolute -left-32 -top-32 h-[30rem] w-[30rem] rounded-full {$theme['card']} opacity-[0.14] blur-[140px]'></div>
                    <div class='pointer-events-none absolute -bottom-40 -right-32 h-[32rem] w-[32rem] rounded-full {$theme['card']} opacity-[0.1] blur-[150px]'></div>
                    <div class='pointer-events-none absolute inset-x-[12%] top-0 border-t {$theme['border']} opacity-70'></div>
                    <div class='relative z-10 mx-auto flex max-w-5xl flex-col items-center space-y-7'>
                        <span class='block text-xs font-semibold uppercase tracking-[0.32em] {$theme['sub']}'>
                            {$tagline}
                        </span>
                        <h1 class='block max-w-5xl text-5xl font-bold leading-[1.03] tracking-tight sm:text-6xl lg:text-7xl {$theme['text']}'>
                            {$heading}
                        </h1>
                        <p class='mx-auto max-w-3xl text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>
                            {$subheading}
                        </p>
                        <a href='{$btnUrl}' class='inline-flex min-h-[52px] items-center justify-center rounded-full px-8 font-bold shadow-lg transition hover:opacity-90 {$buttonClasses}'>
                            {$btnLabel}
                        </a>
                    </div>
                </section>";

                break;

                case 'faq_accordion':
                $eyebrow = e($block['eyebrow'] ?? 'Helpful answers');
                $heading = e($block['heading'] ?? 'Questions, answered clearly');
                $text = e($block['text'] ?? 'Everything visitors need to know before taking the next step.');
                $faqs = is_array($block['faqs'] ?? null) ? array_slice($block['faqs'], 0, 8) : [];
                if (empty($faqs)) {
                    $faqs = [
                        ['question' => 'What can I expect?', 'answer' => 'Clear communication, practical guidance, and a straightforward next step.'],
                        ['question' => 'How do I get started?', 'answer' => 'Send an inquiry and we will help you choose the option that fits your needs.'],
                        ['question' => 'Can I ask a specific question?', 'answer' => 'Absolutely. Share a little context and we will point you in the right direction.'],
                        ['question' => 'When will I hear back?', 'answer' => 'We aim to respond as soon as we can with the details you need.'],
                    ];
                }
                $faqMarkup = '';
                foreach ($faqs as $faq) {
                    $question = e($faq['question'] ?? 'Question');
                    $answer = e($faq['answer'] ?? 'Answer');
                    $faqMarkup .= "<details class='group border-b p-5 last:border-b-0 sm:p-6 {$theme['border']}'><summary class='flex cursor-pointer list-none items-center justify-between gap-5 text-base font-semibold {$theme['text']}'><span>{$question}</span><span class='text-xl transition group-open:rotate-45'>+</span></summary><p class='pt-4 text-sm leading-6 {$theme['sub']}'>{$answer}</p></details>";
                }
                $html .= "<section class='px-6 py-16 sm:px-8 lg:py-20 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16'><div><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-xl text-base leading-7 {$theme['sub']}'>{$text}</p></div><div class='overflow-hidden rounded-2xl border {$theme['border']} {$theme['card']}'>{$faqMarkup}</div></div></section>";
                break;

                case 'contact_details':
                $eyebrow = e($block['eyebrow'] ?? 'Get in touch');
                $heading = e($block['heading'] ?? 'A clear way to reach us');
                $text = e($block['text'] ?? 'Share what you need and we will help you find the right next step.');
                $details = [
                    'Email' => e($block['email'] ?? 'hello@example.com'),
                    'Phone' => e($block['phone'] ?? '+1 (555) 010-0200'),
                    'Visit' => e($block['address'] ?? 'Serving clients by appointment'),
                    'Hours' => e($block['hours'] ?? 'Monday to Friday, 9:00 AM to 5:00 PM'),
                ];
                $detailMarkup = '';
                $detailIndex = 0;
                foreach ($details as $label => $value) {
                    $bottom = $detailIndex < 2 ? 'border-b' : '';
                    $right = $detailIndex % 2 === 0 ? 'sm:border-r' : '';
                    $detailMarkup .= "<div class='min-h-36 p-6 {$bottom} {$right} {$theme['border']}'><p class='text-xs font-semibold uppercase tracking-[0.18em] {$theme['sub']}'>{$label}</p><p class='mt-4 text-base font-semibold leading-6 {$theme['text']}'>{$value}</p></div>";
                    $detailIndex++;
                }
                $html .= "<section class='px-6 py-16 sm:px-8 lg:py-20 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-10 lg:grid-cols-2 lg:gap-16'><div><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-xl text-base leading-7 {$theme['sub']}'>{$text}</p></div><div class='grid overflow-hidden rounded-2xl border sm:grid-cols-2 {$theme['border']} {$theme['card']}'>{$detailMarkup}</div></div></section>";
                break;

                case 'location_map':
                $eyebrow = e($block['eyebrow'] ?? 'Find us');
                $heading = e($block['heading'] ?? 'Close when you need us');
                $text = e($block['text'] ?? 'Visit by appointment or get in touch to confirm the best time.');
                $locationName = e($block['location_name'] ?? 'Your business location');
                $address = e($block['address'] ?? 'Serving your local area');
                $serviceArea = e($block['service_area'] ?? 'Appointments and service visits available.');
                $directionsLabel = e($block['directions_label'] ?? 'Get directions');
                $html .= "<section class='px-6 py-16 sm:px-8 lg:py-20 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-16'><div><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-xl text-base leading-7 {$theme['sub']}'>{$text}</p></div><div class='relative min-h-[22rem] overflow-hidden rounded-3xl border p-7 sm:p-9 {$theme['border']} {$theme['card']}'><div class='absolute inset-0 opacity-30 [background-image:linear-gradient(currentColor_1px,transparent_1px),linear-gradient(90deg,currentColor_1px,transparent_1px)] [background-size:2.5rem_2.5rem] {$theme['sub']}'></div><div class='relative flex min-h-[16rem] h-full flex-col justify-between'><div class='grid h-14 w-14 place-items-center rounded-full border-8 {$theme['border']} {$theme['bg']}'><span class='h-3 w-3 rounded-full bg-current {$theme['text']}'></span></div><div class='max-w-md rounded-2xl border p-5 backdrop-blur {$theme['border']} {$theme['card']}'><p class='text-lg font-semibold {$theme['text']}'>{$locationName}</p><p class='mt-2 text-sm leading-6 {$theme['sub']}'>{$address}</p><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$serviceArea}</p><span class='mt-5 inline-block text-sm font-semibold {$theme['text']}'>{$directionsLabel}</span></div></div></div></div></section>";
                break;

                case 'case_studies_grid':
                $eyebrow = e($block['eyebrow'] ?? 'Selected work');
                $heading = e($block['heading'] ?? 'Results that make the difference.');
                $text = e($block['text'] ?? 'A closer look at practical work shaped around clear goals and useful outcomes.');
                $studies = is_array($block['studies'] ?? null) ? array_slice($block['studies'], 0, 12) : [];
                $studyFallbacks = [
                    '/storage/cms-images/background/background-1.avif',
                    '/storage/cms-images/background/background-2.avif',
                    '/storage/cms-images/background/background-3.avif',
                ];
                if (empty($studies)) {
                    $studies = [[
                        'category' => 'Selected work',
                        'title' => 'A clearer path forward',
                        'summary' => 'A focused project built around useful decisions and a stronger customer experience.',
                        'result' => 'Ready for the next step',
                        'link_label' => 'View case study',
                    ]];
                }
                $studyMarkup = '';
                foreach ($studies as $index => $study) {
                    $imageSource = trim((string) ($study['image_url'] ?? ''));
                    if ($imageSource === '') {
                        $imageSource = $studyFallbacks[$index % count($studyFallbacks)];
                    }
                    $imageUrl = e(self::staticAssetUrl($imageSource));
                    $category = e($study['category'] ?? 'Selected work');
                    $title = e($study['title'] ?? 'A clearer path forward');
                    $summary = e($study['summary'] ?? 'A focused project built around useful decisions and a stronger customer experience.');
                    $result = e($study['result'] ?? 'Ready for the next step');
                    $linkLabel = e($study['link_label'] ?? 'View case study');
                    $featured = $index === 0 ? 'lg:col-span-2 lg:grid lg:grid-cols-2' : '';
                    $imageHeight = $index === 0 ? 'min-h-[18rem] lg:h-full' : 'aspect-[16/10]';
                    $studyMarkup .= "<article class='group overflow-hidden rounded-3xl border {$theme['border']} {$theme['card']} {$featured}'><img src='{$imageUrl}' alt='' width='960' height='640' loading='lazy' decoding='async' class='w-full object-cover {$imageHeight}'><div class='flex flex-col justify-center p-6 sm:p-8 " . ($index === 0 ? 'lg:p-10' : '') . "'><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$category}</p><h3 class='mt-4 text-2xl font-bold leading-tight tracking-tight {$theme['text']}'>{$title}</h3><p class='mt-4 text-sm leading-6 {$theme['sub']}'>{$summary}</p><p class='mt-6 text-sm font-semibold {$theme['text']}'>{$result}</p><span class='mt-5 text-sm font-semibold {$theme['text']}'>{$linkLabel}</span></div></article>";
                }
                $html .= "<section class='px-6 py-16 sm:px-8 lg:py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-10 max-w-2xl sm:mb-12'><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2><p class='mt-5 text-base leading-7 {$theme['sub']}'>{$text}</p></div><div class='grid gap-5 lg:grid-cols-2'>{$studyMarkup}</div></div></section>";
                break;

                case 'jobs_list':
                $eyebrow = e($block['eyebrow'] ?? 'Join our team');
                $heading = e($block['heading'] ?? 'Do work that moves things forward.');
                $text = e($block['text'] ?? 'We are looking for thoughtful people who care about good work and shared progress.');
                $jobs = is_array($block['jobs'] ?? null) ? array_slice($block['jobs'], 0, 16) : [];
                if (empty($jobs)) {
                    $jobs = [['title' => 'Your next role', 'type' => 'Full-time', 'location' => 'By arrangement', 'description' => 'A meaningful opportunity for someone ready to contribute thoughtful work.', 'button_label' => 'View role']];
                }
                $jobMarkup = '';
                foreach ($jobs as $index => $job) {
                    $title = e($job['title'] ?? 'Your next role');
                    $type = e($job['type'] ?? 'Full-time');
                    $location = e($job['location'] ?? 'By arrangement');
                    $description = e($job['description'] ?? 'A meaningful opportunity for someone ready to contribute thoughtful work.');
                    $buttonLabel = e($job['button_label'] ?? 'View role');
                    $topBorder = $index > 0 ? "border-t {$theme['border']}" : '';
                    $jobMarkup .= "<article class='grid gap-5 p-6 sm:p-7 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center {$topBorder}'><div><div class='flex flex-wrap items-center gap-3'><h3 class='text-xl font-bold {$theme['text']}'>{$title}</h3><span class='rounded-full border px-2.5 py-1 text-xs font-semibold {$theme['border']} {$theme['sub']}'>{$type}</span></div><p class='mt-2 text-sm font-medium {$theme['sub']}'>{$location}</p><p class='mt-3 max-w-2xl text-sm leading-6 {$theme['sub']}'>{$description}</p></div><span class='text-sm font-semibold {$theme['text']}'>{$buttonLabel}</span></article>";
                }
                $html .= "<section class='px-6 py-16 sm:px-8 lg:py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-10 max-w-2xl sm:mb-12'><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2><p class='mt-5 text-base leading-7 {$theme['sub']}'>{$text}</p></div><div class='overflow-hidden rounded-2xl border {$theme['border']} {$theme['card']}'>{$jobMarkup}</div></div></section>";
                break;

                case 'events_grid':
                $eyebrow = e($block['eyebrow'] ?? 'Upcoming events');
                $heading = e($block['heading'] ?? 'Useful conversations, coming up.');
                $text = e($block['text'] ?? 'Join practical sessions, thoughtful gatherings, and opportunities to connect with our team.');
                $events = is_array($block['events'] ?? null) ? array_slice($block['events'], 0, 12) : [];
                if (empty($events)) {
                    $events = [['month' => 'OCT', 'day' => '12', 'title' => 'A useful conversation for your next move', 'date' => 'October 12', 'location' => 'Online', 'description' => 'A focused session with useful ideas you can put into action right away.', 'button_label' => 'Learn more']];
                }
                $eventMarkup = '';
                foreach ($events as $event) {
                    $month = e($event['month'] ?? 'OCT');
                    $day = e($event['day'] ?? '12');
                    $title = e($event['title'] ?? 'A useful conversation for your next move');
                    $date = e($event['date'] ?? 'Date to be announced');
                    $location = e($event['location'] ?? 'By arrangement');
                    $description = e($event['description'] ?? 'A focused session with useful ideas you can put into action right away.');
                    $buttonLabel = e($event['button_label'] ?? 'Learn more');
                    $eventMarkup .= "<article class='flex min-h-full flex-col rounded-2xl border p-6 {$theme['border']} {$theme['card']}'><div class='flex items-start gap-4'><div class='grid h-16 w-16 shrink-0 place-items-center rounded-xl border text-center {$theme['border']}'><span class='block text-[10px] font-bold tracking-[0.18em] {$theme['sub']}'>{$month}</span><span class='block text-2xl font-bold leading-none {$theme['text']}'>{$day}</span></div><div><p class='text-xs font-semibold {$theme['sub']}'>{$date}</p><p class='mt-1 text-xs {$theme['sub']}'>{$location}</p></div></div><h3 class='mt-7 text-xl font-bold leading-tight {$theme['text']}'>{$title}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$description}</p><span class='mt-6 text-sm font-semibold {$theme['text']}'>{$buttonLabel}</span></article>";
                }
                $html .= "<section class='px-6 py-16 sm:px-8 lg:py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-10 max-w-2xl sm:mb-12'><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2><p class='mt-5 text-base leading-7 {$theme['sub']}'>{$text}</p></div><div class='grid gap-5 md:grid-cols-3'>{$eventMarkup}</div></div></section>";
                break;

                case 'contact_form_modern':
                $eyebrow = e($block['eyebrow'] ?? 'START A CONVERSATION');
                $heading = e($block['heading'] ?? 'Let’s talk about what’s next.');
                $text = e($block['text'] ?? 'Tell us a little about your goals and our team will help you find the right next step.');
                $email = e($block['email'] ?? 'hello@example.com');
                $phone = e($block['phone'] ?? '+1 (555) 010-0200');
                $address = e($block['address'] ?? 'Available by appointment');
                $submitLabel = e($block['submit_label'] ?? 'Send inquiry');
                // Contact submit is always a branded primary action, including on light/surface forms.
                $primaryTheme = self::getTheme($primaryColor);
                $buttonClasses = "{$primaryTheme['bg']} text-white hover:opacity-90";
                $inputClasses = $blockTheme === 'primary'
                    ? "border-white/20 bg-slate-950/20 placeholder:text-white/40 focus:border-white/60 {$theme['text']}"
                    : "bg-transparent {$theme['border']} {$theme['text']}";
                // Keep native form controls legible against the resolved block surface.
                // Primary is the dark theme slot; white and surface use light controls.
                $nativeColorScheme = $blockTheme === 'primary' ? 'dark' : 'light';
                // Use one explicit icon in the static export while the entire
                // date field remains the click target for the native picker.
                $calendarIconStroke = $nativeColorScheme === 'dark' ? '%23ffffff' : '%230f172a';
                $calendarIcon = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='{$calendarIconStroke}' stroke-width='2'%3E%3Crect x='3' y='5' width='18' height='16' rx='2'/%3E%3Cpath d='M16 3v4M8 3v4M3 10h18'/%3E%3C/svg%3E";
                $contactNativeControlStyles = "[data-cosmic-contact-form][data-cosmic-contact-scheme='{$nativeColorScheme}'] input[type=date]{color-scheme:{$nativeColorScheme};cursor:pointer;background-image:url(\"{$calendarIcon}\");background-position:right 1rem center;background-repeat:no-repeat;background-size:1rem;padding-right:3rem}";
                $contactNativeControlStyles .= "[data-cosmic-contact-form][data-cosmic-contact-scheme='{$nativeColorScheme}'] input[type=date]::-webkit-calendar-picker-indicator{opacity:0}";
                $contactNativeControlStyles .= "[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] [data-cosmic-contact-select]{background:#fff!important;color:#0f172a!important;border-color:#cbd5e1!important}";
                $contactNativeControlStyles .= "[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] [data-cosmic-contact-select] option{background:#fff!important;color:#0f172a!important}";
                $contactNativeControlStyles .= "[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] [data-cosmic-contact-select]{background:rgba(15,23,42,.38)!important;color:#f8fafc!important;border-color:rgba(255,255,255,.22)!important}";
                $contactNativeControlStyles .= "[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] [data-cosmic-contact-select] option{background:#0f172a!important;color:#f8fafc!important}";
                $formFields = self::contactFieldsMarkup(self::contactFields($block['fields'] ?? null), $theme, $inputClasses, $nativeColorScheme);

                $html .= "
                <section class='relative overflow-hidden px-7 py-16 sm:px-10 sm:py-20 lg:px-12 lg:py-24 {$theme['bg']}'>
                    <div class='pointer-events-none absolute -left-32 top-1/2 h-80 w-80 -translate-y-1/2 rounded-full {$theme['card']} opacity-[0.1] blur-[120px]'></div>
                    <div class='relative mx-auto grid max-w-7xl gap-12 lg:grid-cols-[0.88fr_1.12fr] lg:items-start lg:gap-20'>
                        <div class='max-w-xl pt-2'>
                            <p class='text-xs font-semibold uppercase tracking-[0.3em] {$theme['sub']}'>{$eyebrow}</p>
                            <h2 class='mt-5 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2>
                            <p class='mt-5 text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>{$text}</p>
                            <div class='mt-9 space-y-4 border-t pt-7 {$theme['border']}'>
                                <div><p class='text-xs font-semibold uppercase tracking-[0.18em] {$theme['sub']}'>Email</p><p class='mt-1 text-base font-semibold {$theme['text']}'>{$email}</p></div>
                                <div><p class='text-xs font-semibold uppercase tracking-[0.18em] {$theme['sub']}'>Phone</p><p class='mt-1 text-base font-semibold {$theme['text']}'>{$phone}</p></div>
                                <div><p class='text-xs font-semibold uppercase tracking-[0.18em] {$theme['sub']}'>Visit</p><p class='mt-1 text-base font-semibold {$theme['text']}'>{$address}</p></div>
                            </div>
                        </div>
                        <style>{$contactNativeControlStyles}</style>
                        <form action='./cosmic-sync/contact.php' method='post' data-cosmic-contact-form data-cosmic-contact-scheme='{$nativeColorScheme}' class='rounded-[2rem] border p-5 shadow-2xl sm:p-8 {$theme['card']} {$theme['border']}'>
                            <div class='space-y-5'>{$formFields}</div>
                            <label class='hidden' aria-hidden='true'>Company<input name='company' tabindex='-1' autocomplete='off'></label>
                            <button type='submit' class='mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-xl px-6 text-sm font-bold {$buttonClasses}'>{$submitLabel}</button>
                            <p data-cosmic-contact-status aria-live='polite' class='mt-3 text-center text-xs {$theme['sub']}'>We’ll use your details only to respond to your inquiry.</p>
                        </form>
                    </div>
                </section>";

                $html .= <<<'HTML'
                <script>
                document.querySelectorAll('[data-cosmic-contact-form]').forEach(function (form) {
                    form.addEventListener('submit', async function (event) {
                        event.preventDefault();
                        var button = form.querySelector('button[type="submit"]');
                        var status = form.querySelector('[data-cosmic-contact-status]');
                        var originalLabel = button.textContent;

                        button.disabled = true;
                        button.classList.add('opacity-70', 'cursor-wait');
                        button.textContent = 'Sending…';
                        status.textContent = 'Sending your inquiry…';

                        try {
                            // Preview pages are served by Laravel, so the exported connector PHP path
                            // is not executable there. Route preview submissions through the public
                            // preview contact endpoint; exported/live static sites keep contact.php.
                            var previewSlug = null;
                            var pathMatch = window.location.pathname.match(/^\/preview\/([a-z0-9][a-z0-9-]{0,59})(?:\/|$)/i);
                            if (pathMatch) {
                                previewSlug = pathMatch[1];
                            } else if (/\.cosmiccms\.com$/i.test(window.location.hostname)) {
                                previewSlug = window.location.hostname.split('.')[0];
                            }
                            var submitUrl = previewSlug
                                ? '/api/v1/preview/' + encodeURIComponent(previewSlug) + '/contact'
                                : form.action;
                            var response = await fetch(submitUrl, {
                                method: 'POST',
                                body: new FormData(form),
                                headers: { 'Accept': 'application/json' },
                            });
                            var responseText = await response.text();
                            var result;

                            try {
                                result = JSON.parse(responseText);
                            } catch (parseError) {
                                throw new Error('The contact connector did not return a valid response. Reinstall the latest connector, then try again.');
                            }

                            if (!response.ok || result.status !== 'success') {
                                throw new Error(result.message || 'Your inquiry could not be sent.');
                            }

                            form.reset();
                            status.textContent = result.message;
                        } catch (error) {
                            status.textContent = error.message || 'Your inquiry could not be sent. Please try again.';
                        } finally {
                            button.disabled = false;
                            button.classList.remove('opacity-70', 'cursor-wait');
                            button.textContent = originalLabel;
                        }
                    });
                });
                </script>
HTML;

                break;



                case 'services_cards':
                $tagline = e($block['tagline'] ?? 'WHAT WE OFFER');
                $heading = e($block['heading'] ?? 'Solutions Designed To Help Your Business Grow');
                $description = e($block['description'] ?? 'We combine strategy, design, and technology to create digital experiences that help businesses grow with confidence.');

                $cards = $block['cards'] ?? [
                    [
                        'title' => 'Website Development',
                        'desc' => 'Modern, fast, and scalable websites tailored for your business.'
                    ],
                    [
                        'title' => 'UI / UX Design',
                        'desc' => 'Beautiful user experiences focused on clarity and conversion.'
                    ],
                    [
                        'title' => 'Digital Strategy',
                        'desc' => 'Helping businesses grow through thoughtful digital solutions.'
                    ]
                ];

                $icons = ['⚡', '💻', '🚀', '📈', '🛡️', '💡', '🎯', '✨'];
                $cardHtml = '';

                foreach ($cards as $i => $card) {

                    $title = e($card['title'] ?? '');
                    $desc = e($card['desc'] ?? '');
                    $icon = e($card['icon'] ?? $icons[$i % count($icons)]);

                    $cardHtml .= "
                    <div class='{$theme['card']} border {$theme['border']} rounded-3xl p-8 h-full flex flex-col transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl'>
                        <div class='cosmic-adaptive-icon-tile w-16 h-16 rounded-2xl border {$theme['border']} flex items-center justify-center text-2xl mb-6'>
                            {$icon}
                        </div>
                        <h3 class='text-2xl font-bold tracking-tight {$theme['text']}'>
                            {$title}
                        </h3>
                        <div class='w-14 h-px mt-5 mb-5 {$theme['border']} border-t'></div>
                        <p class='text-base leading-8 {$theme['sub']} flex-grow'>
                            {$desc}
                        </p>
                        <div class='mt-8'>
                            <span class='inline-flex items-center gap-2 text-sm font-semibold {$theme['text']} opacity-80 transition-all duration-300 hover:gap-3'>
                                Learn More
                                <span>→</span>
                            </span>
                        </div>
                    </div>";
                }

                $html .= "
                <section class='w-full py-32 px-7 md:px-8 transition-colors duration-500 {$theme['bg']}'>
                    <div class='max-w-7xl mx-auto'>
                        <div class='max-w-3xl mx-auto text-center mb-20'>
                            <span class='text-xs font-semibold tracking-[0.35em] uppercase {$theme['text']} opacity-70 block'>
                                {$tagline}
                            </span>
                            <h2 class='mt-5 text-4xl font-bold tracking-tight leading-[1.05] sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>
                                {$heading}
                            </h2>
                            <p class='mt-6 text-lg leading-8 {$theme['sub']}'>
                                {$description}
                            </p>
                        </div>
                        <div class='grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8'>
                            {$cardHtml}
                        </div>
                    </div>
                </section>";
                break;


                case 'glassmorphism_header':
                $logoText = e($block['logo_text'] ?? 'Your Website');
                $rawLogoImageUrl = (string) ($block['logo_image_url'] ?? '');
                $logoImageUrl = e(self::staticAssetUrl($rawLogoImageUrl));
                $logoFilter = e(self::isDefaultLogoPlaceholder($rawLogoImageUrl)
                    ? (string) ($block['logo_filter'] ?? self::logoFilter((string) ($block['logo_filter_key'] ?? 'midnight')))
                    : 'none');
                $logo = $logoImageUrl !== ''
                    ? "<img src='{$logoImageUrl}' alt='{$logoText}' style='filter:{$logoFilter}' class='h-14 w-auto max-w-[300px] object-contain'>"
                    : $logoText;
                $ctaLabel = e($block['cta_label'] ?? 'Get Started');
                $ctaUrl = e($block['cta_url'] ?? '#');
                $menuItems = $block['menu'] ?? [];
                $overlayRequested = (bool) ($block['overlay_header_on_banner'] ?? false);
                $overlayPrimaryAllowed = ! in_array(strtolower((string) ($primaryColor ?: 'midnight')), ['stone', 'white'], true);
                $overlayHeaderCompatible = in_array(self::$currentPageStyle, ['premium', 'balanced'], true)
                    && $overlayPrimaryAllowed;
                // Overlay Header is intentionally limited to Premium/Balanced on
                // compatible theme families. Clean, Warm Stone and Studio White
                // always render the normal header even if stale saved data says on.
                $overlayHeader = $overlayRequested && $overlayHeaderCompatible;
                $premiumOverlayHeader = $overlayHeader;
                $overlayToneClass = $premiumOverlayHeader
                    ? 'cosmic-overlay-tone-light cosmic-overlay-cta-surface'
                    : 'cosmic-overlay-tone-dark cosmic-overlay-cta-primary';
                $overlayHeaderClass = $overlayHeader
                    ? "cosmic-static-overlay-header {$overlayToneClass} absolute inset-x-0 top-0 border-transparent bg-transparent shadow-none"
                    : 'sticky top-0 bg-white shadow-sm';

                // Header always white in standard mode. Overlay mode preserves
                // the same component while letting the first Spark sit behind it.
                $headerBg = 'bg-white';
                $headerBorder = 'border-slate-200';
                $headerText = 'text-slate-900';
                $menuText = 'text-slate-600';

                // CTA button follows the primary theme.
                $buttonBg = $theme['bg'];
                $buttonText = $theme['text'];

                $renderDesktopMenu = function (array $items, int $depth = 0) use (&$renderDesktopMenu, $menuText): string {
                    $itemsHtml = '';

                    foreach ($items as $item) {
                        if (! is_array($item)) continue;

                        $url = e($item['url'] ?? '#');
                        $label = e($item['label'] ?? '');
                        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
                        $hasChildren = count($children) > 0;
                        $dropdown = '';

                        if ($hasChildren) {
                            // The wrapper touches its parent; inner padding creates visual space
                            // without introducing a hover gap that closes the submenu.
                            $dropdownPosition = $depth > 0
                                ? 'left-full top-0 pl-2'
                                : 'left-0 top-full pt-2';
                            $borderClass = $depth > 0 ? 'border-slate-300' : 'border-slate-200';
                            $dropdown = "<div class='menu-dropdown absolute {$dropdownPosition} z-50 min-w-52'>"
                                . "<ul class='list-none rounded-xl border {$borderClass} bg-white p-2 shadow-2xl ring-1 ring-slate-950/5'>"
                                . $renderDesktopMenu($children, $depth + 1)
                                . "</ul></div>";
                        }

                        $menuClass = $hasChildren ? "menu-node menu-depth-{$depth} relative" : 'menu-leaf';
                        $itemsHtml .= "<li class='{$menuClass}'>"
                            . "<a href='{$url}' class='{$menuText} flex items-center gap-1 whitespace-nowrap transition hover:text-slate-900'>{$label}" . ($hasChildren ? "<span aria-hidden='true' class='text-xs'>⌄</span>" : '') . "</a>"
                            . $dropdown
                            . "</li>";
                    }

                    return $itemsHtml;
                };

                $mobileMenuCounter = 0;
                $renderMobileMenu = function (array $items, int $depth = 0) use (&$renderMobileMenu, &$mobileMenuCounter): string {
                    $itemsHtml = '';

                    foreach ($items as $item) {
                        if (! is_array($item)) continue;

                        $url = e($item['url'] ?? '#');
                        $label = e($item['label'] ?? '');
                        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
                        $hasChildren = count($children) > 0;
                        $indent = min($depth, 3) * 16;

                        if (! $hasChildren) {
                            $itemsHtml .= "<li>"
                                . "<a data-cosmic-mobile-link href='{$url}' class='block rounded-xl px-3 py-3 text-base font-semibold text-slate-800 transition hover:bg-slate-100' style='margin-left: {$indent}px'>{$label}</a>"
                                . "</li>";
                            continue;
                        }

                        $mobileMenuCounter++;
                        $submenuId = 'cosmic-mobile-submenu-' . $mobileMenuCounter;
                        $itemsHtml .= "<li class='border-b border-slate-200/80 py-1 last:border-b-0'>"
                            . "<div class='flex items-center gap-2' style='margin-left: {$indent}px'>"
                            . "<a data-cosmic-mobile-link href='{$url}' class='min-w-0 flex-1 rounded-xl px-3 py-3 text-base font-semibold text-slate-900 transition hover:bg-slate-100'>{$label}</a>"
                            . "<button type='button' class='cosmic-mobile-submenu-toggle inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 hover:text-slate-950' aria-expanded='false' aria-controls='{$submenuId}' aria-label='Toggle {$label} submenu'>"
                            . "<svg aria-hidden='true' viewBox='0 0 24 24' class='h-5 w-5 transition-transform duration-200'><path d='m7 10 5 5 5-5' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/></svg>"
                            . "</button>"
                            . "</div>"
                            . "<ul id='{$submenuId}' class='cosmic-mobile-submenu hidden list-none space-y-1 pb-3 pt-1'>"
                            . $renderMobileMenu($children, $depth + 1)
                            . "</ul>"
                            . "</li>";
                    }

                    return $itemsHtml;
                };

                $desktopNavHtml = $renderDesktopMenu(is_array($menuItems) ? $menuItems : []);
                $mobileNavHtml = $renderMobileMenu(is_array($menuItems) ? $menuItems : []);

                $html .= "
                <style>
                    .cosmic-static-header .menu-node > a > span[aria-hidden='true'] { display: none; }
                    .cosmic-static-header .menu-node > a::after {
                        content: '';
                        width: .35rem;
                        height: .35rem;
                        margin-left: .2rem;
                        border-right: 1.5px solid currentColor;
                        border-bottom: 1.5px solid currentColor;
                        transform: rotate(45deg) translateY(-2px);
                        transition: transform .2s ease;
                    }
                    .cosmic-static-header .menu-node:hover > a::after,
                    .cosmic-static-header .menu-node:focus-within > a::after {
                        transform: rotate(225deg) translate(-1px, -1px);
                    }
                    .cosmic-static-header .menu-node > .menu-dropdown { display: none; }
                    .cosmic-static-header .menu-node:hover > .menu-dropdown,
                    .cosmic-static-header .menu-node:focus-within > .menu-dropdown { display: block; }
                    .cosmic-static-header .menu-dropdown > ul > li > a {
                        display: flex;
                        padding: .65rem .8rem;
                        border-radius: .65rem;
                    }
                    .cosmic-static-header .menu-dropdown > ul > li > a:hover {
                        background: rgb(241 245 249);
                    }
                    .cosmic-mobile-overlay {
                        opacity: 0;
                        visibility: hidden;
                        transition: opacity .28s ease, visibility .28s ease;
                    }
                    .cosmic-mobile-panel {
                        transform: translateX(100%);
                        transition: transform .32s cubic-bezier(.22, 1, .36, 1);
                    }
                    .cosmic-mobile-nav-open .cosmic-mobile-overlay {
                        opacity: 1;
                        visibility: visible;
                    }
                    .cosmic-mobile-nav-open .cosmic-mobile-panel { transform: translateX(0); }
                    .cosmic-mobile-submenu-toggle[aria-expanded='true'] svg { transform: rotate(180deg); }
                    body.cosmic-mobile-menu-locked { overflow: hidden; }
                    @media (min-width: 768px) {
                        .cosmic-mobile-overlay,
                        .cosmic-mobile-panel { display: none !important; }
                    }
                    /* Viewport-aware sizing for full Hero Sparks only. Keep mini heroes and
                       ordinary sections content-sized. A solid header consumes viewport space;
                       an overlay header lives inside the hero and therefore uses the full viewport. */
                    section[data-cosmic-block-type^='hero_'],
                    section[data-cosmic-block-type='image_cta_banner'] {
                        box-sizing: border-box;
                        min-height: calc(100vh - var(--cosmic-header-flow-offset, 0px));
                        padding-top: clamp(4.5rem, 9vh, 8rem) !important;
                        padding-bottom: clamp(4.5rem, 9vh, 8rem) !important;
                        display: flex;
                        align-items: center;
                    }
                    section[data-cosmic-block-type^='hero_'] > .relative,
                    section[data-cosmic-block-type='image_cta_banner'] > .relative { width: 100%; }
                    @supports (height: 100svh) {
                        section[data-cosmic-block-type^='hero_'],
                        section[data-cosmic-block-type='image_cta_banner'] {
                            min-height: calc(100svh - var(--cosmic-header-flow-offset, 0px));
                        }
                    }
                    @media (max-width: 767px) {
                        section[data-cosmic-block-type^='hero_'],
                        section[data-cosmic-block-type='image_cta_banner'] {
                            min-height: auto;
                            padding-top: clamp(3.5rem, 8vh, 5.5rem) !important;
                            padding-bottom: clamp(3.5rem, 8vh, 5.5rem) !important;
                        }
                    }
                    .cosmic-static-overlay-header {
                        position: absolute !important;
                        background: transparent !important;
                        border-bottom-color: transparent !important;
                        box-shadow: none !important;
                    }
                    .cosmic-static-overlay-first-spark {
                        padding-top: calc(var(--cosmic-overlay-header-height, 80px) + clamp(4.25rem, 6vw, 6.5rem)) !important;
                    }
                    @media (max-width: 639px) {
                        .cosmic-static-overlay-first-spark {
                            padding-top: calc(var(--cosmic-overlay-header-height, 72px) + 3.5rem) !important;
                        }
                    }
                    /* Contrast-aware overlay header. Runtime selects a light or dark
                       navigation/logo treatment from the first Spark, while CTA keeps
                       the site's primary brand color unless that would merge into a
                       same-primary hero. */
                    header.cosmic-static-header.cosmic-static-overlay-header[data-cosmic-premium-overlay-header='true'].cosmic-overlay-tone-light > a {
                        color: #fff !important;
                    }
                    header.cosmic-static-header.cosmic-static-overlay-header[data-cosmic-premium-overlay-header='true'].cosmic-overlay-tone-light > a img {
                        filter: brightness(0) invert(1) !important;
                    }
                    header.cosmic-static-header.cosmic-static-overlay-header[data-cosmic-premium-overlay-header='true'].cosmic-overlay-tone-light > nav > ul > li > a {
                        color: #fff !important;
                    }
                    header.cosmic-static-header.cosmic-static-overlay-header[data-cosmic-premium-overlay-header='true'].cosmic-overlay-tone-light > nav > ul > li > a:hover {
                        color: rgba(255,255,255,.82) !important;
                    }
                    .cosmic-static-overlay-header.cosmic-overlay-tone-dark > nav > ul > li > a {
                        color: #0f172a !important;
                    }
                    .cosmic-static-overlay-header.cosmic-overlay-tone-dark > nav > ul > li > a:hover {
                        color: #020617 !important;
                    }
                    header.cosmic-static-header.cosmic-static-overlay-header[data-cosmic-premium-overlay-header='true'].cosmic-overlay-cta-surface > nav > a {
                        background: #fff !important;
                        color: #1e293b !important;
                        box-shadow: 0 10px 30px rgba(15,23,42,.14) !important;
                    }
                    .cosmic-static-overlay-header.cosmic-overlay-cta-primary > nav > a {
                        box-shadow: 0 10px 30px rgba(15,23,42,.18), 0 0 0 1px rgba(255,255,255,.18) !important;
                    }
                </style>

                <header data-cosmic-overlay-header='" . ($overlayHeader ? "true" : "false") . "' data-cosmic-page-style='" . e(self::$currentPageStyle) . "' data-cosmic-primary-overlay-allowed='" . ($overlayPrimaryAllowed ? "true" : "false") . "' data-cosmic-premium-overlay-header='" . ($premiumOverlayHeader ? "true" : "false") . "' class='cosmic-static-header {$overlayHeaderClass} z-50 flex w-full items-center justify-between gap-6 border-b {$headerBorder} px-6 py-4 sm:px-[5%] lg:px-[7%]'>
                    <a href='/' class='relative z-[72] text-xl font-extrabold tracking-wide {$headerText}' aria-label='{$logoText} home'>
                        {$logo}
                    </a>

                    <nav class='hidden min-w-0 items-center md:flex md:gap-8 lg:gap-10 xl:gap-12' aria-label='Primary navigation'>
                        <ul class='m-0 flex list-none items-center gap-7 p-0 lg:gap-9 xl:gap-10'>
                            {$desktopNavHtml}
                        </ul>

                        <a
                            href='{$ctaUrl}'
                            class='cosmic-primary-cta {$buttonBg} {$buttonText} shrink-0 rounded-full px-8 py-4 text-sm font-semibold transition hover:opacity-90 lg:px-10' style='background:var(--p,var(--cosmic-primary,#243447));background-color:var(--p,var(--cosmic-primary,#243447));border-color:var(--p,var(--cosmic-primary,#243447));color:#fff;-webkit-text-fill-color:#fff'
                        >
                            {$ctaLabel}
                        </a>
                    </nav>

                    <button
                        type='button'
                        class='cosmic-mobile-menu-open relative z-[72] inline-flex h-12 w-12 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-900 shadow-sm transition hover:bg-slate-50 md:hidden'
                        aria-expanded='false'
                        aria-controls='cosmic-mobile-panel'
                        aria-label='Open navigation menu'
                    >
                        <svg aria-hidden='true' viewBox='0 0 24 24' class='h-6 w-6'><path d='M4 7h16M4 12h16M4 17h16' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round'/></svg>
                    </button>
                </header>

                <div class='cosmic-mobile-navigation md:hidden' aria-hidden='true'>
                    <button type='button' class='cosmic-mobile-overlay fixed inset-0 z-[68] cursor-default bg-slate-950/60 backdrop-blur-[2px]' aria-label='Close navigation menu'></button>

                    <aside id='cosmic-mobile-panel' class='cosmic-mobile-panel fixed inset-y-0 right-0 z-[70] flex w-[min(88vw,390px)] flex-col bg-white shadow-[-24px_0_70px_rgba(15,23,42,.22)]' role='dialog' aria-modal='true' aria-label='Mobile navigation'>
                        <div class='flex items-center justify-between border-b border-slate-200 px-6 py-5'>
                            <span class='text-xs font-bold uppercase tracking-[.24em] text-slate-500'>Navigation</span>
                            <button type='button' class='cosmic-mobile-menu-close inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 text-slate-700 transition hover:bg-slate-100 hover:text-slate-950' aria-label='Close navigation menu'>
                                <svg aria-hidden='true' viewBox='0 0 24 24' class='h-5 w-5'><path d='m6 6 12 12M18 6 6 18' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round'/></svg>
                            </button>
                        </div>

                        <div class='flex-1 overflow-y-auto px-5 py-5'>
                            <ul class='m-0 list-none space-y-1 p-0'>
                                {$mobileNavHtml}
                            </ul>
                        </div>

                        <div class='border-t border-slate-200 bg-slate-50 px-6 py-6'>
                            <a data-cosmic-mobile-link href='{$ctaUrl}' class='cosmic-primary-cta {$buttonBg} {$buttonText} flex w-full items-center justify-center rounded-full px-7 py-4 text-center text-sm font-semibold shadow-lg transition hover:opacity-90' style='background:var(--p,var(--cosmic-primary,#243447));background-color:var(--p,var(--cosmic-primary,#243447));border-color:var(--p,var(--cosmic-primary,#243447));color:#fff;-webkit-text-fill-color:#fff'>
                                {$ctaLabel}
                            </a>
                        </div>
                    </aside>
                </div>

                <script>
                    (() => {
                        const initCosmicStaticHeader = () => {
                        const anyStaticHeader = document.querySelector('.cosmic-static-header');
                        if (anyStaticHeader) {
                            const syncHeaderFlowOffset = () => {
                                const overlaysHero = anyStaticHeader.dataset.cosmicOverlayHeader === 'true';
                                const headerHeight = Math.ceil(anyStaticHeader.getBoundingClientRect().height || 0);
                                document.documentElement.style.setProperty('--cosmic-header-flow-offset', overlaysHero ? '0px' : `\${headerHeight}px`);
                            };
                            syncHeaderFlowOffset();
                            window.addEventListener('resize', syncHeaderFlowOffset);
                            if (typeof ResizeObserver !== 'undefined') {
                                const headerFlowResizeObserver = new ResizeObserver(syncHeaderFlowOffset);
                                headerFlowResizeObserver.observe(anyStaticHeader);
                            }
                        }

                        const staticHeader = document.querySelector('.cosmic-static-header[data-cosmic-overlay-header=\"true\"]');
                        if (staticHeader) {
                            const sections = Array.from(document.querySelectorAll('section'));
                            const firstSection = sections.find((section) =>
                                Boolean(staticHeader.compareDocumentPosition(section) & Node.DOCUMENT_POSITION_FOLLOWING)
                            );

                            if (firstSection) {
                                firstSection.classList.add('cosmic-static-overlay-first-spark');

                                // Final Page Style contract. Do not inspect the first Spark's
                                // luminance/media anymore: Premium/Balanced + overlay gets the
                                // white header/CTA treatment unless the active primary family is
                                // Warm Stone / Studio White. Every other case stays normal/dark.
                                const useLightHeader = staticHeader.dataset.cosmicPremiumOverlayHeader === 'true';

                                staticHeader.classList.toggle('cosmic-overlay-tone-light', useLightHeader);
                                staticHeader.classList.toggle('cosmic-overlay-tone-dark', !useLightHeader);
                                staticHeader.classList.toggle('cosmic-overlay-cta-surface', useLightHeader);
                                staticHeader.classList.toggle('cosmic-overlay-cta-primary', !useLightHeader);

                                const syncOverlaySpacing = () => {
                                    const headerHeight = Math.ceil(staticHeader.getBoundingClientRect().height || 0);
                                    document.documentElement.style.setProperty('--cosmic-overlay-header-height', `\${headerHeight}px`);
                                };

                                syncOverlaySpacing();
                                window.addEventListener('resize', syncOverlaySpacing);

                                if (typeof ResizeObserver !== 'undefined') {
                                    const overlayResizeObserver = new ResizeObserver(syncOverlaySpacing);
                                    overlayResizeObserver.observe(staticHeader);
                                }
                            }
                        }

                        const navRoot = document.querySelector('.cosmic-mobile-navigation');
                        const openButton = document.querySelector('.cosmic-mobile-menu-open');
                        const closeButton = navRoot?.querySelector('.cosmic-mobile-menu-close');
                        const overlay = navRoot?.querySelector('.cosmic-mobile-overlay');
                        const panel = navRoot?.querySelector('.cosmic-mobile-panel');

                        if (!navRoot || !openButton || !closeButton || !overlay || !panel) return;

                        let lastFocusedElement = null;

                        const setMenuOpen = (isOpen) => {
                            document.documentElement.classList.toggle('cosmic-mobile-nav-open', isOpen);
                            document.body.classList.toggle('cosmic-mobile-menu-locked', isOpen);
                            navRoot.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                            openButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

                            if (isOpen) {
                                lastFocusedElement = document.activeElement;
                                window.setTimeout(() => closeButton.focus(), 60);
                            } else if (lastFocusedElement instanceof HTMLElement) {
                                lastFocusedElement.focus();
                            }
                        };

                        openButton.addEventListener('click', () => setMenuOpen(true));
                        closeButton.addEventListener('click', () => setMenuOpen(false));
                        overlay.addEventListener('click', () => setMenuOpen(false));

                        navRoot.querySelectorAll('[data-cosmic-mobile-link]').forEach((link) => {
                            link.addEventListener('click', () => setMenuOpen(false));
                        });

                        navRoot.querySelectorAll('.cosmic-mobile-submenu-toggle').forEach((toggle) => {
                            toggle.addEventListener('click', () => {
                                const submenuId = toggle.getAttribute('aria-controls');
                                const submenu = submenuId ? document.getElementById(submenuId) : null;
                                if (!submenu) return;

                                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                                toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                                submenu.classList.toggle('hidden', expanded);
                            });
                        });

                        document.addEventListener('keydown', (event) => {
                            if (event.key === 'Escape' && openButton.getAttribute('aria-expanded') === 'true') {
                                setMenuOpen(false);
                            }
                        });

                        window.addEventListener('resize', () => {
                            if (window.innerWidth >= 768 && openButton.getAttribute('aria-expanded') === 'true') {
                                setMenuOpen(false);
                            }
                        });
                        };

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initCosmicStaticHeader, { once: true });
                        } else {
                            initCosmicStaticHeader();
                        }
                    })();
                </script>";
                break;

                case 'minimal_footer':
                $brand = e($block['logo_text'] ?? 'Your Logo');
                $copy = e($block['copyright'] ?? '© ' . date('Y') . '. All rights reserved.');
                $rawLogoImageUrl = (string) ($block['logo_image_url'] ?? '');
                $logoImageUrl = e(self::staticAssetUrl($rawLogoImageUrl));
                $logoHeight = max(24, min(56, (int) ($block['logo_height'] ?? 36)));
                $logoFilterKey = (string) ($block['logo_filter_key'] ?? $block['theme'] ?? 'midnight');
                $logoFilter = e(self::isDefaultLogoPlaceholder($rawLogoImageUrl)
                    ? (string) ($block['logo_filter'] ?? self::logoFilter($logoFilterKey))
                    : 'none');
                $stoneTheme = self::getTheme('stone'); // Hardcoded stone theme
                $footerBrand = $logoImageUrl !== ''
                    ? "<img src='{$logoImageUrl}' alt='{$brand}' style='height:{$logoHeight}px;max-height:56px;filter:{$logoFilter}' class='w-auto max-w-[250px] object-contain'>"
                    : "<div class='font-bold text-lg {$stoneTheme['text']}'>{$brand}</div>";

                $html .= "
                <footer class='w-full {$stoneTheme['bg']} {$stoneTheme['sub']} flex flex-col items-start gap-3 border-t {$stoneTheme['border']} px-6 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-12'>
                    {$footerBrand}
                    <div class='text-sm sm:whitespace-nowrap'>{$copy}</div>
                </footer>";
                break;



                case 'feature_image_left':
                $category = e($block['category'] ?? 'CATEGORY');
                $heading = e($block['heading'] ?? 'Heading Title');
                $text = e($block['text'] ?? 'Add your description here...');
                $btnLabel = e($block['button_label'] ?? 'Read More');
                $btnUrl = e($block['button_url'] ?? '#');
                $imageUrl = e(self::staticAssetUrl($block['image_url'] ?? 'https://picsum.photos/800/600'));

                $html .= "
                <section class='relative py-32 px-7 overflow-hidden {$theme['bg']} transition-colors duration-500'>
                    <div class='max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-20'>
                        <div class='w-full md:w-1/2'>
                            <div class='rounded-3xl overflow-hidden shadow-2xl ring-1 ring-white/10 transition-transform duration-500 hover:scale-[1.02]'>
                                <img src='{$imageUrl}' alt='Feature Image' width='960' height='720' loading='lazy' decoding='async' class='w-full h-auto object-cover'>
                            </div>
                        </div>
                        <div class='w-full md:w-1/2 space-y-8'>
                            <span class='block text-xs font-semibold uppercase tracking-[0.30em] {$theme['sub']}'>
                                {$category}
                            </span>
                            <h2 class='block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>
                                {$heading}
                            </h2>
                            <p class='block text-lg leading-8 max-w-xl {$theme['sub']}'>
                                {$text}
                            </p>
                            <a href='{$btnUrl}' class='inline-flex items-center gap-2 font-semibold transition-all duration-300 hover:gap-3 {$theme['text']}'>
                                {$btnLabel}
                            </a>
                        </div>
                    </div>
                </section>";
                break;



                case 'feature_image_right':
                $category = e($block['category'] ?? 'CATEGORY');
                $heading = e($block['heading'] ?? 'Heading Title');
                $text = e($block['text'] ?? 'Add your description here...');
                $btnLabel = e($block['button_label'] ?? 'Read More');
                $btnUrl = e($block['button_url'] ?? '#');
                $imageUrl = e(self::staticAssetUrl($block['image_url'] ?? 'https://picsum.photos/800/600'));

                $html .= "
                <section class='relative py-32 px-7 overflow-hidden {$theme['bg']} transition-colors duration-500'>
                    <div class='max-w-7xl mx-auto flex flex-col md:flex-row-reverse items-center justify-between gap-20'>
                        <div class='w-full md:w-1/2'>
                            <div class='rounded-3xl overflow-hidden shadow-2xl ring-1 ring-white/10 transition-transform duration-500 hover:scale-[1.02]'>
                                <img src='{$imageUrl}' alt='Feature Image' width='960' height='720' loading='lazy' decoding='async' class='w-full h-auto object-cover'>
                            </div>
                        </div>
                        <div class='w-full md:w-1/2 space-y-8'>
                            <span class='block text-xs font-semibold uppercase tracking-[0.30em] {$theme['sub']}'>
                                {$category}
                            </span>
                            <h2 class='block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>
                                {$heading}
                            </h2>
                            <p class='block text-lg leading-8 max-w-xl {$theme['sub']}'>
                                {$text}
                            </p>
                            <a href='{$btnUrl}' class='inline-flex items-center gap-2 font-semibold transition-all duration-300 hover:gap-3 {$theme['text']}'>
                                {$btnLabel}
                            </a>
                        </div>
                    </div>
                </section>";
                break;



                case 'hero_headline':
                $subtitle = e($block['subtitle'] ?? 'WELCOME TO THE FUTURE');
                $heading = e($block['heading'] ?? 'Build Better Digital Reality.');
                $text = e($block['text'] ?? 'Create a polished website with reusable sections and complete editorial control.');
                
                // Button Logic
                $isLight = in_array($selectedThemeName, ['white', 'stone']);

                $btnBg = $isLight
                    ? self::getTheme($primaryColor)['bg']
                    : 'bg-white';

                $btnText = $isLight
                    ? self::getTheme($primaryColor)['text']
                    : 'text-slate-900';
                $html .= "
                <section class='relative w-full px-6 py-20 sm:px-[8%] sm:py-24 {$theme['bg']} overflow-hidden transition-colors duration-500'>
                    <div class='absolute top-0 right-0 w-[500px] h-[500px] bg-gradient-to-br from-indigo-500 to-transparent opacity-30 blur-[120px] rounded-full'></div>
                    
                    <div class='relative z-10 max-w-4xl'>
                        <span class='font-bold tracking-widest uppercase text-sm block {$theme['sub']}'>{$subtitle}</span>
                        <h1 class='mt-6 text-4xl font-extrabold leading-[1.1] sm:text-5xl md:text-8xl block {$theme['text']}'>{$heading}</h1>
                        <div class='mt-6 max-w-2xl text-base sm:mt-8 sm:text-xl {$theme['sub']}'>{$text}</div>

                        <div class='mt-8 flex flex-col items-stretch gap-3 sm:mt-12 sm:flex-row sm:items-center sm:gap-4'>
                            <a href='#' class='w-full rounded-full px-8 py-4 text-center font-bold transition !opacity-100 sm:w-auto {$btnBg} {$btnText}'>
                                " . e($block['btn1_label'] ?? 'Get Started') . "
                            </a>
                            <a href='#' class='w-full rounded-full border px-8 py-4 text-center font-bold transition sm:w-auto {$theme['border']} {$theme['text']}'>
                                " . e($block['btn2_label'] ?? 'View Docs') . "
                            </a>
                        </div>
                    </div>
                </section>";
                break;





                case 'services_hover_cards':
                $d = array_merge([
                    'eyebrow'=>'EXPLORE OUR CAPABILITIES','heading'=>'Specialist services, designed to work better together.','text'=>'Move from first idea to measurable improvement with senior support across strategy, design, technology, and growth.','primary_label'=>'Discuss your project','primary_url'=>'#',
                    'card_one_number'=>'01','card_one_title'=>'Digital strategy','card_one_summary'=>'Set the direction.','card_one_text'=>'Clarify the opportunity, align priorities, and turn ambition into a focused roadmap.','card_one_link'=>'Explore strategy',
                    'card_two_number'=>'02','card_two_title'=>'Brand systems','card_two_summary'=>'Build recognition.','card_two_text'=>'Create a flexible visual and verbal system that keeps every touchpoint consistent.','card_two_link'=>'Explore branding',
                    'card_three_number'=>'03','card_three_title'=>'Experience design','card_three_summary'=>'Make journeys intuitive.','card_three_text'=>'Shape clear user flows and polished interfaces around the needs of real customers.','card_three_link'=>'Explore experience',
                    'card_four_number'=>'04','card_four_title'=>'Web platforms','card_four_summary'=>'Create a stronger foundation.','card_four_text'=>'Build fast, responsive websites and platforms designed to evolve with your team.','card_four_link'=>'Explore platforms',
                    'card_five_number'=>'05','card_five_title'=>'Growth systems','card_five_summary'=>'Connect the funnel.','card_five_text'=>'Bring campaigns, content, conversion, and measurement into one repeatable system.','card_five_link'=>'Explore growth',
                    'card_six_number'=>'06','card_six_title'=>'Optimisation','card_six_summary'=>'Keep improving.','card_six_text'=>'Use focused testing and insight to improve performance after launch.','card_six_link'=>'Explore optimisation'
                ], $block);
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $muted = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $border = $isPrimarySection ? 'border-white/20' : $theme['border'];
                $card = $isPrimarySection ? 'bg-white/10 text-white' : "{$theme['surface']} {$theme['text']}";
                $hoverBg = $isPrimarySection ? '#ffffff' : (string) ($primaryTheme['palette']['background'] ?? '#0B5D4B');
                $hoverFg = $isPrimarySection ? '#0f172a' : '#ffffff';
                $buttonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $buttonText = $isPrimarySection ? 'text-slate-950' : $primaryTheme['text'];
                $cardsHtml = '';
                foreach (['one','two','three','four','five','six'] as $word) {
                    $cardsHtml .= "<article data-cosmic-services-hover-card='true' style='--cosmic-hover-card-bg:".e($hoverBg).";--cosmic-hover-card-fg:".e($hoverFg)."' class='group relative min-h-[300px] overflow-hidden rounded-[1.75rem] border p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl sm:p-7 {$border} {$card}'><div class='flex items-start justify-between gap-4'><span class='text-xs font-black tracking-[.2em] {$muted}'>".e($d['card_'.$word.'_number'])."</span><span class='flex h-10 w-10 items-center justify-center rounded-full border text-lg transition group-hover:rotate-45 {$border}'>↗</span></div><div class='mt-14'><h3 class='text-2xl font-semibold tracking-[-.03em]'>".e($d['card_'.$word.'_title'])."</h3><p class='mt-3 text-sm font-semibold {$muted}'>".e($d['card_'.$word.'_summary'])."</p><p class='mt-5 translate-y-3 text-sm leading-6 opacity-75 transition duration-300 group-hover:translate-y-0 group-hover:opacity-100'>".e($d['card_'.$word.'_text'])."</p><span class='mt-7 block text-xs font-black uppercase tracking-[.16em] opacity-70 group-hover:opacity-100'>".e($d['card_'.$word.'_link'])."</span></div></article>";
                }
                $html .= "<section data-cosmic-services-hover-cards='true' class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><style>[data-cosmic-services-hover-card=true]{transition-property:transform,box-shadow,background-color,color,border-color}[data-cosmic-services-hover-card=true]:hover,[data-cosmic-services-hover-card=true]:focus-within{background-color:var(--cosmic-hover-card-bg)!important;color:var(--cosmic-hover-card-fg)!important}[data-cosmic-services-hover-card=true]:hover *,[data-cosmic-services-hover-card=true]:focus-within *{color:inherit!important}[data-cosmic-services-hover-card=true]:hover [class*=opacity-],[data-cosmic-services-hover-card=true]:focus-within [class*=opacity-]{opacity:.86}[data-cosmic-services-hover-card=true]:hover [class*=border-],[data-cosmic-services-hover-card=true]:focus-within [class*=border-]{border-color:currentColor!important}</style><div class='mx-auto max-w-7xl'><div class='grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end'><div class='max-w-3xl'><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl lg:text-6xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-5 max-w-2xl text-base leading-7 sm:text-lg {$muted}'>".e($d['text'])."</p></div><a href='".e($d['primary_url'])."' class='inline-flex min-h-[48px] items-center justify-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div><div class='mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-3'>{$cardsHtml}</div></div></section>";
                break;

                case 'services_feature_comparison':
                $d = array_merge([
                    'eyebrow'=>'COMPARE THE APPROACH','heading'=>'Choose the level of capability your next stage needs.','text'=>'See how each service model differs across strategy, delivery, collaboration, and ongoing support.',
                    'option_one_name'=>'Foundation','option_one_kicker'=>'Focused project','option_one_text'=>'A clear, senior-led engagement for one defined priority.',
                    'option_two_name'=>'Growth System','option_two_kicker'=>'Most versatile','option_two_text'=>'Connected strategy and delivery for teams building momentum.','option_two_badge'=>'RECOMMENDED',
                    'option_three_name'=>'Embedded Partner','option_three_kicker'=>'Ongoing capability','option_three_text'=>'Flexible senior support across complex, evolving priorities.',
                    'feature_one'=>'Strategic direction','option_one_one'=>'Focused','option_two_one'=>'Integrated','option_three_one'=>'Embedded',
                    'feature_two'=>'Research depth','option_one_two'=>'Essentials','option_two_two'=>'Extended','option_three_two'=>'Continuous',
                    'feature_three'=>'Design systems','option_one_three'=>'Core','option_two_three'=>'Scalable','option_three_three'=>'Multi-brand',
                    'feature_four'=>'Delivery support','option_one_four'=>'Launch','option_two_four'=>'Launch + optimise','option_three_four'=>'Ongoing',
                    'feature_five'=>'Team access','option_one_five'=>'Lead specialist','option_two_five'=>'Cross-functional','option_three_five'=>'Dedicated pod',
                    'feature_six'=>'Reporting','option_one_six'=>'Wrap-up','option_two_six'=>'Monthly','option_three_six'=>'Custom cadence',
                    'feature_seven'=>'Best suited to','option_one_seven'=>'One clear priority','option_two_seven'=>'Growing teams','option_three_seven'=>'Complex programmes',
                    'feature_eight'=>'Engagement style','option_one_eight'=>'Fixed scope','option_two_eight'=>'Phased roadmap','option_three_eight'=>'Flexible retainer',
                    'primary_label'=>'Discuss the right approach','primary_url'=>'#','footnote'=>'Every engagement is shaped around your goals, team, and delivery requirements.'
                ], $block);
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $muted = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $border = $isPrimarySection ? 'border-white/20' : $theme['border'];
                $baseCard = $isPrimarySection ? 'bg-white/10 text-white' : "{$theme['surface']} {$theme['text']}";
                $featuredCard = $isPrimarySection ? 'bg-white text-slate-950' : 'bg-slate-900 text-white';
                $buttonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $buttonText = $isPrimarySection ? 'text-slate-950' : $primaryTheme['text'];
                $options = [
                    ['option_one', false],
                    ['option_two', true],
                    ['option_three', false],
                ];
                $optionsHtml = '';
                foreach ($options as [$key, $featured]) {
                    $cardClass = $featured ? $featuredCard : $baseCard;
                    $badge = $featured ? "<span class='mb-5 inline-flex rounded-full px-3 py-1 text-[10px] font-black tracking-[.16em] {$primaryTheme['bg']} text-white'>".e($d['option_two_badge'])."</span>" : '';
                    $cardMuted = $featured && !$isPrimarySection ? 'text-white/70' : $muted;
                    $optionsHtml .= "<article class='relative border-b p-6 sm:p-7 {$border} {$cardClass}'>{$badge}<span class='block text-[11px] font-bold uppercase tracking-[.2em] {$cardMuted}'>".e($d[$key.'_kicker'])."</span><h3 class='mt-3 text-2xl font-semibold tracking-[-.03em]'>".e($d[$key.'_name'])."</h3><p class='mt-4 text-sm leading-6 {$cardMuted}'>".e($d[$key.'_text'])."</p></article>";
                }
                $rowsHtml = '';
                foreach (['one','two','three','four','five','six','seven','eight'] as $word) {
                    $rowsHtml .= "<div class='contents'><div class='border-b p-5 text-sm font-semibold lg:p-6 {$border} {$baseCard}'>".e($d['feature_'.$word])."</div>";
                    foreach ($options as [$key, $featured]) {
                        $cardClass = $featured ? $featuredCard : $baseCard;
                        $valueText = $featured && !$isPrimarySection ? 'text-white' : '';
                        $rowsHtml .= "<div class='border-b p-5 text-sm font-semibold lg:p-6 {$border} {$cardClass} {$valueText}'>".e($d[$key.'_'.$word])."</div>";
                    }
                    $rowsHtml .= "</div>";
                }
                $html .= "<section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl lg:text-6xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-5 max-w-2xl text-base leading-7 sm:text-lg {$muted}'>".e($d['text'])."</p></div><div class='mt-12 overflow-hidden rounded-[2rem] border shadow-sm {$border}'><div class='grid lg:grid-cols-[1.15fr_repeat(3,1fr)] {$baseCard}'><div class='hidden border-b p-6 lg:block {$border}'><span class='text-xs font-bold uppercase tracking-[.22em] {$muted}'>Capabilities</span></div>{$optionsHtml}{$rowsHtml}</div></div><div class='mt-8 flex flex-col items-center gap-4 text-center'><a href='".e($d['primary_url'])."' class='inline-flex min-h-[48px] items-center justify-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a><p class='max-w-3xl text-xs leading-5 {$muted}'>".e($d['footnote'])."</p></div></div></section>";
                break;

                case 'services_pricing_comparison':
                $d = array_merge([
                    'eyebrow'=>'CHOOSE THE RIGHT LEVEL OF SUPPORT','heading'=>'Clear packages. No hidden complexity.','text'=>'Compare the level of strategy, delivery, and ongoing support included in each engagement.',
                    'starter_name'=>'Essential','starter_price'=>'$2,500','starter_period'=>'from','starter_description'=>'A focused foundation for one clear business priority.','starter_button_label'=>'Choose Essential','starter_button_url'=>'#',
                    'growth_name'=>'Growth','growth_price'=>'$6,500','growth_period'=>'from','growth_description'=>'A complete growth engagement for ambitious teams.','growth_button_label'=>'Choose Growth','growth_button_url'=>'#','growth_badge'=>'MOST POPULAR',
                    'pro_name'=>'Partner','pro_price'=>'Custom','pro_period'=>'','pro_description'=>'Embedded senior support for complex, ongoing work.','pro_button_label'=>'Talk to our team','pro_button_url'=>'#',
                    'feature_one'=>'Strategic discovery','starter_one'=>'Included','growth_one'=>'Extended','pro_one'=>'Ongoing','feature_two'=>'Design direction','starter_two'=>'1 concept','growth_two'=>'3 concepts','pro_two'=>'Unlimited scope','feature_three'=>'Delivery support','starter_three'=>'Launch','growth_three'=>'Launch + optimise','pro_three'=>'Embedded team','feature_four'=>'Reporting','starter_four'=>'Summary','growth_four'=>'Monthly','pro_four'=>'Custom dashboard','feature_five'=>'Response time','starter_five'=>'3 business days','growth_five'=>'1 business day','pro_five'=>'Priority','feature_six'=>'Best for','starter_six'=>'Focused projects','growth_six'=>'Growing teams','pro_six'=>'Complex programmes','footnote'=>'Every engagement is tailored before work begins. Prices shown are editable starting points.'
                ], $block);
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $muted = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $border = $isPrimarySection ? 'border-white/20' : $theme['border'];
                $baseCard = $isPrimarySection ? 'bg-white/10 text-white' : "{$theme['surface']} {$theme['text']}";
                $featuredCard = $isPrimarySection ? 'bg-white text-slate-950' : "{$primaryTheme['soft']} {$theme['text']}";
                $buttonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $buttonText = $isPrimarySection ? 'text-slate-950' : $primaryTheme['text'];
                $plans = [
                    ['starter', $d['starter_name'], $d['starter_price'], $d['starter_period'], $d['starter_description'], $d['starter_button_label'], $d['starter_button_url'], false],
                    ['growth', $d['growth_name'], $d['growth_price'], $d['growth_period'], $d['growth_description'], $d['growth_button_label'], $d['growth_button_url'], true],
                    ['pro', $d['pro_name'], $d['pro_price'], $d['pro_period'], $d['pro_description'], $d['pro_button_label'], $d['pro_button_url'], false],
                ];
                $plansHtml='';
                foreach($plans as [$key,$name,$price,$period,$description,$label,$url,$featured]){
                    $cardClass=$featured?$featuredCard:$baseCard;
                    $badge=$featured?"<span class='mb-5 inline-flex rounded-full px-3 py-1 text-[10px] font-black tracking-[.16em] {$primaryTheme['bg']} text-white'>".e($d['growth_badge'])."</span>":'';
                    $plansHtml.="<article class='border-b p-6 sm:p-7 {$border} {$cardClass}'>{$badge}<h3 class='text-xl font-semibold'>".e($name)."</h3><div class='mt-4 flex items-end gap-2'><strong class='text-4xl font-semibold tracking-[-.04em]'>".e($price)."</strong><span class='mb-1 text-xs font-semibold uppercase tracking-wider {$muted}'>".e($period)."</span></div><p class='mt-4 text-sm leading-6 {$muted}'>".e($description)."</p><a href='".e($url)."' class='mt-6 inline-flex min-h-[46px] w-full items-center justify-center rounded-full px-5 text-sm font-bold {$buttonBg} {$buttonText}'>".e($label)."</a></article>";
                }
                $rowsHtml='';
                foreach(['one','two','three','four','five','six'] as $word){
                    $rowsHtml.="<div class='contents'><div class='border-b p-5 text-sm font-semibold lg:p-6 {$border} {$baseCard}'>".e($d['feature_'.$word])."</div><div class='border-b p-5 text-sm font-semibold lg:p-6 {$border} {$baseCard}'>".e($d['starter_'.$word])."</div><div class='border-b p-5 text-sm font-semibold lg:p-6 {$border} {$featuredCard}'>".e($d['growth_'.$word])."</div><div class='border-b p-5 text-sm font-semibold lg:p-6 {$border} {$baseCard}'>".e($d['pro_'.$word])."</div></div>";
                }
                $html .= "<section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl lg:text-6xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-5 max-w-2xl text-base leading-7 sm:text-lg {$muted}'>".e($d['text'])."</p></div><div class='mt-12 overflow-hidden rounded-[2rem] border shadow-sm {$border}'><div class='grid lg:grid-cols-[1.15fr_repeat(3,1fr)] {$baseCard}'><div class='hidden border-b p-6 lg:block {$border}'><span class='text-xs font-bold uppercase tracking-[.22em] {$muted}'>Compare packages</span></div>{$plansHtml}{$rowsHtml}</div></div><p class='mx-auto mt-6 max-w-3xl text-center text-xs leading-5 {$muted}'>".e($d['footnote'])."</p></div></section>";
                break;

                case 'services_bento_premium':
                $eyebrow = e($block['eyebrow'] ?? 'SERVICES DESIGNED AROUND MOMENTUM');
                $heading = e($block['heading'] ?? 'Specialist thinking, connected into one clear growth system.');
                $text = e($block['text'] ?? 'Combine strategy, design, technology, and optimisation in a flexible service model built around the way your business actually works.');
                $primaryLabel = e($block['primary_label'] ?? 'Explore our services');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $featuredNumber = e($block['featured_number'] ?? '01');
                $featuredTitle = e($block['featured_title'] ?? 'Digital strategy');
                $featuredText = e($block['featured_text'] ?? 'Clarify the opportunity, align the priorities, and turn ambitious goals into an actionable roadmap.');
                $featuredMeta = e($block['featured_meta'] ?? 'Research · Positioning · Roadmaps');
                $serviceCards = [
                    [e($block['service_two_number'] ?? '02'), e($block['service_two_title'] ?? 'Experience design'), e($block['service_two_text'] ?? 'Shape intuitive journeys and interfaces that make every interaction feel considered.')],
                    [e($block['service_three_number'] ?? '03'), e($block['service_three_title'] ?? 'Web platforms'), e($block['service_three_text'] ?? 'Build fast, scalable digital foundations designed to evolve with your team.')],
                    [e($block['service_four_number'] ?? '04'), e($block['service_four_title'] ?? 'Growth systems'), e($block['service_four_text'] ?? 'Connect content, campaigns, and measurement into a repeatable growth engine.')],
                    [e($block['service_five_number'] ?? '05'), e($block['service_five_title'] ?? 'Ongoing optimisation'), e($block['service_five_text'] ?? 'Improve performance continuously through testing, insight, and focused iteration.')],
                ];
                $proofValue = e($block['proof_value'] ?? '5 disciplines');
                $proofLabel = e($block['proof_label'] ?? 'One integrated senior team');
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $card = $isPrimarySection ? 'border-white/20 bg-white/10 text-white' : "{$theme['border']} {$theme['surface']} {$theme['text']}";
                $muted = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $soft = $isPrimarySection ? 'border-white/20 bg-slate-950/15 text-white' : "{$theme['border']} {$primaryTheme['soft']} {$theme['text']}";
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $smallHtml = '';
                foreach ($serviceCards as $index => [$number, $title, $description]) {
                    $span = $index < 2 ? 'lg:col-span-5' : 'lg:col-span-4';
                    $smallHtml .= "<article class='rounded-[2rem] border p-6 {$span} {$card}'><span class='text-[11px] font-black tracking-[.2em] {$muted}'>{$number}</span><h3 class='mt-8 text-xl font-semibold tracking-[-.02em]'>{$title}</h3><p class='mt-3 text-sm leading-6 {$muted}'>{$description}</p></article>";
                }

                $html .= "
                <section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'>
                    <div class='relative mx-auto max-w-7xl'>
                        <div class='grid gap-8 lg:grid-cols-[1fr_.72fr] lg:items-end lg:gap-16'>
                            <div><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>{$eyebrow}</span><h2 class='mt-5 max-w-4xl text-4xl font-semibold leading-[1] tracking-[-.045em] sm:text-5xl lg:text-6xl {$theme['text']}'>{$heading}</h2></div>
                            <div class='lg:pb-1'><p class='text-base leading-7 sm:text-lg sm:leading-8 {$muted}'>{$text}</p><a href='{$primaryUrl}' class='mt-6 inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a></div>
                        </div>
                        <div class='mt-12 grid gap-4 lg:grid-cols-12'>
                            <article class='rounded-[2rem] border p-7 sm:p-9 lg:col-span-7 lg:row-span-2 {$card}'><div class='flex items-center justify-between gap-4'><span class='text-xs font-black tracking-[.22em] {$muted}'>{$featuredNumber}</span><span class='h-2.5 w-2.5 rounded-full {$primaryTheme['bg']}'></span></div><h3 class='mt-14 max-w-2xl text-3xl font-semibold tracking-[-.035em] sm:text-4xl'>{$featuredTitle}</h3><p class='mt-5 max-w-2xl text-base leading-7 {$muted}'>{$featuredText}</p><div class='mt-10 border-t pt-6 {$theme['border']}'><span class='text-sm font-semibold {$muted}'>{$featuredMeta}</span></div></article>
                            {$smallHtml}
                            <article class='rounded-[2rem] border p-6 lg:col-span-4 {$soft}'><strong class='block text-3xl font-semibold tracking-[-.035em]'>{$proofValue}</strong><span class='mt-3 block text-sm leading-6 {$muted}'>{$proofLabel}</span></article>
                        </div>
                    </div>
                </section>";
                break;

                case 'services_bento':

                $tagline = e($block['tagline'] ?? 'OUR SERVICES');
                $heading = e($block['heading'] ?? 'Solutions Built Around Your Business');
                $description = e($block['description'] ?? 'Helping businesses grow through strategy, design and technology.');

                $services = $block['services'] ?? [];

                $html .= "
                <section class='relative py-32 px-7 overflow-hidden {$theme['bg']} transition-colors duration-500'>
                    <div class='max-w-7xl mx-auto'>

                        <div class='max-w-3xl mb-20'>

                            <span class='block text-xs font-semibold uppercase tracking-[0.35em] {$theme['sub']}'>
                                {$tagline}
                            </span>

                            <h2 class='mt-5 text-4xl font-bold tracking-tight leading-[1.05] sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>
                                {$heading}
                            </h2>

                            <p class='mt-6 text-lg leading-8 {$theme['sub']}'>
                                {$description}
                            </p>

                        </div>

                        <div class='space-y-6'>
                ";

                foreach ($services as $service) {

                    $icon  = e($service['icon'] ?? '⚡');
                    $title = e($service['title'] ?? 'Service Title');
                    $desc  = e($service['desc'] ?? 'Service description.');

                    $html .= "
                        <div class='{$theme['card']} border {$theme['border']} rounded-3xl p-8 flex flex-col md:flex-row md:items-center gap-8 transition-all duration-300 hover:shadow-2xl hover:-translate-y-1'>

                            <div class='w-20 h-20 rounded-3xl bg-white/5 border {$theme['border']} flex items-center justify-center text-4xl shrink-0'>
                                {$icon}
                            </div>

                            <div class='flex-grow'>

                                <h3 class='text-3xl font-bold {$theme['text']}'>
                                    {$title}
                                </h3>

                                <p class='mt-3 text-lg leading-8 {$theme['sub']}'>
                                    {$desc}
                                </p>

                            </div>

                            <div class='shrink-0'>
                                <span class='inline-flex items-center gap-2 text-sm font-semibold {$theme['text']}'>
                                    Learn More →
                                </span>
                            </div>

                        </div>
                    ";

                }

                $html .= "
                        </div>

                    </div>

                </section>";

                break;


                case 'process_timeline':

                $category = e($block['category'] ?? 'HOW IT WORKS');
                $heading  = e($block['heading'] ?? 'Our Simple Process');
                $text     = e($block['text'] ?? 'We follow a proven workflow to deliver consistent quality.');

                $steps = $block['steps'] ?? [];

                $html .= "
                <section class='relative py-32 px-7 overflow-hidden {$theme['bg']} transition-colors duration-500'>

                    <div class='absolute top-0 right-[-180px] w-[420px] h-[420px] rounded-full bg-blue-500/10 blur-[170px] pointer-events-none'></div>

                    <div class='max-w-7xl mx-auto'>

                        <div class='text-center max-w-3xl mx-auto mb-20 space-y-6'>

                            <span class='block text-xs font-semibold uppercase tracking-[0.30em] {$theme['sub']}'>
                                {$category}
                            </span>

                            <h2 class='block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>
                                {$heading}
                            </h2>

                            <p class='block text-lg leading-8 {$theme['sub']}'>
                                {$text}
                            </p>

                        </div>

                        <div class='grid md:grid-cols-4 gap-10'>
                ";

                foreach ($steps as $step) {

                    $number = e($step['number'] ?? '01');
                    $title  = e($step['title'] ?? 'Step');
                    $desc   = e($step['text'] ?? '');

                    $html .= "
                        <div class='relative rounded-3xl {$theme['card']} p-8 border {$theme['border']}'>

                            <div class='text-5xl font-bold opacity-20 mb-6 {$theme['text']}'>
                                {$number}
                            </div>

                            <h3 class='text-2xl font-bold mb-4 {$theme['text']}'>
                                {$title}
                            </h3>

                            <p class='leading-7 {$theme['sub']}'>
                                {$desc}
                            </p>

                        </div>
                    ";
                }

                $html .= "
                        </div>

                    </div>

                </section>";

                break;


                case 'stats_modern':

                $eyebrow = e($block['eyebrow'] ?? 'Why choose us');
                $heading = e($block['heading'] ?? 'Experience you can count on');
                $text = e($block['text'] ?? 'Clear results, dependable service, and a team committed to every project.');
                $metrics = is_array($block['metrics'] ?? null) ? array_slice($block['metrics'], 0, 4) : [];

                if (empty($metrics)) {
                    $metrics = [
                        ['value' => '15+', 'label' => 'Years of experience', 'description' => 'Serving customers with proven expertise.'],
                        ['value' => '250+', 'label' => 'Projects completed', 'description' => 'Delivered across a wide range of needs.'],
                        ['value' => '98%', 'label' => 'Client satisfaction', 'description' => 'Built through reliable service and support.'],
                        ['value' => '24/7', 'label' => 'Responsive support', 'description' => 'Help is available whenever it matters.'],
                    ];
                }

                $html .= "
                <section class='px-6 py-16 sm:px-8 lg:py-20 {$theme['bg']} transition-colors duration-500'>
                    <div class='mx-auto max-w-7xl'>
                        <div class='mb-10 max-w-2xl space-y-4 sm:mb-12'>";

                if ($eyebrow !== '') {
                    $html .= "<span class='block text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$eyebrow}</span>";
                }

                $html .= "
                            <h2 class='block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2>";

                if ($text !== '') {
                    $html .= "<p class='block max-w-xl text-base leading-7 {$theme['sub']}'>{$text}</p>";
                }

                $html .= "
                        </div>
                        <div class='grid grid-cols-1 border-y {$theme['border']} sm:grid-cols-2 lg:grid-cols-4'>";

                foreach ($metrics as $index => $metric) {
                    $value = e($metric['value'] ?? '');
                    $label = e($metric['label'] ?? '');
                    $description = e($metric['description'] ?? '');
                    $lastBorder = $index === count($metrics) - 1 ? 'sm:last:border-r-0' : '';

                    $html .= "
                            <article class='min-w-0 border-b p-6 last:border-b-0 sm:border-b-0 sm:border-r {$lastBorder} lg:p-7 {$theme['border']}'>
                                <div class='block text-3xl font-bold tracking-tight sm:text-4xl {$theme['text']}'>{$value}</div>
                                <h3 class='mt-3 block text-sm font-semibold {$theme['text']}'>{$label}</h3>";

                    if ($description !== '') {
                        $html .= "<p class='mt-2 block text-sm leading-6 {$theme['sub']}'>{$description}</p>";
                    }

                    $html .= "</article>";
                }

                $html .= "
                        </div>
                    </div>
                </section>";

                break;


                case 'team_modern':

                $eyebrow = e($block['eyebrow'] ?? 'Meet the team');
                $heading = e($block['heading'] ?? 'The people behind the work');
                $text = e($block['text'] ?? 'A dedicated team focused on thoughtful service, clear communication, and dependable results.');
                $members = is_array($block['members'] ?? null) ? $block['members'] : [];

                if (empty($members)) {
                    $members = [
                        ['name' => 'Alex Morgan', 'role' => 'Founder & Director', 'bio' => 'Guides the team with a practical, client-first approach.', 'image_url' => '/storage/cms-images/avatars/avatar-1.jpg'],
                        ['name' => 'Jordan Lee', 'role' => 'Client Experience Lead', 'bio' => 'Keeps every project organized, responsive, and easy to navigate.', 'image_url' => '/storage/cms-images/avatars/avatar-2.jpg'],
                        ['name' => 'Taylor Brooks', 'role' => 'Creative Lead', 'bio' => 'Turns clear ideas into useful, polished digital experiences.', 'image_url' => '/storage/cms-images/avatars/avatar-3.jpg'],
                        ['name' => 'Casey Rivera', 'role' => 'Operations Manager', 'bio' => 'Makes sure quality and momentum stay consistent from start to finish.', 'image_url' => '/storage/cms-images/avatars/avatar-4.jpg'],
                    ];
                }

                $html .= "
                <section class='px-6 py-16 sm:px-8 lg:py-20 {$theme['bg']} transition-colors duration-500'>
                    <div class='mx-auto max-w-7xl'>
                        <div class='mb-10 max-w-2xl space-y-4 sm:mb-12'>";

                if ($eyebrow !== '') {
                    $html .= "<span class='block text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$eyebrow}</span>";
                }

                $html .= "<h2 class='block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2>";

                if ($text !== '') {
                    $html .= "<p class='block max-w-xl text-base leading-7 {$theme['sub']}'>{$text}</p>";
                }

                $html .= "</div><div class='grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4'>";

                foreach ($members as $member) {
                    $name = e($member['name'] ?? '');
                    $role = e($member['role'] ?? '');
                    $bio = e($member['bio'] ?? '');
                    $imageUrl = self::staticAssetUrl($member['image_url'] ?? '');

                    $html .= "
                        <article class='overflow-hidden rounded-2xl border {$theme['border']} {$theme['card']}'>
                            <img src='{$imageUrl}' alt='{$name}' width='960' height='720' loading='lazy' decoding='async' class='aspect-[4/3] w-full object-cover' loading='lazy'>
                            <div class='space-y-2 p-5'>
                                <h3 class='block text-base font-semibold {$theme['text']}'>{$name}</h3>
                                <p class='block text-sm font-medium {$theme['sub']}'>{$role}</p>";

                    if ($bio !== '') {
                        $html .= "<p class='block pt-1 text-sm leading-6 {$theme['sub']}'>{$bio}</p>";
                    }

                    $html .= "</div></article>";
                }

                $html .= "
                        </div>
                    </div>
                </section>";

                break;


                case 'testimonials_carousel':

                $tagline = e($block['tagline'] ?? 'CLIENT TESTIMONIALS');
                $heading = e($block['heading'] ?? 'Trusted By Businesses Around The World');
                $text    = e($block['text'] ?? 'See what our satisfied clients say about working with our team.');

                $testimonials = $block['testimonials'] ?? [];

                $html .= "
                <section class='relative py-32 px-7 overflow-hidden {$theme['bg']} transition-colors duration-500'>

                    <div class='max-w-7xl mx-auto'>

                        <div class='text-center max-w-3xl mx-auto mb-20'>

                            <span class='block text-xs font-semibold uppercase tracking-[0.35em] {$theme['sub']}'>
                                {$tagline}
                            </span>

                            <h2 class='block mt-5 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>
                                {$heading}
                            </h2>

                            <p class='block mt-6 text-lg leading-8 {$theme['sub']}'>
                                {$text}
                            </p>

                        </div>

                        <div class='grid md:grid-cols-3 gap-8'>
                ";

                foreach ($testimonials as $item) {

                    $avatar = $item['avatar'] ?? '';

                    if (!$avatar) {
                    $avatar = '/storage/cms-images/avatars/avatar-1.jpg';
                }

                    $avatar = e(self::staticAssetUrl($avatar));
                    
                    $name    = e($item['name'] ?? 'John Smith');
                    $company = e($item['company'] ?? 'Company');
                    $quote   = e($item['quote'] ?? '');
                    $rating  = (int)($item['rating'] ?? 5);

                    $stars = str_repeat('★', max(0, min($rating, 5)));

                    $html .= "
                        <div class='{$theme['card']} border {$theme['border']} rounded-3xl p-7 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl'>

                            <div class='mb-5 text-xl text-yellow-400'>
                                {$stars}
                            </div>

                            <p class='italic leading-8 {$theme['sub']}'>
                                {$quote}
                            </p>

                            <div class='mt-6 flex items-center gap-4'>

                                <img
                                    src='{$avatar}'
                                    alt='{$name}'
                                    width='56' height='56' loading='lazy' decoding='async' class='w-14 h-14 rounded-full object-cover'
                                >

                                <div>

                                    <h3 class='font-bold {$theme['text']}'>
                                        {$name}
                                    </h3>

                                    <p class='text-sm {$theme['sub']}'>
                                        {$company}
                                    </p>

                                </div>

                            </div>

                        </div>
                    ";
                }

                $html .= "
                        </div>

                    </div>

                </section>";

                break;


                case 'hero_background_image':

                $tagline = e($block['tagline'] ?? 'WELCOME TO OUR COMPANY');
                $heading = e($block['heading'] ?? 'Build Beautiful Websites With Confidence');
                $text = e($block['text'] ?? 'Create modern, responsive websites using reusable blocks, AI-generated content, and powerful customization tools.');

                // The Builder and AI schema use image_url. Keep the old
                // backgroundImage field as a compatibility fallback for
                // pages created before the block contract was unified.
                $backgroundImage = e(self::staticAssetUrl($block['image_url'] ?? $block['backgroundImage'] ?? ''));
                $buttonLabel = e($block['button_label'] ?? 'Get Started');
                $buttonUrl = e($block['button_url'] ?? '#');

                $overlayOpacity = max(0, min(100, intval($block['overlayOpacity'] ?? 50)));
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'primary');
                $isLight = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                // Match the Builder: white/surface sections receive a true white
                // wash with a strong minimum instead of a primary-colour tint.
                // Hero Background Image uses a stronger contrast floor than the
                // generic media overlay so the dynamic primary+slate blend stays
                // visible over bright photography. Match the Builder exactly.
                $overlayStrength = ($isLight
                    ? max(96, $overlayOpacity)
                    : max(46, min(68, (int) round($overlayOpacity * 0.90)))) / 100;
                $primaryOverlayTheme = self::getTheme($primaryColor);
                $overlayHex = self::mediaOverlayColor($resolvedTheme, $primaryColor);
                $overlayClass = $isLight ? 'bg-white' : '';
                $textAlign = $block['textAlign'] ?? 'center';
                $height = $block['height'] ?? 'screen';

                $btnBg = $isLight
                    ? self::getTheme($primaryColor)['bg']
                    : 'bg-white';

                $btnText = $isLight
                    ? self::getTheme($primaryColor)['text']
                    : 'text-slate-900';
                $taglineClass = $isLight ? 'text-slate-700' : 'text-white/80';
                $headingClass = $isLight ? 'text-slate-950' : 'text-white';
                $bodyClass = $isLight ? 'text-slate-700' : 'text-white/80';

                // Alignment
                $alignment = match ($textAlign) {
                    'left' => 'items-start text-left',
                    'right' => 'items-end text-right',
                    default => 'items-center text-center',
                };

                // Height
                $heroHeight = match ($height) {
                    'medium' => 'min-h-[500px]',
                    'large' => 'min-h-[650px]',
                    // Legacy AI output used xl; the Builder renders it at 90vh.
                    'xl' => 'min-h-[90vh]',
                    default => 'min-h-screen',
                };

                $backgroundStyle = $backgroundImage
                    ? "background-image:url('{$backgroundImage}');background-size:cover;background-position:center;"
                    : '';

                $html .= "
                <section
                    class='relative overflow-hidden flex {$heroHeight}'
                    style=\"{$backgroundStyle}\"
                >

                    <div
                        class='absolute inset-0 {$overlayClass}'
                        style='background-color:{$overlayHex};opacity:{$overlayStrength};'>
                    </div>

                    <div class='relative z-10 w-full max-w-7xl mx-auto px-6 py-20 sm:px-[8%] sm:py-24 flex flex-col justify-center {$alignment}'>

                        <span class='text-sm uppercase tracking-[0.35em] font-semibold {$taglineClass} block'>
                            {$tagline}
                        </span>

                        <h1 class='mt-6 text-4xl sm:text-5xl md:text-7xl font-bold leading-tight break-words {$headingClass} block'>
                            {$heading}
                        </h1>

                        <div class='mt-6 max-w-2xl text-base leading-7 sm:mt-8 sm:text-xl sm:leading-8 {$bodyClass}'>
                            {$text}
                        </div>

                        <div class='mt-8 sm:mt-12'>
                            <a
                                href='{$buttonUrl}'
                                class='inline-flex w-full items-center justify-center min-h-[52px] rounded-full px-8 font-bold transition sm:w-auto {$btnBg} {$btnText}'
                            >
                                {$buttonLabel}
                            </a>
                        </div>

                    </div>

                </section>";

                break;


                case 'hero_slider_fade':
                    $slides = is_array($block['slides'] ?? null) && ($block['slides'] ?? []) !== []
                        ? array_values($block['slides'])
                        : [[
                            'image_url' => '',
                            'eyebrow' => 'Built for what is next',
                            'heading' => 'Make a confident first impression',
                            'description' => 'Present your business with clear messaging, purposeful imagery, and a direct next step.',
                            'button_1_text' => 'Get started',
                            'button_1_url' => '#',
                            'button_2_text' => 'Explore services',
                            'button_2_url' => '#',
                            'button_3_text' => 'View our work',
                            'button_3_url' => '#',
                            'button_4_text' => 'Learn more',
                            'button_4_url' => '#',
                        ]];

                    $slides = array_values(array_filter(array_map(static function ($slide) {
                        if (! is_array($slide)) {
                            return null;
                        }

                        return [
                            'image_url' => (string) ($slide['image_url'] ?? $slide['image'] ?? $slide['background_image'] ?? ''),
                            'eyebrow' => (string) ($slide['eyebrow'] ?? ''),
                            'heading' => (string) ($slide['heading'] ?? ''),
                            'description' => (string) ($slide['description'] ?? ''),
                            'button_1_text' => (string) ($slide['button_1_text'] ?? $slide['button_text'] ?? ''),
                            'button_1_url' => (string) ($slide['button_1_url'] ?? $slide['button_url'] ?? '#'),
                            'button_2_text' => (string) ($slide['button_2_text'] ?? 'Explore services'),
                            'button_2_url' => (string) ($slide['button_2_url'] ?? '#'),
                            'button_3_text' => (string) ($slide['button_3_text'] ?? 'View our work'),
                            'button_3_url' => (string) ($slide['button_3_url'] ?? '#'),
                            'button_4_text' => (string) ($slide['button_4_text'] ?? 'Learn more'),
                            'button_4_url' => (string) ($slide['button_4_url'] ?? '#'),
                        ];
                    }, $slides)));

                    if ($slides === []) {
                        break;
                    }

                    $sliderId = 'cosmic-slider-' . substr(sha1(json_encode($slides)), 0, 10);
                    $interval = max(3000, (int) ($block['autoplay_interval'] ?? $block['interval'] ?? 6000));
                    $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                    $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                    $sliderOverlayHex = self::mediaOverlayColor($resolvedTheme, $primaryColor);
                    $sliderOverlayClass = $lightMedia ? 'bg-white/90' : '';
                    $sliderOverlayStyle = $lightMedia ? '' : "background-color:{$sliderOverlayHex};opacity:0.50;";
                    $sliderGradientX = $lightMedia ? 'from-white/100 via-white/96 to-white/82' : 'from-slate-950/32 via-slate-950/12 to-transparent';
                    $sliderGradientY = $lightMedia ? 'from-white/94 via-white/36 to-white/76' : 'from-slate-950/48 via-transparent to-slate-950/12';
                    $sliderText = $lightMedia ? 'text-slate-950' : 'text-white';
                    $sliderEyebrow = $lightMedia ? 'text-slate-700' : 'text-white/70';
                    $sliderBody = $lightMedia ? 'text-slate-700' : 'text-white/75';
                    $primaryTheme = self::getTheme($primaryColor);
                    $slideMarkup = '';
                    $dotMarkup = '';

                    foreach ($slides as $slideIndex => $slide) {
                        $imageUrl = e(self::staticAssetUrl($slide['image_url']));
                        $eyebrow = e($slide['eyebrow']);
                        $heading = e($slide['heading']);
                        $description = e($slide['description']);
                        $buttons = '';

                        for ($buttonNumber = 1; $buttonNumber <= 3; $buttonNumber++) {
                            $label = trim($slide["button_{$buttonNumber}_text"]);
                            if ($label === '') {
                                continue;
                            }

                            $buttonUrl = e($slide["button_{$buttonNumber}_url"] ?: '#');
                            $buttonLabel = e($label);
                            $buttonClasses = $buttonNumber === 1
                                ? ($lightMedia ? $primaryTheme['bg'] . ' text-white hover:opacity-90' : 'bg-white text-slate-950 hover:bg-white/90')
                                : ($lightMedia ? 'border border-slate-900/20 bg-white/55 text-slate-950 hover:bg-white/80' : 'border border-white/35 bg-black/20 text-white hover:border-white/60 hover:bg-black/35');

                            $buttons .= "<a href='{$buttonUrl}' class='rounded-full px-6 py-3.5 text-sm font-bold backdrop-blur transition {$buttonClasses}'>{$buttonLabel}</a>";
                        }

                        $floating = '';
                        if (trim($slide['button_4_text']) !== '') {
                            $floatingUrl = e($slide['button_4_url'] ?: '#');
                            $floatingLabel = e($slide['button_4_text']);
                            $floating = "<a href='{$floatingUrl}' class='mr-[30px] rounded-full border border-white/25 bg-black/35 px-4 py-2 text-xs font-bold text-white backdrop-blur transition hover:border-white/50 hover:bg-black/55'>{$floatingLabel}</a>";
                        }

                        $activeClasses = $slideIndex === 0 ? 'z-10 opacity-100' : 'z-0 opacity-0';
                        $ariaHidden = $slideIndex === 0 ? 'false' : 'true';
                        $media = $imageUrl !== ''
                            ? "<img src='{$imageUrl}' alt='' width='1920' height='1080' loading='eager' fetchpriority='high' decoding='async' class='absolute inset-0 h-full w-full object-cover'>"
                            : "<div class='absolute inset-0 bg-slate-900'></div>";

                        $slideMarkup .= "
                        <article data-cosmic-slide='{$slideIndex}' aria-hidden='{$ariaHidden}' class='absolute inset-0 transition-opacity duration-700 {$activeClasses}'>
                            {$media}
                            <div class='absolute inset-0 {$sliderOverlayClass}' style='{$sliderOverlayStyle}'></div>
                            <div class='absolute inset-0 bg-gradient-to-r {$sliderGradientX}'></div>
                            <div class='absolute inset-0 bg-gradient-to-t {$sliderGradientY}'></div>
                            <div class='relative z-10 mx-auto flex min-h-[620px] max-w-7xl items-center px-6 py-24 sm:min-h-[700px] sm:px-10 lg:min-h-[760px] lg:px-14'>
                                <div class='max-w-3xl {$sliderText}' aria-live='polite'>
                                    " . ($eyebrow !== '' ? "<p class='mb-5 text-xs font-bold uppercase tracking-[0.32em] sm:text-sm {$sliderEyebrow}'>{$eyebrow}</p>" : '') . "
                                    <h2 class='max-w-3xl text-5xl font-bold leading-[0.98] tracking-[-0.04em] sm:text-6xl lg:text-7xl'>{$heading}</h2>
                                    <p class='mt-7 max-w-2xl text-base leading-8 sm:text-lg {$sliderBody}'>{$description}</p>
                                    <div class='mt-9 flex flex-wrap gap-3'>{$buttons}</div>
                                </div>
                            </div>
                            <div class='absolute bottom-6 right-32 z-30 hidden items-center sm:flex'>{$floating}</div>
                        </article>";

                        $dotClasses = $slideIndex === 0 ? 'w-8 bg-white' : 'w-2.5 bg-white/45 hover:bg-white/70';
                        $current = $slideIndex === 0 ? " aria-current='true'" : '';
                        $dotMarkup .= "<button type='button' data-slider-dot='{$slideIndex}' class='h-2.5 rounded-full transition-all {$dotClasses}' aria-label='Show slide " . ($slideIndex + 1) . "'{$current}></button>";
                    }

                    $html .= "
                    <section id='{$sliderId}' data-cosmic-slider data-interval='{$interval}' class='relative isolate min-h-[620px] overflow-hidden sm:min-h-[700px] lg:min-h-[760px]' aria-roledescription='carousel' aria-label='Featured content'>
                        {$slideMarkup}
                        <div class='absolute bottom-6 left-6 z-40 flex items-center gap-2 sm:left-10 lg:left-14'>{$dotMarkup}</div>
                        <div class='absolute bottom-6 right-6 z-40 flex items-center gap-2 sm:right-10 lg:right-14'>
                            <button type='button' data-slider-prev class='grid h-10 w-10 place-items-center rounded-full border border-white/25 bg-black/35 text-white backdrop-blur hover:bg-black/55' aria-label='Previous slide'>←</button>
                            <button type='button' data-slider-next class='grid h-10 w-10 place-items-center rounded-full border border-white/25 bg-black/35 text-white backdrop-blur hover:bg-black/55' aria-label='Next slide'>→</button>
                        </div>
                    </section>
                    <script>
                    (function () {
                        var root = document.getElementById('{$sliderId}');
                        if (!root || root.dataset.ready === '1') return;
                        root.dataset.ready = '1';

                        var slides = Array.prototype.slice.call(root.querySelectorAll('[data-cosmic-slide]'));
                        var dots = Array.prototype.slice.call(root.querySelectorAll('[data-slider-dot]'));
                        var index = 0;
                        var timer = null;
                        var paused = false;
                        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                        function show(nextIndex) {
                            if (!slides.length) return;
                            index = (nextIndex + slides.length) % slides.length;

                            slides.forEach(function (slide, slideIndex) {
                                var active = slideIndex === index;
                                slide.classList.toggle('opacity-100', active);
                                slide.classList.toggle('z-10', active);
                                slide.classList.toggle('opacity-0', !active);
                                slide.classList.toggle('z-0', !active);
                                slide.setAttribute('aria-hidden', active ? 'false' : 'true');
                            });

                            dots.forEach(function (dot, dotIndex) {
                                var active = dotIndex === index;
                                dot.classList.toggle('w-8', active);
                                dot.classList.toggle('bg-white', active);
                                dot.classList.toggle('w-2.5', !active);
                                dot.classList.toggle('bg-white/45', !active);
                                if (active) dot.setAttribute('aria-current', 'true');
                                else dot.removeAttribute('aria-current');
                            });
                        }

                        function stop() {
                            if (timer) {
                                clearInterval(timer);
                                timer = null;
                            }
                        }

                        function start() {
                            stop();
                            if (paused || reduced || slides.length < 2) return;
                            timer = setInterval(function () {
                                show(index + 1);
                            }, Math.max(3000, Number(root.dataset.interval) || 6000));
                        }

                        dots.forEach(function (dot, dotIndex) {
                            dot.addEventListener('click', function () {
                                show(dotIndex);
                                start();
                            });
                        });

                        var previous = root.querySelector('[data-slider-prev]');
                        var next = root.querySelector('[data-slider-next]');
                        if (previous) previous.addEventListener('click', function () { show(index - 1); start(); });
                        if (next) next.addEventListener('click', function () { show(index + 1); start(); });

                        root.addEventListener('mouseenter', function () { paused = true; stop(); });
                        root.addEventListener('mouseleave', function () { paused = false; start(); });
                        root.addEventListener('focusin', function () { paused = true; stop(); });
                        root.addEventListener('focusout', function (event) {
                            if (!root.contains(event.relatedTarget)) {
                                paused = false;
                                start();
                            }
                        });

                        show(0);
                        start();
                    })();
                    </script>";

                    break;

                case 'hero_parallax':
                    $eyebrow = e($block['eyebrow'] ?? 'INTRODUCING A NEW PERSPECTIVE');
                    $heading = e($block['heading'] ?? 'Move beyond the ordinary.');
                    $text = e($block['text'] ?? 'Create a memorable first impression with cinematic depth, confident typography, and a clear next step.');
                    $primaryLabel = e($block['primary_label'] ?? 'Start a project');
                    $primaryUrl = e($block['primary_url'] ?? '#');
                    $secondaryLabel = e($block['secondary_label'] ?? 'Explore our work');
                    $secondaryUrl = e($block['secondary_url'] ?? '#');
                    $imageUrl = e(self::staticAssetUrl((string) ($block['image_url'] ?? '/storage/cms-images/background/background-1.avif')));
                    $overlayOpacity = max(20, min(90, (int) ($block['overlayOpacity'] ?? 64)));
                    $parallaxSpeed = max(8, min(40, (int) ($block['parallaxSpeed'] ?? 24)));
                    $contentAlign = in_array(($block['contentAlign'] ?? 'left'), ['left', 'center', 'right'], true)
                        ? $block['contentAlign']
                        : 'left';
                    $height = ($block['height'] ?? 'screen') === 'large' ? 'large' : 'screen';
                    $scrollLabel = e($block['scroll_label'] ?? 'Scroll to explore');
                    $resolvedTheme = (string) ($block['resolvedTheme'] ?? 'primary');
                    $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                    $primaryTheme = self::getTheme($primaryColor);
                    $parallaxId = 'cosmic-parallax-' . substr(sha1(json_encode($block)), 0, 10);

                    $alignmentClasses = [
                        'left' => 'items-start text-left',
                        'center' => 'items-center text-center',
                        'right' => 'items-end text-right',
                    ];
                    $alignmentClass = $alignmentClasses[$contentAlign];
                    $contentWidth = $contentAlign === 'center' ? 'max-w-4xl' : 'max-w-3xl';
                    $heroHeight = $height === 'large' ? 'min-h-[720px]' : 'min-h-[88svh] lg:min-h-screen';
                    $buttonJustify = $contentAlign === 'center'
                        ? 'justify-center'
                        : ($contentAlign === 'right' ? 'justify-end' : 'justify-start');
                    $gradientDirection = $contentAlign === 'right'
                        ? 'bg-gradient-to-l'
                        : ($contentAlign === 'left' ? 'bg-gradient-to-r' : 'bg-gradient-to-t');

                    $overlayHex = self::mediaOverlayColor($resolvedTheme, $primaryColor);
                    $overlayColor = $lightMedia ? 'bg-white' : '';
                    $effectiveOverlayOpacity = $lightMedia ? max(96, $overlayOpacity) : max(32, min(56, (int) round($overlayOpacity * 0.72)));
                    $gradient = $lightMedia
                        ? 'from-white/100 via-white/97 to-white/92'
                        : 'from-slate-950/55 via-slate-950/12 to-slate-950/16';
                    $badge = $lightMedia ? 'border-slate-900/15 bg-white/60' : 'border-white/20 bg-white/10';
                    $eyebrowClass = $lightMedia ? 'text-slate-700' : 'text-white/85';
                    $headingClass = $lightMedia ? 'text-slate-950' : 'text-white';
                    $bodyClass = $lightMedia ? 'text-slate-700' : 'text-white/75';
                    $secondaryClass = $lightMedia
                        ? 'border-slate-900/20 bg-white/50 text-slate-950 hover:bg-white/75'
                        : 'border-white/30 bg-white/10 text-white hover:bg-white/20';
                    $scrollClass = $lightMedia ? 'text-slate-700' : 'text-white/65';
                    $scrollLine = $lightMedia ? 'bg-slate-900/25' : 'bg-white/25';
                    $scrollDot = $lightMedia ? 'bg-slate-900' : 'bg-white';

                    $html .= "
                    <section id='{$parallaxId}' data-cosmic-parallax data-speed='{$parallaxSpeed}' class='relative isolate flex overflow-hidden py-12 sm:py-16 lg:py-20 {$heroHeight}'>
                        <div data-parallax-media class='absolute -inset-y-[18%] inset-x-0 z-0 will-change-transform' style='transform:translate3d(0,0,0) scale(1.14)'>
                            <img src='{$imageUrl}' alt='' width='1920' height='1080' loading='eager' fetchpriority='high' decoding='async' class='absolute inset-0 h-full w-full object-cover'>
                        </div>
                        <div class='absolute inset-0 z-10 {$overlayColor}' style='background-color:{$overlayHex};opacity:" . ($effectiveOverlayOpacity / 100) . "'></div>
                        <div class='absolute inset-0 z-10 {$gradientDirection} {$gradient}'></div>
                        <div data-parallax-content class='relative z-20 mx-auto flex w-full max-w-7xl flex-col justify-center px-4 transition-opacity duration-150 sm:px-6 lg:px-8 {$alignmentClass}' style='transform:translate3d(0,0,0);will-change:transform,opacity'>
                            <div class='{$contentWidth}'>
                                <div class='inline-flex items-center gap-3 rounded-full border px-4 py-2 backdrop-blur-md {$badge}'>
                                    <span class='h-2 w-2 rounded-full {$primaryTheme['bg']}'></span>
                                    <span class='text-xs font-bold uppercase tracking-[0.28em] {$eyebrowClass}'>{$eyebrow}</span>
                                </div>
                                <h1 class='mt-7 text-5xl font-semibold leading-[0.96] tracking-[-0.045em] sm:text-6xl md:text-7xl lg:text-[6.5rem] {$headingClass}'>{$heading}</h1>
                                <p class='mt-7 text-base leading-8 sm:text-lg {$bodyClass} " . ($contentAlign === 'center' ? 'mx-auto max-w-2xl' : 'max-w-2xl') . "'>{$text}</p>
                                <div class='mt-10 flex w-full flex-col gap-3 sm:w-auto sm:flex-row {$buttonJustify}'>
                                    <a href='{$primaryUrl}' class='inline-flex min-h-[54px] items-center justify-center rounded-full px-8 font-bold transition hover:-translate-y-0.5 {$primaryTheme['bg']} {$primaryTheme['text']}'>{$primaryLabel}</a>
                                    <a href='{$secondaryUrl}' class='inline-flex min-h-[54px] items-center justify-center rounded-full border px-8 font-bold backdrop-blur-md transition {$secondaryClass}'>{$secondaryLabel}</a>
                                </div>
                            </div>
                        </div>
                        <div class='pointer-events-none absolute bottom-7 left-1/2 z-20 hidden -translate-x-1/2 flex-col items-center gap-3 sm:flex {$scrollClass}'>
                            <span class='text-[10px] font-bold uppercase tracking-[0.32em]'>{$scrollLabel}</span>
                            <span class='relative h-10 w-px overflow-hidden {$scrollLine}'><span class='absolute left-0 top-0 h-4 w-px animate-bounce {$scrollDot}'></span></span>
                        </div>
                    </section>
                    <script>
                    (function () {
                        var root = document.getElementById('{$parallaxId}');
                        if (!root || root.dataset.ready === '1') return;
                        root.dataset.ready = '1';

                        var media = root.querySelector('[data-parallax-media]');
                        var content = root.querySelector('[data-parallax-content]');
                        if (!media) return;

                        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
                        var mobile = window.matchMedia('(max-width: 639px)');
                        var saveData = !!(navigator.connection && navigator.connection.saveData);
                        var frame = null;

                        function update() {
                            frame = null;

                            if (reduced.matches || mobile.matches || saveData) {
                                media.style.transform = 'translate3d(0,0,0) scale(1.14)';
                                if (content) {
                                    content.style.transform = 'translate3d(0,0,0)';
                                    content.style.opacity = '1';
                                }
                                return;
                            }

                            var rect = root.getBoundingClientRect();
                            var viewport = window.innerHeight || 1;
                            if (rect.bottom <= 0 || rect.top >= viewport) return;

                            var progress = Math.max(0, Math.min(1, (viewport - rect.top) / (viewport + rect.height)));
                            var centered = progress - 0.5;
                            var strength = Number(root.dataset.speed) || 24;

                            media.style.transform = 'translate3d(0,' + (centered * strength * 7) + 'px,0) scale(1.14)';

                            if (content) {
                                content.style.transform = 'translate3d(0,' + (centered * strength * -1.7) + 'px,0)';
                                content.style.opacity = String(Math.max(0.35, 1 - Math.abs(centered) * 0.75));
                            }
                        }

                        function requestUpdate() {
                            if (frame === null) {
                                frame = window.requestAnimationFrame(update);
                            }
                        }

                        update();
                        document.addEventListener('scroll', requestUpdate, true);
                        window.addEventListener('resize', requestUpdate);
                        if (reduced.addEventListener) reduced.addEventListener('change', requestUpdate);
                    })();
                    </script>";

                    break;

                case 'hero_editorial_overlay':

                $tagline = e($block['tagline'] ?? 'BUILT FOR WHAT COMES NEXT');
                $heading = e($block['heading'] ?? 'A stronger first impression starts here.');
                $text = e($block['text'] ?? 'Bring your story, services, and next step into focus with a confident, image-led introduction.');
                $primaryLabel = e($block['primary_label'] ?? 'Start a project');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'Explore services');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $backgroundImage = e(self::staticAssetUrl($block['image_url'] ?? ''));
                $overlayOpacity = max(0, min(100, intval($block['overlayOpacity'] ?? 72)));
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'primary');
                $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                $effectiveOverlayOpacity = $lightMedia ? max(96, $overlayOpacity) : max(32, min(56, (int) round($overlayOpacity * 0.72)));
                $heroHeight = match ($block['height'] ?? 'large') {
                    'medium' => 'min-h-[520px]',
                    'screen' => 'min-h-[72svh] sm:min-h-[80vh] md:min-h-[85vh] lg:min-h-[90vh]',
                    default => 'min-h-[650px]',
                };
                $primaryTheme = self::getTheme($primaryColor);
                $overlayHex = self::mediaOverlayColor($resolvedTheme, $primaryColor);
                $overlayClass = $lightMedia ? 'bg-white' : '';
                $gradientClass = $lightMedia
                    ? 'from-white/100 via-white/97 to-white/92'
                    : 'from-slate-950/55 via-slate-950/22 to-transparent';
                $taglineClass = $lightMedia ? 'text-slate-700' : 'text-white/75';
                $headingClass = $lightMedia ? 'text-slate-950' : 'text-white';
                $bodyClass = $lightMedia ? 'text-slate-700' : 'text-white/80';
                $secondaryClass = $lightMedia
                    ? 'border-slate-900/20 bg-white/70 text-slate-950'
                    : 'border-white/40 bg-white/5 text-white';
                $backgroundStyle = $backgroundImage
                    ? "background-image:url('{$backgroundImage}');background-size:cover;background-position:center;"
                    : '';

                $html .= "
                <section class='relative flex overflow-hidden {$heroHeight}' style=\"{$backgroundStyle}\">
                    <div class='absolute inset-0 {$overlayClass}' style='background-color:{$overlayHex};opacity:" . ($effectiveOverlayOpacity / 100) . ";'></div>
                    <div class='absolute inset-0 bg-gradient-to-r {$gradientClass}'></div>
                    <div class='relative z-10 mx-auto flex w-full max-w-7xl items-center px-7 py-20 sm:py-24'>
                        <div class='max-w-3xl'>
                            <span class='block text-xs font-semibold uppercase tracking-[0.3em] {$taglineClass}'>{$tagline}</span>
                            <h1 class='mt-5 text-5xl font-bold leading-[1.03] tracking-tight {$headingClass} sm:text-6xl md:text-7xl lg:text-8xl'>{$heading}</h1>
                            <div class='mt-6 max-w-2xl text-base leading-7 {$bodyClass} sm:text-lg sm:leading-8'>{$text}</div>
                            <div class='mt-8 flex flex-col gap-3 sm:flex-row sm:items-center'>
                                <a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryTheme['bg']} {$primaryTheme['text']}'>{$primaryLabel}</a>
                                <a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold {$secondaryClass}'>{$secondaryLabel}</a>
                            </div>
                        </div>
                    </div>
                </section>";

                break;

                case 'hero_split_image':

                $tagline = e($block['tagline'] ?? "BUILT FOR WHAT'S NEXT");
                $heading = e($block['heading'] ?? 'Make a stronger first impression.');
                $text = e($block['text'] ?? 'Tell your story clearly, show what makes your business different, and guide visitors toward the next step.');
                $primaryLabel = e($block['primary_label'] ?? 'Get started');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'Learn more');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $trustLine = e($block['trust_line'] ?? 'Trusted by customers who value quality work.');
                $imageBadge = e($block['image_badge'] ?? 'Serving your community');
                $imageUrl = e(self::staticAssetUrl($block['image_url'] ?? ''));
                $primaryTheme = self::getTheme($primaryColor);
                $isPrimarySection = ($block['resolvedTheme'] ?? null) === 'primary';
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $imageStyle = $imageUrl
                    ? "background-image:url('{$imageUrl}');background-size:cover;background-position:center;"
                    : '';

                $html .= "
                <section class='relative overflow-hidden px-7 py-16 sm:px-10 sm:py-20 lg:px-12 lg:py-24 {$theme['bg']}'>
                    <div class='relative mx-auto grid max-w-7xl items-center gap-12 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] lg:gap-20'>
                        <div class='order-2 max-w-2xl lg:order-1'>
                            <span class='block text-xs font-semibold uppercase tracking-[0.3em] {$theme['sub']}'>{$tagline}</span>
                            <h1 class='mt-5 text-5xl font-bold leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl {$theme['text']}'>{$heading}</h1>
                            <div class='mt-6 max-w-xl text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>{$text}</div>
                            <div class='mt-8 flex flex-col gap-3 sm:flex-row sm:items-center'>
                                <a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a>
                                <a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold {$theme['border']} {$theme['text']}'>{$secondaryLabel}</a>
                            </div>
                            <p class='mt-8 border-t pt-5 text-sm {$theme['border']} {$theme['sub']}'>{$trustLine}</p>
                        </div>
                        <div class='order-1 lg:order-2'>
                            <div class='relative aspect-[4/3] overflow-hidden rounded-[2rem] border shadow-2xl {$theme['border']}' style=\"{$imageStyle}\">
                                <span class='absolute bottom-5 left-5 rounded-full bg-slate-950/80 px-4 py-2 text-xs font-semibold text-white'>{$imageBadge}</span>
                            </div>
                        </div>
                    </div>
                </section>";

                break;

                case 'hero_video_premium':

                $eyebrow = e($block['eyebrow'] ?? 'A STORY IN MOTION');
                $heading = e($block['heading'] ?? 'Make the first few seconds impossible to forget.');
                $text = e($block['text'] ?? 'Use cinematic movement, focused copy, and one clear next step to introduce your brand with confidence.');
                $primaryLabel = e($block['primary_label'] ?? 'Start the experience');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'Watch the story');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $mediaBadge = e($block['media_badge'] ?? 'Cinematic brand experience');
                $scrollLabel = e($block['scroll_label'] ?? 'Scroll to explore');
                $rawVideoUrl = trim((string) ($block['video_url'] ?? '')) ?: '/storage/cms-videos/hero-placeholder.mp4';
                $premiumVideoEmbedUrl = self::backgroundVideoEmbedUrl($rawVideoUrl);
                $videoUrl = e(self::staticAssetUrl($rawVideoUrl));
                $posterUrl = e(self::staticAssetUrl(trim((string) ($block['poster_image_url'] ?? '')) ?: '/storage/cms-images/background/background-1.avif'));
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $premiumOverlayHex = self::mediaOverlayColor($resolvedTheme, $primaryColor);
                $premiumOverlayBase = $lightMedia ? 'bg-white/90' : '';
                $premiumOverlayStyle = $lightMedia ? '' : "background-color:{$premiumOverlayHex};opacity:0.26;";
                $premiumGradientX = $lightMedia ? 'from-white/100 via-white/96 to-white/82' : 'from-slate-950/62 via-slate-950/34 to-slate-950/10';
                $premiumGradientY = $lightMedia ? 'from-white/94 via-white/36 to-white/78' : 'from-slate-950/52 via-transparent to-slate-950/16';
                $premiumBorder = $lightMedia ? 'border-slate-900/15' : 'border-white/25';
                $premiumEyebrow = $lightMedia ? 'text-slate-700' : 'text-white/80';
                $premiumBadge = $lightMedia ? 'border-slate-900/15 bg-white/65 text-slate-900' : 'border-white/30 bg-white/10 text-white';
                $premiumHeading = $lightMedia ? 'text-slate-950' : 'text-white';
                $premiumBody = $lightMedia ? 'text-slate-700' : 'text-white/75';
                $premiumSecondary = $lightMedia ? 'border-slate-900/20 bg-white/55 text-slate-950' : 'border-white/45 bg-white/5 text-white';
                $premiumScroll = $lightMedia ? 'text-slate-700' : 'text-white/75';
                $premiumBackgroundMedia = $premiumVideoEmbedUrl
                    ? "<div class='absolute inset-0 overflow-hidden'>
                            <img src='{$posterUrl}' alt='' aria-hidden='true' class='absolute inset-0 h-full w-full object-cover sm:hidden'>
                            <iframe src='" . e($premiumVideoEmbedUrl) . "' title='Background video' allow='autoplay; fullscreen; picture-in-picture' class='pointer-events-none absolute left-1/2 top-1/2 hidden h-[56.25vw] min-h-full w-[177.78vh] min-w-full -translate-x-1/2 -translate-y-1/2 border-0 sm:block'></iframe>
                        </div>"
                    : "<div class='absolute inset-0'>
                            <img src='{$posterUrl}' alt='' aria-hidden='true' class='absolute inset-0 h-full w-full object-cover sm:hidden'>
                            <video class='hidden h-full w-full object-cover sm:block' autoplay muted loop playsinline preload='metadata' poster='{$posterUrl}'><source src='{$videoUrl}' type='video/mp4'></video>
                        </div>";

                $html .= "
                <section class='relative min-h-[84vh] overflow-hidden {$theme['bg']}'>
                    {$premiumBackgroundMedia}
                    <div class='absolute inset-0 {$premiumOverlayBase}' style='{$premiumOverlayStyle}'></div>
                    <div class='absolute inset-0 bg-gradient-to-r {$premiumGradientX}'></div>
                    <div class='absolute inset-0 bg-gradient-to-t {$premiumGradientY}'></div>
                    <div class='relative mx-auto flex min-h-[84vh] max-w-7xl flex-col justify-between px-6 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-12'>
                        <div class='flex items-center justify-between border-b pb-5 {$premiumBorder}'><span class='text-[11px] font-bold uppercase tracking-[.34em] {$premiumEyebrow}'>{$eyebrow}</span><span class='rounded-full border px-4 py-2 text-[11px] font-semibold {$premiumBadge}'>{$mediaBadge}</span></div>
                        <div class='max-w-4xl py-14 sm:py-20 lg:py-24'>
                            <h1 class='max-w-4xl text-5xl font-semibold leading-[.95] tracking-[-.05em] sm:text-7xl lg:text-[6.6rem] {$premiumHeading}'>{$heading}</h1>
                            <p class='mt-7 max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 {$premiumBody}'>{$text}</p>
                            <div class='mt-9 flex flex-col gap-3 sm:flex-row'><a href='{$primaryUrl}' class='inline-flex min-h-[52px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a><a href='{$secondaryUrl}' class='inline-flex min-h-[52px] items-center justify-center rounded-full border px-7 font-bold {$premiumSecondary}'>{$secondaryLabel}</a></div>
                        </div>
                        <div class='flex items-center justify-between border-t pt-5 {$premiumBorder}'><span class='text-xs font-semibold uppercase tracking-[.2em] {$premiumScroll}'>{$scrollLabel}</span><span class='flex h-10 w-6 items-start justify-center rounded-full border p-1 {$premiumBorder}'><span class='h-2 w-1 rounded-full " . ($lightMedia ? "bg-slate-900" : "bg-white") . "'></span></span></div>
                    </div>
                </section>";

                break;

                case 'hero_ai_conversation':
                $eyebrow = e($block['eyebrow'] ?? 'AI THAT WORKS WITH YOU');
                $heading = e($block['heading'] ?? 'Turn a simple prompt into meaningful progress.');
                $text = e($block['text'] ?? 'Show visitors how your AI listens, responds, and helps them move from idea to action in one focused experience.');
                $primaryLabel = e($block['primary_label'] ?? 'Start building');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'See how it works');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $assistantLabel = e($block['assistant_label'] ?? 'Cosmic AI');
                $assistantStatus = e($block['assistant_status'] ?? 'Ready to help');
                $userMessage = e($block['user_message'] ?? 'Create a polished campaign page for our next launch.');
                $assistantMessage = e($block['assistant_message'] ?? 'I’ll shape the structure, write the first draft, and prepare a responsive page you can refine.');
                $promptPlaceholder = e($block['prompt_placeholder'] ?? 'Ask AI to create, improve, or explain...');
                $chips = array_map('e', [$block['chip_one'] ?? 'Strategy-aware', $block['chip_two'] ?? 'Editable output', $block['chip_three'] ?? 'Built to publish']);
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $userBubble = $isPrimarySection ? 'bg-white text-slate-950' : "{$primaryTheme['bg']} text-white";
                $assistantBubble = $isPrimarySection ? 'border-white/20 bg-white/12 text-white' : "{$theme['card']} {$theme['border']} {$theme['text']}";
                $composerSurface = $isPrimarySection ? 'border-white/20 bg-white/10 text-white' : "{$theme['card']} {$theme['border']} {$theme['text']}";
                $chipHtml = ''; foreach ($chips as $chip) { $chipHtml .= "<span class='rounded-full border px-3 py-2 text-xs font-semibold {$theme['border']} {$theme['surface']} {$theme['sub']}'>{$chip}</span>"; }

                $html .= "
                <section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'>
                    <div class='relative mx-auto grid max-w-7xl items-center gap-12 lg:grid-cols-[.88fr_1.12fr] lg:gap-16'>
                        <div><span class='text-xs font-bold uppercase tracking-[.28em] {$theme['sub']}'>{$eyebrow}</span><h1 class='mt-5 text-5xl font-semibold leading-[.96] tracking-[-.05em] sm:text-6xl lg:text-7xl {$theme['text']}'>{$heading}</h1><p class='mt-6 max-w-xl text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>{$text}</p><div class='mt-8 flex flex-col gap-3 sm:flex-row'><a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a><a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold {$theme['border']} {$theme['text']}'>{$secondaryLabel}</a></div><div class='mt-8 flex flex-wrap gap-2'>{$chipHtml}</div></div>
                        <div class='relative rounded-[2rem] border p-4 shadow-2xl sm:p-6 {$theme['border']} {$theme['surface']}'>
                            <div class='flex items-center justify-between border-b pb-4 {$theme['border']}'><div class='flex items-center gap-3'><div class='grid h-11 w-11 place-items-center rounded-2xl {$primaryTheme['bg']} {$primaryTheme['text']}'>✦</div><div><strong class='block text-sm {$theme['text']}'>{$assistantLabel}</strong><span class='mt-1 block text-xs {$theme['sub']}'>{$assistantStatus}</span></div></div><span class='rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-[.18em] {$theme['border']} {$theme['bg']} {$theme['sub']}'>Live preview</span></div>
                            <div class='space-y-4 py-6'><div class='ml-auto max-w-[82%] rounded-[1.4rem] rounded-br-md px-5 py-4 text-sm leading-6 {$userBubble}'>{$userMessage}</div><div class='max-w-[88%] rounded-[1.4rem] rounded-bl-md border px-5 py-4 text-sm leading-6 {$assistantBubble}'>{$assistantMessage}</div></div>
                            <div class='flex items-center gap-3 rounded-2xl border p-3 {$composerSurface}'><span class='min-w-0 flex-1 text-sm {$theme['sub']}'>{$promptPlaceholder}</span><span class='grid h-10 w-10 shrink-0 place-items-center rounded-xl {$primaryTheme['bg']} text-white'>↑</span></div>
                        </div>
                    </div>
                </section>";
                break;


                case 'hero_agency_showcase':
                $eyebrow = e($block['eyebrow'] ?? 'DESIGN THAT MOVES BUSINESS FORWARD');
                $heading = e($block['heading'] ?? 'From overlooked to unforgettable.');
                $text = e($block['text'] ?? 'Pair strategic thinking with polished execution, then show visitors the difference your agency creates at a glance.');
                $primaryLabel = e($block['primary_label'] ?? 'Start a project');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'View case studies');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $beforeLabel = e($block['before_label'] ?? 'Before');
                $beforeCaption = e($block['before_caption'] ?? 'A fragmented digital experience');
                $afterLabel = e($block['after_label'] ?? 'After');
                $afterCaption = e($block['after_caption'] ?? 'A focused brand built to convert');
                $metricValues = array_map('e', [$block['metric_one_value'] ?? '48%', $block['metric_two_value'] ?? '2.4x', $block['metric_three_value'] ?? '6 weeks']);
                $metricLabels = array_map('e', [$block['metric_one_label'] ?? 'More qualified enquiries', $block['metric_two_label'] ?? 'Higher conversion rate', $block['metric_three_label'] ?? 'From strategy to launch']);
                $logos = array_map('e', [$block['logo_one'] ?? 'NORTHSTAR', $block['logo_two'] ?? 'MORROW & CO', $block['logo_three'] ?? 'FOUNDRY', $block['logo_four'] ?? 'KINSHIP']);
                $beforeImage = e(self::staticAssetUrl($block['before_image_url'] ?? ''));
                $afterImage = e(self::staticAssetUrl($block['after_image_url'] ?? ''));
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $metricSurface = $isPrimarySection ? 'border-white/20 bg-white/10 text-white' : "{$theme['border']} {$theme['surface']} {$theme['text']}";
                $metricSub = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $beforeStyle = $beforeImage ? "background-image:url('{$beforeImage}');background-size:cover;background-position:center;" : '';
                $afterStyle = $afterImage ? "background-image:url('{$afterImage}');background-size:cover;background-position:center;" : '';
                $metricHtml = ''; for ($i=0; $i<3; $i++) { $metricHtml .= "<div class='rounded-2xl border p-5 {$metricSurface}'><strong class='block text-3xl font-semibold tracking-tight'>{$metricValues[$i]}</strong><span class='mt-2 block text-sm {$metricSub}'>{$metricLabels[$i]}</span></div>"; }
                $logoHtml = ''; foreach ($logos as $logo) { $logoHtml .= "<span class='text-xs font-black tracking-[.15em] {$theme['text']}'>{$logo}</span>"; }

                $html .= "
                <section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'>
                    <div class='relative mx-auto max-w-7xl'>
                        <div class='grid items-end gap-10 lg:grid-cols-[1fr_.72fr] lg:gap-16'><div><span class='text-xs font-bold uppercase tracking-[.28em] {$theme['sub']}'>{$eyebrow}</span><h1 class='mt-5 max-w-4xl text-5xl font-semibold leading-[.95] tracking-[-.055em] sm:text-6xl lg:text-8xl {$theme['text']}'>{$heading}</h1></div><div class='lg:pb-2'><p class='text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>{$text}</p><div class='mt-7 flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row'><a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a><a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold {$theme['border']} {$theme['text']}'>{$secondaryLabel}</a></div></div></div>
                        <div class='mt-12 rounded-[2rem] border p-3 shadow-2xl sm:p-4 {$theme['border']} {$theme['surface']}'><div class='grid gap-3 md:grid-cols-2'><div class='relative min-h-[300px] overflow-hidden rounded-[1.45rem] grayscale sm:min-h-[390px]' style=\"{$beforeStyle}\"><div class='absolute inset-0 bg-slate-950/50'></div><div class='absolute inset-x-0 bottom-0 p-5 text-white sm:p-7'><span class='text-[11px] font-bold uppercase tracking-[.24em] text-white/70'>{$beforeLabel}</span><strong class='mt-2 block max-w-sm text-xl font-semibold leading-tight text-white sm:text-2xl'>{$beforeCaption}</strong></div></div><div class='relative min-h-[300px] overflow-hidden rounded-[1.45rem] sm:min-h-[390px]' style=\"{$afterStyle}\"><div class='absolute inset-0 bg-gradient-to-t from-slate-950/70 via-slate-950/10 to-transparent'></div><div class='absolute inset-x-0 bottom-0 p-5 text-white sm:p-7'><span class='text-[11px] font-bold uppercase tracking-[.24em] text-white/70'>{$afterLabel}</span><strong class='mt-2 block max-w-sm text-xl font-semibold leading-tight text-white sm:text-2xl'>{$afterCaption}</strong></div></div></div></div>
                        <div class='mt-6 grid gap-3 sm:grid-cols-3'>{$metricHtml}</div>
                        <div class='mt-8 flex flex-wrap items-center justify-between gap-x-8 gap-y-4 border-t pt-7 {$theme['border']}'><span class='text-[10px] font-bold uppercase tracking-[.24em] {$theme['sub']}'>Selected client work</span><div class='flex flex-wrap items-center gap-x-8 gap-y-3'>{$logoHtml}</div></div>
                    </div>
                </section>";
                break;


                case 'hero_bento_premium':
                $eyebrow = e($block['eyebrow'] ?? 'BUILT TO STAND APART');
                $heading = e($block['heading'] ?? 'One clear idea, expressed from every angle.');
                $text = e($block['text'] ?? 'Bring your message, proof, imagery, and next step together in a flexible bento composition designed for modern brands.');
                $primaryLabel = e($block['primary_label'] ?? 'Start a project');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'Explore the work');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $imageUrl = e(self::staticAssetUrl($block['image_url'] ?? ''));
                $imageLabel = e($block['image_label'] ?? 'Featured perspective');
                $metricValue = e($block['metric_value'] ?? '3.4x');
                $metricLabel = e($block['metric_label'] ?? 'More engaged visitors');
                $proofTitle = e($block['proof_title'] ?? 'Built around clarity');
                $proofText = e($block['proof_text'] ?? 'A modular opening experience with strong hierarchy and deliberate rhythm.');
                $cardLabels = array_map('e', [$block['card_one_label'] ?? 'Strategy-led', $block['card_two_label'] ?? 'Responsive by design', $block['card_three_label'] ?? 'Ready to publish']);
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $softCard = $isPrimarySection ? 'border-white/20 bg-white/10 text-white' : "{$theme['border']} {$theme['surface']} {$theme['text']}";
                $softSub = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $secondaryButton = $isPrimarySection ? 'border-white/25 text-white' : "{$theme['border']} {$theme['text']}";
                $featureCard = $isPrimarySection ? 'border-white/20 bg-white/10 text-white' : "{$theme['border']} {$theme['bg']} {$theme['text']}";
                $proofCard = $isPrimarySection ? 'border-white/20 bg-slate-950/20 text-white' : "{$theme['border']} {$primaryTheme['soft']} {$theme['text']}";
                $imageStyle = $imageUrl ? "background-image:url('{$imageUrl}');background-size:cover;background-position:center;" : '';
                $cardHtml = ''; foreach ($cardLabels as $label) { $cardHtml .= "<div class='rounded-2xl border px-4 py-4 text-sm font-semibold {$featureCard}'>{$label}</div>"; }

                $html .= "
                <section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'>
                    <div class='relative mx-auto max-w-7xl'>
                        <div class='grid gap-4 lg:grid-cols-12 lg:grid-rows-[auto_auto]'>
                            <div class='rounded-[2rem] border p-7 sm:p-10 lg:col-span-7 lg:row-span-2 {$softCard}'><span class='text-xs font-bold uppercase tracking-[.28em] {$softSub}'>{$eyebrow}</span><h1 class='mt-5 max-w-4xl text-5xl font-semibold leading-[.95] tracking-[-.055em] sm:text-6xl lg:text-7xl'>{$heading}</h1><p class='mt-6 max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 {$softSub}'>{$text}</p><div class='mt-8 flex flex-col gap-3 sm:flex-row'><a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a><a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold {$secondaryButton}'>{$secondaryLabel}</a></div><div class='mt-10 grid gap-3 sm:grid-cols-3'>{$cardHtml}</div></div>
                            <div class='relative min-h-[310px] overflow-hidden rounded-[2rem] lg:col-span-5' style=\"{$imageStyle}\"><div class='absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-950/10 to-transparent'></div><div class='absolute inset-x-0 bottom-0 p-6 text-white sm:p-8'><strong class='text-sm font-semibold'>{$imageLabel}</strong></div></div>
                            <div class='grid gap-4 sm:grid-cols-2 lg:col-span-5'><div class='rounded-[2rem] border p-6 {$softCard}'><strong class='block text-5xl font-semibold tracking-[-.04em]'>{$metricValue}</strong><span class='mt-3 block text-sm leading-6 {$softSub}'>{$metricLabel}</span></div><div class='rounded-[2rem] border p-6 {$proofCard}'><strong class='block text-lg font-semibold'>{$proofTitle}</strong><span class='mt-3 block text-sm leading-6 {$softSub}'>{$proofText}</span></div></div>
                        </div>
                    </div>
                </section>";
                break;

                case 'hero_luxury_fullscreen':

                $eyebrow = e($block['eyebrow'] ?? 'THE ART OF ARRIVAL');
                $heading = e($block['heading'] ?? 'Quiet confidence, made unforgettable.');
                $text = e($block['text'] ?? 'A refined opening statement for brands defined by craft, place, and exceptional attention to detail.');
                $primaryLabel = e($block['primary_label'] ?? 'Discover the collection');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'Our story');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $locationLabel = e($block['location_label'] ?? 'Crafted in exceptional detail');
                $editionLabel = e($block['edition_label'] ?? 'Private Edition 01');
                $imageUrl = e(self::staticAssetUrl($block['image_url'] ?? ''));
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $isLightMediaTheme = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                $overlayHex = self::mediaOverlayColor($resolvedTheme, $primaryColor);
                $overlayOpacity = $isLightMediaTheme ? 0.96 : 0.52;
                $primaryButtonBg = $isLightMediaTheme ? $primaryTheme['bg'] : ($isPrimarySection ? 'bg-white' : $primaryTheme['bg']);
                $primaryButtonText = $isLightMediaTheme ? 'text-white' : ($isPrimarySection ? 'text-slate-950' : 'text-white');
                $borderClass = $isLightMediaTheme ? 'border-slate-900/15' : 'border-white/25';
                $eyebrowClass = $isLightMediaTheme ? 'text-slate-700' : 'text-white/80';
                $editionClass = $isLightMediaTheme ? 'text-slate-600' : 'text-white/70';
                $headingClass = $isLightMediaTheme ? 'text-slate-950' : 'text-white';
                $bodyClass = $isLightMediaTheme ? 'text-slate-700' : 'text-white/75';
                $secondaryClass = $isLightMediaTheme ? 'border-slate-900/20 bg-white/72 text-slate-950' : 'border-white/45 bg-white/5 text-white';
                $locationClass = $isLightMediaTheme ? 'text-slate-700' : 'text-white/75';
                $dividerClass = $isLightMediaTheme ? 'bg-slate-900/25' : 'bg-white/35';
                $imageStyle = $imageUrl ? "background-image:url('{$imageUrl}');background-size:cover;background-position:center;" : '';
                $overlayStyle = "background-color:{$overlayHex};opacity:{$overlayOpacity};";
                $horizontalGradient = $isLightMediaTheme ? 'bg-gradient-to-r from-white/72 via-white/48 to-white/24' : 'bg-gradient-to-r from-slate-950/30 via-transparent to-transparent';
                $verticalGradient = $isLightMediaTheme ? 'bg-gradient-to-t from-white/56 via-white/20 to-white/24' : 'bg-gradient-to-t from-slate-950/34 via-transparent to-slate-950/8';

                $html .= "
                <section class='relative min-h-[82vh] overflow-hidden {$theme['bg']}' style=\"{$imageStyle}\">
                    <div class='absolute inset-0' style=\"{$overlayStyle}\"></div>
                    <div class='absolute inset-0 {$horizontalGradient}'></div>
                    <div class='absolute inset-0 {$verticalGradient}'></div>
                    <div class='relative mx-auto flex min-h-[82vh] max-w-7xl flex-col justify-between px-6 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-12'>
                        <div class='flex items-center justify-between border-b pb-5 {$borderClass}'><span class='text-[11px] font-bold uppercase tracking-[.34em] {$eyebrowClass}'>{$eyebrow}</span><span class='text-xs font-medium {$editionClass}'>{$editionLabel}</span></div>
                        <div class='max-w-5xl py-14 sm:py-20 lg:py-24'>
                            <h1 class='max-w-5xl text-5xl font-medium leading-[.92] tracking-[-.055em] sm:text-7xl lg:text-[7.2rem] {$headingClass}'>{$heading}</h1>
                            <p class='mt-7 max-w-xl text-base leading-7 sm:text-lg sm:leading-8 {$bodyClass}'>{$text}</p>
                            <div class='mt-9 flex flex-col gap-3 sm:flex-row'><a href='{$primaryUrl}' class='inline-flex min-h-[52px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a><a href='{$secondaryUrl}' class='inline-flex min-h-[52px] items-center justify-center rounded-full border px-7 font-bold {$secondaryClass}'>{$secondaryLabel}</a></div>
                        </div>
                        <div class='flex items-end justify-between border-t pt-5 {$borderClass}'><span class='text-xs font-semibold uppercase tracking-[.2em] {$locationClass}'>{$locationLabel}</span><span class='h-10 w-px {$dividerClass}'></span></div>
                    </div>
                </section>";

                break;

                case 'hero_saas_dashboard':

                $eyebrow = e($block['eyebrow'] ?? 'THE OPERATING SYSTEM FOR GROWTH');
                $heading = e($block['heading'] ?? 'Turn your workflow into a clear, measurable advantage.');
                $text = e($block['text'] ?? 'Bring projects, performance, and customer momentum into one focused workspace built for modern teams.');
                $primaryLabel = e($block['primary_label'] ?? 'Start building');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'View product tour');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $dashboardTitle = e($block['dashboard_title'] ?? 'Workspace overview');
                $dashboardSubtitle = e($block['dashboard_subtitle'] ?? 'Live performance across your team');
                $metricOneValue = e($block['metric_one_value'] ?? '42%');
                $metricOneLabel = e($block['metric_one_label'] ?? 'Faster delivery');
                $metricTwoValue = e($block['metric_two_value'] ?? '18.4k');
                $metricTwoLabel = e($block['metric_two_label'] ?? 'Monthly actions');
                $metricThreeValue = e($block['metric_three_value'] ?? '99.9%');
                $metricThreeLabel = e($block['metric_three_label'] ?? 'Platform uptime');
                $chartLabel = e($block['chart_label'] ?? 'Growth this quarter');
                $logos = array_map('e', [$block['logo_one'] ?? 'NORTHSTAR', $block['logo_two'] ?? 'ARC LABS', $block['logo_three'] ?? 'SCALEWORKS', $block['logo_four'] ?? 'FOUNDRY']);
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $metricCards = "<div class='rounded-2xl border p-5 {$theme['border']} {$theme['bg']}'><strong class='block text-3xl font-semibold tracking-tight {$theme['text']}'>{$metricOneValue}</strong><span class='mt-2 block text-xs font-medium {$theme['sub']}'>{$metricOneLabel}</span></div><div class='rounded-2xl border p-5 {$theme['border']} {$theme['bg']}'><strong class='block text-3xl font-semibold tracking-tight {$theme['text']}'>{$metricTwoValue}</strong><span class='mt-2 block text-xs font-medium {$theme['sub']}'>{$metricTwoLabel}</span></div><div class='rounded-2xl border p-5 {$theme['border']} {$theme['bg']}'><strong class='block text-3xl font-semibold tracking-tight {$theme['text']}'>{$metricThreeValue}</strong><span class='mt-2 block text-xs font-medium {$theme['sub']}'>{$metricThreeLabel}</span></div>";
                $bars = '';
                foreach ([38,58,48,72,66,88,78,96,84,100] as $index => $height) { $opacity = 0.42 + ($index * 0.045); $bars .= "<span class='flex-1 rounded-t-lg {$primaryTheme['bg']}' style='height:{$height}%;opacity:{$opacity}'></span>"; }
                $logoHtml = ''; foreach ($logos as $logo) { $logoHtml .= "<span class='text-xs font-bold tracking-[.16em] {$theme['sub']}'>{$logo}</span>"; }

                $html .= "
                <section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'>
                    <div class='relative mx-auto max-w-7xl'>
                        <div class='mx-auto max-w-4xl text-center'>
                            <span class='text-xs font-bold uppercase tracking-[.28em] {$theme['sub']}'>{$eyebrow}</span>
                            <h1 class='mt-5 text-5xl font-semibold leading-[.98] tracking-[-.05em] sm:text-6xl lg:text-7xl {$theme['text']}'>{$heading}</h1>
                            <p class='mx-auto mt-6 max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>{$text}</p>
                            <div class='mt-8 flex flex-col justify-center gap-3 sm:flex-row'><a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a><a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold {$theme['border']} {$theme['text']}'>{$secondaryLabel}</a></div>
                        </div>
                        <div class='mt-14 overflow-hidden rounded-[2rem] border shadow-2xl {$theme['border']}'>
                            <div class='flex items-center justify-between border-b px-5 py-4 sm:px-7 {$theme['border']} {$theme['surface']}'><div><strong class='block text-sm {$theme['text']}'>{$dashboardTitle}</strong><span class='mt-1 block text-xs {$theme['sub']}'>{$dashboardSubtitle}</span></div><div class='flex gap-1.5'><span class='h-2.5 w-2.5 rounded-full bg-rose-400'></span><span class='h-2.5 w-2.5 rounded-full bg-amber-400'></span><span class='h-2.5 w-2.5 rounded-full bg-emerald-400'></span></div></div>
                            <div class='grid lg:grid-cols-[240px_minmax(0,1fr)] {$theme['surface']}'><aside class='hidden border-r p-5 lg:block {$theme['border']}'><div class='rounded-xl px-3 py-2 text-xs font-semibold {$primaryTheme['soft']} {$theme['text']}'>Overview</div><div class='mt-2 rounded-xl px-3 py-2 text-xs {$theme['sub']}'>Projects</div><div class='mt-2 rounded-xl px-3 py-2 text-xs {$theme['sub']}'>Analytics</div><div class='mt-2 rounded-xl px-3 py-2 text-xs {$theme['sub']}'>Customers</div><div class='mt-2 rounded-xl px-3 py-2 text-xs {$theme['sub']}'>Automations</div></aside><div class='p-5 sm:p-7'><div class='grid gap-4 md:grid-cols-3'>{$metricCards}</div><div class='mt-4 rounded-2xl border p-5 {$theme['border']} {$theme['bg']}'><strong class='block text-sm {$theme['text']}'>{$chartLabel}</strong><div class='mt-7 flex h-40 items-end gap-2 sm:gap-3'>{$bars}</div></div></div></div>
                        </div>
                        <div class='mt-8 border-t pt-7 text-center {$theme['border']}'><p class='text-[11px] font-bold uppercase tracking-[.26em] {$theme['sub']}'>Trusted by teams building what comes next</p><div class='mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4'>{$logoHtml}</div></div>
                    </div>
                </section>";

                break;

                case 'hero_floating_glass':

                $eyebrow = e($block['eyebrow'] ?? 'BUILT FOR MOMENTUM');
                $heading = e($block['heading'] ?? 'A clearer way to move your business forward.');
                $text = e($block['text'] ?? 'Bring your offer, proof, and next step together in one immersive opening experience.');
                $primaryLabel = e($block['primary_label'] ?? 'Start a project');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'See how it works');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $glassTitle = e($block['glass_title'] ?? 'Made for decisive teams');
                $glassText = e($block['glass_text'] ?? 'A focused digital experience designed to turn attention into action.');
                $metricValue = e($block['metric_value'] ?? '3.2x');
                $metricLabel = e($block['metric_label'] ?? 'Faster path to launch');
                $badgeOne = e($block['badge_one'] ?? 'Strategy-led');
                $badgeTwo = e($block['badge_two'] ?? 'Conversion-ready');
                $imageUrl = e(self::staticAssetUrl($block['image_url'] ?? ''));
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $imageStyle = $imageUrl ? "background-image:url('{$imageUrl}');background-size:cover;background-position:center;" : '';

                $html .= "
                <section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'>
                    <div class='relative mx-auto max-w-7xl'>
                        <div class='grid items-center gap-10 lg:grid-cols-[minmax(0,.9fr)_minmax(460px,1.1fr)] lg:gap-14'>
                            <div class='relative z-20'>
                                <span class='text-xs font-bold uppercase tracking-[.28em] {$theme['sub']}'>{$eyebrow}</span>
                                <h1 class='mt-5 max-w-3xl text-5xl font-semibold leading-[.98] tracking-[-.05em] sm:text-6xl lg:text-7xl {$theme['text']}'>{$heading}</h1>
                                <p class='mt-6 max-w-xl text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>{$text}</p>
                                <div class='mt-8 flex flex-col gap-3 sm:flex-row'>
                                    <a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a>
                                    <a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold {$theme['border']} {$theme['text']}'>{$secondaryLabel}</a>
                                </div>
                                <div class='mt-8 flex flex-wrap gap-2'><span class='rounded-full border px-4 py-2 text-xs font-semibold {$theme['border']} {$theme['sub']}'>{$badgeOne}</span><span class='rounded-full border px-4 py-2 text-xs font-semibold {$theme['border']} {$theme['sub']}'>{$badgeTwo}</span></div>
                            </div>
                            <div class='relative min-h-[480px] sm:min-h-[560px]'>
                                <div class='absolute inset-4 overflow-hidden rounded-[2.25rem] border shadow-2xl sm:inset-8 {$theme['border']}' style=\"{$imageStyle}\"><div class='absolute inset-0 bg-gradient-to-br from-slate-950/10 via-transparent to-slate-950/45'></div></div>
                                <div class='absolute left-0 top-10 max-w-[280px] rounded-[1.6rem] border border-white/50 bg-white/65 p-5 text-slate-900 shadow-2xl backdrop-blur-xl sm:left-2 sm:top-14'><strong class='block text-lg'>{$glassTitle}</strong><p class='mt-2 text-sm leading-6 text-slate-600'>{$glassText}</p></div>
                                <div class='absolute bottom-5 right-0 min-w-[190px] rounded-[1.6rem] border border-white/50 bg-slate-950/60 p-5 text-white shadow-2xl backdrop-blur-xl sm:bottom-8 sm:right-2'><strong class='block text-4xl font-semibold tracking-tight text-white'>{$metricValue}</strong><span class='mt-2 block text-xs font-medium text-white/75'>{$metricLabel}</span></div>
                                <div class='absolute right-3 top-1/2 h-24 w-24 -translate-y-1/2 rounded-full opacity-85 sm:right-0 {$primaryTheme['bg']}'></div>
                            </div>
                        </div>
                    </div>
                </section>";

                break;

                case 'hero_split_editorial':

                $eyebrow = e($block['eyebrow'] ?? 'A NEW STANDARD');
                $editorialIndex = e($block['editorial_index'] ?? '01');
                $heading = e($block['heading'] ?? 'Designed to make the right first impression.');
                $text = e($block['text'] ?? 'A considered digital experience that brings your story, expertise, and next step into one confident opening statement.');
                $primaryLabel = e($block['primary_label'] ?? 'Start a conversation');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'Explore our work');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $proofValue = e($block['proof_value'] ?? '15+');
                $proofLabel = e($block['proof_label'] ?? 'Years of considered craft');
                $imageCaption = e($block['image_caption'] ?? 'Built with clarity, confidence, and care.');
                $imageUrl = e(self::staticAssetUrl($block['image_url'] ?? ''));
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $imageStyle = $imageUrl
                    ? "background-image:url('{$imageUrl}');background-size:cover;background-position:center;"
                    : '';

                $html .= "
                <section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'>
                    <div class='relative mx-auto max-w-7xl'>
                        <div class='mb-10 flex items-center justify-between border-b pb-5 {$theme['border']}'>
                            <span class='text-[11px] font-bold uppercase tracking-[0.34em] {$theme['sub']}'>{$eyebrow}</span>
                            <span class='text-xs {$theme['sub']}'>{$editorialIndex}</span>
                        </div>
                        <div class='grid items-end gap-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(360px,0.95fr)] lg:gap-16'>
                            <div class='relative z-10 lg:pb-8'>
                                <h1 class='max-w-4xl text-5xl font-semibold leading-[0.96] tracking-[-0.055em] sm:text-6xl lg:text-[5.5rem] {$theme['text']}'>{$heading}</h1>
                                <div class='mt-8 grid gap-7 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end'>
                                    <div>
                                        <p class='max-w-xl text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>{$text}</p>
                                        <div class='mt-7 flex flex-col gap-3 sm:flex-row'>
                                            <a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a>
                                            <a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold {$theme['border']} {$theme['text']}'>{$secondaryLabel}</a>
                                        </div>
                                    </div>
                                    <div class='min-w-40 border-l pl-5 {$theme['border']}'>
                                        <strong class='block text-4xl font-semibold tracking-tight {$theme['text']}'>{$proofValue}</strong>
                                        <span class='mt-2 block max-w-36 text-xs font-medium leading-5 {$theme['sub']}'>{$proofLabel}</span>
                                    </div>
                                </div>
                            </div>
                            <div class='relative'>
                                <div class='relative aspect-[4/5] overflow-hidden rounded-[2rem] border shadow-2xl sm:aspect-[5/4] lg:aspect-[4/5] {$theme['border']}' style=\"{$imageStyle}\">
                                    <div class='absolute inset-0 bg-gradient-to-t from-slate-950/55 via-transparent to-transparent'></div>
                                    <span class='absolute bottom-5 left-5 right-5 max-w-sm text-sm font-medium leading-6 text-white'>{$imageCaption}</span>
                                </div>
                                <div class='absolute -bottom-5 -left-5 hidden h-24 w-24 rounded-full border sm:block {$theme['border']} {$theme['bg']}'></div>
                                <div class='absolute -bottom-2 -left-2 hidden h-16 w-16 rounded-full sm:block {$primaryTheme['bg']}'></div>
                            </div>
                        </div>
                    </div>
                </section>";

                break;

                case 'image_cta_banner':

                $eyebrow = e($block['eyebrow'] ?? 'READY WHEN YOU ARE');
                $heading = e($block['heading'] ?? 'Let’s make your next step simple.');
                $text = e($block['text'] ?? 'Talk with our team and get a clear plan for moving forward.');
                $primaryLabel = e($block['primary_label'] ?? 'Get started');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'Learn more');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $backgroundImage = e(self::staticAssetUrl($block['image_url'] ?? ''));
                $overlayOpacity = max(0, min(100, intval($block['overlayOpacity'] ?? 76)));
                $primaryTheme = self::getTheme($primaryColor);
                // Builder receives resolvedTheme from the section wrapper, while stored/published
                // block data commonly only contains theme. Recreate the same resolved value here.
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'primary');
                $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                $backgroundStyle = $backgroundImage
                    ? "background-image:url('{$backgroundImage}');background-size:cover;background-position:center;"
                    : '';

                $overlayHex = self::mediaOverlayColor($resolvedTheme, $primaryColor);
                $overlayClass = $lightMedia ? 'bg-white' : '';
                $effectiveOverlayOpacity = $lightMedia ? max(96, $overlayOpacity) : max(32, min(56, (int) round($overlayOpacity * 0.72)));
                $gradientClass = $lightMedia
                    ? 'from-white/100 via-white/96 to-white/82'
                    : 'from-slate-950/48 via-slate-950/18 to-slate-950/10';
                $eyebrowClass = $lightMedia ? 'text-slate-700' : 'text-white/75';
                $headingClass = $lightMedia ? 'text-slate-950' : 'text-white';
                $bodyClass = $lightMedia ? 'text-slate-700' : 'text-white/85';
                $primaryButtonClass = $lightMedia
                    ? "{$primaryTheme['bg']} text-white"
                    : 'bg-white text-slate-950';
                $secondaryButtonClass = $lightMedia
                    ? 'border-slate-900/20 bg-white/78 text-slate-950 hover:bg-white/95'
                    : 'border-white/45 bg-white/5 text-white hover:bg-white/10';

                $html .= "
                <section class='relative flex min-h-[420px] overflow-hidden sm:min-h-[460px] lg:min-h-[500px]' style=\"{$backgroundStyle}\">
                    <div class='absolute inset-0 {$overlayClass}' style='background-color:{$overlayHex};opacity:" . ($effectiveOverlayOpacity / 100) . ";'></div>
                    <div class='absolute inset-0 bg-gradient-to-r {$gradientClass}'></div>
                    <div class='relative z-10 mx-auto flex w-full max-w-7xl items-center justify-center px-7 py-16 text-center sm:px-10 sm:py-20'>
                        <div class='max-w-3xl'>
                            <span class='block text-xs font-semibold uppercase tracking-[0.3em] {$eyebrowClass}'>{$eyebrow}</span>
                            <h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$headingClass}'>{$heading}</h2>
                            <div class='mx-auto mt-5 max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 {$bodyClass}'>{$text}</div>
                            <div class='mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:items-center'>
                                <a href='{$primaryUrl}' class='inline-flex min-h-[48px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonClass}'>{$primaryLabel}</a>
                                <a href='{$secondaryUrl}' class='inline-flex min-h-[48px] items-center justify-center rounded-full border px-7 font-bold transition {$secondaryButtonClass}'>{$secondaryLabel}</a>
                            </div>
                        </div>
                    </div>
                </section>";

                break;


                case 'pricing_cards':

                $tagline = e($block['tagline'] ?? 'SIMPLE PRICING');
                $heading = e($block['heading'] ?? 'Choose The Perfect Plan');
                $text = e($block['text'] ?? 'Flexible pricing options designed for individuals, growing businesses, and enterprise teams.');

                // Button Logic
                $isLight = in_array($selectedThemeName, ['white', 'stone']);

                $btnBg = $isLight
                    ? self::getTheme($primaryColor)['bg']
                    : 'bg-white';

                $btnText = $isLight
                    ? self::getTheme($primaryColor)['text']
                    : 'text-slate-900';

                $primaryTheme = self::getTheme($primaryColor);

                $html .= "
                <section class='relative px-6 py-20 sm:px-8 lg:py-24 {$theme['bg']} transition-colors duration-500'>

                    <div class='max-w-7xl mx-auto'>

                        <div class='text-center max-w-3xl mx-auto mb-12 sm:mb-14'>

                            <span class='block text-xs font-semibold uppercase tracking-[0.35em] {$theme['sub']}'>
                                {$tagline}
                            </span>

                            <h2 class='block mt-5 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>
                                {$heading}
                            </h2>

                            <div class='mt-6 text-lg leading-8 {$theme['sub']}'>
                                {$text}
                            </div>

                        </div>

                        <div class='grid gap-6 md:grid-cols-3 lg:gap-7'>
                ";

                foreach (($block['plans'] ?? []) as $plan) {

                    $featured = !empty($plan['featured']);

                    $html .= "
                        <div class='relative rounded-3xl border {$theme['border']} {$theme['card']} p-7 lg:p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl " .
                        ($featured ? "scale-105 ring-2 ring-white/40" : "") .
                        "'>";

                    if (!empty($plan['badge'])) {

                        $html .= "
                            <div class='absolute -top-3 left-1/2 z-10 -translate-x-1/2'>
                                <span class='inline-flex whitespace-nowrap rounded-full px-3 py-1.5 {$primaryTheme['bg']} {$primaryTheme['text']} text-[10px] font-semibold uppercase tracking-[0.16em] shadow-sm'>
                                    " . e($plan['badge']) . "
                                </span>
                            </div>";
                    }

                    $html .= "

                            <h3 class='text-2xl font-bold {$theme['text']}'>
                                " . e($plan['title']) . "
                            </h3>

                            <div class='mt-5 flex items-end gap-2'>

                                <span class='text-4xl font-bold sm:text-5xl {$theme['text']}'>
                                    " . e($plan['price']) . "
                                </span>

                                <span class='mb-2 {$theme['sub']}'>
                                    " . e($plan['period']) . "
                                </span>

                            </div>

                            <div class='mt-5 leading-7 {$theme['sub']}'>
                                " . e($plan['description']) . "
                            </div>

                            <div class='mt-7 space-y-3'>
                    ";

                    foreach (($plan['features'] ?? []) as $feature) {

                        $featureText = is_array($feature)
                            ? ($feature['text'] ?? '')
                            : $feature;

                        $html .= "
                            <div class='flex items-center gap-3'>

                                <svg class='w-5 h-5 {$theme['text']}' fill='none' stroke='currentColor' stroke-width='2.5' viewBox='0 0 24 24'>
                                    <path stroke-linecap='round' stroke-linejoin='round' d='M5 13l4 4L19 7'/>
                                </svg>

                                <span class='{$theme['text']}'>
                                    " . e($featureText) . "
                                </span>

                            </div>";
                    }

                    $html .= "
                            </div>

                            <div class='mt-8'>

                                <a
                                    href='" . e($plan['button_url'] ?? '#') . "'
                                    class='w-full inline-flex items-center justify-center min-h-[52px] px-8 rounded-full font-bold transition {$btnBg} {$btnText}'
                                >
                                    " . e($plan['button_label'] ?? 'Get Started') . "
                                </a>

                            </div>

                        </div>";
                }

                $html .= "
                        </div>

                    </div>

                </section>";

                break;


                case 'hero_floating_cards':

                $tagline = e(
                    $block['tagline'] ??
                    'BUILT AROUND YOUR NEXT STEP'
                );

                $heading = e(
                    $block['heading'] ??
                    'A better way to move your business forward.'
                );

                $text = e(
                    $block['text'] ??
                    'Present your strongest message, highlight what makes your business different, and help visitors take action with confidence.'
                );

                $primaryLabel = e(
                    $block['primary_label'] ??
                    'Get started'
                );

                $primaryUrl = e(
                    $block['primary_url'] ??
                    '#'
                );

                $secondaryLabel = e(
                    $block['secondary_label'] ??
                    'Explore services'
                );

                $secondaryUrl = e(
                    $block['secondary_url'] ??
                    '#'
                );

                $imageUrl = e(
                    self::staticAssetUrl(
                        $block['image_url'] ??
                        'https://picsum.photos/1000/800'
                    )
                );

                $imageBadge = e(
                    $block['image_badge'] ??
                    'Professional service you can rely on'
                );

                $cardOneValue = e(
                    $block['card_one_value'] ??
                    '15+'
                );

                $cardOneLabel = e(
                    $block['card_one_label'] ??
                    'Years of experience'
                );

                $cardTwoTitle = e(
                    $block['card_two_title'] ??
                    'Trusted expertise'
                );

                $cardTwoText = e(
                    $block['card_two_text'] ??
                    'Thoughtful service, clear communication, and dependable results.'
                );

                $isLight = in_array(
                    $selectedThemeName,
                    ['white', 'stone'],
                    true
                );

                $primaryTheme = self::getTheme($primaryColor);

                $primaryButtonBg = $isLight
                    ? $primaryTheme['bg']
                    : 'bg-white';

                $primaryButtonText = $isLight
                    ? $primaryTheme['text']
                    : 'text-slate-950';

                $html .= "
                <section class='relative overflow-hidden px-7 py-16 sm:px-10 sm:py-20 lg:px-12 lg:py-24 {$theme['bg']} transition-colors duration-500'>

                    <div class='pointer-events-none absolute -left-40 top-10 h-96 w-96 rounded-full {$primaryTheme['bg']} opacity-[0.08] blur-[130px]'></div>

                    <div class='pointer-events-none absolute -right-44 bottom-0 h-96 w-96 rounded-full {$primaryTheme['bg']} opacity-[0.06] blur-[140px]'></div>

                    <div class='relative mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-20'>

                        <div class='max-w-2xl'>

                            <span class='block text-xs font-semibold uppercase tracking-[0.3em] {$theme['sub']}'>
                                {$tagline}
                            </span>

                            <h1 class='mt-5 block text-5xl font-bold leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl {$theme['text']}'>
                                {$heading}
                            </h1>

                            <p class='mt-6 block max-w-xl text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>
                                {$text}
                            </p>

                            <div class='mt-8 flex flex-col gap-3 sm:flex-row sm:items-center'>

                                <a
                                    href='{$primaryUrl}'
                                    class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold transition hover:opacity-90 {$primaryButtonBg} {$primaryButtonText}'
                                >
                                    {$primaryLabel}
                                </a>

                                <a
                                    href='{$secondaryUrl}'
                                    class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold transition hover:opacity-80 {$theme['border']} {$theme['text']}'
                                >
                                    {$secondaryLabel}
                                </a>

                            </div>

                        </div>

                        <div class='relative mx-auto w-full max-w-2xl pb-16 pt-4 sm:px-8 lg:pb-10'>

                            <div class='relative overflow-hidden rounded-[2rem] border shadow-2xl {$theme['border']}'>

                                <img
                                    src='{$imageUrl}'
                                    alt='{$heading}'
                                    width='960' height='720' loading='lazy' decoding='async' class='aspect-[4/3] w-full object-cover'
                                >

                                <div class='absolute inset-0 bg-gradient-to-t from-slate-950/40 via-transparent to-transparent'></div>

                                <span class='absolute bottom-5 left-5 max-w-[calc(100%-2.5rem)] rounded-full bg-slate-950/80 px-4 py-2 text-xs font-semibold text-white backdrop-blur'>
                                    {$imageBadge}
                                </span>

                            </div>

                            <div class='absolute -bottom-1 left-0 w-[170px] rounded-2xl border p-4 shadow-xl backdrop-blur sm:left-1 sm:w-[190px] {$theme['card']} {$theme['border']}'>

                                <div class='block text-3xl font-bold tracking-tight {$theme['text']}'>
                                    {$cardOneValue}
                                </div>

                                <div class='mt-1 block text-xs font-semibold leading-5 {$theme['sub']}'>
                                    {$cardOneLabel}
                                </div>

                            </div>

                            <div class='absolute -right-1 top-0 w-[205px] rounded-2xl border p-4 shadow-xl backdrop-blur sm:right-0 sm:w-[225px] {$theme['card']} {$theme['border']}'>

                                <div class='mb-3 flex h-9 w-9 items-center justify-center rounded-xl {$primaryTheme['bg']} {$primaryTheme['text']}'>
                                    ✓
                                </div>

                                <h3 class='block text-sm font-bold {$theme['text']}'>
                                    {$cardTwoTitle}
                                </h3>

                                <p class='mt-1.5 block text-xs leading-5 {$theme['sub']}'>
                                    {$cardTwoText}
                                </p>

                            </div>

                        </div>

                    </div>

                </section>";

                break;


                case 'hero_video_style':

                $tagline = e(
                    $block['tagline'] ??
                    'SEE WHAT SETS US APART'
                );

                $heading = e(
                    $block['heading'] ??
                    'A clear vision for what comes next.'
                );

                $text = e(
                    $block['text'] ??
                    'Introduce your business with a strong message, a compelling visual, and a simple path for visitors to learn more.'
                );

                $primaryLabel = e(
                    $block['primary_label'] ??
                    'Get started'
                );

                $primaryUrl = e(
                    $block['primary_url'] ??
                    '#'
                );

                $videoLabel = e(
                    $block['video_label'] ??
                    'Watch our story'
                );

                $videoUrl = e(
                    $block['video_url'] ??
                    '#'
                );

                $playLabel = e(
                    $block['play_label'] ??
                    'Play video'
                );

                $imageBadge = e(
                    $block['image_badge'] ??
                    'Discover our approach'
                );

                $rawImageUrl = trim((string) ($block['image_url'] ?? ''));
                $imageUrl = e(
                    self::staticAssetUrl(
                        $rawImageUrl !== ''
                            ? $rawImageUrl
                            : 'https://picsum.photos/1200/675'
                    )
                );

                $rawVideoUrl = trim((string) ($block['video_url'] ?? ''));
                $videoEmbedUrl = $rawVideoUrl !== '' && $rawVideoUrl !== '#'
                    ? self::backgroundVideoEmbedUrl($rawVideoUrl)
                    : null;
                $staticVideoUrl = e(self::staticAssetUrl($rawVideoUrl));

                // Published pages do not include Builder editing dialogs. A valid
                // source plays directly inside the visual; otherwise its image is
                // retained as a safe fallback.
                $videoMedia = $videoEmbedUrl
                    ? "<div class='relative w-full' style='aspect-ratio: 16 / 9;'>
                            <img src='{$imageUrl}' alt='{$heading}' width='1280' height='720' loading='lazy' decoding='async' class='absolute inset-0 h-full w-full object-cover sm:hidden'>
                            <iframe
                                src='" . e($videoEmbedUrl) . "'
                                title='Video preview'
                                allow='autoplay; fullscreen; picture-in-picture'
                                class='pointer-events-none absolute inset-0 hidden h-full w-full border-0 sm:block'
                            ></iframe>
                        </div>"
                    : ($rawVideoUrl !== '' && $rawVideoUrl !== '#'
                        ? "<div class='relative w-full' style='aspect-ratio: 16 / 9;'>
                                <img src='{$imageUrl}' alt='{$heading}' width='1280' height='720' loading='lazy' decoding='async' class='absolute inset-0 h-full w-full object-cover sm:hidden'>
                                <video autoplay muted loop playsinline preload='metadata' poster='{$imageUrl}' class='hidden h-full w-full object-cover sm:block'>
                                    <source src='{$staticVideoUrl}' type='video/mp4'>
                                </video>
                            </div>"
                        : "<img src='{$imageUrl}' alt='{$heading}' width='1280' height='720' loading='lazy' decoding='async' class='w-full object-cover transition duration-500 group-hover:scale-[1.03]' style='aspect-ratio: 16 / 9;'>");

                $isLight = in_array(
                    $selectedThemeName,
                    ['white', 'stone'],
                    true
                );

                $primaryTheme = self::getTheme(
                    $primaryColor
                );

                $primaryButtonBg = $isLight
                    ? $primaryTheme['bg']
                    : 'bg-white';

                $primaryButtonText = $isLight
                    ? $primaryTheme['text']
                    : 'text-slate-950';

                $html .= "
                <section class='relative overflow-hidden px-7 py-16 sm:px-10 sm:py-20 lg:px-12 lg:py-24 {$theme['bg']} transition-colors duration-500'>

                    <div class='pointer-events-none absolute -left-36 top-10 h-96 w-96 rounded-full {$primaryTheme['bg']} opacity-[0.08] blur-[130px]'></div>

                    <div class='pointer-events-none absolute -right-36 bottom-0 h-96 w-96 rounded-full {$primaryTheme['bg']} opacity-[0.06] blur-[140px]'></div>

                    <div class='relative mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-20'>

                        <div class='max-w-2xl'>

                            <span class='block text-xs font-semibold uppercase tracking-[0.3em] {$theme['sub']}'>
                                {$tagline}
                            </span>

                            <h1 class='mt-5 block text-5xl font-bold leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl {$theme['text']}'>
                                {$heading}
                            </h1>

                            <p class='mt-6 block max-w-xl text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>
                                {$text}
                            </p>

                            <div class='mt-8 flex flex-col gap-3 sm:flex-row sm:items-center'>

                                <a
                                    href='{$primaryUrl}'
                                    class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold transition hover:opacity-90 {$primaryButtonBg} {$primaryButtonText}'
                                >
                                    {$primaryLabel}
                                </a>

                                <a
                                    href='{$videoUrl}'
                                    target='_blank'
                                    rel='noreferrer'
                                    class='inline-flex min-h-[50px] items-center justify-center gap-3 rounded-full border px-7 font-bold transition hover:opacity-80 {$theme['border']} {$theme['text']}'
                                >
                                    <span aria-hidden='true'>▶</span>
                                    {$videoLabel}
                                </a>

                            </div>

                            <div class='mt-8 flex items-center gap-3 border-t pt-5 {$theme['border']}'>

                                <div class='flex h-9 w-9 shrink-0 items-center justify-center rounded-full {$primaryTheme['bg']} {$primaryTheme['text']}'>
                                    ▶
                                </div>

                                <div class='text-sm font-semibold {$theme['sub']}'>
                                    {$playLabel}
                                </div>

                            </div>

                        </div>

                        <div class='relative mx-auto w-full max-w-2xl pb-10 sm:px-6 lg:pb-0'>

                            <div class='group relative overflow-hidden rounded-[2rem] border shadow-2xl {$theme['border']}'>

                                {$videoMedia}

                                <div class='pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/70 via-slate-950/10 to-slate-950/10'></div>

                                <a
                                    href='{$videoUrl}'
                                    target='_blank'
                                    rel='noreferrer'
                                    aria-label='{$playLabel}'
                                    class='absolute inset-0 flex items-center justify-center'
                                >
                                    <span class='flex h-20 w-20 items-center justify-center rounded-full border-4 border-white/30 bg-white text-2xl text-slate-950 shadow-2xl transition duration-300 group-hover:scale-110 sm:h-24 sm:w-24'>
                                        ▶
                                    </span>
                                </a>

                                <div class='pointer-events-none absolute bottom-5 left-5 right-5 flex items-end justify-between gap-4'>

                                    <div class='block max-w-[70%] text-sm font-semibold text-white sm:text-base'>
                                        {$imageBadge}
                                    </div>

                                    <span class='rounded-full border border-white/20 bg-slate-950/60 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-white backdrop-blur'>
                                        Video
                                    </span>

                                </div>

                            </div>

                            <div class='absolute -bottom-3 right-0 rounded-2xl border px-5 py-4 shadow-xl backdrop-blur sm:right-2 {$theme['card']} {$theme['border']}'>

                                <div class='flex items-center gap-3'>

                                    <div class='flex h-9 w-9 items-center justify-center rounded-full {$primaryTheme['bg']} {$primaryTheme['text']}'>
                                        ▶
                                    </div>

                                    <div>

                                        <div class='block text-sm font-bold {$theme['text']}'>
                                            {$videoLabel}
                                        </div>

                                        <div class='mt-0.5 block text-xs {$theme['sub']}'>
                                            {$playLabel}
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>";

                break;


                case 'hero_video_background':

                $tagline = e(
                    $block['tagline'] ??
                    'STEP INTO THE EXPERIENCE'
                );

                $heading = e(
                    $block['heading'] ??
                    'Make every first impression unforgettable.'
                );

                $text = e(
                    $block['text'] ??
                    'Introduce your business through motion, strong storytelling, and a clear next step for every visitor.'
                );

                $primaryLabel = e(
                    $block['primary_label'] ??
                    'Get started'
                );

                $primaryUrl = e(
                    $block['primary_url'] ??
                    '#'
                );

                $secondaryLabel = e(
                    $block['secondary_label'] ??
                    'Explore more'
                );

                $secondaryUrl = e(
                    $block['secondary_url'] ??
                    '#'
                );

                $rawVideoUrl = trim((string) ($block['video_url'] ?? '')) ?: '/storage/cms-videos/hero-placeholder.mp4';
                $backgroundVideoEmbedUrl = self::backgroundVideoEmbedUrl($rawVideoUrl);
                $videoUrl = e(self::staticAssetUrl($rawVideoUrl));

                $posterImageUrl = e(
                    self::staticAssetUrl(
                        trim((string) ($block['poster_image_url'] ?? '')) ?: '/storage/cms-images/background/background-1.avif'
                    )
                );

                $videoBadge = e(
                    $block['video_badge'] ??
                    'Discover what makes us different'
                );

                $scrollLabel = e(
                    $block['scroll_label'] ??
                    'Explore'
                );

                $primaryTheme = self::getTheme(
                    $primaryColor
                );
                $resolvedTheme = (string) ($block['resolvedTheme'] ?? $blockTheme ?? $selectedThemeName ?? 'surface');
                $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                $videoOverlayHex = self::mediaOverlayColor($resolvedTheme, $primaryColor);
                $videoOverlayBase = $lightMedia ? 'bg-white/90' : '';
                $videoOverlayStyle = $lightMedia ? '' : "background-color:{$videoOverlayHex};opacity:0.50;";
                $videoGradientX = $lightMedia ? 'from-white/100 via-white/96 to-white/82' : 'from-slate-950/48 via-slate-950/20 to-transparent';
                $videoGradientY = $lightMedia ? 'from-white/94 via-white/36 to-white/76' : 'from-slate-950/40 via-transparent to-slate-950/10';
                $videoTagline = $lightMedia ? 'text-slate-700' : 'text-white/70';
                $videoHeading = $lightMedia ? 'text-slate-950' : 'text-white';
                $videoBody = $lightMedia ? 'text-slate-700' : 'text-white/75';
                $videoSecondary = $lightMedia ? 'border-slate-900/20 bg-white/78 text-slate-950' : 'border-white/30 bg-white/10 text-white';
                $videoPill = $lightMedia ? 'border-slate-900/15 bg-white/55 text-slate-900' : 'border-white/15 bg-slate-950/35 text-white';
                $videoScroll = $lightMedia ? 'text-slate-700' : 'text-white/70';
                $videoScrollBorder = $lightMedia ? 'border-slate-900/30' : 'border-white/30';
                $videoScrollDot = $lightMedia ? 'bg-slate-900' : 'bg-white';
                $videoMediaCard = $lightMedia ? 'border-slate-900/15 bg-white/45' : 'border-white/20 bg-slate-950/35';

                $backgroundMedia = $backgroundVideoEmbedUrl
                    ? "<div class='absolute inset-0 overflow-hidden'>
                            <img src='{$posterImageUrl}' alt='' aria-hidden='true' class='absolute inset-0 h-full w-full object-cover sm:hidden'>
                            <iframe
                                src='" . e($backgroundVideoEmbedUrl) . "'
                                title='Background video'
                                allow='autoplay; fullscreen; picture-in-picture'
                                class='pointer-events-none absolute left-1/2 top-1/2 hidden h-[56.25vw] min-h-full w-[177.78vh] min-w-full -translate-x-1/2 -translate-y-1/2 border-0 sm:block'
                            ></iframe>
                        </div>"
                    : "<div class='absolute inset-0'>
                            <img src='{$posterImageUrl}' alt='' aria-hidden='true' class='absolute inset-0 h-full w-full object-cover sm:hidden'>
                            <video autoplay muted loop playsinline preload='metadata' poster='{$posterImageUrl}' class='hidden h-full w-full object-cover sm:block'>
                                <source src='{$videoUrl}' type='video/mp4'>
                            </video>
                        </div>";

                $html .= "
                <section class='relative isolate min-h-[680px] overflow-hidden {$theme['bg']}'>

                    <div class='absolute inset-0'>

                        {$backgroundMedia}

                        <div class='absolute inset-0 {$videoOverlayBase}' style='{$videoOverlayStyle}'></div>

                        <div class='absolute inset-0 bg-gradient-to-r {$videoGradientX}'></div>

                        <div class='absolute inset-0 bg-gradient-to-t {$videoGradientY}'></div>

                    </div>

                    <div class='pointer-events-none absolute -left-40 top-16 h-96 w-96 rounded-full {$primaryTheme['bg']} opacity-[0.18] blur-[150px]'></div>

                    <div class='relative z-10 mx-auto flex min-h-[680px] max-w-7xl items-center px-7 py-24 sm:px-10 lg:px-12'>

                        <div class='max-w-3xl'>

                            <span class='block text-xs font-semibold uppercase tracking-[0.34em] {$videoTagline}'>
                                {$tagline}
                            </span>

                            <h1 class='mt-6 block text-5xl font-bold leading-[0.98] tracking-tight sm:text-6xl lg:text-8xl {$videoHeading}'>
                                {$heading}
                            </h1>

                            <p class='mt-7 block max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 {$videoBody}'>
                                {$text}
                            </p>

                            <div class='mt-9 flex flex-col gap-3 sm:flex-row sm:items-center'>

                                <a
                                    href='{$primaryUrl}'
                                    class='inline-flex min-h-[52px] items-center justify-center rounded-full px-8 font-bold shadow-xl transition hover:-translate-y-0.5 hover:opacity-90 {$primaryTheme['bg']} {$primaryTheme['text']}'
                                >
                                    {$primaryLabel}
                                </a>

                                <a
                                    href='{$secondaryUrl}'
                                    class='inline-flex min-h-[52px] items-center justify-center rounded-full border px-8 font-bold backdrop-blur transition {$videoSecondary}'
                                >
                                    {$secondaryLabel}
                                </a>

                            </div>

                            <div class='mt-10 flex items-center gap-3'>

                                <div class='flex items-center gap-3 rounded-full border px-4 py-2.5 backdrop-blur {$videoPill}'>

                                    <span class='flex h-8 w-8 items-center justify-center rounded-full {$primaryTheme['bg']} {$primaryTheme['text']}'>
                                        ▶
                                    </span>

                                    <span class='text-sm font-semibold text-white'>
                                        {$videoBadge}
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                    <div class='absolute bottom-0 left-0 right-0 z-10'>

                        <div class='mx-auto flex max-w-7xl items-end justify-between gap-6 px-7 pb-7 sm:px-10 lg:px-12'>

                            <div class='flex items-center gap-3 {$videoScroll}'>

                                <span class='flex h-9 w-6 items-start justify-center rounded-full border p-1.5 {$videoScrollBorder}'>
                                    <span class='h-1.5 w-1.5 rounded-full {$videoScrollDot}'></span>
                                </span>

                                <span class='text-xs font-semibold uppercase tracking-[0.24em]'>
                                    {$scrollLabel}
                                </span>

                            </div>

                            <div class='hidden w-48 overflow-hidden rounded-2xl border shadow-2xl backdrop-blur sm:block {$videoMediaCard}'>

                                <img
                                    src='{$posterImageUrl}'
                                    alt='{$heading}'
                                    width='640' height='360' loading='lazy' decoding='async' class='aspect-video w-full object-cover opacity-80'
                                >

                            </div>

                        </div>

                    </div>

                </section>";

                break;
            }

            $fragment = substr($html, $fragmentStart);
            if ($fragment !== '' && preg_match('/<section\b/i', $fragment)) {
                $semanticTheme = e((string) $blockTheme);
                $semanticType = e((string) ($block['type'] ?? ''));
                $taggedFragment = preg_replace(
                    '/<section(?![^>]*data-cosmic-resolved-theme)/i',
                    "<section data-cosmic-resolved-theme='{$semanticTheme}' data-cosmic-block-type='{$semanticType}'",
                    $fragment,
                    1
                );

                if (is_string($taggedFragment)) {
                    $html = substr($html, 0, $fragmentStart) . $taggedFragment;
                }
            }
        }
        $html = preg_replace('/<section(?![^>]*data-cosmic-spark)/i', '<section data-cosmic-spark', $html) ?? $html;

        return self::normalizePublishedAssetUrls($html);
    }

    private static function isDefaultLogoPlaceholder(string $url): bool
    {
        $normalized = strtolower(str_replace('\\', '/', trim($url)));
        if ($normalized === '') {
            return false;
        }

        $path = parse_url($normalized, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = $normalized;
        }

        return '/' . ltrim($path, '/') === '/storage/branding/your-logo.png';
    }

    private static function logoFilter(string $theme): string
    {
        return match ($theme) {
            'emerald' => 'brightness(0) saturate(100%) invert(29%) sepia(64%) saturate(610%) hue-rotate(126deg) brightness(94%) contrast(96%)',
            'coffee' => 'brightness(0) saturate(100%) invert(20%) sepia(44%) saturate(1248%) hue-rotate(356deg) brightness(92%) contrast(95%)',
            'rose' => 'brightness(0) saturate(100%) invert(33%) sepia(36%) saturate(1248%) hue-rotate(313deg) brightness(93%) contrast(94%)',
            'ocean' => 'brightness(0) saturate(100%) invert(32%) sepia(44%) saturate(1064%) hue-rotate(174deg) brightness(93%) contrast(94%)',
            'indigo' => 'brightness(0) saturate(100%) invert(34%) sepia(34%) saturate(1335%) hue-rotate(228deg) brightness(92%) contrast(95%)',
            'amber' => 'brightness(0) saturate(100%) invert(39%) sepia(88%) saturate(715%) hue-rotate(8deg) brightness(95%) contrast(96%)',
            'violet' => 'brightness(0) saturate(100%) invert(28%) sepia(55%) saturate(1150%) hue-rotate(244deg) brightness(94%) contrast(94%)',
            'teal' => 'brightness(0) saturate(100%) invert(30%) sepia(55%) saturate(705%) hue-rotate(145deg) brightness(93%) contrast(95%)',
            'ruby' => 'brightness(0) saturate(100%) invert(24%) sepia(72%) saturate(1450%) hue-rotate(327deg) brightness(93%) contrast(94%)',
            'forest' => 'brightness(0) saturate(100%) invert(28%) sepia(33%) saturate(664%) hue-rotate(95deg) brightness(93%) contrast(95%)',
            'navy' => 'brightness(0) saturate(100%) invert(26%) sepia(38%) saturate(1118%) hue-rotate(177deg) brightness(95%) contrast(92%)',
            'slate' => 'brightness(0) saturate(100%) invert(36%) sepia(11%) saturate(831%) hue-rotate(175deg) brightness(94%) contrast(92%)',
            default => 'brightness(0) saturate(100%) invert(20%) sepia(14%) saturate(1108%) hue-rotate(176deg) brightness(94%) contrast(91%)',
        };
    }

}
