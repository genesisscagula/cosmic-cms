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
        $theme['gradient'] ??= ($themes['midnight']['gradient'] ?? [
            'from' => '#071426', 'via' => '#111936', 'to' => '#28164D',
            'glow' => '#7C3AED', 'glowSoft' => 'rgba(124, 58, 237, 0.20)',
            'glowStrong' => 'rgba(124, 58, 237, 0.38)', 'angle' => 120,
        ]);

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
        $productUrl = static fn ($p) => e((string) (($p['storefront_url'] ?? '') ?: (($p['slug'] ?? '') ? '/product/'.ltrim((string) $p['slug'], '/') : '/shop')));

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
            $collectionUrl = e((string) (($category['storefront_url'] ?? '') ?: (($category['slug'] ?? '') ? '/shop/category/'.ltrim((string) $category['slug'], '/') : '/shop')));
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
            $benefitCount = max(1, min(4, (int) ($block['benefit_count'] ?? 4)));
            for ($i = 1; $i <= $benefitCount; $i++) {
                $benefitTitle = e((string) ($block["benefit_{$i}_title"] ?? ''));
                if ($benefitTitle === '') continue;
                $benefitText = e((string) ($block["benefit_{$i}_text"] ?? ''));
                $benefitMarkup .= "<div class='flex gap-3'><div class='mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-black' style='background:color-mix(in srgb,var(--commerce-accent) 12%,transparent);color:var(--commerce-accent)'>✓</div><div><h3 class='text-sm font-bold'>{$benefitTitle}</h3><p class='mt-1 text-xs leading-5' style='color:var(--commerce-muted)'>{$benefitText}</p></div></div>";
            }
            $benefitsHeading = e((string) ($block['heading'] ?? 'Shop with confidence'));
            $body = "<div class='rounded-[26px] border px-6 py-7 sm:px-8' style='{$cardStyle}'><h2 class='mb-6 text-xl font-semibold tracking-[-0.03em]'>{$benefitsHeading}</h2><div class='grid gap-5 sm:grid-cols-2 lg:grid-cols-4'>{$benefitMarkup}</div></div>";
        } elseif ($type === 'commerce_mini_cart') {
            $cartUrl = e((string) (($commerce['runtime_urls']['cart'] ?? '') ?: '/cart'));
            $miniHeading = e((string) ($block['heading'] ?? 'Your cart'));
            $emptyText = e((string) ($block['empty_text'] ?? 'Your cart is ready for products.'));
            $buttonLabel = e((string) ($block['button_label'] ?? 'View cart'));
            $body = "<div class='ml-auto max-w-md rounded-3xl border p-6 shadow-xl' style='{$cardStyle}'><div class='flex items-center justify-between'><h2 class='text-xl font-bold'>{$miniHeading}</h2><span class='rounded-full px-2.5 py-1 text-xs font-bold' style='background:var(--commerce-accent);color:white'>Live cart</span></div><p class='py-10 text-center text-sm' style='color:var(--commerce-muted)'>{$emptyText}</p><a href='{$cartUrl}' class='mt-4 block w-full rounded-xl px-4 py-3 text-center font-bold' style='background:var(--commerce-accent);color:white'>{$buttonLabel}</a></div>";
        } elseif (in_array($type, ['commerce_cart_classic', 'commerce_cart_split', 'commerce_cart_compact'], true)) {
            $shopUrl = e((string) (($commerce['runtime_urls']['shop'] ?? '') ?: '/shop'));
            $checkoutUrl = e((string) (($commerce['runtime_urls']['checkout'] ?? '') ?: '/checkout'));
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
            $countryRows = array_values(array_filter((array) ($commerce['countries'] ?? []), fn ($row) => is_array($row) && !empty($row['code']) && !empty($row['name'])));
            if ($countryRows === []) $countryRows = collect(config('cosmic-commerce.countries', []))->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values()->all();
            $countryOptions = "<option value=''>Select country / region</option>";
            foreach ($countryRows as $row) $countryOptions .= "<option value='".e((string) $row['code'])."'>".e((string) $row['name'])."</option>";
            $countryField = "<label><span class='mb-1.5 block text-[11px] font-bold uppercase tracking-[0.13em]' style='color:var(--commerce-muted)'>Country</span><select data-commerce-preview-country class='min-h-11 w-full rounded-xl border px-3.5 text-sm outline-none' style='border-color:var(--commerce-border);background:var(--commerce-surface);color:var(--commerce-text)'>{$countryOptions}</select></label>";
            $compact = $type === 'commerce_checkout_express';
            $customer = "<div class='rounded-[26px] border ".($compact?'p-4 sm:p-5':'p-5 sm:p-6')."' style='{$cardStyle}'><div class='flex items-center justify-between gap-4'><h3 class='text-base font-bold'>Customer details</h3><span class='text-[10px] font-bold uppercase tracking-[0.14em]' style='color:var(--commerce-accent)'>Runtime bound</span></div><div class='mt-5 grid gap-3 sm:grid-cols-2'>".$field('First name','Alex').$field('Last name','Morgan').$field('Email','alex@example.com',true).$countryField.$field('Region','State / province / region').(!$compact?$field('Street address','123 Commerce Street',true):'')."</div><p data-commerce-preview-destination class='mt-3 text-[11px] leading-5' style='color:var(--commerce-muted)'>Choose a country to preview the destination control. Live checkout recalculates shipping and tax.</p></div>";
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
                $body .= "<a href='".e((string) (($c['storefront_url'] ?? '') ?: (($c['slug'] ?? '') ? '/shop/category/'.ltrim((string) $c['slug'], '/') : '/shop')))."' class='block rounded-2xl border p-6' style='{$cardStyle}'><div class='text-lg font-bold'>".e((string) ($c['name'] ?? ''))."</div><p class='mt-2 text-sm' style='color:var(--commerce-muted)'>".e((string) ($c['description'] ?? 'Explore this collection'))."</p></a>";
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

        if (in_array($type, ['commerce_checkout_classic', 'commerce_checkout_split', 'commerce_checkout_express'], true)) {
            $body .= "<script>(function(){var r=document.getElementById('{$rootId}');if(!r)return;var s=r.querySelector('[data-commerce-preview-country]'),o=r.querySelector('[data-commerce-preview-destination]');if(!s||!o)return;var u=function(){var n=s.options[s.selectedIndex];o.textContent=s.value?'Preview destination: '+(n?n.text:s.value)+'. Live checkout recalculates shipping and tax.':'Choose a country to preview the destination control. Live checkout recalculates shipping and tax.'};s.addEventListener('change',u);u();})();</script>";
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
        if(type==='commerce_catalog_editorial') return `<a ${attrs(p)} href="${esc(p.storefront_url||'/shop')}" class="group overflow-hidden rounded-[26px] border ${i%5===0?'lg:col-span-7':'lg:col-span-5'}" style="${cardStyle}"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="${i%5===0?'aspect-[16/10]':'aspect-[4/3]'} w-full object-cover"><div class="p-6 sm:p-7"><p class="text-[10px] font-bold uppercase tracking-[0.18em]" style="color:var(--commerce-muted)">${esc(catName(p))}</p><div class="mt-2 flex items-end justify-between gap-5"><h3 class="text-xl font-semibold tracking-[-0.03em]">${esc(p.title)}</h3><p class="shrink-0 text-base font-bold">${esc(price(p,data))}</p></div></div></a>`;
        if(type==='commerce_catalog_compact') return `<a ${attrs(p)} href="${esc(p.storefront_url||'/shop')}" class="grid grid-cols-[72px_1fr_auto] items-center gap-4 p-3.5 transition hover:bg-black/5"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="h-[72px] w-[72px] rounded-xl object-cover"><div class="min-w-0"><h3 class="truncate text-sm font-semibold">${esc(p.title)}</h3><p class="mt-1 truncate text-xs" style="color:var(--commerce-muted)">${esc(catName(p))}</p></div><div class="text-right"><p class="text-sm font-bold">${esc(price(p,data))}</p>${p.track_inventory?`<p class="mt-1 text-[11px]" style="color:var(--commerce-muted)">${Number(p.stock_quantity||0)>0?esc(p.stock_quantity)+' in stock':(p.allow_backorders?'Backorder':'Out of stock')}</p>`:''}</div></a>`;
        return `<a ${attrs(p)} href="${esc(p.storefront_url||'/shop')}" class="group overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style="${cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="aspect-[4/5] w-full object-cover"><div class="p-5"><h3 class="text-[16px] font-semibold tracking-[-0.02em]">${esc(p.title)}</h3><div class="mt-3 flex items-center justify-between gap-3"><p class="text-[15px] font-bold">${esc(price(p,data))}</p>${p.is_featured?`<span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide" style="background:color-mix(in srgb,var(--commerce-accent) 12%,transparent);color:var(--commerce-accent)">Featured</span>`:''}</div></div></a>`;
      };
      body.innerHTML=list.slice(0,limit).map(renderCard).join('');
      const search=root.querySelector('[data-catalog-search]'), category=root.querySelector('[data-catalog-category]'), sort=root.querySelector('[data-catalog-sort]'), empty=root.querySelector('[data-catalog-empty]');
      const apply=()=>{let rows=[...body.querySelectorAll('[data-catalog-product]')], q=String(search?.value||'').trim().toLowerCase(), cat=String(category?.value||''); rows.forEach(el=>el.hidden=!!((q&&!String(el.dataset.title||'').includes(q))||(cat&&!String(el.dataset.categoryIds||'').split(',').includes(cat)))); const visible=rows.filter(el=>!el.hidden); const mode=sort?.value||'featured'; visible.sort((a,b)=>mode==='price_asc'?Number(a.dataset.price)-Number(b.dataset.price):mode==='price_desc'?Number(b.dataset.price)-Number(a.dataset.price):mode==='name'?String(a.dataset.title).localeCompare(String(b.dataset.title)):mode==='newest'?Number(b.dataset.id)-Number(a.dataset.id):Number(b.dataset.featured)-Number(a.dataset.featured)); visible.forEach(el=>body.appendChild(el)); if(empty) empty.classList.toggle('hidden',visible.length>0);};
      search?.addEventListener('input',apply); category?.addEventListener('change',apply); sort?.addEventListener('change',apply); apply();
    } else if(type==='commerce_product_grid'){
      body.innerHTML=list.filter(p=>!block.featured_only||p.is_featured).slice(0,limit).map(p=>`<a href="${esc(p.storefront_url||'/shop')}" class="group block overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style="${cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="aspect-square w-full object-cover"><div class="p-5"><h3 class="text-[16px] font-semibold tracking-[-0.02em]">${esc(p.title)}</h3><div class="mt-3 flex items-center justify-between gap-3"><p class="text-[15px] font-bold">${esc(price(p,data))}</p>${p.track_inventory?`<span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background:color-mix(in srgb,var(--commerce-accent) 12%,transparent);color:var(--commerce-accent)">${Number(p.stock_quantity||0)>0?esc(p.stock_quantity)+' in stock':(p.allow_backorders?'Available on backorder':'Out of stock')}</span>`:''}</div></div></a>`).join('');
    } else if(type==='commerce_featured_products'){
      const featured=list.filter(p=>p.is_featured); const items=(featured.length?featured:list).slice(0,limit);
      body.innerHTML=items.map(p=>`<a href="${esc(p.storefront_url||'/shop')}" class="group block overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style="${cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="aspect-[4/5] w-full object-cover"><div class="p-5"><h3 class="text-[16px] font-semibold tracking-[-0.02em]">${esc(p.title)}</h3><p class="mt-2 text-[15px] font-bold">${esc(price(p,data))}</p></div></a>`).join('');
    } else if(type==='commerce_featured_collection'){
      const cats=data.categories||[], cat=cats.find(c=>Number(c.id)===Number(block.category_id))||cats[0]||null;
      body.innerHTML=cat?list.filter(p=>(p.category_ids||[]).map(Number).includes(Number(cat.id))).slice(0,limit).map(p=>`<a href="${esc(p.storefront_url||'/shop')}" class="group block overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style="${cardStyle};box-shadow:0 12px 35px rgba(2,6,23,.10)"><img src="${esc(image(p))}" alt="${esc(p.featured_image_alt||p.title||'')}" class="aspect-[4/5] w-full object-cover"><div class="p-5"><h3 class="text-[16px] font-semibold tracking-[-0.02em]">${esc(p.title)}</h3><p class="mt-2 text-[15px] font-bold">${esc(price(p,data))}</p></div></a>`).join(''):'';
    } else if(type==='commerce_categories'){
      body.innerHTML=(data.categories||[]).map(c=>`<a href="${esc(c.storefront_url||'/shop')}" class="block rounded-2xl border p-6" style="${cardStyle}"><div class="text-lg font-bold">${esc(c.name)}</div><p class="mt-2 text-sm" style="color:var(--commerce-muted)">${esc(c.description||'Explore this collection')}</p></a>`).join('');
    } else if(type==='commerce_price'){
      body.innerHTML=product?`<div class="rounded-2xl border p-6" style="${cardStyle}"><p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--commerce-muted)">${esc(block.label||'Price')}</p><div class="mt-2 text-4xl font-black">${esc(price(product,data))}</div></div>`:'No published product';
    } else if(type==='commerce_product_gallery'){
      if(!product){body.innerHTML='No published product';return;} const imgs=[image(product),...(product.gallery||[]).map(g=>g.url)].filter((v,i,a)=>v&&a.indexOf(v)===i); body.innerHTML=`<img src="${esc(imgs[0]||'')}" alt="${esc(product.title||'')}" class="aspect-square w-full rounded-2xl object-cover"><div class="grid grid-cols-2 gap-3">${imgs.slice(1,5).map(u=>`<img src="${esc(u)}" alt="" class="aspect-square w-full rounded-xl object-cover">`).join('')}</div>`;
    } else if(type==='commerce_variation_selector'){
      body.innerHTML=product?(product.options||[]).map(o=>`<div><p class="mb-2 text-sm font-semibold">${esc(o.name)}</p><div class="flex flex-wrap gap-2">${(o.values||[]).map(v=>`<span class="rounded-xl border px-4 py-2 text-sm font-semibold" style="border-color:var(--commerce-border);background:var(--commerce-surface)">${esc(v.label)}</span>`).join('')}</div></div>`).join(''):'No published product';
    } else if(type==='commerce_related_products'){
      if(!product){body.innerHTML='';return;} const cats=new Set((product.category_ids||[]).map(Number)); body.innerHTML=list.filter(p=>Number(p.id)!==Number(product.id)&&(!cats.size||(p.category_ids||[]).some(id=>cats.has(Number(id))))).slice(0,limit).map(p=>`<a href="${esc(p.storefront_url||'/shop')}" class="block overflow-hidden rounded-2xl border" style="${cardStyle}"><img src="${esc(image(p))}" alt="" class="aspect-square w-full object-cover"><div class="p-4"><b>${esc(p.title)}</b><p class="mt-1 text-sm">${esc(price(p,data))}</p></div></a>`).join('');
    }
    root.dataset.commerceLive='1';
  };
  fetch(cfg.endpoint,{headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():Promise.reject(r.status)).then(data=>{window.CosmicCommerceRuntime=window.CosmicCommerceRuntime||{};window.CosmicCommerceRuntime[cfg.endpoint]=data;render(data);}).catch(()=>{root.dataset.commerceLive='0';});
})();
JS;
            $runtimeJs = str_replace('__ROOT_ID__', json_encode($rootId), $runtimeJs);
            $script = '<script>'.$runtimeJs.'</script>';
        }

        $outerHead = in_array($type, ['commerce_promo_split', 'commerce_benefits_strip', 'commerce_mini_cart'], true) ? '' : "<div class='mb-8 max-w-2xl'><h2 class='text-3xl font-semibold tracking-[-0.035em] md:text-4xl'>{$heading}</h2>".($text!==''?"<p class='mt-3 max-w-xl text-[15px] leading-7' style='color:var(--commerce-muted)'>{$text}</p>":'')."</div>";
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

            // Clean owns the complete light section rhythm. Every full Hero is
            // explicitly forced to WHITE so a hero inserted later in the page can
            // never inherit a primary/surface slot from the alternating pattern.
            // Non-hero Sparks keep the normal Clean rhythm.
            $blockType = (string) ($block['type'] ?? '');
            $isFullHero = str_starts_with($blockType, 'hero_');
            if (self::$currentPageStyle === 'clean' && $isFullHero) {
                $blockTheme = 'white';
            } elseif (self::$currentPageStyle === 'clean' || $blockTheme === "auto") {
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
                case 'commerce_mini_cart':
                case 'commerce_cart_classic':
                case 'commerce_cart_split':
                case 'commerce_cart_compact':
                case 'commerce_checkout_classic':
                case 'commerce_checkout_split':
                case 'commerce_checkout_express':
                    $html .= self::commerceSparkHtml($type, $block, $context, $theme);
                    break;

                case 'newsletter_cta':
                $themePrimary = self::getTheme($primaryColor);
                $newsletterTheme = $theme;
                $newsletterResolved = (string) ($blockTheme ?? $block['resolvedTheme'] ?? 'primary');
                $newsletterIsPrimary = $newsletterResolved === 'primary';
                $newsletterButtonBg = $newsletterIsPrimary ? $newsletterTheme['card'] : $themePrimary['bg'];
                $newsletterButtonText = $newsletterIsPrimary ? $newsletterTheme['text'] : $themePrimary['text'];
                $eyebrow = e($block['eyebrow'] ?? 'Stay in the loop');
                $heading = e($block['heading'] ?? 'Get weekly insights');
                $text = e($block['text'] ?? 'Practical ideas, useful updates, and new resources delivered occasionally.');
                $placeholder = e($block['placeholder'] ?? 'Your email address');
                $buttonLabel = e($block['button_label'] ?? 'Subscribe');
                $disclaimer = e($block['disclaimer'] ?? 'No spam. Unsubscribe anytime.');
                $newsletterVariant = (string) ($block['layout_variant'] ?? 'newsletter-01');
                $newsletterOuter = $newsletterVariant === 'newsletter-02'
                    ? 'text-center'
                    : ($newsletterVariant === 'newsletter-03' ? 'grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-center' : 'lg:flex lg:items-center lg:justify-between lg:gap-12');
                $newsletterCopy = $newsletterVariant === 'newsletter-02' ? 'mx-auto max-w-2xl' : 'max-w-2xl';
                $newsletterForm = $newsletterVariant === 'newsletter-02' ? 'mx-auto mt-8' : ($newsletterVariant === 'newsletter-03' ? '' : 'mt-8 lg:mt-0');
                $newsletterFields = $newsletterVariant === 'newsletter-03' ? 'flex-col' : 'flex-col sm:flex-row';
                $html .= "<section class='px-6 py-14 sm:px-8 lg:px-12 lg:py-20 {$newsletterTheme['bg']}'><div class='mx-auto max-w-7xl'><div class='rounded-3xl border px-6 py-10 shadow-[0_24px_70px_rgba(15,23,42,0.16)] sm:px-10 lg:px-14 lg:py-12 {$newsletterTheme['card']} {$newsletterTheme['text']} {$newsletterTheme['border']} {$newsletterOuter}'><div class='{$newsletterCopy}'><p class='text-xs font-semibold uppercase tracking-[0.28em] {$newsletterTheme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-3xl font-bold leading-[1.05] tracking-tight sm:text-4xl'>{$heading}</h2><p class='mt-4 max-w-xl text-base leading-7 {$newsletterTheme['sub']} ".($newsletterVariant === 'newsletter-02'?'mx-auto':'')."'>{$text}</p></div><form class='{$newsletterForm} w-full max-w-md' onsubmit='return false'><div class='flex gap-3 {$newsletterFields}'><input type='email' aria-label='Email address' placeholder='{$placeholder}' class='min-h-[50px] flex-1 rounded-xl border px-4 text-sm outline-none {$newsletterTheme['border']} {$newsletterTheme['bg']} {$newsletterTheme['text']}'><button type='submit' class='min-h-[50px] rounded-xl px-6 text-sm font-bold {$newsletterButtonBg} {$newsletterButtonText}'>{$buttonLabel}</button></div><p class='mt-3 text-xs {$newsletterTheme['sub']}'>{$disclaimer}</p></form></div></div></section>";
                break;

                case 'latest_resources':
                $resourceThemeName = ($block['theme'] ?? 'white') === 'primary' ? $primaryColor : (($block['theme'] ?? 'white') === 'surface' ? 'stone' : 'white');
                $resourceTheme = self::getTheme($resourceThemeName);
                $eyebrow = e($block['eyebrow'] ?? 'Keep exploring');
                $heading = e($block['heading'] ?? 'Latest resources');
                $text = e($block['text'] ?? 'Helpful next reads for visitors who want to learn more.');
                $resources = is_array($block['resources'] ?? null) ? array_slice($block['resources'], 0, 6) : [];
                if (empty($resources)) {
                    $resources = [
                        ['eyebrow' => 'Guide', 'title' => 'A practical checklist for your next step', 'text' => 'A concise starting point for making a clearer, more confident decision.', 'cta_label' => 'Read the guide', 'cta_url' => '#'],
                        ['eyebrow' => 'Resource', 'title' => 'Questions worth asking before you begin', 'text' => 'Use this focused resource to prepare for a better conversation with your team.', 'cta_label' => 'Explore resource', 'cta_url' => '#'],
                    ];
                }
                $resourcesVariant = (string) ($block['layout_variant'] ?? 'resources-01');
                $normalCards = '';
                $compactCards = '';
                foreach ($resources as $index => $resource) {
                    if (!is_array($resource)) continue;
                    $rEyebrow=e($resource['eyebrow'] ?? 'Resource');$rTitle=e($resource['title'] ?? '');$rText=e($resource['text'] ?? '');$rUrl=e($resource['cta_url'] ?? '#');$rLabel=e($resource['cta_label'] ?? 'Read more');$num=str_pad((string)($index+1),2,'0',STR_PAD_LEFT);
                    $inner="<p class='text-[11px] font-semibold uppercase tracking-[0.22em] {$resourceTheme['sub']}'>{$rEyebrow}</p><h3 class='mt-4 text-2xl font-bold leading-tight tracking-tight {$resourceTheme['text']}'>{$rTitle}</h3><p class='mt-4 text-sm leading-6 {$resourceTheme['sub']}'>{$rText}</p><a href='{$rUrl}' class='mt-7 inline-flex text-sm font-semibold underline underline-offset-4 {$resourceTheme['text']}'>{$rLabel}</a>";
                    $normalCards.="<article class='group rounded-2xl border p-7 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg sm:p-8 {$resourceTheme['border']} {$resourceTheme['card']}'>{$inner}</article>";
                    $compactCards.="<article class='group grid gap-4 rounded-2xl border p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg sm:grid-cols-[130px_1fr] {$resourceTheme['border']} {$resourceTheme['card']}'><div class='flex min-h-28 items-center justify-center rounded-xl text-3xl font-black {$resourceTheme['bg']} {$resourceTheme['sub']}'>{$num}</div><div>{$inner}</div></article>";
                }
                $copy="<div class='max-w-3xl'><p class='text-xs font-semibold uppercase tracking-[0.28em] {$resourceTheme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$resourceTheme['text']}'>{$heading}</h2><p class='mt-5 max-w-2xl text-base leading-7 {$resourceTheme['sub']} ".($resourcesVariant==='resources-02'?'mx-auto':'')."'>{$text}</p></div>";
                if ($resourcesVariant === 'resources-03') {
                    $content="<div class='grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-start'>{$copy}<div class='grid gap-4'>{$compactCards}</div></div>";
                } elseif ($resourcesVariant === 'resources-02') {
                    $content="<div class='mx-auto max-w-3xl text-center'>{$copy}</div><div class='mx-auto mt-10 grid max-w-4xl gap-5'>{$compactCards}</div>";
                } else {
                    $content="{$copy}<div class='mt-10 grid gap-5 md:grid-cols-2'>{$normalCards}</div>";
                }
                $html .= "<section class='px-6 py-16 sm:px-8 lg:px-12 lg:py-24 {$resourceTheme['bg']} {$resourceTheme['text']}'><div class='mx-auto max-w-7xl'>{$content}</div></section>";
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
                $blogMiniVariant = (string) ($block['layout_variant'] ?? 'mini-header-01');
                if ($blogMiniVariant === 'mini-header-02') {
                    $miniInner = "<div class='mx-auto max-w-4xl text-center'><div><p class='text-xs font-semibold uppercase tracking-[0.28em] {$theme['sub']}'>{$eyebrow}</p><h1 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h1></div><p class='mx-auto mt-5 max-w-2xl text-base leading-7 sm:text-lg {$theme['sub']}'>{$text}</p></div>";
                } elseif ($blogMiniVariant === 'mini-header-03') {
                    $miniInner = "<div class='grid items-end gap-8 lg:grid-cols-[1.15fr_.85fr]'><div><p class='text-xs font-semibold uppercase tracking-[0.28em] {$theme['sub']}'>{$eyebrow}</p><h1 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h1></div><p class='mt-5 max-w-2xl border-l pl-8 text-base leading-7 sm:text-lg {$theme['sub']} {$theme['border']}'>{$text}</p></div>";
                } else {
                    $miniInner = "<div class='max-w-3xl'><div><p class='text-xs font-semibold uppercase tracking-[0.28em] {$theme['sub']}'>{$eyebrow}</p><h1 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h1></div><p class='mt-5 max-w-2xl text-base leading-7 sm:text-lg {$theme['sub']}'>{$text}</p></div>";
                }
                $html .= "<section class='relative overflow-hidden border-b px-6 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-24 {$theme['bg']} {$theme['border']}'><div class='pointer-events-none absolute -right-24 -top-28 h-72 w-72 rounded-full {$theme['card']} opacity-10 blur-3xl'></div><div class='relative mx-auto max-w-7xl'>{$miniInner}</div></section>";
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
                $posts = is_array($block['posts'] ?? null) ? array_slice($block['posts'], 0, 4) : [];
                if (empty($posts)) {
                    $posts = [
                        ['category' => 'Strategy', 'title' => 'Start with the problem worth solving', 'excerpt' => 'A simple framework for making your first website decisions clearer.', 'image_url' => '/storage/cms-images/background/background-2.avif'],
                        ['category' => 'Design', 'title' => 'Consistency earns customer trust', 'excerpt' => 'A focused visual system helps every page feel more credible.', 'image_url' => '/storage/cms-images/background/background-3.avif'],
                        ['category' => 'Updates', 'title' => 'What a publish-ready website needs', 'excerpt' => 'The details that help you go from draft to a confident launch.', 'image_url' => '/storage/cms-images/background/background-5.avif'],
                        ['category' => 'Growth', 'title' => 'Make your next update easier to manage', 'excerpt' => 'Keep content and customer questions organized.', 'image_url' => '/storage/cms-images/background/background-1.avif'],
                    ];
                }
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

                $blogHubVariant = (string) ($block['layout_variant'] ?? 'blog-cards-01');
                $featuredGridClass = $blogHubVariant === 'blog-cards-02' ? 'md:grid-cols-[.8fr_1.2fr]' : ($blogHubVariant === 'blog-cards-03' ? 'md:grid-cols-1' : 'md:grid-cols-2');
                $featuredImageClass = $blogHubVariant === 'blog-cards-03' ? 'h-[240px] sm:h-[340px] lg:h-[420px]' : 'min-h-[260px] h-full';
                $postsGridClass = $blogHubVariant === 'blog-cards-02' ? 'lg:grid-cols-2' : ($blogHubVariant === 'blog-cards-03' ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2 lg:grid-cols-4');
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
                $html .= "<section class='px-6 py-16 sm:px-8 lg:px-12 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'>{$introMarkup}<article class='{$featuredSpacing} grid overflow-hidden rounded-3xl border {$theme['border']} {$theme['card']} {$featuredGridClass}'><img src='{$featuredImage}' alt='{$featuredTitle}' width='960' height='640' loading='lazy' decoding='async' class='{$featuredImageClass} w-full object-cover'><div class='flex min-h-[260px] flex-col justify-center p-7 sm:p-10'><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$featuredCategory}</p><h3 class='mt-4 text-3xl font-bold tracking-tight {$theme['text']}'>{$featuredTitle}</h3><p class='mt-4 text-base leading-7 {$theme['sub']}'>{$featuredExcerpt}</p><a href='{$featuredUrl}' class='mt-7 text-sm font-semibold {$theme['text']} hover:underline'>{$featuredCta}</a></div></article><div class='mt-7 grid gap-5 {$postsGridClass}'>{$postMarkup}</div></div></section>";
                break;

                case 'blog_magazine_premium':
                case 'blog_featured_article_premium':
                case 'blog_editors_pick_premium':
                case 'blog_sidebar_news_premium':
                case 'blog_newsletter_premium':
                case 'blog_trending_premium':
                case 'blog_categories_grid_premium':
                case 'blog_author_profile_premium':
                    $variant = $type;
                    $eyebrow = e($block['eyebrow'] ?? 'INSIGHTS');
                    $heading = e($block['heading'] ?? 'Ideas worth reading.');
                    $text = e($block['text'] ?? 'A premium editorial section for useful articles, stories, and updates.');
                    $header = "<div class='max-w-3xl'><p class='text-xs font-bold uppercase tracking-[.28em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-semibold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-5 text-base leading-7 {$theme['sub']}'>{$text}</p></div>";
                    if ($variant === 'blog_magazine_premium') {
                        $image = e(self::staticAssetUrl($block['feature_image_url'] ?? '/storage/cms-images/background/background-1.avif'));
                        $title = e($block['feature_title'] ?? 'A better way to think about the next chapter');
                        $excerpt = e($block['feature_excerpt'] ?? 'Lead with the story that deserves the most attention.');
                        $cards = '';
                        foreach (['one','two','three'] as $word) {
                            $st = e($block["story_{$word}_title"] ?? 'Editorial story');
                            $sm = e($block["story_{$word}_meta"] ?? 'Article');
                            $cards .= "<article class='rounded-2xl border p-6 {$theme['card']} {$theme['border']}'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$sm}</p><h3 class='mt-3 text-xl font-semibold {$theme['text']}'>{$st}</h3></article>";
                        }
                        $url = e($block['primary_url'] ?? '#'); $label = e($block['primary_label'] ?? 'Read article');
                        $body = "<div class='mt-10 grid gap-5 lg:grid-cols-12'><article class='overflow-hidden rounded-3xl border lg:col-span-7 {$theme['card']} {$theme['border']}'><img src='{$image}' alt='{$title}' class='h-72 w-full object-cover'><div class='p-7'><h3 class='text-3xl font-semibold {$theme['text']}'>{$title}</h3><p class='mt-3 {$theme['sub']}'>{$excerpt}</p><a href='{$url}' class='mt-5 inline-block font-bold {$theme['text']}'>{$label}</a></div></article><div class='grid gap-5 lg:col-span-5'>{$cards}</div></div>";
                    } elseif ($variant === 'blog_featured_article_premium') {
                        $image = e(self::staticAssetUrl($block['feature_image_url'] ?? '/storage/cms-images/background/background-2.avif'));
                        $category = e($block['feature_category'] ?? 'Perspective');
                        $title = e($block['feature_title'] ?? 'Build the thing people actually need');
                        $excerpt = e($block['feature_excerpt'] ?? 'Use a strong excerpt that explains why this article matters right now.');
                        $author = e($block['author_line'] ?? 'By your editorial team');
                        $url = e($block['primary_url'] ?? '#'); $label = e($block['primary_label'] ?? 'Read article');
                        $header = '';
                        $body = "<div class='grid gap-8 lg:grid-cols-2 lg:items-center'><img src='{$image}' alt='{$title}' class='min-h-[430px] h-full w-full rounded-3xl object-cover'><div><p class='text-xs font-bold uppercase tracking-[.28em] {$theme['sub']}'>{$eyebrow}</p><p class='mt-6 text-sm font-semibold {$theme['sub']}'>{$category}</p><h3 class='mt-3 text-4xl font-semibold sm:text-5xl {$theme['text']}'>{$title}</h3><p class='mt-5 leading-7 {$theme['sub']}'>{$excerpt}</p><p class='mt-5 text-sm {$theme['sub']}'>{$author}</p><a href='{$url}' class='mt-7 inline-block font-bold {$theme['text']}'>{$label}</a></div></div>";
                    } elseif ($variant === 'blog_editors_pick_premium') {
                        $image = e(self::staticAssetUrl($block['pick_image_url'] ?? '/storage/cms-images/background/background-3.avif'));
                        $title = e($block['pick_title'] ?? 'The one idea we keep coming back to');
                        $excerpt = e($block['pick_excerpt'] ?? 'Curate one strong recommendation, then support it with a small reading list.');
                        $visibleCount=max(1,min(3,(int)($block['visible_count']??3))); $list=''; foreach(array_slice(['one','two','three'],0,$visibleCount) as $i=>$word){$item=e($block["item_{$word}"]??'Recommended read');$n=$i+1;$list.="<div class='border-b py-5 {$theme['border']}'><span class='text-xs font-black {$theme['sub']}'>0{$n}</span><p class='mt-2 text-xl font-semibold {$theme['text']}'>{$item}</p></div>";}
                        $body = "<div class='mt-10 grid gap-5 lg:grid-cols-[1.2fr_.8fr]'><article class='overflow-hidden rounded-3xl border {$theme['card']} {$theme['border']}'><img src='{$image}' alt='{$title}' class='h-72 w-full object-cover'><div class='p-7'><h3 class='text-3xl font-semibold {$theme['text']}'>{$title}</h3><p class='mt-3 {$theme['sub']}'>{$excerpt}</p></div></article><aside class='rounded-3xl border p-6 {$theme['card']} {$theme['border']}'>{$list}</aside></div>";
                    } elseif ($variant === 'blog_newsletter_premium') {
                        $note=e($block['newsletter_note']??'Share what subscribers can expect.'); $url=e($block['primary_url']??'#'); $label=e($block['primary_label']??'Subscribe'); $chips=''; foreach(['one','two','three'] as $w){$v=e($block["topic_{$w}"]??'Topic');$chips.="<span class='rounded-full border px-3 py-2 text-xs font-bold {$theme['border']} {$theme['text']}'>{$v}</span>";}
                        $body="<div class='mx-auto grid max-w-6xl gap-8 rounded-3xl border p-7 sm:p-10 lg:grid-cols-[1fr_.8fr] lg:items-end {$theme['card']} {$theme['border']}'><div>{$header}</div><div><div class='flex flex-wrap gap-2'>{$chips}</div><p class='mt-5 text-sm {$theme['sub']}'>{$note}</p><div class='mt-5 flex gap-3'><div class='min-w-0 flex-1 rounded-xl border px-4 py-3 text-sm {$theme['border']} {$theme['sub']}'>Email address</div><a href='{$url}' class='rounded-xl border px-4 py-3 font-bold {$theme['border']} {$theme['text']}'>{$label}</a></div></div></div>"; $header='';
                    } elseif ($variant === 'blog_trending_premium') {
                        $cards=''; foreach(array_slice(['one','two','three','four'],0,max(1,min(4,(int)($block['visible_count']??4)))) as $i=>$w){$t=e($block["item_{$w}_title"]??'Featured story');$m=e($block["item_{$w}_meta"]??'Article');$n=$i+1;$cards.="<article class='rounded-2xl border p-6 {$theme['card']} {$theme['border']}'><span class='text-4xl font-black opacity-25 {$theme['text']}'>0{$n}</span><p class='mt-4 text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$m}</p><h3 class='mt-2 text-xl font-semibold {$theme['text']}'>{$t}</h3></article>";} $body="<div class='mt-10 grid gap-4 md:grid-cols-2'>{$cards}</div>";
                    } elseif ($variant === 'blog_categories_grid_premium') {
                        $cards=''; foreach(array_slice(['one','two','three','four'],0,max(1,min(4,(int)($block['visible_count']??4)))) as $i=>$w){$t=e($block["category_{$w}"]??'Topic');$x=e($block["category_{$w}_text"]??'Browse this topic.');$n=$i+1;$cards.="<article class='min-h-52 rounded-3xl border p-7 {$theme['card']} {$theme['border']}'><span class='text-xs font-black {$theme['sub']}'>0{$n}</span><h3 class='mt-10 text-3xl font-semibold {$theme['text']}'>{$t}</h3><p class='mt-3 {$theme['sub']}'>{$x}</p></article>";} $body="<div class='mt-10 grid gap-5 sm:grid-cols-2'>{$cards}</div>";
                    } elseif ($variant === 'blog_author_profile_premium') {
                        $image=e(self::staticAssetUrl($block['author_image_url']??'/storage/cms-images/background/background-4.avif'));$name=e($block['author_name']??'Author name');$role=e($block['author_role']??'Role or specialty');$bio=e($block['author_bio']??'Add a concise biography based on supplied information.');$chips='';foreach(['one','two','three'] as $w){$v=e($block["specialty_{$w}"]??'Specialty');$chips.="<span class='rounded-full border px-3 py-2 text-xs font-bold {$theme['border']} {$theme['text']}'>{$v}</span>";}$url=e($block['primary_url']??'#');$label=e($block['primary_label']??'View profile');$header='';$body="<div class='mx-auto grid max-w-6xl overflow-hidden rounded-3xl border lg:grid-cols-[.8fr_1.2fr] {$theme['card']} {$theme['border']}'><img src='{$image}' alt='{$name}' class='min-h-[420px] h-full w-full object-cover'><div class='p-7 sm:p-10 lg:p-12'><p class='text-xs font-bold uppercase tracking-[.28em] {$theme['sub']}'>{$eyebrow}</p><h3 class='mt-5 text-4xl font-semibold {$theme['text']}'>{$name}</h3><p class='mt-2 text-sm font-bold {$theme['sub']}'>{$role}</p><p class='mt-5 leading-7 {$theme['sub']}'>{$bio}</p><div class='mt-6 flex flex-wrap gap-2'>{$chips}</div><a href='{$url}' class='mt-7 inline-block font-bold {$theme['text']}'>{$label}</a></div></div>";
                    } else {
                        $image = e(self::staticAssetUrl($block['lead_image_url'] ?? '/storage/cms-images/background/background-5.avif'));
                        $title = e($block['lead_title'] ?? 'The latest update from the team');
                        $excerpt = e($block['lead_excerpt'] ?? 'Use the main column for timely editorial content and the sidebar for quick navigation.');
                        $news=''; foreach(['one','two'] as $word){$nt=e($block["news_{$word}_title"]??'Latest story');$nm=e($block["news_{$word}_meta"]??'News');$news.="<article class='rounded-2xl border p-5 {$theme['card']} {$theme['border']}'><p class='text-xs font-bold {$theme['sub']}'>{$nm}</p><h3 class='mt-2 text-lg font-semibold {$theme['text']}'>{$nt}</h3></article>";}
                        $topics=''; foreach(['one','two','three'] as $word){$topic=e($block["topic_{$word}"]??'Topic');$topics.="<p class='border-b py-4 text-lg font-semibold {$theme['border']} {$theme['text']}'>{$topic}</p>";}
                        $body = "<div class='mt-10 grid gap-6 lg:grid-cols-[1fr_320px]'><div><article class='overflow-hidden rounded-3xl border {$theme['card']} {$theme['border']}'><img src='{$image}' alt='{$title}' class='h-72 w-full object-cover'><div class='p-7'><h3 class='text-3xl font-semibold {$theme['text']}'>{$title}</h3><p class='mt-3 {$theme['sub']}'>{$excerpt}</p></div></article><div class='mt-5 grid gap-4 sm:grid-cols-2'>{$news}</div></div><aside class='rounded-3xl border p-6 {$theme['card']} {$theme['border']}'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>Browse topics</p>{$topics}</aside></div>";
                    }
                    $html .= "<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'>{$header}{$body}</div></section>";
                    break;

                case 'footer_mega_premium':
                case 'footer_agency_premium':
                case 'footer_saas_premium':
                case 'footer_luxury_premium':
                case 'footer_dark_premium':
                case 'footer_minimal_premium':
                    $variant = $type;
                    $brand = e($block['brand_name'] ?? 'Your Brand');
                    $tagline = e($block['tagline'] ?? 'A concise closing statement for the business.');
                    $copyright = e($block['copyright'] ?? '© Your Brand. All rights reserved.');
                    $url = e($block['primary_url'] ?? '#'); $label = e($block['primary_label'] ?? 'Get in touch');
                    $footerLinks = [];
                    foreach (['one','two','three'] as $word) {
                        $linkLabel = e($block["link_{$word}_label"] ?? ucfirst($word));
                        $linkUrl = e($block["link_{$word}_url"] ?? '#');
                        $footerLinks[] = "<a href='{$linkUrl}' class='hover:underline'>{$linkLabel}</a>";
                    }
                    $links = implode(' · ', $footerLinks);
                    if ($variant === 'footer_dark_premium') {
                        $eyebrow=e($block['eyebrow']??'STAY CONNECTED'); $meta1=e($block['meta_one']??'Privacy'); $meta2=e($block['meta_two']??'Terms');
                        $body="<div class='grid gap-10 border-b border-white/10 pb-12 lg:grid-cols-[1.5fr_1fr]'><div><p class='text-xs font-bold uppercase tracking-wider text-white/50'>{$eyebrow}</p><h2 class='mt-5 text-4xl font-semibold text-white'>{$brand}</h2><p class='mt-4 max-w-xl text-white/65'>{$tagline}</p><a href='{$url}' class='mt-7 inline-block font-bold text-white'>{$label}</a></div><div class='grid grid-cols-2 gap-6 self-end text-sm text-white/70'>{$footerLinks[0]}{$footerLinks[1]}{$footerLinks[2]}<span class='text-white/50'>{$meta1}</span></div></div><div class='flex flex-col gap-4 pt-6 text-xs text-white/45 sm:flex-row sm:justify-between'><span>{$copyright}</span><span>{$meta1} · {$meta2}</span></div>";
                        $html .= "<footer class='bg-slate-950 px-6 py-16 text-white'><div class='mx-auto max-w-7xl'>{$body}</div></footer>";
                        break;
                    } elseif ($variant === 'footer_minimal_premium') {
                        $meta1=e($block['meta_one']??'Privacy'); $meta2=e($block['meta_two']??'Terms');
                        $body="<div class='flex flex-col gap-7 border-t pt-8 md:flex-row md:items-center md:justify-between {$theme['border']}'><div><h2 class='text-lg font-semibold {$theme['text']}'>{$brand}</h2><p class='mt-1 text-sm {$theme['sub']}'>{$tagline}</p></div><div class='flex flex-wrap gap-x-6 gap-y-3 text-sm {$theme['text']}'>{$footerLinks[0]}{$footerLinks[1]}{$footerLinks[2]}<span class='{$theme['sub']}'>{$meta1}</span><span class='{$theme['sub']}'>{$meta2}</span></div><p class='text-xs {$theme['sub']}'>{$copyright}</p></div>";
                        $html .= "<footer class='px-6 py-10 {$theme['bg']}'><div class='mx-auto max-w-7xl'>{$body}</div></footer>";
                        break;
                    } elseif ($variant === 'footer_agency_premium') {
                        $eyebrow=e($block['eyebrow']??'NEXT PROJECT');$heading=e($block['heading']??'Have something ambitious in mind?');$location=e($block['location']??'Your location');$email=e($block['email']??'');
                        $body="<p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-5 max-w-5xl text-5xl font-semibold {$theme['text']}'>{$heading}</h2><a href='{$url}' class='mt-8 inline-block text-lg font-bold {$theme['text']}'>{$label}</a><div class='mt-14 grid gap-8 border-t pt-8 md:grid-cols-3 {$theme['border']}'><div><p class='text-2xl font-semibold {$theme['text']}'>{$brand}</p><p class='mt-2 text-sm {$theme['sub']}'>{$location}</p></div><p class='text-sm {$theme['text']}'>{$email}</p><p class='text-sm md:text-right {$theme['sub']}'>{$copyright}</p></div>";
                    } elseif ($variant === 'footer_saas_premium') {
                        $cols=''; foreach([['Product','product_one','product_two'],['Company','company_one','company_two'],['Resources','resource_one','resource_two']] as $c){$a=e($block[$c[1]]??'Link');$b=e($block[$c[2]]??'Link');$cols.="<div><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$c[0]}</p><p class='mt-4 {$theme['text']}'>{$a}</p><p class='mt-3 {$theme['text']}'>{$b}</p></div>";} $body="<div class='rounded-3xl border p-7 sm:p-10 {$theme['card']} {$theme['border']}'><div class='grid gap-10 lg:grid-cols-[1.2fr_2fr]'><div><h2 class='text-3xl font-semibold {$theme['text']}'>{$brand}</h2><p class='mt-4 max-w-sm {$theme['sub']}'>{$tagline}</p><a href='{$url}' class='mt-6 inline-block font-bold {$theme['text']}'>{$label}</a></div><div class='grid grid-cols-2 gap-8 sm:grid-cols-3'>{$cols}</div></div><p class='mt-8 border-t pt-6 text-xs {$theme['border']} {$theme['sub']}'>{$copyright}</p></div>";
                    } elseif ($variant === 'footer_luxury_premium') {
                        $eyebrow=e($block['eyebrow']??'ESTABLISHED WITH INTENT');$heading=e($block['heading']??'Crafted for people who value the details.');$location=e($block['location']??'Your location');$s1=e($block['social_one']??'Instagram');$s2=e($block['social_two']??'LinkedIn');$body="<div class='text-center'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-8 text-5xl font-medium {$theme['text']}'>{$brand}</h2><p class='mx-auto mt-5 max-w-2xl text-lg {$theme['sub']}'>{$heading}</p><a href='{$url}' class='mt-8 inline-block font-semibold {$theme['text']}'>{$label}</a><div class='mt-16 flex flex-col gap-5 border-t pt-7 text-sm sm:flex-row sm:justify-between {$theme['border']}'><span class='{$theme['sub']}'>{$location}</span><span class='{$theme['text']}'>{$s1} · {$s2}</span><span class='{$theme['sub']}'>{$copyright}</span></div></div>";
                    } else {
                        $groupCount=max(1,min(3,(int)($block['group_count']??3)));
                        $groups=''; foreach(array_slice(['one','two','three'],0,$groupCount) as $w){$gt=e($block["group_{$w}_title"]??'Explore');$gi=e($block["group_{$w}_item"]??'Link');$groups.="<div><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$gt}</p><p class='mt-4 text-lg {$theme['text']}'>{$gi}</p></div>";} $body="<div class='grid gap-10 border-b pb-12 lg:grid-cols-[1.2fr_2fr] lg:gap-16 {$theme['border']}'><div><h2 class='text-3xl font-semibold {$theme['text']}'>{$brand}</h2><p class='mt-4 max-w-sm {$theme['sub']}'>{$tagline}</p><a href='{$url}' class='mt-6 inline-block font-bold {$theme['text']}'>{$label}</a></div><div class='grid gap-8 sm:grid-cols-3'>{$groups}</div></div><div class='flex flex-col gap-4 pt-6 sm:flex-row sm:items-center sm:justify-between'><p class='text-xs {$theme['sub']}'>{$copyright}</p><div class='flex gap-5 text-sm {$theme['text']}'>{$footerLinks[0]}{$footerLinks[1]}{$footerLinks[2]}</div></div>";
                    }
                    $html .= "<footer class='px-6 py-16 {$theme['bg']}'><div class='mx-auto max-w-7xl'>{$body}</div></footer>";
                    break;

                case 'agency_dashboard_preview_premium':
                case 'agency_client_portal_premium':
                case 'agency_white_label_showcase_premium':
                case 'agency_website_management_premium':
                case 'agency_maintenance_plans_premium':
                case 'agency_support_plans_premium':
                case 'agency_workflow_premium':
                case 'agency_project_pipeline_premium':
                case 'agency_client_reviews_premium':
                case 'agency_website_reports_premium':
                    $eyebrow=e($block['eyebrow']??'AGENCY'); $heading=e($block['heading']??'Built for agencies that manage more than one moving part.'); $text=e($block['text']??'Present real agency capabilities clearly.');
                    $items=is_array($block['items']??null)?array_values($block['items']):[];
                    if(empty($items)){
                        $agencyDefaults=[
                            'agency_dashboard_preview_premium'=>[['title'=>'Websites','text'=>'Show a real supplied site count or use an editable placeholder.'],['title'=>'Activity','text'=>'Summarise a real workflow or management signal.'],['title'=>'Reporting','text'=>'Describe an actually available reporting capability.']],
                            'agency_client_portal_premium'=>[['title'=>'Approvals','text'=>'Describe a real approval or feedback workflow.'],['title'=>'Updates','text'=>'Describe how clients actually receive project or website updates.'],['title'=>'Assets','text'=>'Describe real files, reports, or resources clients can access.']],
                            'agency_white_label_showcase_premium'=>[['title'=>'Branding','text'=>'Describe real logo, colour, domain, or identity controls.'],['title'=>'Client-facing','text'=>'Describe real branded client experiences.'],['title'=>'Handoff','text'=>'Describe a real delivery or ownership workflow.']],
                            'agency_website_management_premium'=>[['title'=>'Portfolio view','text'=>'Describe how managed websites are actually organised.'],['title'=>'Updates','text'=>'Describe a real publishing, maintenance, or update workflow.'],['title'=>'Oversight','text'=>'Describe real status, access, or reporting controls.']],
                            'agency_maintenance_plans_premium'=>[['title'=>'Routine care','text'=>'Describe a real recurring maintenance task or service.'],['title'=>'Updates','text'=>'Describe real update, patching, or content support included.'],['title'=>'Reporting','text'=>'Describe real maintenance reporting or review provided.']],
                            'agency_support_plans_premium'=>[['title'=>'Channels','text'=>'Describe actual support channels offered.'],['title'=>'Coverage','text'=>'Describe real support scope or availability without invented SLA claims.'],['title'=>'Escalation','text'=>'Describe a real escalation or handoff path.']],
                            'agency_workflow_premium'=>[['title'=>'Discover','text'=>'Describe a real discovery, brief, or planning stage.'],['title'=>'Create','text'=>'Describe a real design, build, or review stage.'],['title'=>'Deliver','text'=>'Describe a real launch, handoff, or ongoing-support stage.']],
                            'agency_project_pipeline_premium'=>[['title'=>'Planned','text'=>'Describe what belongs in the planning stage.'],['title'=>'In progress','text'=>'Describe a real active-work stage without fake status data.'],['title'=>'Ready','text'=>'Describe a real review, launch, or completion stage.']],
                            'agency_client_reviews_premium'=>[['title'=>'Client name or role','text'=>'Add a verified client quote here, or keep this clearly editable as a placeholder.'],['title'=>'Client name or role','text'=>'Add another verified client quote without inventing ratings or outcomes.'],['title'=>'Client name or role','text'=>'Use supplied feedback only; avoid fabricated endorsements.']],
                            'agency_website_reports_premium'=>[['title'=>'Performance','text'=>'Describe real supplied website performance reporting or an editable report module.'],['title'=>'Activity','text'=>'Describe real update, maintenance, publishing, or workflow reporting.'],['title'=>'Next actions','text'=>'Describe real recommendations or follow-up items included in reporting.']],
                        ];
                        $items=$agencyDefaults[$variant]??[];
                    }
                    $items=array_slice($items,0,6);
                    $cards=''; foreach($items as $i=>$item){$t=e($item['title']??'Capability');$x=e($item['text']??'Describe a real supplied capability.');$n=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);$width=min(100,48+$i*12);$cards.="<article class='relative overflow-hidden rounded-[2rem] border p-6 {$theme['border']} {$theme['card']}'><span class='absolute right-5 top-4 text-4xl font-black opacity-10 {$theme['text']}'>{$n}</span><h3 class='pr-10 text-lg font-bold {$theme['text']}'>{$t}</h3><p class='mt-4 text-sm leading-6 {$theme['sub']}'>{$x}</p><div class='mt-8 h-1.5 rounded-full border {$theme['border']}'><div class='h-full rounded-full {$theme['text']} opacity-20' style='width:{$width}%'></div></div></article>";}
                    $html.="<section class='px-6 py-20 sm:px-8 sm:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='grid gap-10 lg:grid-cols-[.8fr_1.2fr] lg:items-end'><div><p class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-black tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2></div><p class='max-w-2xl leading-7 lg:justify-self-end {$theme['sub']}'>{$text}</p></div><div class='mt-10 grid gap-4 lg:grid-cols-3'>{$cards}</div></div></section>";
                    break;

                case 'ai_prompt_showcase_premium':
                case 'ai_workflow_premium':
                case 'ai_assistant_premium':
                case 'ai_timeline_premium':
                case 'ai_builder_premium':
                case 'ai_automation_premium':
                case 'ai_credits_dashboard_premium':
                case 'ai_generation_process_premium':
                case 'ai_statistics_premium':
                case 'ai_prompt_examples_premium':
                    $eyebrow=e($block['eyebrow']??'AI'); $heading=e($block['heading']??'A clearer AI-assisted experience.'); $text=e($block['text']??'Explain supported AI capabilities clearly.');
                    $items=is_array($block['items']??null)?array_values($block['items']):[];
                    if(empty($items)){
                        $aiDefaults=[
                            'ai_prompt_showcase_premium'=>[['title'=>'Describe the goal','text'=>'Example: Build a clean service page for a local business.'],['title'=>'Add the details','text'=>'Example: Include services, trust signals, and a clear contact action.'],['title'=>'Refine the direction','text'=>'Example: Make the tone more editorial and concise.']],
                            'ai_workflow_premium'=>[['title'=>'Understand','text'=>'Collect the real brief, business context, and requested page intent.'],['title'=>'Create','text'=>'Generate content and layout choices from supported inputs.'],['title'=>'Review','text'=>'Let the user review, edit, and publish the result.']],
                            'ai_assistant_premium'=>[['title'=>'First prompt','text'=>'Start with the real question, task, or page goal.'],['title'=>'Assistant response','text'=>'Show a supported answer or generation step based on supplied context.'],['title'=>'Next prompt','text'=>'Ask for a change in tone, layout, or content priority.']],
                            'ai_timeline_premium'=>[['title'=>'01 · Brief','text'=>'Capture the prompt, brand direction, and page goal.'],['title'=>'02 · Generate','text'=>'Create supported content and section recommendations.'],['title'=>'03 · Refine','text'=>'Review, edit, and prepare the page for publishing.']],
                            'ai_builder_premium'=>[['title'=>'Describe','text'=>'Start from a real goal, page type, and brand direction.'],['title'=>'Build','text'=>'Generate supported sections and content into the visual builder.'],['title'=>'Refine','text'=>'Review, edit, rearrange, and prepare the page for publishing.']],
                            'ai_automation_premium'=>[['title'=>'Trigger','text'=>'A supported user action starts the workflow.'],['title'=>'Process','text'=>'The product performs the real configured steps.'],['title'=>'Review','text'=>'Keep a clear human review or next-action point where applicable.']],
                            'ai_credits_dashboard_premium'=>[['title'=>'Available','text'=>'Display the real available credit balance when supplied.'],['title'=>'Usage','text'=>'Explain how supported AI actions consume credits.'],['title'=>'Top up','text'=>'Show a real purchase or plan path only when it exists.']],
                            'ai_generation_process_premium'=>[['title'=>'Understand','text'=>'Read the supplied prompt and page context.'],['title'=>'Generate','text'=>'Create supported content and section choices.'],['title'=>'Prepare','text'=>'Return the result for review, editing, and publishing.']],
                            'ai_statistics_premium'=>[['title'=>'Generations','text'=>'Add a real supplied usage total or leave this as editable sample copy.'],['title'=>'Adoption','text'=>'Show a verified adoption or usage metric only when available.'],['title'=>'Efficiency','text'=>'Use a supplied time or workflow metric without implying guaranteed savings.']],
                            'ai_prompt_examples_premium'=>[['title'=>'Landing page','text'=>'Create a concise landing page for a local service business with services, proof, and a contact CTA.'],['title'=>'Rewrite','text'=>'Rewrite this section in a confident, clear tone while keeping the supplied facts unchanged.'],['title'=>'Structure','text'=>'Suggest a page structure for this goal using only supported section types.']],
                        ];
                        $items=$aiDefaults[$type]??[['title'=>'Step one','text'=>'Add supported content here.'],['title'=>'Step two','text'=>'Add supported content here.'],['title'=>'Step three','text'=>'Add supported content here.']];
                    }
                    $items=array_slice($items,0,6);
                    $cards=''; foreach($items as $i=>$item){$t=e($item['title']??'Item');$x=e($item['text']??'Describe a supported AI step or example.');$n=$i+1;$cards.="<article class='rounded-[1.75rem] border p-6 {$theme['border']} {$theme['card']}'><span class='inline-flex h-9 w-9 items-center justify-center rounded-xl border text-xs font-black {$theme['border']} {$theme['text']}'>{$n}</span><h3 class='mt-5 text-lg font-bold {$theme['text']}'>{$t}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$x}</p></article>";}
                    $html.="<section class='relative overflow-hidden px-6 py-20 sm:px-8 sm:py-24 {$theme['bg']}'><div class='pointer-events-none absolute inset-0 opacity-[.05]' style='background-image:radial-gradient(currentColor 1px, transparent 1px);background-size:24px 24px'></div><div class='relative mx-auto max-w-7xl'><div class='max-w-3xl'><p class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-black tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p></div><div class='mt-10 grid gap-4 lg:grid-cols-3'>{$cards}</div></div></section>";
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
                $faqs = is_array($block['faqs'] ?? null) ? array_values($block['faqs']) : [];
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

                case 'sales_comparison_premium':
                case 'sales_feature_matrix_premium':
                case 'sales_competitor_comparison_premium':
                case 'sales_roi_premium':
                case 'sales_guarantee_premium':
                case 'sales_trust_premium':
                case 'sales_integrations_premium':
                    $eyebrow=e($block['eyebrow']??'COMPARE'); $heading=e($block['heading']??'Make the decision easier to understand.'); $text=e($block['text']??'Use factual supplied information.');
                    $items=is_array($block['items']??null)?array_values($block['items']):[];
                    if(!$items){
                        $salesDefaults=[
                            'sales_comparison_premium'=>[['title'=>'Option A','text'=>'Add factual strengths and limits.'],['title'=>'Option B','text'=>'Add factual strengths and limits.'],['title'=>'Best fit','text'=>'Explain who each option suits.']],
                            'sales_feature_matrix_premium'=>[['title'=>'Core','text'=>'List real capabilities.'],['title'=>'Advanced','text'=>'List real capabilities.'],['title'=>'Support','text'=>'List real support details.']],
                            'sales_competitor_comparison_premium'=>[['title'=>'Your offer','text'=>'State factual strengths.'],['title'=>'Alternative','text'=>'Use verified public or supplied facts.'],['title'=>'Decision criteria','text'=>'Compare relevant criteria neutrally.']],
                            'sales_roi_premium'=>[['title'=>'Investment','text'=>'Use supplied cost or effort.'],['title'=>'Potential value','text'=>'Use supplied assumptions only.'],['title'=>'Payback logic','text'=>'Explain the calculation clearly.']],
                            'sales_guarantee_premium'=>[['title'=>'What is covered','text'=>'Add the actual coverage or commitment.'],['title'=>'What to expect','text'=>'Explain the real process or remedy.'],['title'=>'Terms','text'=>'Link or summarise the supplied conditions.']],
                            'sales_trust_premium'=>[['title'=>'Verified proof','text'=>'Add a real certification, client proof, or credential.'],['title'=>'Clear process','text'=>'Explain a real safeguard or working standard.'],['title'=>'Support','text'=>'Add a factual support or service commitment.']],
                            'sales_integrations_premium'=>[['title'=>'Platform one','text'=>'Add a real supported integration.'],['title'=>'Platform two','text'=>'Add a real supported integration.'],['title'=>'Platform three','text'=>'Add a real supported integration.']],
                        ];
                        $items=$salesDefaults[$variant]??[];
                    }
                    $items=array_slice($items,0,6);
                    $cards=''; foreach($items as $i=>$item){$n=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);$title=e($item['title']??'Item');$desc=e($item['text']??'');$cards.="<article class='rounded-[1.75rem] border p-6 {$theme['border']} {$theme['card']}'><span class='text-xs font-bold {$theme['sub']}'>{$n}</span><h3 class='mt-8 text-xl font-bold {$theme['text']}'>{$title}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$desc}</p></article>";}
                    $note=e($block['note']??''); $noteHtml=$note!==''?"<p class='mt-5 text-xs leading-5 {$theme['sub']}'>{$note}</p>":'';
                    $html.="<section class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto max-w-7xl'><p class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 max-w-4xl text-4xl font-black tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-3xl leading-7 {$theme['sub']}'>{$text}</p><div class='mt-10 grid gap-4 lg:grid-cols-3'>{$cards}</div>{$noteHtml}</div></section>";
                    break;

                case 'faq_accordion_pro':
                    $eyebrow=e($block['eyebrow']??'Frequently asked'); $heading=e($block['heading']??'Answers without the fine-print feeling.'); $text=e($block['text']??'Clear answers for the questions that matter most.');
                    $faqs=is_array($block['faqs']??null)?array_values($block['faqs']):[]; if(!$faqs){$faqs=[['question'=>'What should I know before getting started?','answer'=>'Add the real information visitors need before they take the next step.'],['question'=>'How does the process work?','answer'=>'Explain the real process clearly and accurately.']];}
                    $items=''; foreach($faqs as $i=>$faq){$q=e($faq['question']??'Question');$a=e($faq['answer']??'Answer');$n=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);$items.="<details class='group border-b p-6 last:border-b-0 {$theme['border']}'><summary class='flex cursor-pointer list-none items-start gap-5 font-semibold {$theme['text']}'><span class='pt-1 text-xs {$theme['sub']}'>{$n}</span><span class='flex-1'>{$q}</span><span class='text-xl'>+</span></summary><p class='pl-9 pt-4 text-sm leading-7 {$theme['sub']}'>{$a}</p></details>";}
                    $html.="<section class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.8fr_1.2fr] lg:gap-20'><div><p class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 text-base leading-7 {$theme['sub']}'>{$text}</p></div><div class='overflow-hidden rounded-[2rem] border {$theme['border']} {$theme['card']}'>{$items}</div></div></section>";
                    break;

                case 'faq_search_premium':
                    $eyebrow=e($block['eyebrow']??'Search help');$heading=e($block['heading']??'Find the answer in seconds.');$text=e($block['text']??'Search the questions below.');$placeholder=e($block['search_placeholder']??'Search questions…');$faqs=is_array($block['faqs']??null)?array_values($block['faqs']):[]; if(!$faqs){$faqs=[['question'=>'What should I know before getting started?','answer'=>'Use this answer for the real information visitors need before they take the next step.'],['question'=>'How does the process work?','answer'=>'Explain the real process clearly and avoid promises that are not part of your service.'],['question'=>'What options are available?','answer'=>'Replace this copy with the actual options, inclusions, or service choices you offer.'],['question'=>'Where can I get more help?','answer'=>'Point visitors to a real contact, support, booking, or documentation path.']];}
                    $uid='cosmic-faq-'.$index.'-'.substr(md5(json_encode($block)),0,8); $cards=''; foreach($faqs as $faq){$q=e($faq['question']??'Question');$a=e($faq['answer']??'Answer');$search=e(strtolower(strip_tags(($faq['question']??'').' '.($faq['answer']??''))));$cards.="<article data-faq-card data-search='{$search}' class='rounded-2xl border p-6 {$theme['border']} {$theme['card']}'><h3 class='font-semibold {$theme['text']}'>{$q}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$a}</p></article>";}
                    $html.="<section id='{$uid}' class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto max-w-6xl'><div class='grid gap-8 lg:grid-cols-[1fr_24rem] lg:items-end'><div><p class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 text-base leading-7 {$theme['sub']}'>{$text}</p></div><input aria-label='Search FAQ' oninput=\"(function(v){var r=document.getElementById('{$uid}'),n=0;if(!r)return;r.querySelectorAll('[data-faq-card]').forEach(function(el){var show=el.dataset.search.indexOf(v.toLowerCase())>-1;el.style.display=show?'':'none';if(show)n++;});var empty=r.querySelector('[data-faq-empty]');if(empty)empty.hidden=n>0;})(this.value)\" placeholder='{$placeholder}' class='w-full rounded-full border bg-transparent px-5 py-3 text-sm {$theme['border']} {$theme['text']}'></div><div class='mt-10 grid gap-4 md:grid-cols-2'>{$cards}</div><p data-faq-empty hidden class='mt-6 rounded-2xl border p-6 text-center text-sm {$theme['border']} {$theme['sub']}'>No matching questions. Try another search.</p></div></section>";
                    break;

                case 'faq_categories_premium':
                    $eyebrow=e($block['eyebrow']??'Browse by topic');$heading=e($block['heading']??'Everything is easier when it is organised.');$text=e($block['text']??'Browse common questions by topic.');$cats=is_array($block['categories']??null)?array_values($block['categories']):[]; if(!$cats){$cats=[['title'=>'Getting started','description'=>'First steps, preparation, and what to expect.','faqs'=>[['question'=>'What should I know before getting started?','answer'=>'Use this answer for the real information visitors need before they take the next step.'],['question'=>'How does the process work?','answer'=>'Explain the real process clearly and accurately.']]],['title'=>'Services & options','description'=>'Scope, inclusions, and practical choices.','faqs'=>[['question'=>'What options are available?','answer'=>'Replace this copy with the actual options, inclusions, or service choices you offer.']]],['title'=>'Help & support','description'=>'Where to go when visitors need assistance.','faqs'=>[['question'=>'Where can I get more help?','answer'=>'Point visitors to a real contact, support, booking, or documentation path.']]]];}$groups=''; foreach($cats as $cat){$title=e($cat['title']??'Topic');$desc=e($cat['description']??'Helpful answers.');$faqHtml='';foreach(array_slice(is_array($cat['faqs']??null)?$cat['faqs']:[],0,4) as $faq){$q=e($faq['question']??'Question');$a=e($faq['answer']??'Answer');$faqHtml.="<details class='border-t py-4 {$theme['border']}'><summary class='cursor-pointer text-sm font-semibold {$theme['text']}'>{$q}</summary><p class='mt-2 text-sm leading-6 {$theme['sub']}'>{$a}</p></details>";}$groups.="<article class='rounded-[1.75rem] border p-6 {$theme['border']} {$theme['card']}'><h3 class='text-xl font-bold {$theme['text']}'>{$title}</h3><p class='mt-2 text-sm leading-6 {$theme['sub']}'>{$desc}</p><div class='mt-5'>{$faqHtml}</div></article>";}
                    $html.="<section class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto max-w-7xl'><p class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl text-base leading-7 {$theme['sub']}'>{$text}</p><div class='mt-12 grid gap-6 lg:grid-cols-3'>{$groups}</div></div></section>";
                    break;

                case 'faq_support_portal_premium':
                    $eyebrow=e($block['eyebrow']??'Support portal');$heading=e($block['heading']??'Start with the answer. Escalate when you need to.');$text=e($block['text']??'Self-service help with a clear support path.');$label=e($block['primary_label']??'Contact support');$url=e($block['primary_url']??'/contact');$topics=is_array($block['topics']??null)?array_values($block['topics']):[]; if(!$topics){$topics=[['title'=>'Getting started','description'=>'Setup, onboarding, and first steps.','count_label'=>'Guide'],['title'=>'Using the service','description'=>'Common workflows and practical help.','count_label'=>'How-to'],['title'=>'Plans & billing','description'=>'Real billing, plan, or payment information.','count_label'=>'Account'],['title'=>'Policies','description'=>'Terms, cancellations, returns, or service policies.','count_label'=>'Policy']];}$topicHtml='';foreach($topics as $t){$tl=e($t['count_label']??'Guide');$tt=e($t['title']??'Help topic');$td=e($t['description']??'Useful help and guidance.');$topicHtml.="<article class='rounded-2xl border p-5 {$theme['border']} {$theme['card']}'><p class='text-[11px] font-bold uppercase tracking-[.18em] {$theme['sub']}'>{$tl}</p><h3 class='mt-4 text-lg font-bold {$theme['text']}'>{$tt}</h3><p class='mt-2 text-sm leading-6 {$theme['sub']}'>{$td}</p></article>";} $supportFaqs=is_array($block['faqs']??null)?array_values($block['faqs']):[]; if(!$supportFaqs){$supportFaqs=[['question'=>'What should I know before getting started?','answer'=>'Use this answer for the real information visitors need before they take the next step.'],['question'=>'How does the process work?','answer'=>'Explain the real process clearly and accurately.'],['question'=>'Where can I get more help?','answer'=>'Point visitors to a real contact, support, booking, or documentation path.']];}$faqHtml='';foreach(array_slice($supportFaqs,0,4) as $faq){$q=e($faq['question']??'Question');$a=e($faq['answer']??'Answer');$faqHtml.="<details class='rounded-2xl border p-5 {$theme['border']} {$theme['card']}'><summary class='cursor-pointer font-semibold {$theme['text']}'>{$q}</summary><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$a}</p></details>";}
                    $primary=self::getTheme($primaryColor);$html.="<section class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between'><div><p class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl text-base leading-7 {$theme['sub']}'>{$text}</p></div><a href='{$url}' class='inline-flex w-fit rounded-full px-6 py-3 text-sm font-bold text-white {$primary['bg']}'>{$label}</a></div><div class='mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4'>{$topicHtml}</div><div class='mt-8 grid gap-4 md:grid-cols-2'>{$faqHtml}</div></div></section>";
                    break;

                case 'faq_documentation_premium':
                    $eyebrow=e($block['eyebrow']??'Documentation');$heading=e($block['heading']??'Guidance that stays easy to navigate.');$text=e($block['text']??'Clear documentation topics.');$label=e($block['primary_label']??'View guide');$url=e($block['primary_url']??'/');$topics=is_array($block['topics']??null)?array_values($block['topics']):[]; if(!$topics){$topics=[['title'=>'Getting started','text'=>'Setup, preparation, or first steps.'],['title'=>'Core workflows','text'=>'Common tasks and practical instructions.'],['title'=>'Configuration','text'=>'Options, settings, or service choices.'],['title'=>'Troubleshooting','text'=>'Real solutions for common issues.']];}$steps=is_array($block['steps']??null)?array_values($block['steps']):[]; if(!$steps){$steps=[['title'=>'01 · Understand','text'=>'Explain the first real step.'],['title'=>'02 · Configure','text'=>'Describe the next practical action.'],['title'=>'03 · Continue','text'=>'Point visitors to the real next step.']];}$topicHtml='';foreach($topics as $t){$tt=e($t['title']??'Topic');$tx=e($t['text']??'Helpful guidance.');$topicHtml.="<div class='rounded-2xl border p-4 {$theme['border']}'><h3 class='font-semibold {$theme['text']}'>{$tt}</h3><p class='mt-1 text-xs leading-5 {$theme['sub']}'>{$tx}</p></div>";}$stepHtml='';foreach($steps as $st){$tt=e($st['title']??'Step');$tx=e($st['text']??'Guidance.');$stepHtml.="<div class='rounded-2xl border p-4 {$theme['border']}'><h4 class='text-sm font-bold {$theme['text']}'>{$tt}</h4><p class='mt-2 text-xs leading-5 {$theme['sub']}'>{$tx}</p></div>";}$ft=e($block['featured_title']??'Start here');$fx=e($block['featured_text']??'Use the featured guide for your most important help content.');$html.="<section class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto max-w-7xl'><p class='text-xs font-bold uppercase tracking-[.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p><div class='mt-12 grid gap-6 lg:grid-cols-[.7fr_1.3fr]'><aside class='rounded-3xl border p-5 {$theme['border']} {$theme['card']}'><div class='space-y-2'>{$topicHtml}</div></aside><article class='rounded-3xl border p-7 {$theme['border']} {$theme['card']}'><h3 class='text-2xl font-bold {$theme['text']}'>{$ft}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$fx}</p><div class='mt-7 grid gap-3 sm:grid-cols-3'>{$stepHtml}</div><a href='{$url}' class='mt-7 inline-flex rounded-full border px-5 py-3 text-sm font-bold {$theme['border']} {$theme['text']}'>{$label}</a></article></div></div></section>";
                    break;

                case 'lead_magnet_premium':
                    $eyebrow=e($block['eyebrow']??'Free resource');$heading=e($block['heading']??'Give visitors something useful.');$text=e($block['text']??'Offer a real supplied resource.');$rl=e($block['resource_label']??'Guide');$rt=e($block['resource_title']??'Practical resource');$rx=e($block['resource_text']??'Describe the real resource.');$label=e($block['primary_label']??'Get the resource');$url=e($block['primary_url']??'#');$items=is_array($block['benefits']??null)?array_values($block['benefits']):[];if(!$items){$items=[['title'=>'Useful','text'=>'Explain one factual benefit of the resource.'],['title'=>'Focused','text'=>'Show who the resource is designed to help.'],['title'=>'Practical','text'=>'Describe what the visitor will actually receive.']];}$cards='';foreach($items as $i){$t=e($i['title']??'Benefit');$x=e($i['text']??'Describe a factual benefit.');$cards.="<div class='rounded-2xl border p-4 {$theme['border']}'><h4 class='text-sm font-bold {$theme['text']}'>{$t}</h4><p class='mt-2 text-xs leading-5 {$theme['sub']}'>{$x}</p></div>";}$html.="<section class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-8 lg:grid-cols-2 lg:items-center'><div><p class='text-xs font-bold uppercase tracking-[.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 leading-7 {$theme['sub']}'>{$text}</p><a href='{$url}' class='mt-7 inline-flex rounded-full border px-5 py-3 text-sm font-bold {$theme['border']} {$theme['text']}'>{$label}</a></div><article class='rounded-[2rem] border p-7 {$theme['border']} {$theme['card']}'><p class='text-xs font-bold uppercase tracking-[.2em] {$theme['sub']}'>{$rl}</p><h3 class='mt-5 text-3xl font-bold {$theme['text']}'>{$rt}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$rx}</p><div class='mt-6 grid gap-3 sm:grid-cols-3'>{$cards}</div></article></div></section>";
                    break;

                case 'lead_free_audit_premium':
                    $eyebrow=e($block['eyebrow']??'Audit request');$heading=e($block['heading']??'Start with a focused review.');$text=e($block['text']??'Use factual audit scope.');$label=e($block['primary_label']??'Request audit');$url=e($block['primary_url']??'#');$note=e($block['note']??'Replace with the real audit scope and eligibility.');$items=is_array($block['items']??null)?array_values($block['items']):[];if(!$items){$items=[['title'=>'Current setup','text'=>'Review a real part of the visitor’s existing setup.'],['title'=>'Priority issues','text'=>'Identify supplied or observable areas to examine.'],['title'=>'Next steps','text'=>'Explain what the visitor can expect after the review.']];}$items=array_slice($items,0,6);$cards='';foreach($items as $k=>$i){$t=e($i['title']??'Audit area');$x=e($i['text']??'Describe the real scope.');$n=str_pad((string)($k+1),2,'0',STR_PAD_LEFT);$cards.="<article class='rounded-3xl border p-6 {$theme['border']} {$theme['card']}'><span class='text-xs font-bold {$theme['sub']}'>{$n}</span><h3 class='mt-8 text-xl font-bold {$theme['text']}'>{$t}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$x}</p></article>";}$html.="<section class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto max-w-7xl'><p class='text-xs font-bold uppercase tracking-[.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p><a href='{$url}' class='mt-7 inline-flex rounded-full border px-5 py-3 text-sm font-bold {$theme['border']} {$theme['text']}'>{$label}</a><div class='mt-12 grid gap-4 lg:grid-cols-3'>{$cards}</div><p class='mt-4 rounded-2xl border p-5 text-sm leading-6 {$theme['border']} {$theme['sub']}'>{$note}</p></div></section>";
                    break;

                case 'lead_website_audit_premium':
                    $eyebrow=e($block['eyebrow']??'Website audit');$heading=e($block['heading']??'Find the friction before you rebuild.');$text=e($block['text']??'Promote a real website review.');$label=e($block['primary_label']??'Request website audit');$url=e($block['primary_url']??'#');$items=is_array($block['audit_items']??null)?array_values($block['audit_items']):[];if(!$items){$items=[['title'=>'Experience','text'=>'Review structure, usability, and key journeys.'],['title'=>'Performance','text'=>'Check supplied or measured speed and technical issues.'],['title'=>'Search visibility','text'=>'Assess real SEO basics and content structure.'],['title'=>'Conversion','text'=>'Review calls to action and lead paths.']];}$items=array_slice($items,0,6);$cards='';foreach($items as $i){$t=e($i['title']??'Audit area');$x=e($i['text']??'Describe the audit area.');$cards.="<article class='rounded-2xl border p-5 {$theme['border']} {$theme['card']}'><h3 class='font-bold {$theme['text']}'>{$t}</h3><p class='mt-2 text-xs leading-5 {$theme['sub']}'>{$x}</p></article>";}$rl=e($block['report_label']??'Audit preview');$rt=e($block['report_title']??'A focused review, not a fabricated score.');$rx=e($block['report_text']??'Replace with the actual deliverables.');$html.="<section class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-8 lg:grid-cols-2'><div><p class='text-xs font-bold uppercase tracking-[.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 leading-7 {$theme['sub']}'>{$text}</p><div class='mt-8 grid gap-3 sm:grid-cols-2'>{$cards}</div><a href='{$url}' class='mt-7 inline-flex rounded-full border px-5 py-3 text-sm font-bold {$theme['border']} {$theme['text']}'>{$label}</a></div><aside class='rounded-[2rem] border p-7 {$theme['border']} {$theme['card']}'><p class='text-xs font-bold uppercase tracking-[.2em] {$theme['sub']}'>{$rl}</p><div class='mt-10 grid grid-cols-2 gap-3'>".implode('',array_map(fn($x)=>"<div class='rounded-2xl border p-5 {$theme['border']}'><div class='text-3xl font-black {$theme['text']}'>—</div><div class='mt-2 text-xs font-bold {$theme['sub']}'>{$x}</div></div>",['UX','SEO','Speed','CTA']))."</div><h3 class='mt-8 text-2xl font-bold {$theme['text']}'>{$rt}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$rx}</p></aside></div></section>";
                    break;


                case 'lead_quote_form_premium':
                    $eyebrow=e($block['eyebrow']??'Request a quote');$heading=e($block['heading']??'Tell us what you need.');$text=e($block['text']??'Share the details needed for a real quote.');$label=e($block['primary_label']??'Request quote');$note=e($block['form_note']??'Final pricing is confirmed after review.');$items=is_array($block['items']??null)?array_values($block['items']):[];if(!$items){$items=[['title'=>'Project type','text'=>'Describe the service, product, or work required.'],['title'=>'Scope','text'=>'Capture useful size, quantity, or requirement details.'],['title'=>'Timeline','text'=>'Ask for the preferred timing without promising availability.']];}$items=array_slice($items,0,6);$cards='';foreach($items as $i){$t=e($i['title']??'Detail');$x=e($i['text']??'Share useful project information.');$cards.="<div class='rounded-2xl border p-5 {$theme['border']} {$theme['card']}'><h3 class='font-bold {$theme['text']}'>{$t}</h3><p class='mt-2 text-sm {$theme['sub']}'>{$x}</p></div>";}$primary=self::getTheme($primaryColor);$html.="<section class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-8 lg:grid-cols-2'><div><p class='text-xs font-bold uppercase tracking-[.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 leading-7 {$theme['sub']}'>{$text}</p><div class='mt-8 grid gap-3'>{$cards}</div></div><form action='cosmic-sync/contact.php' method='post' class='rounded-[2rem] border p-7 {$theme['border']} {$theme['card']}'><input type='hidden' name='inquiry_type' value='quote'><div class='grid gap-4 sm:grid-cols-2'><input required name='name' placeholder='Name' class='rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'><input required type='email' name='email' placeholder='Email' class='rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></div><input name='project' placeholder='Project or service' class='mt-4 w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'><textarea required name='message' placeholder='Scope, quantity, timing, or requirements' class='mt-4 min-h-32 w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></textarea><button class='mt-5 rounded-full px-6 py-3 text-sm font-bold text-white {$primary['bg']}'>{$label}</button><p class='mt-4 text-xs leading-5 {$theme['sub']}'>{$note}</p></form></div></section>";
                    break;

                case 'lead_roi_calculator_premium':
                    $eyebrow=e($block['eyebrow']??'ROI calculator');$heading=e($block['heading']??'Model the upside with your own assumptions.');$text=e($block['text']??'Create an illustrative estimate.');$l1=e($block['input_one_label']??'Estimated investment');$l2=e($block['input_two_label']??'Expected monthly value');$l3=e($block['input_three_label']??'Months');$rl=e($block['result_label']??'Illustrative ROI');$note=e($block['note']??'Estimate only. Actual results can vary.');$id='cosmic-roi-'.$index.'-'.substr(md5(json_encode($block)),0,8);$html.="<section id='{$id}' class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-8 lg:grid-cols-2 lg:items-center'><div><p class='text-xs font-bold uppercase tracking-[.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 leading-7 {$theme['sub']}'>{$text}</p></div><div class='rounded-[2rem] border p-7 {$theme['border']} {$theme['card']}'><div class='grid gap-4 sm:grid-cols-3'><label class='text-xs font-bold {$theme['sub']}'>{$l1}<input data-roi='investment' type='number' min='0' step='any' value='1000' class='mt-2 w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></label><label class='text-xs font-bold {$theme['sub']}'>{$l2}<input data-roi='value' type='number' min='0' step='any' value='250' class='mt-2 w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></label><label class='text-xs font-bold {$theme['sub']}'>{$l3}<input data-roi='months' type='number' min='1' step='1' value='12' class='mt-2 w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></label></div><div class='mt-6 rounded-2xl border p-6 {$theme['border']}'><div class='text-xs font-bold uppercase tracking-[.18em] {$theme['sub']}'>{$rl}</div><div data-roi-result class='mt-2 text-4xl font-black {$theme['text']}'>—</div></div><p class='mt-5 text-xs leading-5 {$theme['sub']}'>{$note}</p></div></div><script>(function(){var r=document.getElementById('{$id}');if(!r)return;function u(){var i=+r.querySelector('[data-roi=investment]').value||0,v=+r.querySelector('[data-roi=value]').value||0,m=+r.querySelector('[data-roi=months]').value||0,o=r.querySelector('[data-roi-result]');o.textContent=i>0?(((v*m-i)/i)*100).toFixed(1)+'%':'—';}r.querySelectorAll('input').forEach(function(x){x.addEventListener('input',u)});u();})();</script></section>";
                    break;

                case 'lead_cost_calculator_premium':
                    $eyebrow=e($block['eyebrow']??'Cost estimator');$heading=e($block['heading']??'Build a quick working estimate.');$text=e($block['text']??'Enter quantity and a unit rate.');$ql=e($block['quantity_label']??'Quantity');$rr=e($block['rate_label']??'Estimated unit rate');$rl=e($block['result_label']??'Estimated cost');$note=e($block['note']??'Estimate only. Final pricing may vary.');$id='cosmic-cost-'.$index.'-'.substr(md5(json_encode($block)),0,8);$html.="<section id='{$id}' class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto max-w-6xl'><p class='text-xs font-bold uppercase tracking-[.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p><div class='mt-10 grid gap-5 rounded-[2rem] border p-7 sm:grid-cols-[1fr_1fr_.8fr] {$theme['border']} {$theme['card']}'><label class='text-xs font-bold {$theme['sub']}'>{$ql}<input data-cost='qty' type='number' min='0' step='any' value='1' class='mt-2 w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></label><label class='text-xs font-bold {$theme['sub']}'>{$rr}<input data-cost='rate' type='number' min='0' step='any' value='0' class='mt-2 w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></label><div class='rounded-2xl border p-5 {$theme['border']}'><div class='text-xs font-bold {$theme['sub']}'>{$rl}</div><div data-cost-result class='mt-2 text-3xl font-black {$theme['text']}'>0.00</div></div></div><p class='mt-4 text-xs leading-5 {$theme['sub']}'>{$note}</p></div><script>(function(){var r=document.getElementById('{$id}');if(!r)return;function u(){var q=+r.querySelector('[data-cost=qty]').value||0,v=+r.querySelector('[data-cost=rate]').value||0;r.querySelector('[data-cost-result]').textContent=(q*v).toFixed(2);}r.querySelectorAll('input').forEach(function(x){x.addEventListener('input',u)});u();})();</script></section>";
                    break;

                case 'lead_consultation_booking_premium':
                    $eyebrow=e($block['eyebrow']??'Book a consultation');$heading=e($block['heading']??'Start with a focused conversation.');$text=e($block['text']??'Request a consultation.');$label=e($block['primary_label']??'Request consultation');$note=e($block['note']??'A request is not confirmed until the business responds.');$items=is_array($block['items']??null)?array_values($block['items']):[];if(empty($items)){$items=[['title'=>'Your goal','text'=>'Share what you want to achieve.'],['title'=>'Current situation','text'=>'Add the context that will make the conversation useful.'],['title'=>'Preferred timing','text'=>'Suggest a preferred day or timeframe without promising a slot.']];}$items=array_slice($items,0,6);$cards='';foreach($items as $i){$t=e($i['title']??'Prepare');$x=e($i['text']??'Share useful context.');$cards.="<div class='rounded-2xl border p-5 {$theme['border']} {$theme['card']}'><h3 class='font-bold {$theme['text']}'>{$t}</h3><p class='mt-2 text-xs leading-5 {$theme['sub']}'>{$x}</p></div>";}$primary=self::getTheme($primaryColor);$html.="<section class='px-6 py-20 sm:px-8 lg:py-28 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-8 lg:grid-cols-2'><div><p class='text-xs font-bold uppercase tracking-[.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 leading-7 {$theme['sub']}'>{$text}</p><div class='mt-8 grid gap-3 sm:grid-cols-3'>{$cards}</div></div><form action='cosmic-sync/contact.php' method='post' class='rounded-[2rem] border p-7 {$theme['border']} {$theme['card']}'><input type='hidden' name='inquiry_type' value='consultation'><input required name='name' placeholder='Name' class='w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'><input required type='email' name='email' placeholder='Email' class='mt-4 w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'><input name='preferred_timing' placeholder='Preferred day or timeframe' class='mt-4 w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'><textarea required name='message' placeholder='What would you like to discuss?' class='mt-4 min-h-28 w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></textarea><button class='mt-5 rounded-full px-6 py-3 text-sm font-bold text-white {$primary['bg']}'>{$label}</button><p class='mt-4 text-xs leading-5 {$theme['sub']}'>{$note}</p></form></div></section>";
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
                $directionsUrl = e($block['directions_url'] ?? 'https://www.google.com/maps/search/?api=1&query=Your+Business+Location');
                $html .= "<section class='px-6 py-16 sm:px-8 lg:py-20 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-16'><div><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-xl text-base leading-7 {$theme['sub']}'>{$text}</p></div><div class='relative min-h-[22rem] overflow-hidden rounded-3xl border p-7 sm:p-9 {$theme['border']} {$theme['card']}'><div class='absolute inset-0 opacity-30 [background-image:linear-gradient(currentColor_1px,transparent_1px),linear-gradient(90deg,currentColor_1px,transparent_1px)] [background-size:2.5rem_2.5rem] {$theme['sub']}'></div><div class='relative flex min-h-[16rem] h-full flex-col justify-between'><div class='grid h-14 w-14 place-items-center rounded-full border-8 {$theme['border']} {$theme['bg']}'><span class='h-3 w-3 rounded-full bg-current {$theme['text']}'></span></div><div class='max-w-md rounded-2xl border p-5 backdrop-blur {$theme['border']} {$theme['card']}'><p class='text-lg font-semibold {$theme['text']}'>{$locationName}</p><p class='mt-2 text-sm leading-6 {$theme['sub']}'>{$address}</p><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$serviceArea}</p><a href='{$directionsUrl}' target='_blank' rel='noopener noreferrer' class='mt-5 inline-flex rounded-full border px-4 py-2 text-sm font-semibold {$theme['border']} {$theme['text']}'>{$directionsLabel}</a></div></div></div></div></section>";
                break;

                case 'portfolio_masonry':
                case 'portfolio_pinterest':
                case 'portfolio_hover_video':
                $defaults=['eyebrow'=>'SELECTED WORK','heading'=>'A portfolio with range, rhythm, and room to breathe.','text'=>'A curated selection of recent work.','primary_label'=>'View all projects','primary_url'=>'#'];
                $d=array_merge($defaults,$block); $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $buttonBg=$isPrimarySection?'bg-white':$primaryTheme['bg']; $buttonText=$isPrimarySection?'text-slate-950':$primaryTheme['text'];
                $portfolioCount=max(1,min(6,(int)($d['portfolio_count']??6))); $portfolioWords=array_slice(['one','two','three','four','five','six'],0,$portfolioCount);
                $items=''; foreach($portfolioWords as $i=>$w){$img=trim((string)($d['project_'.$w.'_image_url']??'')); if($img===''){$img='/storage/cms-images/background/background-'.(($i%3)+1).'.avif';} $ratio=$block['type']==='portfolio_hover_video'?'aspect-[4/3]':($block['type']==='portfolio_pinterest'?($i%2===0?'aspect-[4/5]':'aspect-[3/4]'):($i%3===0?'aspect-[4/5]':($i%3===1?'aspect-[4/3]':'aspect-square'))); $video=trim((string)($d['project_'.$w.'_video_url']??'')); $media="<img src='".e(self::staticAssetUrl($img))."' alt='' class='h-full w-full object-cover transition duration-300'>"; if($block['type']==='portfolio_hover_video' && $video!==''){ $media.="<video muted loop playsinline preload='metadata' poster='".e(self::staticAssetUrl($img))."' src='".e(self::staticAssetUrl($video))."' class='absolute inset-0 h-full w-full object-cover opacity-0 transition duration-300' data-cosmic-hover-video></video>"; } $hint=$block['type']==='portfolio_hover_video'?"<span class='absolute inset-x-4 bottom-4 rounded-full bg-black/60 px-4 py-2 text-center text-[11px] font-bold uppercase tracking-[.16em] text-white backdrop-blur'>".($video!==''?'Hover to preview':'Poster preview')."</span>":''; $items.="<article class='group mb-4 break-inside-avoid overflow-hidden rounded-[1.75rem] border {$border} {$card}' data-cosmic-hover-card><div class='relative {$ratio} overflow-hidden'>{$media}{$hint}</div><div class='p-5'><h3 class='text-xl font-semibold'>".e($d['project_'.$w.'_title']??'Selected project')."</h3><p class='mt-2 text-sm {$muted}'>".e($d['project_'.$w.'_meta']??'Project details')."</p></div></article>";}
                $columns=$block['type']==='portfolio_pinterest'?'columns-2 sm:columns-3 lg:columns-4':($block['type']==='portfolio_hover_video'?'grid gap-4 sm:grid-cols-2 lg:grid-cols-3':'columns-1 sm:columns-2 lg:columns-3');
                $hoverScript=$block['type']==='portfolio_hover_video'?"<script>(function(){document.querySelectorAll('[data-cosmic-hover-card]').forEach(function(c){var v=c.querySelector('[data-cosmic-hover-video]');if(!v)return;function p(){v.style.opacity='1';var x=v.play();if(x&&x.catch)x.catch(function(){});}function q(){v.pause();v.currentTime=0;v.style.opacity='0';}c.addEventListener('mouseenter',p);c.addEventListener('mouseleave',q);c.addEventListener('focusin',p);c.addEventListener('focusout',q);});})();</script>":''; $html.="<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-black uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-4 leading-7 {$muted}'>".e($d['text'])."</p></div><div class='mt-10 {$columns} gap-4'>{$items}</div><a href='".e($d['primary_url']?:'/projects')."' class='mt-7 inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div>{$hoverScript}</section>";
                break;

                case 'portfolio_case_study':
                $d=array_merge(['eyebrow'=>'FEATURED CASE STUDY','heading'=>'Turn one strong project into a story worth reading.','text'=>'Lead with the work, then explain the challenge, approach, and outcome.','project_title'=>'Featured project','project_meta'=>'Strategy · Design · Delivery','image_url'=>'/storage/cms-images/background/background-1.avif','challenge_label'=>'CHALLENGE','challenge_text'=>'Define the problem clearly.','approach_label'=>'APPROACH','approach_text'=>'Explain the practical approach.','outcome_label'=>'OUTCOME','outcome_text'=>'Describe the supported outcome conservatively.','metric_value'=>'—','metric_label'=>'Add a verified result','primary_label'=>'Read the case study','primary_url'=>'#','footnote'=>'Only publish performance metrics that the business can verify.'],$block);
                $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $buttonBg=$isPrimarySection?'bg-white':$primaryTheme['bg']; $buttonText=$isPrimarySection?'text-slate-950':$primaryTheme['text']; $img=trim((string)$d['image_url']); if($img===''){$img='/storage/cms-images/background/background-1.avif';}
                $details=''; foreach(['challenge','approach','outcome'] as $k){$details.="<div class='mt-7'><span class='text-[11px] font-black tracking-[.18em] {$muted}'>".e($d[$k.'_label'])."</span><p class='mt-2 text-sm leading-6 {$muted}'>".e($d[$k.'_text'])."</p></div>";}
                $html.="<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-black uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-4 leading-7 {$muted}'>".e($d['text'])."</p></div><div class='mt-10 overflow-hidden rounded-[2rem] border {$border} {$card}'><div class='grid lg:grid-cols-[1.1fr_.9fr]'><div class='min-h-[420px]'><img src='".e(self::staticAssetUrl($img))."' alt='' class='h-full w-full object-cover'></div><div class='p-7 sm:p-9'><p class='text-xs font-black uppercase tracking-[.2em] {$muted}'>".e($d['project_meta'])."</p><h3 class='mt-5 text-3xl font-semibold tracking-[-.035em]'>".e($d['project_title'])."</h3>{$details}<div class='mt-8 flex items-end justify-between gap-5'><div><span class='block text-4xl font-semibold tracking-[-.05em]'>".e($d['metric_value'])."</span><span class='mt-1 block text-xs {$muted}'>".e($d['metric_label'])."</span></div><a href='".e($d['primary_url'])."' class='inline-flex min-h-[48px] items-center rounded-full px-6 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div><p class='mt-5 text-[11px] {$muted}'>".e($d['footnote'])."</p></div></div></div></div></section>";
                break;

                case 'portfolio_before_after':
                $d=array_merge(['eyebrow'=>'BEFORE / AFTER','heading'=>'Show the transformation, not just the finished frame.','text'=>'Compare what changed and why it matters.','project_title'=>'Featured transformation','project_meta'=>'Project comparison','before_label'=>'Before','after_label'=>'After','before_image_url'=>'/storage/cms-images/background/background-2.avif','after_image_url'=>'/storage/cms-images/background/background-1.avif','outcome_label'=>'WHAT CHANGED','outcome_text'=>'Explain the supported transformation clearly.','primary_label'=>'View project','primary_url'=>'/projects'],$block);
                $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $buttonBg=$isPrimarySection?'bg-white':$primaryTheme['bg']; $buttonText=$isPrimarySection?'text-slate-950':$primaryTheme['text'];
                $before=e(self::staticAssetUrl($d['before_image_url'])); $after=e(self::staticAssetUrl($d['after_image_url'])); $id='cosmic-before-after-'.$index;
                $html.="<section id='{$id}' class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-black uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-4 leading-7 {$muted}'>".e($d['text'])."</p></div><div class='mt-10 overflow-hidden rounded-[2rem] border {$border} {$card}'><div data-ba-stage class='relative aspect-[16/9] min-h-[320px] overflow-hidden bg-slate-950' style='--cosmic-split:50%'><img src='{$after}' alt='".e($d['after_label'])."' class='absolute inset-0 h-full w-full object-cover'><img data-ba-before src='{$before}' alt='".e($d['before_label'])."' class='absolute inset-0 h-full w-full object-cover' style='clip-path:inset(0 calc(100% - var(--cosmic-split)) 0 0)'><div data-ba-line class='pointer-events-none absolute inset-y-0 w-0.5 bg-white shadow' style='left:var(--cosmic-split)'></div><span class='absolute left-4 top-4 rounded-full bg-black/60 px-3 py-1 text-xs font-bold text-white'>".e($d['before_label'])."</span><span class='absolute right-4 top-4 rounded-full bg-black/60 px-3 py-1 text-xs font-bold text-white'>".e($d['after_label'])."</span><input data-ba-range aria-label='Before and after comparison' type='range' min='0' max='100' value='50' class='absolute inset-x-6 bottom-5 w-[calc(100%-3rem)]'></div><div class='grid gap-8 p-6 sm:p-8 lg:grid-cols-[1fr_.8fr]'><div><span class='text-xs font-black uppercase tracking-[.18em] {$muted}'>".e($d['project_meta'])."</span><h3 class='mt-3 text-2xl font-semibold'>".e($d['project_title'])."</h3></div><div><span class='text-xs font-black uppercase tracking-[.18em] {$muted}'>".e($d['outcome_label'])."</span><p class='mt-2 text-sm leading-6 {$muted}'>".e($d['outcome_text'])."</p><a href='".e($d['primary_url']?:'/projects')."' class='mt-5 inline-flex min-h-[46px] items-center rounded-full px-6 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div></div></div></div><script>(function(){var r=document.getElementById('{$id}');if(!r)return;var s=r.querySelector('[data-ba-stage]'),i=r.querySelector('[data-ba-range]');if(!s||!i)return;function u(){s.style.setProperty('--cosmic-split',i.value+'%');}i.addEventListener('input',u);u();})();</script></section>";
                break;

                case 'portfolio_filterable':
                $d=array_merge(['eyebrow'=>'PROJECT INDEX','heading'=>'Find the work that matters to you.','text'=>'Organise a varied portfolio into a few clear categories.','primary_label'=>'Start a project','primary_url'=>'/contact'],$block); $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $buttonBg=$isPrimarySection?'bg-white':$primaryTheme['bg']; $buttonText=$isPrimarySection?'text-slate-950':$primaryTheme['text']; $id='cosmic-filterable-'.$index; $portfolioCount=max(1,min(6,(int)($d['portfolio_count']??6))); $portfolioWords=array_slice(['one','two','three','four','five','six'],0,$portfolioCount); $items=''; $categories=[]; foreach($portfolioWords as $i=>$w){$img=trim((string)($d['project_'.$w.'_image_url']??'')); if($img===''){$img='/storage/cms-images/background/background-'.(($i%3)+1).'.avif';} $cat=trim((string)($d['project_'.$w.'_category']??'Project')); if($cat==='')$cat='Project'; $categories[$cat]=true; $items.="<article data-portfolio-item data-category='".e(strtolower($cat))."' class='overflow-hidden rounded-[1.6rem] border {$border} {$card}'><img src='".e(self::staticAssetUrl($img))."' alt='' class='aspect-[4/3] w-full object-cover'><div class='p-5'><span class='text-[11px] font-black uppercase tracking-[.18em] {$muted}'>".e($cat)."</span><h3 class='mt-2 text-xl font-semibold'>".e($d['project_'.$w.'_title']??'Selected project')."</h3><p class='mt-2 text-sm {$muted}'>".e($d['project_'.$w.'_meta']??'Project details')."</p></div></article>";} $filters="<button type='button' data-filter='all' aria-pressed='true' class='rounded-full border px-4 py-2 text-xs font-bold {$buttonBg} {$buttonText}'>All</button>"; foreach(array_keys($categories) as $cat){$filters.="<button type='button' data-filter='".e(strtolower($cat))."' aria-pressed='false' class='rounded-full border px-4 py-2 text-xs font-bold {$border} {$card}'>".e($cat)."</button>";} $html.="<section id='{$id}' class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-black uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-4 leading-7 {$muted}'>".e($d['text'])."</p></div><div class='mt-8 flex flex-wrap gap-2' data-filter-controls>{$filters}</div><div class='mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3'>{$items}</div><a href='".e($d['primary_url']?:'/contact')."' class='mt-8 inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div><script>(function(){var r=document.getElementById('{$id}');if(!r)return;var bs=r.querySelectorAll('[data-filter]'),is=r.querySelectorAll('[data-portfolio-item]');bs.forEach(function(b){b.addEventListener('click',function(){var f=b.getAttribute('data-filter');bs.forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false');});is.forEach(function(x){x.hidden=f!=='all'&&x.getAttribute('data-category')!==f;});});});})();</script></section>"; break;

                case 'portfolio_animated':
                $d=array_merge(['eyebrow'=>'SELECTED PROJECTS','heading'=>'A portfolio that feels alive before you click.','text'=>'Subtle motion, strong imagery, and editorial labels make each project feel more tactile.','primary_label'=>'Explore all work','primary_url'=>'/projects'],$block); $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $buttonBg=$isPrimarySection?'bg-white':$primaryTheme['bg']; $buttonText=$isPrimarySection?'text-slate-950':$primaryTheme['text']; $id='cosmic-animated-'.$index; $items=''; foreach(['one','two','three','four'] as $i=>$w){$img=trim((string)($d['project_'.$w.'_image_url']??'')); if($img===''){$img='/storage/cms-images/background/background-'.(($i%3)+1).'.avif';} $items.="<article data-animated-project tabindex='0' class='relative min-h-[360px] overflow-hidden rounded-[2rem]'><img src='".e(self::staticAssetUrl($img))."' alt='' class='absolute inset-0 h-full w-full object-cover' style='transition:transform .7s ease'><div class='absolute inset-0 bg-gradient-to-t from-black/80 via-black/15 to-transparent'></div><div data-animated-copy class='absolute inset-x-0 bottom-0 p-6 text-white' style='transition:transform .45s ease'><span class='text-[11px] font-black uppercase tracking-[.18em] text-white/65'>0".($i+1)."</span><h3 class='mt-2 text-2xl font-semibold'>".e($d['project_'.$w.'_title']??'Selected project')."</h3><p class='mt-2 text-sm text-white/70'>".e($d['project_'.$w.'_meta']??'Project details')."</p></div></article>";} $html.="<section id='{$id}' class='overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-black uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-4 leading-7 {$muted}'>".e($d['text'])."</p></div><div class='mt-10 grid gap-4 md:grid-cols-2'>{$items}</div><a href='".e($d['primary_url']?:'/projects')."' class='mt-8 inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div><script>(function(){var r=document.getElementById('{$id}');if(!r)return;r.querySelectorAll('[data-animated-project]').forEach(function(c){var im=c.querySelector('img'),tx=c.querySelector('[data-animated-copy]');function on(){if(im)im.style.transform='scale(1.05)';if(tx)tx.style.transform='translateY(-8px)';}function off(){if(im)im.style.transform='scale(1)';if(tx)tx.style.transform='translateY(0)';}c.addEventListener('mouseenter',on);c.addEventListener('mouseleave',off);c.addEventListener('focusin',on);c.addEventListener('focusout',off);});})();</script></section>"; break;

                case 'portfolio_project_timeline':
                $d=array_merge(['eyebrow'=>'PROJECT TIMELINE','heading'=>'From first conversation to finished experience.','text'=>'Reveal the progression behind a featured project.','project_title'=>'Featured project','project_meta'=>'Strategy · Design · Delivery','primary_label'=>'Read full case study','primary_url'=>'#'],$block); $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $buttonBg=$isPrimarySection?'bg-white':$primaryTheme['bg']; $buttonText=$isPrimarySection?'text-slate-950':$primaryTheme['text']; $steps=''; foreach(['one','two','three','four'] as $i=>$w){$img=trim((string)($d['step_'.$w.'_image_url']??'')); if($img===''){$img='/storage/cms-images/background/background-'.(($i%3)+1).'.avif';} $steps.="<article class='grid gap-5 border-b py-8 {$border} lg:grid-cols-[120px_.7fr_1.3fr] lg:items-center'><span class='text-sm font-black {$muted}'>".e($d['step_'.$w.'_number']??'0'.($i+1))."</span><div><h3 class='text-2xl font-semibold {$theme['text']}'>".e($d['step_'.$w.'_title']??'Project stage')."</h3><p class='mt-2 text-sm leading-6 {$muted}'>".e($d['step_'.$w.'_text']??'Project milestone details.')."</p></div><img src='".e(self::staticAssetUrl($img))."' alt='' class='aspect-[16/7] w-full rounded-[1.5rem] object-cover'></article>";} $html.="<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-end'><div><span class='text-xs font-black uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-4 leading-7 {$muted}'>".e($d['text'])."</p></div><div class='rounded-[1.75rem] border p-6 {$border} {$card}'><span class='text-xs font-black uppercase tracking-[.18em] {$muted}'>".e($d['project_meta'])."</span><h3 class='mt-3 text-2xl font-semibold'>".e($d['project_title'])."</h3></div></div><div class='mt-10'>{$steps}</div><a href='".e($d['primary_url'])."' class='mt-8 inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div></section>"; break;

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
                    $studies = [
                        ['category'=>'Strategy','title'=>'A clearer digital path','summary'=>'A focused engagement that turned a complex challenge into a practical, confident next step.','result'=>'Built for measurable progress','image_url'=>'/storage/cms-images/background/background-1.avif','link_label'=>'View case study'],
                        ['category'=>'Design','title'=>'An experience made simpler','summary'=>'A thoughtful redesign that made important information easier to find and act on.','result'=>'Clarity at every step','image_url'=>'/storage/cms-images/background/background-2.avif','link_label'=>'View case study'],
                        ['category'=>'Growth','title'=>'A stronger launch foundation','summary'=>'A collaborative website project shaped around the customer journey and real business goals.','result'=>'Ready to grow with confidence','image_url'=>'/storage/cms-images/background/background-3.avif','link_label'=>'View case study'],
                    ];
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
                    $jobs = [
                        ['title'=>'Senior designer','type'=>'Full-time','location'=>'New York, NY','description'=>'Help shape thoughtful digital experiences for ambitious teams and their customers.','button_label'=>'View role'],
                        ['title'=>'Project manager','type'=>'Full-time','location'=>'Remote','description'=>'Keep client work organized, moving clearly, and grounded in practical next steps.','button_label'=>'View role'],
                        ['title'=>'Growth strategist','type'=>'Flexible','location'=>'Hybrid','description'=>'Turn research, insight, and collaboration into clear opportunities for clients.','button_label'=>'View role'],
                        ['title'=>'Client partner','type'=>'Full-time','location'=>'London, UK','description'=>'Build trusted relationships and make every stage of delivery feel well supported.','button_label'=>'View role'],
                    ];
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
                    $events = [
                        ['month'=>'OCT','day'=>'12','title'=>'A practical session for your next move','date'=>'October 12 · 10:00 AM','location'=>'Online','description'=>'A focused conversation with useful ideas you can put into action right away.','button_label'=>'Reserve a place'],
                        ['month'=>'NOV','day'=>'04','title'=>'Meet the people behind the work','date'=>'November 4 · 6:00 PM','location'=>'Our studio','description'=>'An informal evening to connect, exchange ideas, and see how we approach the work.','button_label'=>'Save your seat'],
                        ['month'=>'DEC','day'=>'08','title'=>'Plan a stronger year ahead','date'=>'December 8 · 1:00 PM','location'=>'Online','description'=>'A guided planning session for teams ready to set clearer priorities for what is next.','button_label'=>'Join the session'],
                    ];
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

                case 'contact_split_premium':
                case 'contact_map_premium':
                case 'contact_appointment_premium':
                case 'contact_support_center_premium':
                case 'contact_faq_premium':
                case 'contact_multistep_premium':
                case 'contact_live_chat_premium':
                    $variant = (string) ($block['type'] ?? 'contact_split_premium');
                    $eyebrow = e($block['eyebrow'] ?? 'CONTACT');
                    $heading = e($block['heading'] ?? 'Let’s make the next step simple.');
                    $text = e($block['text'] ?? 'Choose the contact option that fits your needs.');
                    $email = e($block['email'] ?? 'hello@example.com');
                    $phone = e($block['phone'] ?? '+1 (555) 010-0200');
                    $address = e($block['address'] ?? 'Available by appointment');
                    $primaryLabel = e($block['primary_label'] ?? 'Send inquiry');
                    $header = "<p class='text-xs font-semibold uppercase tracking-[0.24em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-5 text-base leading-7 {$theme['sub']}'>{$text}</p>";
                    $form = "<form action='./cosmic-sync/contact.php' method='post' class='space-y-4'><div class='grid gap-4 sm:grid-cols-2'><input name='name' required placeholder='Your name' class='w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'><input type='email' name='email' required placeholder='you@example.com' class='w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></div><input name='message' required placeholder='Tell us what you need' class='w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'><label class='hidden' aria-hidden='true'>Company<input name='company' tabindex='-1' autocomplete='off'></label><button type='submit' class='w-full rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white'>{$primaryLabel}</button></form>";

                    if ($variant === 'contact_split_premium') {
                        $html .= "<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.9fr_1.1fr]'><div>{$header}<div class='mt-8 space-y-3 border-t pt-6 {$theme['border']}'><p class='font-semibold {$theme['text']}'>{$email}</p><p class='font-semibold {$theme['text']}'>{$phone}</p><p class='{$theme['sub']}'>{$address}</p></div></div><div class='rounded-[2rem] border p-6 sm:p-8 {$theme['card']} {$theme['border']}'>{$form}</div></div></section>";
                    } elseif ($variant === 'contact_map_premium') {
                        $mapLabel=e($block['map_label'] ?? 'Map preview'); $directionsLabel=e($block['directions_label'] ?? 'Get directions'); $directionsUrl=e($block['directions_url'] ?? 'https://www.google.com/maps/search/?api=1&query=Your+Business+Location');
                        $html .= "<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-10 lg:grid-cols-2'><div>{$header}<div class='mt-8 space-y-3'><p class='text-lg font-semibold {$theme['text']}'>{$address}</p><p class='{$theme['sub']}'>{$phone}</p><p class='{$theme['sub']}'>{$email}</p></div><a href='{$directionsUrl}' class='mt-6 inline-flex rounded-full bg-slate-900 px-5 py-3 text-sm font-bold text-white'>{$directionsLabel}</a></div><div class='grid min-h-80 place-items-center overflow-hidden rounded-[2rem] border {$theme['card']} {$theme['border']}'><div class='text-center'><div class='mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full border {$theme['border']} {$theme['bg']}'>⌖</div><p class='text-sm font-semibold {$theme['text']}'>{$mapLabel}</p><p class='mt-2 text-xs {$theme['sub']}'>Connect a real map or directions URL before publishing.</p></div></div></div></section>";
                    } elseif ($variant === 'contact_appointment_premium') {
                        $bookingUrl=e($block['booking_url'] ?? 'https://calendly.com/'); $note=e($block['appointment_note'] ?? 'Connect your real booking link and availability before publishing.'); $duration=e($block['duration_label'] ?? 'Appointment length varies');
                        $html .= "<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-5xl rounded-[2rem] border p-7 sm:p-10 {$theme['card']} {$theme['border']}'><div class='grid gap-10 lg:grid-cols-[1fr_.8fr]'><div>{$header}<p class='mt-6 text-sm {$theme['sub']}'>{$note}</p></div><div class='rounded-2xl border p-6 {$theme['bg']} {$theme['border']}'><p class='text-sm font-semibold {$theme['text']}'>{$duration}</p><a href='{$bookingUrl}' class='mt-5 inline-flex w-full justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white'>{$primaryLabel}</a><p class='mt-4 text-xs {$theme['sub']}'>Availability is not generated by Cosmic AI.</p></div></div></div></section>";
                    } elseif ($variant === 'contact_support_center_premium') {
                        $cards=''; foreach(array_slice(['one','two','three'],0,max(1,min(3,(int)($block['support_count']??3)))) as $n){$t=e($block["support_{$n}_title"] ?? ucfirst($n).' support');$x=e($block["support_{$n}_text"] ?? 'Add your real support details.');$cards.="<article class='rounded-2xl border p-6 {$theme['card']} {$theme['border']}'><h3 class='text-lg font-bold {$theme['text']}'>{$t}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$x}</p></article>";}
                        $html .= "<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-2xl'>{$header}</div><div class='mt-10 grid gap-5 md:grid-cols-3'>{$cards}</div><div class='mt-8 flex flex-wrap gap-4 border-t pt-6 {$theme['border']}'><p class='font-semibold {$theme['text']}'>{$email}</p><p class='{$theme['sub']}'>{$phone}</p></div></div></section>";
                    } elseif ($variant === 'contact_faq_premium') {
                        $faq=''; foreach(array_slice(['one','two','three'],0,max(1,min(3,(int)($block['faq_count']??3)))) as $idx=>$n){$q=e($block["faq_{$n}_question"] ?? 'Common question');$a=e($block["faq_{$n}_answer"] ?? 'Add your real answer before publishing.');$open=$idx===0?' open':'';$faq.="<details{$open} class='rounded-2xl border p-5 {$theme['card']} {$theme['border']}'><summary class='cursor-pointer list-none font-semibold {$theme['text']}'>{$q}</summary><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$a}</p></details>";}
                        $html .= "<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.05fr_.95fr]'><div>{$header}<div class='mt-8 space-y-3'>{$faq}</div></div><div class='rounded-[2rem] border p-6 sm:p-8 {$theme['card']} {$theme['border']}'>{$form}<div class='mt-6 border-t pt-5 text-sm {$theme['border']} {$theme['sub']}'><p>{$email}</p><p class='mt-1'>{$phone}</p></div></div></div></section>";
                    } elseif ($variant === 'contact_multistep_premium') {
                        $submitLabel=e($block['submit_label'] ?? 'Send inquiry'); $wizardId='cosmic-contact-steps-'.$index.'-'.substr(md5(json_encode($block)),0,8); $tabs=''; foreach(['one','two','three'] as $i=>$n){$t=e($block["step_{$n}_title"] ?? 'Step '.($i+1));$tabs.="<button type='button' data-contact-tab='{$i}' class='px-5 py-4 text-left text-sm font-semibold {$theme['sub']}'>0".($i+1)." · {$t}</button>";}
                        $descriptions=''; foreach(['one','two','three'] as $i=>$n){$t=e($block["step_{$n}_title"] ?? 'Step '.($i+1));$x=e($block["step_{$n}_text"] ?? 'Add the information requested here.');$hidden=$i===0?'':' hidden';$descriptions.="<div data-contact-copy='{$i}'{$hidden}><h3 class='text-2xl font-bold {$theme['text']}'>{$t}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$x}</p></div>";}
                        $html .= "<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-5xl'><div class='max-w-2xl'>{$header}</div><div id='{$wizardId}' class='mt-10 overflow-hidden rounded-[2rem] border {$theme['card']} {$theme['border']}'><div class='grid border-b sm:grid-cols-3 {$theme['border']}'>{$tabs}</div><div class='grid gap-8 p-6 sm:p-8 lg:grid-cols-[.75fr_1.25fr]'><div>{$descriptions}</div><form action='./cosmic-sync/contact.php' method='post'><div data-contact-step='0'><label class='mb-2 block text-xs font-semibold uppercase tracking-[.16em] {$theme['sub']}'>Name</label><input name='name' required placeholder='Your name' class='w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></div><div data-contact-step='1' hidden><label class='mb-2 block text-xs font-semibold uppercase tracking-[.16em] {$theme['sub']}'>Request</label><input name='message' required placeholder='Tell us what you need' class='w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></div><div data-contact-step='2' hidden><label class='mb-2 block text-xs font-semibold uppercase tracking-[.16em] {$theme['sub']}'>Email</label><input type='email' name='email' required placeholder='you@example.com' class='w-full rounded-xl border bg-transparent px-4 py-3 {$theme['border']} {$theme['text']}'></div><label class='hidden' aria-hidden='true'>Company<input name='company' tabindex='-1' autocomplete='off'></label><div class='mt-5 flex justify-between gap-3'><button type='button' data-contact-back class='rounded-xl border px-4 py-3 text-sm font-semibold {$theme['border']} {$theme['text']}' disabled>Back</button><button type='button' data-contact-next class='rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white'>Continue</button><button type='submit' data-contact-submit class='hidden rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white'>{$submitLabel}</button></div></form></div></div></div></section><script>(function(){var root=document.getElementById('{$wizardId}');if(!root)return;var panels=[].slice.call(root.querySelectorAll('[data-contact-step]')),copies=[].slice.call(root.querySelectorAll('[data-contact-copy]')),tabs=[].slice.call(root.querySelectorAll('[data-contact-tab]')),back=root.querySelector('[data-contact-back]'),next=root.querySelector('[data-contact-next]'),submit=root.querySelector('[data-contact-submit]'),step=0;function render(){panels.forEach(function(p,i){p.hidden=i!==step});copies.forEach(function(p,i){p.hidden=i!==step});tabs.forEach(function(t,i){t.classList.toggle('bg-black/5',i===step);t.classList.toggle('dark:bg-white/5',i===step);});back.disabled=step===0;next.classList.toggle('hidden',step===2);submit.classList.toggle('hidden',step!==2);}function valid(){var fields=[].slice.call(panels[step].querySelectorAll('input,textarea,select'));for(var i=0;i<fields.length;i++){if(!fields[i].reportValidity())return false;}return true;}next.addEventListener('click',function(){if(valid()&&step<2){step++;render();}});back.addEventListener('click',function(){if(step>0){step--;render();}});tabs.forEach(function(t,i){t.addEventListener('click',function(){step=i;render();});});render();})();</script>";
                    } else {
                        $chatUrl=e($block['chat_url'] ?? 'https://www.messenger.com/'); $chatNote=e($block['chat_note'] ?? 'Chat availability depends on your connected support channel.'); $secondaryLabel=e($block['secondary_label'] ?? 'Send an email'); $secondaryUrl=e($block['secondary_url'] ?? 'mailto:hello@example.com');
                        $html .= "<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto grid max-w-6xl gap-8 rounded-[2rem] border p-7 sm:p-10 lg:grid-cols-[1fr_.72fr] lg:items-center {$theme['card']} {$theme['border']}'><div>{$header}<div class='mt-7 flex flex-wrap gap-3'><a href='{$chatUrl}' class='rounded-full bg-slate-900 px-6 py-3 text-sm font-bold text-white'>{$primaryLabel}</a><a href='{$secondaryUrl}' class='rounded-full border px-6 py-3 text-sm font-bold {$theme['border']} {$theme['text']}'>{$secondaryLabel}</a></div></div><div class='rounded-2xl border p-6 {$theme['bg']} {$theme['border']}'><div class='flex items-start gap-4'><div class='grid h-12 w-12 shrink-0 place-items-center rounded-full border {$theme['border']}'>✦</div><div><p class='font-semibold {$theme['text']}'>Connected chat</p><p class='mt-2 text-sm leading-6 {$theme['sub']}'>{$chatNote}</p></div></div><p class='mt-5 text-xs {$theme['sub']}'>Cosmic AI does not generate live availability or presence claims.</p></div></div></section>";
                    }
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





                case 'about_timeline_story':
                $d = array_merge([
                    'eyebrow'=>'OUR STORY','heading'=>'Built one meaningful chapter at a time.','text'=>'Show how the business evolved without turning the About page into a wall of copy.','primary_label'=>'Meet the team','primary_url'=>'#',
                    'year_one'=>'The beginning','title_one'=>'The beginning','text_one'=>'A focused idea became a practical service built around real customer needs.',
                    'year_two'=>'Next chapter','title_two'=>'Growing with purpose','text_two'=>'The team expanded its capabilities while keeping the experience personal and clear.',
                    'year_three'=>'Evolution','title_three'=>'A stronger platform','text_three'=>'New systems, sharper positioning, and broader expertise created room for the next stage.',
                    'year_four'=>'Today','title_four'=>'What comes next','text_four'=>'We continue to improve the work, the process, and the value we create for every client.'
                ], $block);
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $muted = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $border = $isPrimarySection ? 'border-white/20' : $theme['border'];
                $card = $isPrimarySection ? 'bg-white/10 text-white' : "{$theme['surface']} {$theme['text']}";
                $buttonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $buttonText = $isPrimarySection ? 'text-slate-950' : $primaryTheme['text'];
                $items = '';
                $timelineCount = max(1, min(4, (int) ($d['about_timeline_count'] ?? 4)));
                foreach (array_slice(['one','two','three','four'], 0, $timelineCount) as $i => $word) {
                    $items .= "<article class='relative ml-16 rounded-[1.75rem] border p-6 sm:p-7 {$border} {$card}'><span class='absolute -left-[49px] top-7 flex h-9 w-9 items-center justify-center rounded-full border text-xs font-black {$border} {$card}'>".($i+1)."</span><span class='text-xs font-black uppercase tracking-[.18em] {$muted}'>".e($d['year_'.$word])."</span><h3 class='mt-3 text-2xl font-semibold tracking-[-.03em]'>".e($d['title_'.$word])."</h3><p class='mt-3 text-sm leading-6 {$muted}'>".e($d['text_'.$word])."</p></article>";
                }
                $line = $isPrimarySection ? 'bg-white/20' : 'bg-slate-200';
                $html .= "<section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:gap-16'><div><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-5 text-base leading-7 {$muted}'>".e($d['text'])."</p><a href='".e($d['primary_url'])."' class='mt-7 inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div><div class='relative'><div class='absolute bottom-0 left-[31px] top-0 w-px {$line}'></div><div class='space-y-5'>{$items}</div></div></div></div></section>";
                break;

                case 'about_founder_story':
                $d = array_merge(['eyebrow'=>'FOUNDER STORY','heading'=>'Built from a belief that better work starts with better listening.','text'=>'Use this space for the human reason behind the business—the problem the founder wanted to solve and the standard they wanted to set.','quote'=>'Do the useful work first. Make it beautiful second. Then keep improving both.','founder_name'=>'Founder','founder_role'=>'Founder','principle_one'=>'Clarity over complexity','principle_two'=>'Craft with purpose','principle_three'=>'Long-term partnerships','image_url'=>'/storage/cms-images/background/background-1.avif','primary_label'=>'Our approach','primary_url'=>'#'], $block);
                $primaryTheme = self::getTheme($primaryColor);
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $muted = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $border = $isPrimarySection ? 'border-white/20' : $theme['border'];
                $card = $isPrimarySection ? 'bg-white/10 text-white' : "{$theme['surface']} {$theme['text']}";
                $buttonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $buttonText = $isPrimarySection ? 'text-slate-950' : $primaryTheme['text'];
                $principles=''; foreach(['one','two','three'] as $w){$principles.="<div class='rounded-2xl border p-4 text-sm font-semibold {$border} {$card}'>".e($d['principle_'.$w])."</div>";}
                $html .= "<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-10 lg:grid-cols-12 lg:items-stretch'><div class='relative min-h-[460px] overflow-hidden rounded-[2rem] lg:col-span-5'><img src='".e($d['image_url'])."' alt='' class='absolute inset-0 h-full w-full object-cover'><div class='absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent'></div><div class='absolute bottom-0 p-7 text-white'><h3 class='text-2xl font-semibold'>".e($d['founder_name'])."</h3><p class='mt-1 text-sm text-white/70'>".e($d['founder_role'])."</p></div></div><div class='lg:col-span-7 lg:py-4'><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-6 text-base leading-7 sm:text-lg {$muted}'>".e($d['text'])."</p><blockquote class='mt-8 rounded-[1.75rem] border p-6 text-xl font-medium leading-8 sm:p-8 {$border} {$card}'>".e($d['quote'])."</blockquote><div class='mt-6 grid gap-3 sm:grid-cols-3'>{$principles}</div><a href='".e($d['primary_url'])."' class='mt-7 inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div></div></section>";
                break;

                case 'about_mission_grid':
                $d = array_merge(['eyebrow'=>'WHY WE EXIST','heading'=>'A clear mission, translated into everyday decisions.','text'=>'Make the purpose of the company concrete by connecting the big idea to the way the team actually works.','mission_label'=>'MISSION','mission_title'=>'Make complex things feel simple.','mission_text'=>'Create useful experiences that remove friction and help people move forward with confidence.','vision_label'=>'VISION','vision_title'=>'Raise the standard','vision_text'=>'Build a business known for thoughtful work, dependable delivery, and relationships that last.','value_one_title'=>'Stay curious','value_one_text'=>'Ask better questions before reaching for familiar answers.','value_two_title'=>'Own the outcome','value_two_text'=>'Take responsibility for the result, not just the task.','value_three_title'=>'Keep it human','value_three_text'=>'Communicate clearly, listen carefully, and respect people’s time.'], $block);
                $primaryTheme = self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $soft=$isPrimarySection?'bg-black/10 text-white':"{$primaryTheme['card']} {$primaryTheme['text']}"; $softMuted=$isPrimarySection?'text-white/70':$primaryTheme['sub']; $softBorder=$isPrimarySection?'border-white/20':$primaryTheme['border'];
                $values=''; foreach(['one','two','three'] as $w){$values.="<article class='rounded-[2rem] border p-6 lg:col-span-4 {$border} {$card}'><h3 class='text-xl font-semibold'>".e($d['value_'.$w.'_title'])."</h3><p class='mt-3 text-sm leading-6 {$muted}'>".e($d['value_'.$w.'_text'])."</p></article>";}
                $html .= "<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-4xl'><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl lg:text-6xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-5 max-w-2xl text-base leading-7 {$muted}'>".e($d['text'])."</p></div><div class='mt-12 grid gap-4 lg:grid-cols-12'><article class='rounded-[2rem] border p-7 sm:p-9 lg:col-span-7 {$border} {$card}'><span class='text-xs font-black tracking-[.2em] {$muted}'>".e($d['mission_label'])."</span><h3 class='mt-12 text-3xl font-semibold tracking-[-.035em] sm:text-4xl'>".e($d['mission_title'])."</h3><p class='mt-4 max-w-xl leading-7 {$muted}'>".e($d['mission_text'])."</p></article><article class='rounded-[2rem] border p-7 sm:p-9 lg:col-span-5 {$softBorder} {$soft}'><span class='text-xs font-black tracking-[.2em] {$softMuted}'>".e($d['vision_label'])."</span><h3 class='mt-12 text-3xl font-semibold tracking-[-.035em]'>".e($d['vision_title'])."</h3><p class='mt-4 leading-7 {$softMuted}'>".e($d['vision_text'])."</p></article>{$values}</div></div></section>";
                break;

                case 'about_interactive_stats':
                $d = array_merge(['eyebrow'=>'THE COMPANY IN NUMBERS','heading'=>'Proof that the work has momentum behind it.','text'=>'Use meaningful company metrics to give visitors a quick sense of scale, experience, reach, or progress.','stat_one_value'=>'12+','stat_one_label'=>'Years building','stat_one_text'=>'Experience shaped across changing markets and technologies.','stat_two_value'=>'48','stat_two_label'=>'Projects this year','stat_two_text'=>'Focused engagements delivered across strategy, design, and technology.','stat_three_value'=>'6','stat_three_label'=>'Core disciplines','stat_three_text'=>'A connected team covering the work from direction through delivery.','stat_four_value'=>'4','stat_four_label'=>'Markets served','stat_four_text'=>'Local understanding combined with a broader point of view.','footnote'=>'Replace starter metrics with verified company data before publishing.'], $block);
                $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}";
                $stats=''; $statsCount=max(1,min(4,(int)($d['about_stats_count']??4))); foreach(array_slice(['one','two','three','four'],0,$statsCount) as $w){$stats.="<article class='relative overflow-hidden rounded-[2rem] border p-7 sm:p-8 {$border} {$card}'><span class='block text-5xl font-semibold tracking-[-.055em] sm:text-6xl'>".e($d['stat_'.$w.'_value'])."</span><h3 class='mt-6 text-lg font-semibold'>".e($d['stat_'.$w.'_label'])."</h3><p class='mt-3 text-sm leading-6 {$muted}'>".e($d['stat_'.$w.'_text'])."</p></article>";}
                $html .= "<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='grid gap-8 lg:grid-cols-[.85fr_1.15fr] lg:items-end'><div><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2></div><p class='max-w-2xl text-base leading-7 sm:text-lg {$muted}'>".e($d['text'])."</p></div><div class='mt-12 grid gap-4 md:grid-cols-2'>{$stats}</div><p class='mt-5 text-xs {$muted}'>".e($d['footnote'])."</p></div></section>";
                break;


                case 'about_brand_journey':
                $d=array_merge(['eyebrow'=>'BRAND JOURNEY','heading'=>'How the brand became what it is today.','text'=>'Connect the moments that shaped the identity, the offer, and the way customers experience the business.','chapter_one_label'=>'ORIGIN','chapter_one_title'=>'Start with the problem','chapter_one_text'=>'The brand began with a simple observation: customers deserved a clearer, more thoughtful option.','chapter_two_label'=>'REFINEMENT','chapter_two_title'=>'Find the point of view','chapter_two_text'=>'The offer became sharper, the language more confident, and the experience more recognisable.','chapter_three_label'=>'EXPANSION','chapter_three_title'=>'Grow without losing focus','chapter_three_text'=>'New capabilities were added while the core promise stayed consistent.','chapter_four_label'=>'NOW','chapter_four_title'=>'Build the next chapter','chapter_four_text'=>'The brand keeps evolving around what customers value most.','primary_label'=>'See our work','primary_url'=>'#'],$block);
                $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $buttonBg=$isPrimarySection?'bg-white':$primaryTheme['bg']; $buttonText=$isPrimarySection?'text-slate-950':$primaryTheme['text'];
                $chapters=''; $journeyCount=max(1,min(4,(int)($d['about_journey_count']??4))); foreach(array_slice(['one','two','three','four'],0,$journeyCount) as $i=>$w){$chapters.="<article class='rounded-[2rem] border p-6 sm:p-7 {$border} {$card}'><div class='flex items-center justify-between'><span class='text-xs font-black tracking-[.2em] {$muted}'>".e($d['chapter_'.$w.'_label'])."</span><span class='text-sm font-bold {$muted}'>0".($i+1)."</span></div><h3 class='mt-12 text-2xl font-semibold tracking-[-.03em]'>".e($d['chapter_'.$w.'_title'])."</h3><p class='mt-4 text-sm leading-6 {$muted}'>".e($d['chapter_'.$w.'_text'])."</p></article>";}
                $html.="<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='grid gap-10 lg:grid-cols-[.9fr_1.1fr]'><div><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl lg:text-6xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-6 max-w-xl text-base leading-7 {$muted}'>".e($d['text'])."</p><a href='".e($d['primary_url'])."' class='mt-7 inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div><div class='grid gap-4 sm:grid-cols-2'>{$chapters}</div></div></div></section>";
                break;

                case 'about_awards_timeline':
                $d=array_merge(['eyebrow'=>'RECOGNITION','heading'=>'Selected recognition along the way.','text'=>'Use this section only for awards, shortlistings, certifications, or recognitions the business can verify.','award_one_year'=>'2022','award_one_title'=>'Industry Award','award_one_org'=>'Awarding organisation','award_two_year'=>'2023','award_two_title'=>'Design Recognition','award_two_org'=>'Awarding organisation','award_three_year'=>'2024','award_three_title'=>'Customer Experience Award','award_three_org'=>'Awarding organisation','award_four_year'=>'2025','award_four_title'=>'Innovation Recognition','award_four_org'=>'Awarding organisation','footnote'=>'Replace all starter entries with verified recognition before publishing.'],$block);
                $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $items=''; $awardsCount=max(1,min(4,(int)($d['about_awards_count']??4))); foreach(array_slice(['one','two','three','four'],0,$awardsCount) as $w){$items.="<article class='grid gap-4 border-t py-6 sm:grid-cols-[120px_1fr_auto] sm:items-center {$border}'><span class='text-sm font-black {$muted}'>".e($d['award_'.$w.'_year'])."</span><h3 class='text-2xl font-semibold tracking-[-.03em] {$theme['text']}'>".e($d['award_'.$w.'_title'])."</h3><span class='rounded-full border px-4 py-2 text-xs font-bold {$border} {$card}'>".e($d['award_'.$w.'_org'])."</span></article>";}
                $html.="<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-5 text-base leading-7 {$muted}'>".e($d['text'])."</p></div><div class='mt-12 border-b {$border}'>{$items}</div><p class='mt-5 text-xs {$muted}'>".e($d['footnote'])."</p></div></section>";
                break;

                case 'about_culture_section':
                $d=array_merge(['eyebrow'=>'HOW WE WORK','heading'=>'A culture built around useful work and good people.','text'=>'Show the behaviours that shape everyday decisions, collaboration, and the experience of working with the team.','pillar_one_title'=>'Be clear','pillar_one_text'=>'Say what matters, remove ambiguity, and make the next step easy to understand.','pillar_two_title'=>'Stay curious','pillar_two_text'=>'Ask better questions and keep learning instead of defaulting to familiar answers.','pillar_three_title'=>'Own the outcome','pillar_three_text'=>'Take responsibility for the result and help the whole team move forward.','pillar_four_title'=>'Respect the craft','pillar_four_text'=>'Care about the details without losing sight of the customer or the goal.','closing_line'=>'The best culture is visible in the work, not just written on the wall.'],$block);
                $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $soft=$isPrimarySection?'bg-black/10 text-white':"{$primaryTheme['card']} {$primaryTheme['text']}"; $softMuted=$isPrimarySection?'text-white/70':$primaryTheme['sub']; $softBorder=$isPrimarySection?'border-white/20':$primaryTheme['border']; $pillars=''; $cultureCount=max(1,min(4,(int)($d['about_culture_count']??4))); foreach(array_slice(['one','two','three','four'],0,$cultureCount) as $i=>$w){$cls=$i===0?$soft:$card; $itemMuted=$i===0?$softMuted:$muted; $itemBorder=$i===0?$softBorder:$border; $pillars.="<article class='rounded-[2rem] border p-7 {$itemBorder} {$cls}'><span class='text-xs font-black {$itemMuted}'>0".($i+1)."</span><h3 class='mt-10 text-2xl font-semibold tracking-[-.03em]'>".e($d['pillar_'.$w.'_title'])."</h3><p class='mt-4 text-sm leading-6 {$itemMuted}'>".e($d['pillar_'.$w.'_text'])."</p></article>";}
                $html.="<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='grid gap-10 lg:grid-cols-12'><div class='lg:col-span-5'><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-5 text-base leading-7 {$muted}'>".e($d['text'])."</p></div><div class='grid gap-4 sm:grid-cols-2 lg:col-span-7'>{$pillars}</div></div><div class='mt-5 rounded-[2rem] border px-6 py-5 text-center text-sm font-semibold {$border} {$card}'>".e($d['closing_line'])."</div></div></section>";
                break;

                case 'about_office_gallery':
                $d=array_merge(['eyebrow'=>'INSIDE THE STUDIO','heading'=>'A place designed for focused work and good collaboration.','text'=>'Use real workplace, studio, venue, clinic, showroom, or team-environment photography to make the business feel tangible.','image_one_url'=>'/storage/cms-images/background/background-1.avif','image_one_caption'=>'Main workspace','image_two_url'=>'/storage/cms-images/background/background-2.avif','image_two_caption'=>'Collaboration space','image_three_url'=>'/storage/cms-images/background/background-3.avif','image_three_caption'=>'Details that make it ours'],$block);
                $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub'];
                $html.="<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-bold uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-5 text-base leading-7 {$muted}'>".e($d['text'])."</p></div><div class='mt-12 grid gap-4 lg:grid-cols-12'><figure class='relative min-h-[520px] overflow-hidden rounded-[2rem] lg:col-span-7'><img src='".e($d['image_one_url'])."' alt='' class='absolute inset-0 h-full w-full object-cover'><figcaption class='absolute bottom-4 left-4 rounded-full bg-black/65 px-4 py-2 text-xs font-bold text-white'>".e($d['image_one_caption'])."</figcaption></figure><div class='grid gap-4 lg:col-span-5'><figure class='relative min-h-[250px] overflow-hidden rounded-[2rem]'><img src='".e($d['image_two_url'])."' alt='' class='absolute inset-0 h-full w-full object-cover'><figcaption class='absolute bottom-4 left-4 rounded-full bg-black/65 px-4 py-2 text-xs font-bold text-white'>".e($d['image_two_caption'])."</figcaption></figure><figure class='relative min-h-[250px] overflow-hidden rounded-[2rem]'><img src='".e($d['image_three_url'])."' alt='' class='absolute inset-0 h-full w-full object-cover'><figcaption class='absolute bottom-4 left-4 rounded-full bg-black/65 px-4 py-2 text-xs font-bold text-white'>".e($d['image_three_caption'])."</figcaption></figure></div></div></div></section>";
                break;

                case 'services_sticky_scroll':
                case 'services_horizontal':
                $isHorizontal = $type === 'services_horizontal';
                $d = array_merge(['eyebrow'=>$isHorizontal?'CAPABILITIES':'WHAT WE DO','heading'=>$isHorizontal?'Explore the work from left to right.':'Specialist services, connected by one clear strategy.','text'=>'A focused set of complementary services designed around practical customer outcomes.','primary_label'=>$isHorizontal?'Talk to us':'Start a project','primary_url'=>'#'], $block);
                $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $buttonBg=$isPrimarySection?'bg-white':$primaryTheme['bg']; $buttonText=$isPrimarySection?'text-slate-950':$primaryTheme['text'];
                $defaults=[['01','Strategy','Define priorities and a practical direction.'],['02','Brand systems','Create a consistent identity and message system.'],['03','Experience design','Shape clear journeys around customer needs.'],['04','Digital platforms','Build responsive, scalable digital experiences.'],['05','Growth systems','Connect content, campaigns, conversion, and measurement.'],['06','Optimisation','Improve performance through testing and insight.']]; $serviceCount=max(1,min(6,(int)($d['service_count']??6))); $cards=''; foreach(array_slice(['one','two','three','four','five','six'],0,$serviceCount) as $i=>$w){$num=e($d['service_'.$w.'_number']??$defaults[$i][0]);$title=e($d['service_'.$w.'_title']??$defaults[$i][1]);$text=e($d['service_'.$w.'_text']??$defaults[$i][2]); if($isHorizontal){$cards.="<article class='min-w-[78%] snap-start rounded-[2rem] border p-7 sm:min-w-[46%] lg:min-w-[31%] {$border} {$card}'><span class='text-xs font-black tracking-[.2em] {$muted}'>{$num}</span><h3 class='mt-16 text-3xl font-semibold tracking-[-.035em]'>{$title}</h3><p class='mt-4 leading-7 {$muted}'>{$text}</p></article>";}else{$cards.="<article class='rounded-[2rem] border p-6 sm:p-8 {$border} {$card}'><div class='flex gap-5'><span class='pt-1 text-xs font-black tracking-[.2em] {$muted}'>{$num}</span><div><h3 class='text-2xl font-semibold tracking-[-.03em] sm:text-3xl'>{$title}</h3><p class='mt-3 leading-7 {$muted}'>{$text}</p></div></div></article>";}}
                if($isHorizontal){$html.="<section class='overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between'><div class='max-w-3xl'><span class='text-xs font-black uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-4 leading-7 {$muted}'>".e($d['text'])."</p></div><a href='".e($d['primary_url'])."' class='inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div><div class='cosmic-hide-scrollbar mt-10 flex snap-x gap-4 overflow-x-auto pb-3' style='scrollbar-width:none;-ms-overflow-style:none'>{$cards}</div></div></section>";}else{$html.="<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto grid max-w-7xl gap-10 lg:grid-cols-[.75fr_1.25fr] lg:gap-16'><div class='lg:sticky lg:top-24 lg:self-start'><span class='text-xs font-black uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-5 leading-7 {$muted}'>".e($d['text'])."</p><a href='".e($d['primary_url'])."' class='mt-7 inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div><div class='space-y-4'>{$cards}</div></div></section>";}
                break;

                case 'services_interactive_tabs':
                $d=array_merge(['eyebrow'=>'SERVICES','heading'=>'One team. Four connected disciplines.','text'=>'Explore the service areas that work together to move the business forward.','primary_label'=>'Discuss your needs','primary_url'=>'/contact'],$block); $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $buttonBg=$isPrimarySection?'bg-white':$primaryTheme['bg']; $buttonText=$isPrimarySection?'text-slate-950':$primaryTheme['text']; $defs=[['Strategy','Set the direction','Define priorities, audience needs, positioning, and the clearest path forward.'],['Brand','Build the system','Create a distinctive identity with practical rules for consistent use.'],['Experience','Design the journey','Turn customer needs into clear interfaces, content, and interactions.'],['Technology','Ship the platform','Build fast, maintainable digital experiences that can evolve with the business.']]; $tabCount=max(1,min(4,(int)($d['tab_count']??4))); $id='cosmic-service-tabs-'.$index; $buttons=''; $panels=''; foreach(array_slice(['one','two','three','four'],0,$tabCount) as $i=>$w){$label=e($d['tab_'.$w.'_label']??$defs[$i][0]);$title=e($d['tab_'.$w.'_title']??$defs[$i][1]);$text=e($d['tab_'.$w.'_text']??$defs[$i][2]);$sel=$i===0?'true':'false';$hide=$i===0?'':' hidden';$btnClass=$i===0?"{$buttonBg} {$buttonText}":"{$border} {$card}";$buttons.="<button type='button' role='tab' aria-selected='{$sel}' data-tab='{$i}' class='w-full rounded-2xl border px-5 py-4 text-left text-sm font-bold {$btnClass}'>{$label}</button>";$panels.="<article role='tabpanel' data-panel='{$i}' class='min-h-[330px] rounded-[2rem] border p-7 sm:p-10 {$border} {$card}{$hide}'><span class='text-xs font-black tracking-[.2em] {$muted}'>0".($i+1)."</span><h3 class='mt-16 text-3xl font-semibold tracking-[-.035em] sm:text-4xl'>{$title}</h3><p class='mt-5 max-w-2xl text-base leading-7 sm:text-lg {$muted}'>{$text}</p><a href='".e($d['primary_url']?:'/contact')."' class='mt-8 inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></article>";} $html.="<section id='{$id}' class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-black uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-4 leading-7 {$muted}'>".e($d['text'])."</p></div><div class='mt-10 grid gap-4 lg:grid-cols-[.65fr_1.35fr]'><div class='space-y-2' role='tablist'>{$buttons}</div><div>{$panels}</div></div></div><script>(function(){var r=document.getElementById('{$id}');if(!r)return;var bs=r.querySelectorAll('[data-tab]'),ps=r.querySelectorAll('[data-panel]');bs.forEach(function(b){b.addEventListener('click',function(){var n=b.getAttribute('data-tab');bs.forEach(function(x){x.setAttribute('aria-selected',x===b?'true':'false');});ps.forEach(function(p){p.hidden=p.getAttribute('data-panel')!==n;});});});})();</script></section>"; break;

                case 'services_mega_grid':
                $d=array_merge(['eyebrow'=>'FULL CAPABILITY','heading'=>'Everything needed to move from idea to growth.','text'=>'A broad set of connected specialist capabilities.','primary_label'=>'View all capabilities','primary_url'=>'#'],$block); $primaryTheme=self::getTheme($primaryColor); $resolvedTheme=(string)($blockTheme??$block['resolvedTheme']??$selectedThemeName??'surface'); $isPrimarySection=$resolvedTheme==='primary'; $muted=$isPrimarySection?'text-white/70':$theme['sub']; $border=$isPrimarySection?'border-white/20':$theme['border']; $card=$isPrimarySection?'bg-white/10 text-white':"{$theme['surface']} {$theme['text']}"; $buttonBg=$isPrimarySection?'bg-white':$primaryTheme['bg']; $buttonText=$isPrimarySection?'text-slate-950':$primaryTheme['text']; $defs=[['Strategy','Direction, positioning, roadmaps, and priorities.'],['Research','Audience insight, market review, and opportunity mapping.'],['Brand','Identity, messaging, guidelines, and brand systems.'],['Content','Content strategy, copy, editorial, and campaign assets.'],['UX / UI','Journeys, prototypes, interfaces, and design systems.'],['Development','Websites, applications, integrations, and technical delivery.'],['Campaigns','Launch planning, lifecycle, and conversion.'],['Optimisation','Analytics, testing, iteration, and performance improvement.']]; $itemCount=max(1,min(8,(int)($d['item_count']??8))); $items=''; foreach(array_slice(['one','two','three','four','five','six','seven','eight'],0,$itemCount) as $i=>$w){$items.="<article class='rounded-[1.5rem] border p-5 sm:p-6 {$border} {$card}'><span class='text-xs font-black tracking-[.2em] {$muted}'>".str_pad((string)($i+1),2,'0',STR_PAD_LEFT)."</span><h3 class='mt-8 text-xl font-semibold'>".e($d['item_'.$w.'_title']??$defs[$i][0])."</h3><p class='mt-2 text-sm leading-6 {$muted}'>".e($d['item_'.$w.'_text']??$defs[$i][1])."</p></article>";} $html.="<section class='px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='grid gap-8 lg:grid-cols-[.8fr_1.2fr]'><div><span class='text-xs font-black uppercase tracking-[.28em] {$muted}'>".e($d['eyebrow'])."</span><h2 class='mt-5 text-4xl font-semibold tracking-[-.045em] sm:text-5xl {$theme['text']}'>".e($d['heading'])."</h2><p class='mt-4 leading-7 {$muted}'>".e($d['text'])."</p><a href='".e($d['primary_url'])."' class='mt-7 inline-flex min-h-[48px] items-center rounded-full px-7 text-sm font-bold {$buttonBg} {$buttonText}'>".e($d['primary_label'])."</a></div><div class='grid gap-3 sm:grid-cols-2'>{$items}</div></div></div></section>"; break;

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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $muted = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $border = $isPrimarySection ? 'border-white/20' : $theme['border'];
                $card = $isPrimarySection ? 'bg-white/10 text-white' : "{$theme['surface']} {$theme['text']}";
                $hoverBg = $isPrimarySection ? '#ffffff' : (string) ($primaryTheme['palette']['background'] ?? '#0B5D4B');
                $hoverFg = $isPrimarySection ? '#0f172a' : '#ffffff';
                $buttonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $buttonText = $isPrimarySection ? 'text-slate-950' : $primaryTheme['text'];
                $cardsHtml = '';
                $serviceCount=max(1,min(6,(int)($d['service_count']??6)));
                foreach (array_slice(['one','two','three','four','five','six'],0,$serviceCount) as $word) {
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
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
                $featureRowCount=max(1,min(8,(int)($d['feature_row_count']??8)));
                foreach (array_slice(['one','two','three','four','five','six','seven','eight'],0,$featureRowCount) as $word) {
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $muted = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $border = $isPrimarySection ? 'border-white/20' : $theme['border'];
                $baseCard = $isPrimarySection ? 'bg-white/10 text-white' : "{$theme['surface']} {$theme['text']}";
                $featuredCard = $isPrimarySection ? 'bg-white text-slate-950' : "{$primaryTheme['card']} {$primaryTheme['text']}";
                $featuredMuted = $isPrimarySection ? 'text-slate-600' : $primaryTheme['sub'];
                $featuredBorder = $isPrimarySection ? $border : $primaryTheme['border'];
                $buttonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $buttonText = $isPrimarySection ? 'text-slate-950' : $primaryTheme['text'];
                $plans = [
                    ['starter', $d['starter_name'], $d['starter_price'], $d['starter_period'], $d['starter_description'], $d['starter_button_label'], $d['starter_button_url'], false],
                    ['growth', $d['growth_name'], $d['growth_price'], $d['growth_period'], $d['growth_description'], $d['growth_button_label'], $d['growth_button_url'], true],
                    ['pro', $d['pro_name'], $d['pro_price'], $d['pro_period'], $d['pro_description'], $d['pro_button_label'], $d['pro_button_url'], false],
                ];
                $plansHtml='';
                foreach($plans as [$key,$name,$price,$period,$description,$label,$url,$featured]){
                    $cardClass=$featured?$featuredCard:$baseCard; $cardMuted=$featured?$featuredMuted:$muted; $cardBorder=$featured?$featuredBorder:$border;
                    $badge=$featured?"<span class='mb-5 inline-flex rounded-full px-3 py-1 text-[10px] font-black tracking-[.16em] {$primaryTheme['bg']} text-white'>".e($d['growth_badge'])."</span>":'';
                    $plansHtml.="<article class='border-b p-6 sm:p-7 {$cardBorder} {$cardClass}'>{$badge}<h3 class='text-xl font-semibold'>".e($name)."</h3><div class='mt-4 flex items-end gap-2'><strong class='text-4xl font-semibold tracking-[-.04em]'>".e($price)."</strong><span class='mb-1 text-xs font-semibold uppercase tracking-wider {$cardMuted}'>".e($period)."</span></div><p class='mt-4 text-sm leading-6 {$cardMuted}'>".e($description)."</p><a href='".e($url)."' class='mt-6 inline-flex min-h-[46px] w-full items-center justify-center rounded-full px-5 text-sm font-bold {$buttonBg} {$buttonText}'>".e($label)."</a></article>";
                }
                $rowsHtml='';
                $comparisonRowCount=max(1,min(6,(int)($d['comparison_row_count']??6)));
                foreach(array_slice(['one','two','three','four','five','six'],0,$comparisonRowCount) as $word){
                    $rowsHtml.="<div class='contents'><div class='border-b p-5 text-sm font-semibold lg:p-6 {$border} {$baseCard}'>".e($d['feature_'.$word])."</div><div class='border-b p-5 text-sm font-semibold lg:p-6 {$border} {$baseCard}'>".e($d['starter_'.$word])."</div><div class='border-b p-5 text-sm font-semibold lg:p-6 {$featuredBorder} {$featuredCard}'>".e($d['growth_'.$word])."</div><div class='border-b p-5 text-sm font-semibold lg:p-6 {$border} {$baseCard}'>".e($d['pro_'.$word])."</div></div>";
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $card = $isPrimarySection ? 'border-white/20 bg-white/10 text-white' : "{$theme['border']} {$theme['surface']} {$theme['text']}";
                $muted = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $soft = $isPrimarySection ? 'border-white/20 bg-slate-950/15 text-white' : "{$primaryTheme['border']} {$primaryTheme['card']} {$primaryTheme['text']}";
                $softMuted = $isPrimarySection ? 'text-white/70' : $primaryTheme['sub'];
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
                            <article class='rounded-[2rem] border p-6 lg:col-span-4 {$soft}'><strong class='block text-3xl font-semibold tracking-[-.035em]'>{$proofValue}</strong><span class='mt-3 block text-sm leading-6 {$softMuted}'>{$proofLabel}</span></article>
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


                case 'stats_animated_counters_premium':
                    $eyebrow=e($block['eyebrow']??'BY THE NUMBERS');$heading=e($block['heading']??'A clear view of the progress behind the work.');$text=e($block['text']??'Replace sample values with verified company metrics before publishing.');
                    $metrics=is_array($block['metrics']??null)?array_slice(array_values($block['metrics']),0,8):[];if(empty($metrics)){$metrics=[['value'=>'—','label'=>'Verified metric','description'=>'Add verified company data.'],['value'=>'—','label'=>'Verified metric','description'=>'Add verified company data.'],['value'=>'—','label'=>'Verified metric','description'=>'Add verified company data.'],['value'=>'—','label'=>'Verified metric','description'=>'Add verified company data.']];}
                    $cards='';foreach($metrics as $i=>$m){$n=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);$v=e($m['value']??'—');$l=e($m['label']??'Metric');$d=e($m['description']??'');$cards.="<article class='min-h-56 border-b p-7 sm:border-r lg:border-b-0 {$theme['border']} {$theme['card']}'><span class='text-xs font-bold {$theme['sub']}'>{$n}</span><strong class='mt-8 block text-5xl font-black tracking-tight {$theme['text']}'>{$v}</strong><h3 class='mt-3 font-semibold {$theme['text']}'>{$l}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$d}</p></article>";}
                    $html.="<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-10 max-w-3xl'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-semibold sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p></div><div class='grid overflow-hidden rounded-3xl border sm:grid-cols-2 lg:grid-cols-4 {$theme['border']}'>{$cards}</div></div></section>";
                    break;

                case 'stats_revenue_dashboard_premium':
                    $eyebrow=e($block['eyebrow']??'PERFORMANCE SNAPSHOT');$heading=e($block['heading']??'Commercial performance at a glance.');$text=e($block['text']??'Use only supplied and verified financial figures.');$period=e($block['period_label']??'Selected period');
                    $metrics=is_array($block['metrics']??null)?array_slice(array_values($block['metrics']),0,8):[];if(empty($metrics)){$metrics=[['value'=>'—','label'=>'Revenue','description'=>'Add verified revenue.'],['value'=>'—','label'=>'Recurring revenue','description'=>'Add a verified value.'],['value'=>'—','label'=>'Average order','description'=>'Add a verified average.'],['value'=>'—','label'=>'Active accounts','description'=>'Add a verified count.']];}
                    $cards='';foreach($metrics as $m){$v=e($m['value']??'—');$l=e($m['label']??'Metric');$d=e($m['description']??'');$cards.="<article class='rounded-2xl border p-5 {$theme['border']}'><p class='text-sm font-medium {$theme['sub']}'>{$l}</p><strong class='mt-5 block text-3xl {$theme['text']}'>{$v}</strong><p class='mt-3 text-xs leading-5 {$theme['sub']}'>{$d}</p></article>";}
                    $html.="<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-10 max-w-3xl'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-semibold sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p></div><div class='rounded-3xl border p-5 sm:p-7 {$theme['border']} {$theme['card']}'><div class='mb-6 flex items-center justify-between gap-4'><span class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>Dashboard</span><span class='text-sm {$theme['sub']}'>{$period}</span></div><div class='grid gap-4 sm:grid-cols-2 lg:grid-cols-4'>{$cards}</div></div></div></section>";
                    break;

                case 'stats_growth_charts_premium':
                    $eyebrow=e($block['eyebrow']??'GROWTH');$heading=e($block['heading']??'See the direction, not just the headline.');$text=e($block['text']??'Replace sample trend data with verified values.');$label=e($block['chart_label']??'Six-period trend');
                    $series=is_array($block['series']??null)?array_slice(array_values($block['series']),0,6):[];if(empty($series)){$series=[['label'=>'P1','value'=>28],['label'=>'P2','value'=>38],['label'=>'P3','value'=>46],['label'=>'P4','value'=>58],['label'=>'P5','value'=>72],['label'=>'P6','value'=>84]];}$bars='';foreach($series as $p){$pl=e($p['label']??'');$pv=max(8,min(100,(int)($p['value']??0)));$bars.="<div class='flex min-w-0 flex-1 flex-col items-center justify-end gap-3'><div class='flex h-48 w-full items-end'><div class='w-full rounded-t-xl bg-current opacity-20' style='height:{$pv}%'></div></div><span class='text-xs {$theme['sub']}'>{$pl}</span></div>";}
                    $metrics=is_array($block['metrics']??null)?array_slice(array_values($block['metrics']),0,3):[];if(empty($metrics)){$metrics=[['value'=>'—','label'=>'Growth rate','description'=>'Add a verified rate.'],['value'=>'—','label'=>'New customers','description'=>'Add a verified count.'],['value'=>'—','label'=>'Retention','description'=>'Add a verified rate.']];}$cards='';foreach($metrics as $m){$v=e($m['value']??'—');$l=e($m['label']??'');$d=e($m['description']??'');$cards.="<article class='rounded-3xl border p-5 {$theme['border']} {$theme['card']}'><strong class='block text-3xl {$theme['text']}'>{$v}</strong><h3 class='mt-2 font-semibold {$theme['text']}'>{$l}</h3><p class='mt-2 text-xs leading-5 {$theme['sub']}'>{$d}</p></article>";}
                    $html.="<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-10 max-w-3xl'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-semibold sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p></div><div class='grid gap-6 lg:grid-cols-[1.5fr_.8fr]'><div class='rounded-3xl border p-6 {$theme['border']} {$theme['card']}'><strong class='text-sm {$theme['text']}'>{$label}</strong><div class='mt-8 flex h-64 items-end gap-3'>{$bars}</div></div><div class='grid gap-3'>{$cards}</div></div></div></section>";
                    break;

                case 'stats_achievements_premium':
                    $eyebrow=e($block['eyebrow']??'MILESTONES');$heading=e($block['heading']??'Moments worth marking.');$text=e($block['text']??'Use real milestones or clearly editable placeholders until verified.');$items=is_array($block['achievements']??null)?array_slice(array_values($block['achievements']),0,8):[];if(empty($items)){$items=[['year'=>'20XX','badge'=>'Milestone','title'=>'Company milestone','text'=>'Replace with a verified achievement.'],['year'=>'20XX','badge'=>'Recognition','title'=>'Recognition or award','text'=>'Publish only when supplied and verified.'],['year'=>'20XX','badge'=>'Growth','title'=>'Growth milestone','text'=>'Use factual company history.'],['year'=>'20XX','badge'=>'Impact','title'=>'Impact milestone','text'=>'Avoid unsupported performance claims.']];}$cards='';foreach($items as $a){$y=e($a['year']??'20XX');$b=e($a['badge']??'Milestone');$t=e($a['title']??'');$d=e($a['text']??'');$cards.="<article class='min-h-64 p-7 {$theme['card']}'><div class='flex items-center justify-between gap-3'><strong class='text-sm {$theme['text']}'>{$y}</strong><span class='rounded-full border px-3 py-1 text-xs {$theme['border']} {$theme['sub']}'>{$b}</span></div><h3 class='mt-12 text-2xl font-semibold {$theme['text']}'>{$t}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$d}</p></article>";}
                    $html.="<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-10 max-w-3xl'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-semibold sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p></div><div class='grid gap-px overflow-hidden rounded-3xl border sm:grid-cols-2 {$theme['border']}'>{$cards}</div></div></section>";
                    break;

                case 'stats_global_presence_premium':
                    $eyebrow=e($block['eyebrow']??'GLOBAL PRESENCE');$heading=e($block['heading']??'Where the work reaches.');$text=e($block['text']??'Replace placeholders with verified offices, markets, regions, or service areas before publishing.');$items=is_array($block['locations']??null)?array_slice(array_values($block['locations']),0,8):[];if(empty($items)){$items=[['region'=>'Region 01','value'=>'—','label'=>'Markets served','description'=>'Add a verified location or coverage fact.'],['region'=>'Region 02','value'=>'—','label'=>'Active locations','description'=>'Use supplied geographic information only.'],['region'=>'Region 03','value'=>'—','label'=>'Local presence','description'=>'Replace with a real office, market, or service area.'],['region'=>'Region 04','value'=>'—','label'=>'Coverage','description'=>'Avoid unsupported global-reach claims.']];}$cards='';foreach($items as $item){$r=e($item['region']??'Region');$v=e($item['value']??'—');$l=e($item['label']??'');$d=e($item['description']??'');$cards.="<article class='rounded-3xl border p-5 {$theme['border']} {$theme['card']}'><span class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$r}</span><strong class='mt-6 block text-3xl {$theme['text']}'>{$v}</strong><h3 class='mt-2 font-semibold {$theme['text']}'>{$l}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$d}</p></article>";}
                    $html.="<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-10 max-w-3xl'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-semibold sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p></div><div class='grid gap-6 lg:grid-cols-[.9fr_1.1fr]'><div class='relative min-h-[360px] overflow-hidden rounded-3xl border p-7 {$theme['border']} {$theme['card']}'><div class='absolute inset-0 opacity-30' style='background-image:radial-gradient(circle at 20% 35%, currentColor 0 2px, transparent 3px),radial-gradient(circle at 58% 28%, currentColor 0 2px, transparent 3px),radial-gradient(circle at 74% 62%, currentColor 0 2px, transparent 3px),radial-gradient(circle at 36% 72%, currentColor 0 2px, transparent 3px);background-size:120px 100px'></div><div class='relative flex min-h-[304px] flex-col justify-between'><span class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>Presence overview</span><div><strong class='block text-6xl {$theme['text']}'>04</strong><p class='mt-3 text-sm {$theme['sub']}'>Editable regions or markets. Replace all placeholders with verified geographic data.</p></div></div></div><div class='grid gap-3 sm:grid-cols-2'>{$cards}</div></div></div></section>";
                    break;

                case 'stats_timeline_metrics_premium':
                    $eyebrow=e($block['eyebrow']??'PROGRESS OVER TIME');$heading=e($block['heading']??'A measurable story, period by period.');$text=e($block['text']??'Use only verified dates and figures before publishing.');$items=is_array($block['periods']??null)?array_slice(array_values($block['periods']),0,8):[];if(empty($items)){$items=[['period'=>'20XX','value'=>'—','label'=>'Metric or milestone','description'=>'Replace with a verified historical figure.'],['period'=>'20XX','value'=>'—','label'=>'Metric or milestone','description'=>'Use factual business history only.'],['period'=>'20XX','value'=>'—','label'=>'Metric or milestone','description'=>'Add a supplied period and measured result.'],['period'=>'20XX','value'=>'—','label'=>'Metric or milestone','description'=>'Do not publish unsupported growth claims.']];}$cards='';$i=0;foreach($items as $item){$i++;$period=e($item['period']??'20XX');$value=e($item['value']??'—');$label=e($item['label']??'');$desc=e($item['description']??'');$cards.="<article class='rounded-3xl border p-6 {$theme['border']} {$theme['card']}'><span class='flex h-7 w-7 items-center justify-center rounded-full border text-xs {$theme['border']} {$theme['text']}'>{$i}</span><span class='mt-6 block text-sm font-bold {$theme['sub']}'>{$period}</span><strong class='mt-5 block text-4xl {$theme['text']}'>{$value}</strong><h3 class='mt-3 font-semibold {$theme['text']}'>{$label}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$desc}</p></article>";}
                    $html.="<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-10 max-w-3xl'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-semibold sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p></div><div class='grid gap-4 lg:grid-cols-4'>{$cards}</div></div></section>";
                    break;

                case 'team_culture_premium':
                    $eyebrow=e($block['eyebrow']??'OUR CULTURE');$heading=e($block['heading']??'How we work together.');$text=e($block['text']??'A practical set of principles that shape how the team collaborates and delivers.');$label=e($block['culture_label']??'What guides us');
                    $values=is_array($block['values']??null)?array_slice(array_values($block['values']),0,8):[]; if(empty($values)){$values=[['title'=>'Clear communication','text'=>'Share context early and keep decisions understandable.'],['title'=>'Thoughtful craft','text'=>'Care about the details that improve the final experience.'],['title'=>'Shared ownership','text'=>'Take responsibility for outcomes, not just assigned tasks.'],['title'=>'Keep improving','text'=>'Learn from feedback and refine the way the team works.']];}
                    $valueCount=str_pad((string)count($values),2,'0',STR_PAD_LEFT);$cards='';foreach($values as $i=>$v){$n=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);$title=e($v['title']??'');$copy=e($v['text']??'');$cards.="<article class='min-h-52 p-6 {$theme['card']}'><span class='text-xs font-bold {$theme['sub']}'>{$n}</span><h3 class='mt-10 text-xl font-semibold {$theme['text']}'>{$title}</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$copy}</p></article>";}
                    $html.="<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='mb-10 max-w-3xl'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-semibold sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p></div><div class='mb-7 flex items-end justify-between gap-5'><strong class='{$theme['text']}'>{$label}</strong><span class='text-xs uppercase tracking-wider {$theme['sub']}'>{$valueCount} principles</span></div><div class='grid gap-px overflow-hidden rounded-3xl border sm:grid-cols-2 lg:grid-cols-4 {$theme['border']}'>{$cards}</div></div></section>";
                    break;

                case 'team_open_positions_premium':
                    $eyebrow=e($block['eyebrow']??'JOIN THE TEAM');$heading=e($block['heading']??'Open roles for people who care about the work.');$text=e($block['text']??'Explore current opportunities and find the role that best matches your strengths.');$label=e($block['positions_label']??'Current openings');$cta=e($block['primary_label']??'View all opportunities');$url=e($block['primary_url']??'#');
                    $positions=is_array($block['positions']??null)?array_slice(array_values($block['positions']),0,8):[]; if(empty($positions)){$positions=[['title'=>'Product Designer','meta'=>'Design · Example role','location'=>'Flexible / Hybrid','summary'=>'Editable placeholder opening for a design role.'],['title'=>'Frontend Developer','meta'=>'Engineering · Example role','location'=>'Flexible / Hybrid','summary'=>'Editable placeholder opening for an engineering role.'],['title'=>'Client Success Lead','meta'=>'Client Experience · Example role','location'=>'Flexible / Hybrid','summary'=>'Editable placeholder opening for a customer role.'],['title'=>'Content Strategist','meta'=>'Marketing · Example role','location'=>'Remote','summary'=>'Editable placeholder opening for a content role.']];}
                    $rows='';foreach($positions as $job){$jt=e($job['title']??'');$meta=e($job['meta']??'');$loc=e($job['location']??'');$sum=e($job['summary']??'');$rows.="<article class='grid gap-4 p-6 md:grid-cols-[1.2fr_.8fr_2fr_auto] md:items-center'><div><h3 class='text-lg font-semibold {$theme['text']}'>{$jt}</h3><p class='mt-1 text-sm {$theme['sub']}'>{$meta}</p></div><p class='text-sm font-medium {$theme['text']}'>{$loc}</p><p class='text-sm leading-6 {$theme['sub']}'>{$sum}</p><span class='text-sm font-semibold {$theme['text']}'>Explore →</span></article>";}
                    $html.="<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-6xl'><div class='mb-10 max-w-3xl'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-semibold sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p></div><div class='mb-6 flex flex-wrap items-center justify-between gap-4'><strong class='{$theme['text']}'>{$label}</strong><a href='{$url}' class='rounded-full border px-4 py-2 text-sm font-semibold {$theme['border']} {$theme['text']}'>{$cta}</a></div><div class='divide-y rounded-3xl border {$theme['border']} {$theme['card']}'>{$rows}</div></div></section>";
                    break;

                case 'team_cards_premium':
                case 'team_timeline_premium':
                case 'team_org_chart_premium':
                case 'team_leadership_premium':
                    $eyebrow=e($block['eyebrow']??'MEET THE TEAM');$heading=e($block['heading']??'People who make the work happen.');$text=e($block['text']??'A focused team built around clear thinking and dependable delivery.');
                    $members=is_array($block['members']??null)?array_values($block['members']):[];
                    if(empty($members)){$members=[['name'=>'Alex Morgan','role'=>'Founder & Director','bio'=>'Guides the team with a clear, client-first approach.','image_url'=>'/storage/cms-images/avatars/avatar-1.jpg'],['name'=>'Jordan Lee','role'=>'Client Experience Lead','bio'=>'Keeps projects organized and responsive.','image_url'=>'/storage/cms-images/avatars/avatar-2.jpg'],['name'=>'Taylor Brooks','role'=>'Creative Lead','bio'=>'Turns ideas into polished digital experiences.','image_url'=>'/storage/cms-images/avatars/avatar-3.jpg'],['name'=>'Casey Rivera','role'=>'Operations Manager','bio'=>'Coordinates quality and momentum.','image_url'=>'/storage/cms-images/avatars/avatar-4.jpg']];}
                    $head="<div class='mb-10 max-w-3xl'><p class='text-xs font-bold uppercase tracking-wider {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-semibold sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-4 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p></div>";
                    $cards=[]; foreach($members as $memberIndex=>$m){$name=e($m['name']??'');$role=e($m['role']??'');$bio=e($m['bio']??'');$rawImg=trim((string)($m['image_url']??''));if($rawImg===''){$rawImg='/storage/cms-images/avatars/avatar-'.(($memberIndex%4)+1).'.jpg';}$img=e(self::staticAssetUrl($rawImg));$cards[]="<article class='overflow-hidden rounded-3xl border {$theme['border']} {$theme['card']}'><img src='{$img}' alt='{$name}' width='960' height='720' loading='lazy' decoding='async' class='aspect-[4/3] w-full object-cover'><div class='p-5'><h3 class='text-lg font-semibold {$theme['text']}'>{$name}</h3><p class='mt-1 text-sm font-medium {$theme['sub']}'>{$role}</p><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$bio}</p></div></article>";}
                    if($type==='team_timeline_premium'){$members=array_slice($members,0,4);$timelineLabel=e($block['timeline_label']??'How the team grew');$rows='';foreach($members as $i=>$m){$name=e($m['name']??'');$role=e($m['role']??'');$bio=e($m['bio']??'');$n=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);$rows.="<div class='relative grid gap-4 py-6 pl-8 md:grid-cols-[80px_1fr_2fr]'><span class='absolute -left-1.5 top-8 h-3 w-3 rounded-full bg-current'></span><span class='text-xs font-bold {$theme['sub']}'>{$n}</span><strong class='{$theme['text']}'>{$name}</strong><div><p class='text-sm font-medium {$theme['text']}'>{$role}</p><p class='mt-2 text-sm {$theme['sub']}'>{$bio}</p></div></div>";}$body="<div class='mb-6 text-sm font-semibold {$theme['text']}'>{$timelineLabel}</div><div class='border-l {$theme['border']}'>{$rows}</div>";
                    }elseif($type==='team_org_chart_premium'){$cards=array_slice($cards,0,4);$chartLabel=e($block['chart_label']??'How we work together');$top=$cards[0]??'';$lower=implode('',array_slice($cards,1,3));$body="<div class='mb-8 text-center text-sm font-semibold {$theme['sub']}'>{$chartLabel}</div><div class='mx-auto max-w-sm'>{$top}</div><div class='mx-auto h-10 w-px border-l {$theme['border']}'></div><div class='grid gap-5 md:grid-cols-3'>{$lower}</div>";
                    }elseif($type==='team_leadership_premium'){$leadMembers=array_slice($members,0,4);$topCards='';foreach(array_slice($leadMembers,0,2) as $i=>$m){$name=e($m['name']??'');$role=e($m['role']??'');$bio=e($m['bio']??'');$rawImg=trim((string)($m['image_url']??''));if($rawImg===''){$rawImg='/storage/cms-images/avatars/avatar-'.(($i%4)+1).'.jpg';}$img=e(self::staticAssetUrl($rawImg));$topCards.="<article class='overflow-hidden rounded-3xl border {$theme['border']} {$theme['card']}'><img src='{$img}' alt='{$name}' width='960' height='768' loading='lazy' decoding='async' class='aspect-[5/4] w-full object-cover'><div class='p-7'><h3 class='text-2xl font-semibold {$theme['text']}'>{$name}</h3><p class='mt-1 text-sm font-medium {$theme['sub']}'>{$role}</p><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$bio}</p></div></article>";}$compact='';foreach(array_slice($leadMembers,2,2) as $j=>$m){$memberIndex=$j+2;$name=e($m['name']??'');$role=e($m['role']??'');$rawImg=trim((string)($m['image_url']??''));if($rawImg===''){$rawImg='/storage/cms-images/avatars/avatar-'.(($memberIndex%4)+1).'.jpg';}$img=e(self::staticAssetUrl($rawImg));$compact.="<div class='flex items-center gap-4'><img src='{$img}' alt='' width='112' height='112' loading='lazy' decoding='async' class='h-14 w-14 rounded-2xl object-cover'><div><strong class='{$theme['text']}'>{$name}</strong><p class='text-sm {$theme['sub']}'>{$role}</p></div></div>";}$body="<div class='grid gap-7 lg:grid-cols-2'>{$topCards}</div><div class='mt-8 grid gap-4 border-t pt-7 sm:grid-cols-2 {$theme['border']}'>{$compact}</div>";
                    }else{$body="<div class='grid gap-6 sm:grid-cols-2 lg:grid-cols-4'>".implode('',array_slice($cards,0,8))."</div>";}
                    $html.="<section class='px-6 py-20 {$theme['bg']}'><div class='mx-auto max-w-7xl'>{$head}{$body}</div></section>";
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


                case 'testimonials_video_premium':
                case 'testimonials_scrolling_marquee':
                case 'testimonials_wall_of_love':
                case 'testimonials_card_stack':
                case 'testimonials_trust_dashboard':
                case 'testimonials_review_grid':
                case 'testimonials_review_carousel_pro':
                    $eyebrow = e($block['eyebrow'] ?? 'CLIENT STORIES');
                    $heading = e($block['heading'] ?? 'Proof that feels personal.');
                    $text = e($block['text'] ?? 'Bring authentic customer voices forward with a premium editorial treatment.');
                    $defaultTestimonials = [
                        ['avatar' => '/storage/cms-images/avatars/avatar-1.jpg', 'name' => 'Alex Morgan', 'company' => 'North & Co.', 'quote' => 'Sample review — replace this with a verified customer testimonial.'],
                        ['avatar' => '/storage/cms-images/avatars/avatar-2.jpg', 'name' => 'Jordan Lee', 'company' => 'Studio Lane', 'quote' => 'Sample review — use a real quote before publishing this section.'],
                        ['avatar' => '/storage/cms-images/avatars/avatar-3.jpg', 'name' => 'Taylor Brooks', 'company' => 'Arc Works', 'quote' => 'Sample review — add customer-approved feedback here.'],
                        ['avatar' => '/storage/cms-images/avatars/avatar-4.jpg', 'name' => 'Casey Rivera', 'company' => 'Field House', 'quote' => 'Sample review — replace with authentic social proof.'],
                    ];
                    $items = array_values(is_array($block['testimonials'] ?? null) && count($block['testimonials']) > 0 ? $block['testimonials'] : $defaultTestimonials);
                    $variant = (string) ($block['type'] ?? 'testimonials_wall_of_love');
                    $uid = 'cosmic-testimonials-'.$index.'-'.substr(md5(json_encode($block)),0,8);
                    $html .= "<section id='{$uid}' class='relative overflow-hidden px-7 py-28 {$theme['bg']}'><div class='mx-auto max-w-7xl'>";
                    $html .= "<div class='mx-auto mb-14 max-w-3xl text-center'><span class='block text-xs font-semibold uppercase tracking-[.32em] {$theme['sub']}'>{$eyebrow}</span><h2 class='mt-4 block text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mx-auto mt-5 block max-w-2xl text-base leading-7 {$theme['sub']}'>{$text}</p></div>";

                    if ($variant === 'testimonials_video_premium') {
                        $rawVideo = trim((string)($block['video_url'] ?? '')) ?: 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4';
                        $videoUrl = e(self::staticAssetUrl($rawVideo));
                        $videoLabelRaw = trim((string)($block['video_label'] ?? '')); if ($videoLabelRaw === '') { $videoLabelRaw = trim((string)($block['video_url'] ?? '')) !== '' ? 'Watch customer story' : 'Demo video — replace before publishing'; } $videoLabel = e($videoLabelRaw);
                        $html .= "<div class='grid gap-6 lg:grid-cols-[1.3fr_.7fr]'><div class='relative min-h-[360px] overflow-hidden rounded-3xl border {$theme['border']} {$theme['card']}'><video class='absolute inset-0 h-full w-full object-cover' src='{$videoUrl}' controls preload='metadata' playsinline></video><span class='absolute bottom-5 left-5 rounded-full bg-black/65 px-4 py-2 text-sm font-semibold text-white'>{$videoLabel}</span></div><div class='grid gap-6'>";
                    } elseif ($variant === 'testimonials_scrolling_marquee') {
                        $html .= "<div data-marquee class='cosmic-hide-scrollbar flex gap-5 overflow-x-auto scroll-smooth pb-4' style='scrollbar-width:none;-ms-overflow-style:none'>";
                    } elseif ($variant === 'testimonials_wall_of_love') {
                        $html .= "<div class='columns-1 gap-5 space-y-5 md:columns-2 lg:columns-3'>";
                    } elseif ($variant === 'testimonials_card_stack') {
                        $html .= "<div data-stack class='relative mx-auto min-h-[260px] max-w-3xl'>";
                    } elseif ($variant === 'testimonials_trust_dashboard') {
                        $html .= "<div class='grid gap-6 lg:grid-cols-[.7fr_1.3fr]'><aside class='rounded-3xl border p-8 {$theme['border']} {$theme['card']}'><div class='text-sm {$theme['sub']}'>Customer proof</div><div class='mt-3 text-6xl font-bold {$theme['text']}'>".count($items)."</div><div class='mt-2 text-sm {$theme['sub']}'>review cards ready for verified feedback</div></aside><div class='grid gap-5 sm:grid-cols-2'>";
                    } elseif ($variant === 'testimonials_review_grid') {
                        $html .= "<div class='grid gap-5 md:grid-cols-2 lg:grid-cols-4'>";
                    } elseif ($variant === 'testimonials_review_carousel_pro') {
                        $html .= "<div class='mb-5 flex justify-end gap-2'><button type='button' data-prev aria-label='Previous review' class='h-11 w-11 rounded-full border {$theme['border']} {$theme['text']}'>←</button><button type='button' data-next aria-label='Next review' class='h-11 w-11 rounded-full border {$theme['border']} {$theme['text']}'>→</button></div><div data-carousel class='cosmic-hide-scrollbar flex snap-x snap-mandatory gap-5 overflow-x-auto scroll-smooth pb-4' style='scrollbar-width:none;-ms-overflow-style:none'>";
                    } else {
                        $html .= "<div class='mx-auto max-w-3xl space-y-4'>";
                    }

                    foreach ($items as $i => $item) {
                        $avatar = e(self::staticAssetUrl($item['avatar'] ?? '/storage/cms-images/avatars/avatar-1.jpg'));
                        $name = e($item['name'] ?? 'Customer');
                        $company = e($item['company'] ?? 'Company');
                        $quote = e($item['quote'] ?? '');
                        if ($variant === 'testimonials_card_stack') {
                            $offset = min(2, $i);
                            $wrap = $i === 0 ? "relative" : "absolute inset-x-0 top-0";
                            $style = "transform:translateY(".($offset*14)."px) scale(".(1-$offset*.035).") rotate(".($offset%2?1:-1)."deg);z-index:".(30-$offset).";opacity:".(1-$offset*.18).";";
                            if ($i > 2) { $style .= 'display:none;'; }
                            $extra = " data-stack-card data-index='{$i}' style='{$style}'";
                        } else {
                            $wrap = in_array($variant, ['testimonials_scrolling_marquee','testimonials_review_carousel_pro'], true) ? "min-w-[300px] max-w-sm flex-1 snap-center" : ($variant === 'testimonials_wall_of_love' ? 'mb-5 break-inside-avoid' : '');
                            $extra = '';
                        }
                        $html .= "<div class='{$wrap}'{$extra}><article class='rounded-3xl border p-6 {$theme['border']} {$theme['card']}'><div class='mb-5 flex items-center gap-3'><img src='{$avatar}' alt='{$name}' width='96' height='96' loading='lazy' decoding='async' class='h-12 w-12 rounded-full object-cover'><div><h3 class='block font-semibold {$theme['text']}'>{$name}</h3><p class='block text-sm {$theme['sub']}'>{$company}</p></div></div><blockquote class='block text-base leading-7 {$theme['text']}'>{$quote}</blockquote></article></div>";
                    }

                    if ($variant === 'testimonials_video_premium' || $variant === 'testimonials_trust_dashboard') $html .= "</div>";
                    $html .= "</div>";
                    if ($variant === 'testimonials_card_stack') {
                        $count = max(1,count($items));
                        $html .= "<div class='mt-8 flex items-center justify-center gap-3'><button type='button' data-stack-prev class='rounded-full border px-5 py-2 text-sm font-semibold {$theme['border']} {$theme['text']}'>Previous</button><span data-stack-count class='text-xs {$theme['sub']}'>1 / {$count}</span><button type='button' data-stack-next class='rounded-full border px-5 py-2 text-sm font-semibold {$theme['border']} {$theme['text']}'>Next</button></div>";
                    }
                    $html .= "</div></section>";

                    if ($variant === 'testimonials_scrolling_marquee') {
                        $html .= "<style>#{$uid} [data-marquee]::-webkit-scrollbar{display:none;width:0;height:0}</style><script>(function(){var r=document.getElementById('{$uid}'),el=r&&r.querySelector('[data-marquee]');if(!el)return;var pause=false,last=performance.now();el.addEventListener('mouseenter',function(){pause=true});el.addEventListener('mouseleave',function(){pause=false});el.addEventListener('focusin',function(){pause=true});el.addEventListener('focusout',function(){pause=false});function t(n){if(!pause&&el.scrollWidth>el.clientWidth){el.scrollLeft+=(n-last)*.035;if(el.scrollLeft>=el.scrollWidth-el.clientWidth-2)el.scrollLeft=0}last=n;requestAnimationFrame(t)}requestAnimationFrame(t)})();</script>";
                    } elseif ($variant === 'testimonials_review_carousel_pro') {
                        $html .= "<style>#{$uid} [data-carousel]::-webkit-scrollbar{display:none;width:0;height:0}</style><script>(function(){var r=document.getElementById('{$uid}'),el=r&&r.querySelector('[data-carousel]');if(!el)return;function m(d){el.scrollBy({left:d*Math.max(280,el.clientWidth*.72),behavior:'smooth'})}r.querySelector('[data-prev]').addEventListener('click',function(){m(-1)});r.querySelector('[data-next]').addEventListener('click',function(){m(1)})})();</script>";
                    } elseif ($variant === 'testimonials_card_stack') {
                        $html .= "<script>(function(){var r=document.getElementById('{$uid}'),c=r&&r.querySelectorAll('[data-stack-card]');if(!c||!c.length)return;var i=0,o=r.querySelector('[data-stack-count]');function s(n){i=(n+c.length)%c.length;c.forEach(function(x,j){var d=(j-i+c.length)%c.length;if(d>2){x.style.display='none';return;}x.style.display='';x.style.position=d===0?'relative':'absolute';x.style.inset=d===0?'auto':'0 0 auto 0';x.style.top=d===0?'auto':'0';x.style.transform='translateY('+(d*14)+'px) scale('+(1-d*.035)+') rotate('+(d%2?1:-1)+'deg)';x.style.zIndex=30-d;x.style.opacity=1-d*.18;});if(o)o.textContent=(i+1)+' / '+c.length}var p=r.querySelector('[data-stack-prev]'),q=r.querySelector('[data-stack-next]');if(p)p.addEventListener('click',function(){s(i-1)});if(q)q.addEventListener('click',function(){s(i+1)});s(0)})();</script>";
                    }
                    break;

                case 'testimonials_carousel':

                $tagline = e($block['tagline'] ?? 'CLIENT TESTIMONIALS');
                $heading = e($block['heading'] ?? 'Trusted By Businesses Around The World');
                $text    = e($block['text'] ?? 'See what our satisfied clients say about working with our team.');

                $testimonials = is_array($block['testimonials'] ?? null) && ($block['testimonials'] ?? []) !== []
                    ? array_values($block['testimonials'])
                    : [
                        ['avatar' => '/storage/cms-images/avatars/avatar-1.jpg', 'name' => 'John Smith', 'company' => 'ABC Construction', 'quote' => 'Professional from start to finish. The entire process exceeded our expectations.', 'rating' => 5],
                        ['avatar' => '/storage/cms-images/avatars/avatar-2.jpg', 'name' => 'Sarah Johnson', 'company' => 'Modern Interiors', 'quote' => 'Outstanding quality and communication. Highly recommended.', 'rating' => 5],
                        ['avatar' => '/storage/cms-images/avatars/avatar-3.jpg', 'name' => 'Michael Brown', 'company' => 'Prime Builders', 'quote' => 'Exceptional workmanship and attention to every detail.', 'rating' => 5],
                    ];

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

                    $sliderId = 'cosmic-slider-' . substr(sha1(json_encode($slides) . '|' . uniqid('', true)), 0, 12);
                    $interval = max(3000, (int) ($block['autoplay_interval'] ?? $block['interval'] ?? 6000));
                    $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
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
                    $parallaxId = 'cosmic-parallax-' . $index . '-' . substr(sha1(json_encode($block)), 0, 10);

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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $userBubble = $isPrimarySection ? 'bg-white text-slate-950' : "{$primaryTheme['bg']} text-white";
                $assistantBubble = $isPrimarySection ? 'border-white/20 bg-white/12 text-white' : "{$theme['card']} {$theme['border']} {$theme['text']}";
                $composerSurface = $isPrimarySection ? 'border-white/20 bg-white/10 text-white' : "{$theme['card']} {$theme['border']} {$theme['text']}";
                $familyGlow = e((string) ($primaryTheme['gradient']['glowSoft'] ?? 'rgba(124, 58, 237, 0.20)'));
                $decorOpacity = in_array($resolvedTheme, ['white', 'surface', 'stone'], true) ? '0.45' : '1';
                $chipHtml = ''; foreach ($chips as $chip) { $chipHtml .= "<span class='rounded-full border px-3 py-2 text-xs font-semibold {$theme['border']} {$theme['surface']} {$theme['sub']}'>{$chip}</span>"; }

                $html .= "
                <section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'>
                    <div class='pointer-events-none absolute inset-0' style='background:radial-gradient(circle at 75% 25%, {$familyGlow}, transparent 34%);opacity:{$decorOpacity}'></div>
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
                $isPrimarySection = $resolvedTheme === 'primary';
                $primaryButtonBg = $isPrimarySection ? 'bg-white' : $primaryTheme['bg'];
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : 'text-white';
                $softCard = $isPrimarySection ? 'border-white/20 bg-white/10 text-white' : "{$theme['border']} {$theme['surface']} {$theme['text']}";
                $softSub = $isPrimarySection ? 'text-white/70' : $theme['sub'];
                $secondaryButton = $isPrimarySection ? 'border-white/25 text-white' : "{$theme['border']} {$theme['text']}";
                $featureCard = $isPrimarySection ? 'border-white/20 bg-white/10 text-white' : "{$theme['border']} {$theme['bg']} {$theme['text']}";
                $proofCard = $isPrimarySection ? 'border-white/20 bg-slate-950/20 text-white' : "{$primaryTheme['border']} {$primaryTheme['card']} {$primaryTheme['text']}";
                $proofSub = $isPrimarySection ? 'text-white/70' : $primaryTheme['sub'];
                $familyGlow = e((string) ($primaryTheme['gradient']['glowSoft'] ?? 'rgba(124, 58, 237, 0.20)'));
                $decorOpacity = in_array($resolvedTheme, ['white', 'surface', 'stone'], true) ? '0.45' : '1';
                $imageStyle = $imageUrl ? "background-image:url('{$imageUrl}');background-size:cover;background-position:center;" : '';
                $cardHtml = ''; foreach ($cardLabels as $label) { $cardHtml .= "<div class='rounded-2xl border px-4 py-4 text-sm font-semibold {$featureCard}'>{$label}</div>"; }

                $html .= "
                <section class='relative overflow-hidden px-6 py-16 sm:px-10 sm:py-20 lg:px-14 lg:py-24 {$theme['bg']}'>
                    <div class='pointer-events-none absolute inset-0' style='background:radial-gradient(circle at 80% 12%, {$familyGlow}, transparent 28%);opacity:{$decorOpacity}'></div>
                    <div class='relative mx-auto max-w-7xl'>
                        <div class='grid gap-4 lg:grid-cols-12 lg:grid-rows-[auto_auto]'>
                            <div class='rounded-[2rem] border p-7 sm:p-10 lg:col-span-7 lg:row-span-2 {$softCard}'><span class='text-xs font-bold uppercase tracking-[.28em] {$softSub}'>{$eyebrow}</span><h1 class='mt-5 max-w-4xl text-5xl font-semibold leading-[.95] tracking-[-.055em] sm:text-6xl lg:text-7xl'>{$heading}</h1><p class='mt-6 max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 {$softSub}'>{$text}</p><div class='mt-8 flex flex-col gap-3 sm:flex-row'><a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryButtonBg} {$primaryButtonText}'>{$primaryLabel}</a><a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold {$secondaryButton}'>{$secondaryLabel}</a></div><div class='mt-10 grid gap-3 sm:grid-cols-3'>{$cardHtml}</div></div>
                            <div class='relative min-h-[310px] overflow-hidden rounded-[2rem] lg:col-span-5' style=\"{$imageStyle}\"><div class='absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-950/10 to-transparent'></div><div class='absolute inset-x-0 bottom-0 p-6 text-white sm:p-8'><strong class='text-sm font-semibold'>{$imageLabel}</strong></div></div>
                            <div class='grid gap-4 sm:grid-cols-2 lg:col-span-5'><div class='rounded-[2rem] border p-6 {$softCard}'><strong class='block text-5xl font-semibold tracking-[-.04em]'>{$metricValue}</strong><span class='mt-3 block text-sm leading-6 {$softSub}'>{$metricLabel}</span></div><div class='rounded-[2rem] border p-6 {$proofCard}'><strong class='block text-lg font-semibold'>{$proofTitle}</strong><span class='mt-3 block text-sm leading-6 {$proofSub}'>{$proofText}</span></div></div>
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
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
                            <div class='grid lg:grid-cols-[240px_minmax(0,1fr)] {$theme['surface']}'><aside class='hidden border-r p-5 lg:block {$theme['border']}'><div class='rounded-xl px-3 py-2 text-xs font-semibold {$primaryTheme['card']} {$primaryTheme['text']}'>Overview</div><div class='mt-2 rounded-xl px-3 py-2 text-xs {$theme['sub']}'>Projects</div><div class='mt-2 rounded-xl px-3 py-2 text-xs {$theme['sub']}'>Analytics</div><div class='mt-2 rounded-xl px-3 py-2 text-xs {$theme['sub']}'>Customers</div><div class='mt-2 rounded-xl px-3 py-2 text-xs {$theme['sub']}'>Automations</div></aside><div class='p-5 sm:p-7'><div class='grid gap-4 md:grid-cols-3'>{$metricCards}</div><div class='mt-4 rounded-2xl border p-5 {$theme['border']} {$theme['bg']}'><strong class='block text-sm {$theme['text']}'>{$chartLabel}</strong><div class='mt-7 flex h-40 items-end gap-2 sm:gap-3'>{$bars}</div></div></div></div>
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
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

                case 'cta_glass_premium':
                case 'cta_gradient_premium':
                case 'cta_newsletter_premium':
                case 'cta_book_demo_premium':
                case 'cta_calendly_premium':
                case 'cta_free_trial_premium':
                case 'cta_countdown_premium':
                case 'cta_limited_offer_premium':
                    $eyebrow=e($block['eyebrow'] ?? 'NEXT STEP'); $heading=e($block['heading'] ?? 'Ready to move forward?'); $text=e($block['text'] ?? 'Give visitors one clear action.'); $label=e($block['primary_label'] ?? 'Get started'); $rawPrimaryUrl=trim((string) ($block['primary_url'] ?? '')); $variant=$block['type'] ?? 'cta_glass_premium'; $fallbackPrimaryUrl=match($variant){'cta_free_trial_premium'=>'/start','cta_newsletter_premium'=>'https://example.com/newsletter','cta_limited_offer_premium'=>'https://example.com/offer','cta_book_demo_premium'=>'https://example.com/demo',default=>'https://example.com'}; $url=e(($rawPrimaryUrl === '' || $rawPrimaryUrl === '#') ? $fallbackPrimaryUrl : $rawPrimaryUrl); $primaryTheme=self::getTheme($primaryColor);
                    $ctaSectionBg = $variant === 'cta_gradient_premium' ? 'bg-gradient-to-br from-violet-950 via-slate-950 to-indigo-950' : $theme['bg'];
                    $html .= "<section class='relative overflow-hidden px-6 py-20 sm:px-8 lg:py-24 {$ctaSectionBg}'><div class='mx-auto max-w-6xl'>";
                    if ($variant === 'cta_gradient_premium') {
                        $secondary=e($block['secondary_label'] ?? 'Learn more'); $secondaryUrl=e($block['secondary_url'] ?? '#');
                        $html .= "<div class='grid items-end gap-8 rounded-[2.25rem] border border-white/15 bg-white/5 p-8 text-white shadow-2xl backdrop-blur sm:p-12 lg:grid-cols-[1fr_auto]'><span class='text-xs font-bold uppercase tracking-[.24em] text-white/70'>{$eyebrow}</span><h2 class='mt-5 max-w-4xl text-4xl font-bold tracking-tight sm:text-5xl'>{$heading}</h2><p class='mt-5 max-w-2xl leading-7 text-white/75'>{$text}</p><div class='mt-8 flex flex-wrap gap-3'><a href='{$url}' class='rounded-full bg-white px-6 py-3 text-sm font-bold text-slate-950'>{$label}</a><a href='{$secondaryUrl}' class='rounded-full border border-white/30 px-6 py-3 text-sm font-bold text-white'>{$secondary}</a></div></div>";
                    } elseif ($variant === 'cta_newsletter_premium') {
                        $placeholder=e($block['input_placeholder'] ?? 'Email address'); $privacy=e($block['privacy_note'] ?? 'Add your real privacy details before publishing.'); $rawNewsletterUrl=trim((string) ($block['primary_url'] ?? '')); $newsletterUrl=e(($rawNewsletterUrl === '' || $rawNewsletterUrl === '#') ? 'https://example.com/newsletter' : $rawNewsletterUrl);
                        $html .= "<div class='grid gap-8 rounded-[2rem] border p-8 sm:p-12 lg:grid-cols-2 lg:items-center {$theme['border']} {$theme['card']}'><div><span class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</span><h2 class='mt-5 text-4xl font-bold {$theme['text']}'>{$heading}</h2><p class='mt-5 leading-7 {$theme['sub']}'>{$text}</p></div><div><form action='{$newsletterUrl}' method='get' class='flex gap-2 rounded-2xl border p-2 {$theme['border']} {$theme['bg']}'><input required type='email' name='email' aria-label='Email address' placeholder='{$placeholder}' class='min-w-0 flex-1 bg-transparent px-3 py-2 {$theme['text']}'/><button type='submit' class='rounded-xl {$primaryTheme['bg']} {$primaryTheme['text']} px-5 py-3 text-sm font-bold'>{$label}</button></form><p class='mt-3 text-xs {$theme['sub']}'>{$privacy}</p></div></div>";
                    } elseif ($variant === 'cta_book_demo_premium') {
                        $secondary=e($block['secondary_label'] ?? 'View features'); $secondaryUrl=e($block['secondary_url'] ?? '#');
                        $html .= "<div class='grid gap-8 lg:grid-cols-[1fr_.72fr] lg:items-center'><div><span class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</span><h2 class='mt-5 text-4xl font-bold {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p><div class='mt-8 flex gap-3'><a href='{$url}' class='rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-6 py-3 text-sm font-bold'>{$label}</a><a href='{$secondaryUrl}' class='rounded-full border px-6 py-3 text-sm font-bold {$theme['border']} {$theme['text']}'>{$secondary}</a></div></div><div class='rounded-[2rem] border p-7 {$theme['border']} {$theme['card']}'><span class='text-xs font-bold uppercase tracking-[.2em] {$theme['sub']}'>Demo details</span>"; foreach(['one','two','three'] as $i){$v=e($block['detail_'.$i] ?? 'Demo detail');$html.="<div class='mb-3 rounded-2xl border p-4 text-sm font-semibold {$theme['border']} {$theme['text']}'>{$v}</div>";} $html .= "</div></div>";
                    } elseif ($variant === 'cta_calendly_premium') {
                        $rawBookingUrl=trim((string) ($block['booking_url'] ?? '')); $bookingUrl=e(($rawBookingUrl === '' || $rawBookingUrl === '#') ? 'https://calendly.com/example/30min' : $rawBookingUrl); $availability=e($block['availability_note'] ?? 'Connect your real scheduling link before publishing.'); $duration=e($block['duration_label'] ?? 'Choose a meeting time');
                        $calendarDays=''; for($day=1;$day<=21;$day++){ $cellClass=$day===11 ? "{$primaryTheme['bg']} {$primaryTheme['text']}" : "{$theme['border']} {$theme['bg']} {$theme['text']}"; $calendarDays .= "<span class='flex aspect-square items-center justify-center rounded-lg border text-xs {$cellClass}'>{$day}</span>"; }
                        $html .= "<div data-cosmic-calendly class='grid gap-8 lg:grid-cols-[1fr_.75fr] lg:items-center'><div><span class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</span><h2 class='mt-5 text-4xl font-bold {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p><a href='{$bookingUrl}' class='mt-8 inline-flex rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-6 py-3 text-sm font-bold'>{$label}</a></div><div class='rounded-[2rem] border p-6 {$theme['border']} {$theme['card']}'><div class='grid grid-cols-7 gap-2 text-center text-[10px] font-bold uppercase {$theme['sub']}'><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span></div><div class='mt-3 grid grid-cols-7 gap-2'>{$calendarDays}</div><p class='mt-5 text-sm font-semibold {$theme['text']}'>{$duration}</p><p class='mt-2 text-xs {$theme['sub']}'>{$availability}</p></div></div>";
                    } elseif ($variant === 'cta_free_trial_premium') {
                        $trial=e($block['trial_note'] ?? 'Add your real trial terms before publishing.');
                        $html .= "<div class='grid gap-8 rounded-[2rem] border p-8 sm:p-12 lg:grid-cols-[1fr_.8fr] {$theme['border']} {$theme['card']}'><div><span class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</span><h2 class='mt-5 text-4xl font-bold {$theme['text']}'>{$heading}</h2><p class='mt-5 leading-7 {$theme['sub']}'>{$text}</p><a href='{$url}' class='mt-8 inline-flex rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-6 py-3 text-sm font-bold'>{$label}</a></div><div>"; foreach(['one','two','three'] as $i){$v=e($block['benefit_'.$i] ?? 'Trial benefit');$html.="<div class='mb-3 rounded-2xl border p-4 text-sm font-semibold {$theme['border']} {$theme['bg']} {$theme['text']}'>{$v}</div>";} $html .= "<p class='mt-4 text-xs leading-5 {$theme['sub']}'>{$trial}</p></div></div>";
                    } elseif ($variant === 'cta_countdown_premium') {
                        $deadlineNote=e($block['deadline_note'] ?? 'Demo countdown shown. Replace demo:+7d with a real ISO deadline before publishing.');
                        $deadlineRaw=trim((string) ($block['countdown_deadline'] ?? 'demo:+7d')) ?: 'demo:+7d';
                        $deadlineAttr=e($deadlineRaw);
                        $countdownId='cosmic-countdown-'.substr(sha1(json_encode($block).'|'.uniqid('', true)),0,12);
                        $html .= "<div id='{$countdownId}' data-cosmic-countdown data-deadline='{$deadlineAttr}' class='rounded-[2rem] border p-8 text-center sm:p-12 {$theme['border']} {$theme['card']}'><span class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</span><h2 class='mt-5 text-4xl font-bold {$theme['text']}'>{$heading}</h2><p class='mx-auto mt-5 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p><div class='mx-auto mt-8 grid max-w-2xl grid-cols-2 gap-3 sm:grid-cols-4'>";
                        foreach(['days'=>'Days','hours'=>'Hours','minutes'=>'Minutes','seconds'=>'Seconds'] as $k=>$caption){$html.="<div class='rounded-2xl border p-4 {$theme['border']} {$theme['bg']}'><strong data-countdown-part='{$k}' class='block text-3xl tabular-nums {$theme['text']}'>00</strong><span class='mt-1 block text-[10px] uppercase {$theme['sub']}'>{$caption}</span></div>";}
                        $html .= "</div><p class='mx-auto mt-5 max-w-xl rounded-xl border px-4 py-3 text-sm {$theme['border']} {$theme['sub']}'>Deadline: {$deadlineAttr}</p><a href='{$url}' class='mt-8 inline-flex rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-6 py-3 text-sm font-bold'>{$label}</a><p class='mx-auto mt-4 max-w-xl text-xs {$theme['sub']}'>{$deadlineNote}</p></div>";
                        $html .= "<script>(function(){var root=document.getElementById(".json_encode($countdownId).");if(!root)return;var raw=(root.getAttribute('data-deadline')||'').trim();var target;if(raw.toLowerCase()==='demo:+7d'){target=Date.now()+7*24*60*60*1000;}else{target=Date.parse(raw);}function pad(n){return String(Math.max(0,n)).padStart(2,'0');}function render(){var left=Number.isFinite(target)?Math.max(0,target-Date.now()):0;var values={days:Math.floor(left/86400000),hours:Math.floor((left%86400000)/3600000),minutes:Math.floor((left%3600000)/60000),seconds:Math.floor((left%60000)/1000)};Object.keys(values).forEach(function(k){var el=root.querySelector('[data-countdown-part=\"'+k+'\"]');if(el)el.textContent=pad(values[k]);});if(left<=0&&timer)clearInterval(timer);}render();var timer=setInterval(render,1000);})();</script>";
                    } elseif ($variant === 'cta_limited_offer_premium') {
                        $badge=e($block['offer_badge'] ?? 'Special offer'); $detail=e($block['offer_detail'] ?? 'Add the real offer value or benefit.'); $terms=e($block['terms_note'] ?? 'Add genuine offer terms before publishing.');
                        $html .= "<div class='relative overflow-hidden rounded-[2rem] border p-8 sm:p-12 {$theme['border']} {$theme['card']}'><span class='absolute right-0 top-0 rounded-bl-2xl {$primaryTheme['bg']} {$primaryTheme['text']} px-5 py-3 text-xs font-bold uppercase'>{$badge}</span><span class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</span><h2 class='mt-5 max-w-3xl text-4xl font-bold {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p><p class='mt-6 text-xl font-semibold {$theme['text']}'>{$detail}</p><a href='{$url}' class='mt-8 inline-flex rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-6 py-3 text-sm font-bold'>{$label}</a><p class='mt-4 max-w-2xl text-xs leading-5 {$theme['sub']}'>{$terms}</p></div>";
                    } else {
                        $secondary=e($block['secondary_label'] ?? 'Learn more'); $secondaryUrl=e($block['secondary_url'] ?? '#');
                        $html .= "<div class='rounded-[2.25rem] border p-8 shadow-2xl backdrop-blur-xl sm:p-12 {$theme['border']} {$theme['card']}'><span class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>{$eyebrow}</span><h2 class='mt-5 max-w-4xl text-4xl font-bold {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-2xl leading-7 {$theme['sub']}'>{$text}</p><div class='mt-8 flex gap-3'><a href='{$url}' class='rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-6 py-3 text-sm font-bold'>{$label}</a><a href='{$secondaryUrl}' class='rounded-full border px-6 py-3 text-sm font-bold {$theme['border']} {$theme['text']}'>{$secondary}</a></div></div>";
                    }
                    $html .= "</div></section>";
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


                case 'pricing_comparison_premium':
                case 'pricing_toggle_premium':
                case 'pricing_enterprise_premium':
                case 'pricing_calculator_premium':
                case 'pricing_credit_premium':
                case 'pricing_agency_premium':
                case 'pricing_feature_matrix_premium':
                    $variant = $type;
                    $eyebrow = e($block['eyebrow'] ?? 'PREMIUM PRICING');
                    $heading = e($block['heading'] ?? 'Choose the right plan for your next stage.');
                    $text = e($block['text'] ?? 'Clear, flexible pricing presented with the details that matter.');
                    $primaryTheme = self::getTheme($primaryColor);
                    $html .= "<section class='px-6 py-20 sm:px-8 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'><div class='max-w-3xl'><span class='text-xs font-bold uppercase tracking-[.28em] {$theme['sub']}'>{$eyebrow}</span><h2 class='mt-5 text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-2xl text-base leading-7 {$theme['sub']}'>{$text}</p></div>";

                    if ($variant === 'pricing_comparison_premium') {
                        $names = [e($block['plan_one_name'] ?? 'Starter'), e($block['plan_two_name'] ?? 'Growth'), e($block['plan_three_name'] ?? 'Pro')];
                        $rows = ['one','two','three','four','five','six'];
                        $html .= "<div class='mt-12 overflow-hidden rounded-3xl border {$theme['border']}'><div class='grid lg:grid-cols-4'><div class='hidden p-6 lg:block {$theme['card']} {$theme['text']}'>Compare plans</div>";
                        foreach ($names as $name) { $html .= "<div class='p-6 font-semibold {$theme['card']} {$theme['text']}'>{$name}</div>"; }
                        foreach ($rows as $row) {
                            $feature = e($block['feature_'.$row] ?? ucfirst($row).' feature');
                            $html .= "<div class='border-t p-5 text-sm font-semibold {$theme['border']} {$theme['card']} {$theme['text']}'>{$feature}</div>";
                            foreach (['one','two','three'] as $plan) { $value=e($block[$plan.'_'.$row] ?? 'Included'); $html .= "<div class='border-t p-5 text-sm {$theme['border']} {$theme['card']} {$theme['text']}'>{$value}</div>"; }
                        }
                        $foot=e($block['footnote'] ?? 'Replace placeholder pricing and inclusions with your actual offer details.');
                        $html .= "</div><p class='p-5 text-xs {$theme['sub']}'>{$foot}</p></div>";
                    } elseif ($variant === 'pricing_toggle_premium') {
                        $toggleId='cosmic-pricing-toggle-'.$index.'-'.substr(md5(json_encode($block)),0,8);
                        $monthlyLabel=e($block['monthly_label'] ?? 'Monthly'); $yearlyLabel=e($block['yearly_label'] ?? 'Yearly');
                        $html .= "<div id='{$toggleId}'><div class='mt-8 inline-flex rounded-full border p-1 {$theme['border']} {$theme['card']} {$theme['text']}' role='group' aria-label='Billing period'><button type='button' data-billing='monthly' aria-pressed='true' class='rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white'>{$monthlyLabel}</button><button type='button' data-billing='yearly' aria-pressed='false' class='rounded-full px-5 py-2 text-sm font-semibold'>{$yearlyLabel}</button></div><div class='mt-8 grid gap-5 lg:grid-cols-3'>";
                        foreach (['one','two','three'] as $i) {
                            $name=e($block['plan_'.$i.'_name'] ?? ucfirst($i)); $monthly=e($block['plan_'.$i.'_monthly'] ?? '$49'); $yearly=e($block['plan_'.$i.'_yearly'] ?? '$490'); $desc=e($block['plan_'.$i.'_description'] ?? 'Flexible plan for growing needs.'); $label=e($block['plan_'.$i.'_button_label'] ?? 'Choose plan'); $url=e($block['plan_'.$i.'_button_url'] ?? '/contact');
                            if ($url === '#' || trim($url) === '') { $url='/contact'; }
                            $html .= "<article class='rounded-3xl border p-7 {$theme['border']} {$theme['card']}'><h3 class='text-xl font-semibold {$theme['text']}'>{$name}</h3><div class='mt-4 text-4xl font-bold {$theme['text']}'><span data-price data-monthly='{$monthly}' data-yearly='{$yearly}'>{$monthly}</span><span data-period class='ml-2 text-xs font-normal {$theme['sub']}'>/month</span></div><p class='mt-4 text-sm leading-6 {$theme['sub']}'>{$desc}</p><a href='{$url}' class='mt-6 inline-flex min-h-[46px] w-full items-center justify-center rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-5 text-sm font-bold'>{$label}</a></article>";
                        }
                        $note=e($block['yearly_note'] ?? 'Switch between monthly and yearly billing.');
                        $html .= "</div><p class='mt-5 text-sm {$theme['sub']}'>{$note}</p></div><script>(function(){var r=document.getElementById('{$toggleId}');if(!r)return;var bs=r.querySelectorAll('[data-billing]'),ps=r.querySelectorAll('[data-price]'),periods=r.querySelectorAll('[data-period]');function set(mode){bs.forEach(function(b){var a=b.getAttribute('data-billing')===mode;b.setAttribute('aria-pressed',a?'true':'false');b.classList.toggle('bg-slate-900',a);b.classList.toggle('text-white',a);});ps.forEach(function(p){p.textContent=p.getAttribute(mode==='yearly'?'data-yearly':'data-monthly')||'';});periods.forEach(function(p){p.textContent=mode==='yearly'?'/year':'/month';});}bs.forEach(function(b){b.addEventListener('click',function(){set(b.getAttribute('data-billing'));});});set('monthly');})();</script>";
                    } elseif ($variant === 'pricing_enterprise_premium') {
                        $price=e($block['price_text'] ?? 'Talk to sales'); $label=e($block['primary_label'] ?? 'Book a consultation'); $url=e($block['primary_url'] ?? '#');
                        $secondaryLabel=e($block['secondary_label'] ?? 'View capabilities'); $secondaryUrl=e($block['secondary_url'] ?? '#'); $note=e($block['note'] ?? 'Only publish claims and service levels that your business can actually provide.');
                        $html .= "<div class='mt-12 grid gap-6 lg:grid-cols-[1.1fr_.9fr]'><div class='rounded-[2rem] border p-8 {$theme['border']} {$theme['card']}'><span class='text-xs font-bold uppercase tracking-[.24em] {$theme['sub']}'>".e($block['price_label'] ?? 'CUSTOM PRICING')."</span><div class='mt-4 text-5xl font-bold {$theme['text']}'>{$price}</div><div class='mt-8 flex flex-wrap gap-3'><a href='{$url}' class='inline-flex rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-6 py-3 text-sm font-bold'>{$label}</a><a href='{$secondaryUrl}' class='inline-flex rounded-full border px-6 py-3 text-sm font-bold {$theme['border']} {$theme['text']}'>{$secondaryLabel}</a></div></div><div class='grid gap-3'>";
                        foreach (['one','two','three','four'] as $i) { $v=e($block['point_'.$i] ?? 'Tailored enterprise support'); $html .= "<div class='rounded-2xl border p-5 font-semibold {$theme['border']} {$theme['card']} {$theme['text']}'>{$v}</div>"; }
                        $html .= "<p class='mt-2 text-xs leading-5 {$theme['sub']}'>{$note}</p></div></div>";
                    } elseif ($variant === 'pricing_credit_premium') {
                        $html .= "<div class='mt-12 grid gap-5 lg:grid-cols-3'>";
                        foreach (array_values(array_slice(['one','two','three'],0,max(1,min(3,(int)($block['plan_count']??3))))) as $idx=>$i) { $name=e($block['pack_'.$i.'_name'] ?? ucfirst($i).' Pack'); $credits=e($block['pack_'.$i.'_credits'] ?? '1,000 credits'); $price=e($block['pack_'.$i.'_price'] ?? '$19'); $desc=e($block['pack_'.$i.'_description'] ?? 'Flexible prepaid usage credits.'); $label=e($block['pack_'.$i.'_button_label'] ?? 'Choose pack'); $url=e($block['pack_'.$i.'_button_url'] ?? '#'); $featured=$idx===1 ? ' ring-2 ring-current' : ''; $badge=$idx===1 ? "<span class='mb-5 inline-flex rounded-full bg-slate-900 px-3 py-1 text-[10px] font-black tracking-[.16em] text-white'>".e($block['pack_two_badge'] ?? 'MOST POPULAR')."</span>" : ''; $html .= "<article class='relative rounded-[2rem] border p-7 {$theme['border']} {$theme['card']}{$featured}'>{$badge}<h3 class='text-xl font-semibold {$theme['text']}'>{$name}</h3><div class='mt-5 text-3xl font-bold {$theme['text']}'>{$credits}</div><div class='mt-2 text-lg font-semibold {$theme['sub']}'>{$price}</div><p class='mt-4 text-sm leading-6 {$theme['sub']}'>{$desc}</p><a href='{$url}' class='mt-6 inline-flex min-h-[46px] w-full items-center justify-center rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-5 text-sm font-bold'>{$label}</a></article>"; }
                        $html .= "</div><p class='mt-5 text-xs {$theme['sub']}'>".e($block['note'] ?? 'Replace placeholder pack sizes and prices with your actual credit policy before publishing.')."</p>";
                    } elseif ($variant === 'pricing_agency_premium') {
                        $html .= "<div class='mt-12 grid gap-5 lg:grid-cols-3'>";
                        foreach (array_values(array_slice(['one','two','three'],0,max(1,min(3,(int)($block['plan_count']??3))))) as $idx=>$i) { $name=e($block['plan_'.$i.'_name'] ?? ucfirst($i)); $price=e($block['plan_'.$i.'_price'] ?? 'Custom'); $period=e($block['plan_'.$i.'_period'] ?? ''); $capacity=e($block['plan_'.$i.'_capacity'] ?? 'Flexible client capacity'); $desc=e($block['plan_'.$i.'_description'] ?? 'Agency package for growing client work.'); $label=e($block['plan_'.$i.'_button_label'] ?? 'Choose plan'); $url=e($block['plan_'.$i.'_button_url'] ?? '#'); $featured=$idx===1 ? ' ring-2 ring-current' : ''; $badge=$idx===1 ? "<span class='mb-5 inline-flex rounded-full bg-slate-900 px-3 py-1 text-[10px] font-black tracking-[.16em] text-white'>".e($block['plan_two_badge'] ?? 'MOST POPULAR')."</span>" : ''; $features=''; foreach(['one','two','three'] as $f){ $features .= "<div class='text-sm {$theme['sub']}'>✓ ".e($block['feature_'.$f] ?? 'Included feature')."</div>"; } $html .= "<article class='rounded-[2rem] border p-7 {$theme['border']} {$theme['card']}{$featured}'>{$badge}<h3 class='text-xl font-semibold {$theme['text']}'>{$name}</h3><div class='mt-4 text-4xl font-bold {$theme['text']}'>{$price}<span class='ml-1 text-xs font-normal {$theme['sub']}'>{$period}</span></div><div class='mt-4 text-sm font-semibold {$theme['text']}'>{$capacity}</div><p class='mt-3 text-sm leading-6 {$theme['sub']}'>{$desc}</p><div class='mt-5 space-y-2'>{$features}</div><a href='{$url}' class='mt-6 inline-flex min-h-[46px] w-full items-center justify-center rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-5 text-sm font-bold'>{$label}</a></article>"; }
                        $html .= "</div><p class='mt-5 text-xs {$theme['sub']}'>".e($block['note'] ?? 'Use only real limits, prices, and service levels that your agency actually offers.')."</p>";
                    } elseif ($variant === 'pricing_feature_matrix_premium') {
                        $html .= "<div class='mt-12 overflow-hidden rounded-3xl border {$theme['border']}'><div class='grid grid-cols-4'><div class='p-5 {$theme['card']}'></div>";
                        foreach (['one','two','three'] as $i) { $html .= "<div class='p-5 text-sm font-bold {$theme['card']} {$theme['text']}'>".e($block['plan_'.$i.'_name'] ?? ucfirst($i))."</div>"; }
                        $groups=['group_one'=>['one','two'],'group_two'=>['three','four'],'group_three'=>['five','six']]; foreach ($groups as $g=>$rows) { $html .= "<div class='col-span-4 border-t px-5 py-3 text-xs font-bold uppercase tracking-wider {$theme['border']} {$theme['card']} {$theme['sub']}'>".e($block[$g] ?? 'Features')."</div>"; foreach ($rows as $r) { $html .= "<div class='border-t p-5 text-sm font-semibold {$theme['border']} {$theme['card']} {$theme['text']}'>".e($block['feature_'.$r] ?? 'Feature')."</div>"; foreach (['one','two','three'] as $plan) { $html .= "<div class='border-t p-5 text-sm {$theme['border']} {$theme['card']} {$theme['text']}'>".e($block[$plan.'_'.$r] ?? 'Included')."</div>"; } } }
                        $html .= "</div><p class='p-5 text-xs {$theme['sub']}'>".e($block['note'] ?? 'Replace placeholder capabilities with your actual plan inclusions before publishing.')."</p></div>";
                    } else {
                        $unit=e($block['unit_label'] ?? 'Seats'); $currency=e($block['currency'] ?? '$'); $rate=(float)($block['base_price'] ?? 49); $min=max(1,(int)($block['minimum_units'] ?? 1)); $max=max($min,(int)($block['maximum_units'] ?? 20)); $label=e($block['primary_label'] ?? 'Get a custom quote'); $url=e($block['primary_url'] ?? '/contact'); if ($url === '#' || trim($url) === '') { $url='/contact'; } $calcId='cosmic-pricing-calc-'.$index.'-'.substr(md5(json_encode($block)),0,8);
                        $html .= "<div id='{$calcId}' class='mt-12 grid gap-8 rounded-3xl border p-8 lg:grid-cols-2 {$theme['border']} {$theme['card']}' data-rate='".e((string)$rate)."' data-min='{$min}' data-max='{$max}' data-currency='{$currency}'><div><div class='font-semibold {$theme['text']}'>{$unit}</div><div class='mt-5 flex items-center gap-4'><button type='button' data-step='down' class='h-11 w-11 rounded-full border {$theme['border']} {$theme['text']}' aria-label='Decrease {$unit}'>−</button><div data-units class='min-w-16 text-center text-3xl font-bold {$theme['text']}'>{$min}</div><button type='button' data-step='up' class='h-11 w-11 rounded-full border {$theme['border']} {$theme['text']}' aria-label='Increase {$unit}'>+</button></div><p class='mt-4 text-sm {$theme['sub']}'>Rate: {$currency}".e((string)$rate)." per ".e(strtolower($block['unit_label'] ?? 'unit'))."</p></div><div><span class='text-xs font-bold uppercase tracking-[.22em] {$theme['sub']}'>Estimated total</span><div data-total class='mt-3 text-5xl font-bold {$theme['text']}'></div><a href='{$url}' class='mt-6 inline-flex rounded-full {$primaryTheme['bg']} {$primaryTheme['text']} px-6 py-3 text-sm font-bold'>{$label}</a><p class='mt-4 text-xs {$theme['sub']}'>".e($block['note'] ?? 'Estimate only. Final pricing may vary based on scope and requirements.')."</p></div></div><script>(function(){var r=document.getElementById('{$calcId}');if(!r)return;var n=+r.dataset.min||1,min=n,max=+r.dataset.max||n,rate=+r.dataset.rate||0,c=r.dataset.currency||'',u=r.querySelector('[data-units]'),t=r.querySelector('[data-total]');function draw(){u.textContent=n;t.textContent=c+(n*rate).toLocaleString(undefined,{maximumFractionDigits:2});r.querySelector('[data-step=down]').disabled=n<=min;r.querySelector('[data-step=up]').disabled=n>=max;}r.querySelector('[data-step=down]').addEventListener('click',function(){n=Math.max(min,n-1);draw();});r.querySelector('[data-step=up]').addEventListener('click',function(){n=Math.min(max,n+1);draw();});draw();})();</script>";
                    }
                    $html .= "</div></section>";
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
                $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'surface');
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

                case 'hero_grid_pulse_tech_premium':
                case 'hero_light_trails_premium':
                case 'hero_device_showcase_premium':
                case 'hero_app_screens_carousel_premium':
                case 'hero_editorial_image_sequence_premium':
                case 'hero_interactive_bento_premium':
                    $p5Type = (string) ($block['type'] ?? 'hero_grid_pulse_tech_premium');
                    $p5Id = 'cosmic-p5-' . substr(sha1($p5Type . '|' . json_encode($block) . '|' . uniqid('', true)), 0, 12);
                    $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'primary');
                    $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                    $isPrimaryHero = !$lightMedia && ($resolvedTheme === 'primary' || $resolvedTheme === 'auto');
                    $primaryThemeForHero = self::getTheme($primaryColor ?: 'midnight');
                    $primaryBgHex = e((string) ($primaryThemeForHero['palette']['background'] ?? '#0f766e'));
                    $gradient = $primaryThemeForHero['gradient'] ?? [];
                    $gradientFrom = e((string) ($gradient['from'] ?? '#071426'));
                    $gradientVia = e((string) ($gradient['via'] ?? '#111936'));
                    $gradientTo = e((string) ($gradient['to'] ?? '#28164D'));
                    $gradientGlow = e((string) ($gradient['glow'] ?? '#7C3AED'));
                    $gradientGlowSoft = e((string) ($gradient['glowSoft'] ?? 'rgba(124, 58, 237, 0.20)'));
                    $gradientAngle = max(0, min(360, (int) ($gradient['angle'] ?? 120)));
                    $stateClass = $lightMedia ? ' cosmic-p5-light' : ($isPrimaryHero ? ' cosmic-p5-primary' : '');
                    $heroStyle = $lightMedia ? 'background:#fff;color:#0f172a' : ($isPrimaryHero ? "background:{$primaryBgHex};color:#fff" : 'background:#020617;color:#fff');
                    $gradientVars = "--p5-primary:{$primaryBgHex};--p5-from:{$gradientFrom};--p5-via:{$gradientVia};--p5-to:{$gradientTo};--p5-glow:{$gradientGlow};--p5-glow-soft:{$gradientGlowSoft};--p5-angle:{$gradientAngle}deg";
                    $eyebrow = e((string) ($block['eyebrow'] ?? 'DESIGNED TO MOVE'));
                    $heading = e((string) ($block['heading'] ?? 'A premium opening with motion built into the story.'));
                    $body = e((string) ($block['text'] ?? 'Lightweight animation adds depth while keeping the message clear.'));
                    $primaryLabel = e((string) ($block['primary_label'] ?? 'Get started'));
                    $primaryUrl = e((string) ($block['primary_url'] ?? '#'));
                    $secondaryLabel = e((string) ($block['secondary_label'] ?? 'Explore more'));
                    $secondaryUrl = e((string) ($block['secondary_url'] ?? '#'));
                    $image1 = e(self::staticAssetUrl((string) ($block['image_url'] ?? '/storage/cms-images/background/background-1.avif')));
                    $image2 = e(self::staticAssetUrl((string) ($block['image_url_2'] ?? '/storage/cms-images/background/background-2.avif')));
                    $image3 = e(self::staticAssetUrl((string) ($block['image_url_3'] ?? '/storage/cms-images/background/background-3.avif')));
                    $copy = "<div class='p5-copy'><p>{$eyebrow}</p><h1>{$heading}</h1><div class='p5-body'>{$body}</div><div class='p5-actions'><a href='{$primaryUrl}'>{$primaryLabel}</a><a class='secondary' href='{$secondaryUrl}'>{$secondaryLabel}</a></div></div>";
                    if ($p5Type === 'hero_device_showcase_premium') $visual = "<div class='p5-device'><img src='{$image1}' alt='' decoding='async'><img loading='lazy' decoding='async' src='{$image2}' alt=''></div>";
                    elseif ($p5Type === 'hero_app_screens_carousel_premium') $visual = "<div class='p5-screens'><img src='{$image1}' alt='' decoding='async'><img loading='lazy' decoding='async' src='{$image2}' alt=''><img loading='lazy' decoding='async' src='{$image3}' alt=''></div>";
                    elseif ($p5Type === 'hero_editorial_image_sequence_premium') $visual = "<div class='p5-editorial'><img src='{$image1}' alt='' decoding='async'><img loading='lazy' decoding='async' src='{$image2}' alt=''><img loading='lazy' decoding='async' src='{$image3}' alt=''></div>";
                    elseif ($p5Type === 'hero_interactive_bento_premium') $visual = "<div class='p5-bento'><img src='{$image1}' alt='' decoding='async'><img loading='lazy' decoding='async' src='{$image2}' alt=''><img loading='lazy' decoding='async' src='{$image3}' alt=''></div>";
                    else $visual = "<div class='p5-abstract'><i></i><i></i><i></i><i></i></div>";
                    $html .= "<section id='{$p5Id}' class='cosmic-p5{$stateClass}' data-type='{$p5Type}' style='{$heroStyle};{$gradientVars}'><div class='p5-grid'>{$copy}{$visual}</div></section><style>
#{$p5Id}{min-height:86svh;overflow:hidden}#{$p5Id} *{box-sizing:border-box}#{$p5Id} .p5-grid{display:grid;grid-template-columns:.9fr 1.1fr;align-items:center;gap:4rem;min-height:86svh;max-width:80rem;margin:auto;padding:6rem 3.5rem}#{$p5Id} .p5-copy{max-width:44rem}#{$p5Id} .p5-copy>p{font-size:.75rem;font-weight:800;letter-spacing:.3em;color:rgba(255,255,255,.65)}#{$p5Id} h1{font-size:clamp(3rem,6vw,6rem);line-height:.96;letter-spacing:-.045em;margin:1.25rem 0}#{$p5Id} .p5-body{max-width:38rem;line-height:1.8;color:rgba(255,255,255,.72)}#{$p5Id} .p5-actions{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:2rem}#{$p5Id} .p5-actions a{padding:.85rem 1.3rem;border-radius:999px;background:#fff;color:#0f172a;text-decoration:none;font-weight:800}#{$p5Id} .p5-actions a.secondary{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.25)}#{$p5Id} img{width:100%;height:100%;object-fit:cover}#{$p5Id} .p5-device,#{$p5Id} .p5-screens,#{$p5Id} .p5-editorial,#{$p5Id} .p5-bento,#{$p5Id} .p5-abstract{position:relative;height:30rem}
#{$p5Id} .p5-device img:first-child{position:absolute;inset:8% 4%;height:76%;border:8px solid #1e293b;border-radius:1.8rem;animation:p5Float 5s ease-in-out infinite}#{$p5Id} .p5-device img:last-child{position:absolute;right:2%;bottom:2%;width:28%;height:58%;border:7px solid #1e293b;border-radius:1.7rem;animation:p5Float 4.4s .6s ease-in-out infinite}#{$p5Id} .p5-screens img{position:absolute;left:50%;top:50%;width:38%;height:88%;border-radius:1.6rem;transform:translate(-50%,-50%);box-shadow:0 2rem 5rem #0008;border:1px solid rgba(255,255,255,.15)}#{$p5Id} .p5-screens img:nth-child(2){transform:translate(-115%,-50%) scale(.82);opacity:.55}#{$p5Id} .p5-screens img:nth-child(3){transform:translate(15%,-50%) scale(.82);opacity:.55}#{$p5Id} .p5-editorial,#{$p5Id} .p5-bento{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}#{$p5Id} .p5-editorial img,#{$p5Id} .p5-bento img{border-radius:1.6rem;min-height:0}#{$p5Id} .p5-editorial img:first-child,#{$p5Id} .p5-bento img:first-child{grid-row:span 2}#{$p5Id} .p5-bento img{transition:transform .35s ease}#{$p5Id} .p5-bento img:hover{transform:scale(1.025)}
#{$p5Id} .p5-abstract{border:1px solid rgba(255,255,255,.12);border-radius:2rem;background-image:radial-gradient(circle at 70% 25%,var(--p5-glow-soft),transparent 38%),linear-gradient(rgba(255,255,255,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.08) 1px,transparent 1px);background-size:auto,42px 42px,42px 42px}#{$p5Id}[data-type='hero_light_trails_premium'] .p5-abstract{background-image:linear-gradient(var(--p5-angle),var(--p5-from),var(--p5-via),var(--p5-to))}#{$p5Id} .p5-abstract i{position:absolute;left:-20%;width:140%;height:1px;background:linear-gradient(90deg,transparent,var(--p5-glow),transparent);animation:p5Beam 4s ease-in-out infinite}#{$p5Id} .p5-abstract i:nth-child(1){top:20%}#{$p5Id} .p5-abstract i:nth-child(2){top:40%;animation-delay:.4s}#{$p5Id} .p5-abstract i:nth-child(3){top:60%;animation-delay:.8s}#{$p5Id} .p5-abstract i:nth-child(4){top:80%;animation-delay:1.2s}
#{$p5Id}.cosmic-p5-light{background:#fff!important;color:#0f172a!important}#{$p5Id}.cosmic-p5-light .p5-copy>p{color:#64748b}#{$p5Id}.cosmic-p5-light .p5-body{color:#475569}#{$p5Id}.cosmic-p5-light .p5-actions a{background:var(--p5-primary);color:#fff}#{$p5Id}.cosmic-p5-light .p5-actions a.secondary{background:rgba(255,255,255,.78);color:#0f172a;border-color:#cbd5e1}#{$p5Id}.cosmic-p5-light .p5-abstract{border-color:#e2e8f0;background-image:radial-gradient(circle at 70% 25%,var(--p5-glow-soft),transparent 38%),linear-gradient(rgba(100,116,139,.14) 1px,transparent 1px),linear-gradient(90deg,rgba(100,116,139,.14) 1px,transparent 1px);background-size:auto,42px 42px,42px 42px}#{$p5Id}.cosmic-p5-light[data-type='hero_light_trails_premium'] .p5-abstract{background:rgba(255,255,255,.65)}#{$p5Id}.cosmic-p5-light .p5-device img{border-color:#cbd5e1}#{$p5Id}.cosmic-p5-light .p5-screens img{border-color:#e2e8f0}
@keyframes p5Float{50%{transform:translateY(-12px)}}@keyframes p5Beam{50%{transform:translateX(14%);opacity:.3}}@media(max-width:850px){#{$p5Id} .p5-grid{grid-template-columns:1fr;padding:5rem 1.5rem;gap:2rem}#{$p5Id} .p5-device,#{$p5Id} .p5-screens,#{$p5Id} .p5-editorial,#{$p5Id} .p5-bento,#{$p5Id} .p5-abstract{height:24rem}}@media(prefers-reduced-motion:reduce){#{$p5Id} *{animation:none!important;scroll-behavior:auto!important}}
</style>";
                    if ($p5Type === 'hero_app_screens_carousel_premium') {
                        $html .= "<script>(function(){const r=document.getElementById(" . json_encode($p5Id) . " );if(!r||matchMedia('(prefers-reduced-motion: reduce)').matches)return;const cards=[...r.querySelectorAll('.p5-screens img')];let a=0,id=0;const paint=()=>cards.forEach((el,i)=>{let d=(i-a+3)%3;if(d===2)d=-1;el.style.transition='transform .7s ease,opacity .7s ease';el.style.transform='translate(-50%,-50%) translateX('+(d*65)+'%) scale('+(d===0?1:.82)+')';el.style.opacity=d===0?1:.5;el.style.zIndex=d===0?3:1});const stop=()=>{if(id){clearInterval(id);id=0}},start=()=>{if(!id)id=setInterval(()=>{a=(a+1)%3;paint()},3000)};paint();new IntersectionObserver(([e])=>e.isIntersecting?start():stop(),{rootMargin:'120px'}).observe(r)})();</script>";
                    }
                    break;

                case 'hero_image_mask_reveal_premium':
                case 'hero_stacked_cards_premium':
                case 'hero_perspective_carousel_premium':
                case 'hero_before_after_premium':
                case 'hero_scroll_morph_premium':
                case 'hero_glass_orb_premium':
                case 'hero_particle_constellation_premium':
                    $motion4Type = (string) ($block['type'] ?? 'hero_image_mask_reveal_premium');
                    $motion4Id = 'cosmic-motion4-hero-' . substr(sha1($motion4Type . '|' . json_encode($block) . '|' . uniqid('', true)), 0, 12);
                    $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'primary');
                    $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                    $motion4StateClass = $lightMedia ? ' cosmic-motion4-light' : '';
                    $primaryThemeForHero = self::getTheme($primaryColor ?: 'midnight');
                    $primaryBgHex = e((string) ($primaryThemeForHero['palette']['background'] ?? '#0f766e'));
                    $heroGradient = is_array($primaryThemeForHero['gradient'] ?? null) ? $primaryThemeForHero['gradient'] : ['glow'=>'#7C3AED','glowSoft'=>'rgba(124, 58, 237, 0.20)','glowStrong'=>'rgba(124, 58, 237, 0.38)'];
                    $heroGradientGlow = e((string) ($heroGradient['glow'] ?? '#7C3AED'));
                    $heroGradientGlowSoft = e((string) ($heroGradient['glowSoft'] ?? 'rgba(124, 58, 237, 0.20)'));
                    $heroGradientGlowStrong = e((string) ($heroGradient['glowStrong'] ?? 'rgba(124, 58, 237, 0.38)'));
                    $heroGradientVars = "--hero-glow:{$heroGradientGlow};--hero-glow-soft:{$heroGradientGlowSoft};--hero-glow-strong:{$heroGradientGlowStrong};";
                    $eyebrow = e((string) ($block['eyebrow'] ?? 'MOTION, WITH PURPOSE'));
                    $heading = e((string) ($block['heading'] ?? 'Give the first screen a premium sense of depth.'));
                    $body = e((string) ($block['text'] ?? 'Distinct interaction and restrained motion create a memorable opening.'));
                    $primaryLabel = e((string) ($block['primary_label'] ?? 'Start a project'));
                    $primaryUrl = e((string) ($block['primary_url'] ?? '#'));
                    $secondaryLabel = e((string) ($block['secondary_label'] ?? 'Explore more'));
                    $secondaryUrl = e((string) ($block['secondary_url'] ?? '#'));
                    $image1 = e(self::staticAssetUrl((string) ($block['image_url'] ?? '/storage/cms-images/background/background-1.avif')));
                    $image2 = e(self::staticAssetUrl((string) ($block['image_url_2'] ?? '/storage/cms-images/background/background-2.avif')));
                    $image3 = e(self::staticAssetUrl((string) ($block['image_url_3'] ?? '/storage/cms-images/background/background-3.avif')));
                    $split = max(20, min(80, (int) ($block['splitPosition'] ?? 52)));
                    $strength = max(10, min(40, (int) ($block['motionStrength'] ?? 28)));
                    $particleCount = max(18, min(54, (int) ($block['particleCount'] ?? 36)));
                    $copy = "<div class='cosmic-motion4-copy'><p>{$eyebrow}</p><h1>{$heading}</h1><div class='cosmic-motion4-body'>{$body}</div><div class='cosmic-motion4-actions'><a href='{$primaryUrl}'>{$primaryLabel}</a><a class='secondary' href='{$secondaryUrl}'>{$secondaryLabel}</a></div></div>";

                    if ($motion4Type === 'hero_image_mask_reveal_premium') {
                        $scene = "<div class='cosmic-motion4-mask'><img src='{$image1}' alt='' decoding='async'></div><div class='cosmic-motion4-shade'></div><div class='cosmic-motion4-copywrap'>{$copy}</div>";
                    } elseif ($motion4Type === 'hero_stacked_cards_premium') {
                        $scene = "<div class='cosmic-motion4-grid'>{$copy}<div class='cosmic-motion4-stack' data-active='0'><article><img src='{$image1}' alt=''></article><article><img loading='lazy' src='{$image2}' alt=''></article><article><img loading='lazy' src='{$image3}' alt=''></article></div></div>";
                    } elseif ($motion4Type === 'hero_perspective_carousel_premium') {
                        $scene = "<div class='cosmic-motion4-grid'>{$copy}<div class='cosmic-motion4-carousel' data-active='0'><button type='button'><img src='{$image1}' alt=''></button><button type='button'><img loading='lazy' src='{$image2}' alt=''></button><button type='button'><img loading='lazy' src='{$image3}' alt=''></button></div></div>";
                    } elseif ($motion4Type === 'hero_before_after_premium') {
                        $scene = "<div class='cosmic-motion4-grid'>{$copy}<div class='cosmic-motion4-beforeafter' data-split='{$split}'><img class='before' src='{$image1}' alt='Before'><div class='after'><img src='{$image2}' alt='After'></div><div class='divider'><span>↔</span></div><b class='label before-label'>Before</b><b class='label after-label'>After</b></div></div>";
                    } elseif ($motion4Type === 'hero_scroll_morph_premium') {
                        $scene = "<div class='cosmic-motion4-sticky'><div class='cosmic-motion4-grid'>{$copy}<div class='cosmic-motion4-morph' data-strength='{$strength}'><img src='{$image1}' alt=''></div></div></div>";
                    } elseif ($motion4Type === 'hero_glass_orb_premium') {
                        $scene = "<div class='cosmic-motion4-orb orb-a'></div><div class='cosmic-motion4-orb orb-b'></div><div class='cosmic-motion4-orb orb-c'></div><div class='cosmic-motion4-copywrap'>{$copy}</div>";
                    } else {
                        $scene = "<canvas class='cosmic-motion4-particles' data-count='{$particleCount}' aria-hidden='true'></canvas><div class='cosmic-motion4-copywrap'>{$copy}</div>";
                    }
                    $extraClass = $motion4Type === 'hero_scroll_morph_premium' ? ' cosmic-motion4-tall' : '';
                    $html .= "<section id='{$motion4Id}' class='cosmic-motion4{$extraClass}{$motion4StateClass}' data-type='{$motion4Type}' style='{$heroGradientVars}'>{$scene}</section>";
                    $html .= "<style>
#{$motion4Id}{position:relative;isolation:isolate;min-height:86svh;overflow:hidden;background:#020617;color:#fff}#{$motion4Id}.cosmic-motion4-tall{min-height:110svh;overflow:visible}#{$motion4Id} *{box-sizing:border-box}#{$motion4Id} .cosmic-motion4-copywrap{position:relative;z-index:4;display:flex;align-items:center;min-height:86svh;max-width:80rem;margin:auto;padding:6rem 3.5rem}#{$motion4Id} .cosmic-motion4-copy{max-width:46rem}#{$motion4Id} .cosmic-motion4-copy>p{font-size:.75rem;font-weight:800;letter-spacing:.32em;text-transform:uppercase;color:rgba(255,255,255,.68)}#{$motion4Id} .cosmic-motion4-copy h1{margin:1.4rem 0 0;font-size:clamp(3rem,7vw,6.7rem);line-height:.94;letter-spacing:-.05em}#{$motion4Id} .cosmic-motion4-body{max-width:42rem;margin-top:1.5rem;font-size:1.06rem;line-height:1.8;color:rgba(255,255,255,.74)}#{$motion4Id} .cosmic-motion4-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2rem}#{$motion4Id} .cosmic-motion4-actions a{padding:.85rem 1.35rem;border-radius:999px;background:#fff;color:#0f172a;font-size:.9rem;font-weight:800;text-decoration:none}#{$motion4Id} .cosmic-motion4-actions a.secondary{border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.08);color:#fff;backdrop-filter:blur(12px)}#{$motion4Id} .cosmic-motion4-grid{position:relative;z-index:3;display:grid;grid-template-columns:.86fr 1.14fr;align-items:center;gap:4rem;min-height:86svh;max-width:80rem;margin:auto;padding:6rem 3.5rem}#{$motion4Id} .cosmic-motion4-mask{position:absolute;inset:0;animation:cosmicMotion4Mask 1.35s cubic-bezier(.22,1,.36,1) both}#{$motion4Id} .cosmic-motion4-mask img{width:100%;height:100%;object-fit:cover}#{$motion4Id} .cosmic-motion4-shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(2,6,23,.82),rgba(2,6,23,.24),rgba(2,6,23,.12));z-index:2}@keyframes cosmicMotion4Mask{0%{clip-path:inset(16% 28% round 36px);transform:scale(1.08)}100%{clip-path:inset(0 round 0);transform:scale(1)}}
#{$motion4Id} .cosmic-motion4-stack{position:relative;height:34rem}#{$motion4Id} .cosmic-motion4-stack article{position:absolute;left:8%;right:8%;top:50%;overflow:hidden;padding:.45rem;border:1px solid rgba(255,255,255,.15);border-radius:2rem;background:rgba(255,255,255,.08);box-shadow:0 2rem 5rem rgba(0,0,0,.35);transition:transform .7s ease,opacity .7s ease}#{$motion4Id} .cosmic-motion4-stack article img{display:block;width:100%;aspect-ratio:16/11;object-fit:cover;border-radius:1.6rem}
#{$motion4Id} .cosmic-motion4-carousel{position:relative;height:30rem;perspective:1200px}#{$motion4Id} .cosmic-motion4-carousel button{position:absolute;left:50%;top:50%;width:70%;padding:.45rem;border:1px solid rgba(255,255,255,.15);border-radius:1.8rem;background:#0f172a;box-shadow:0 2rem 5rem rgba(0,0,0,.4);transition:transform .7s ease,opacity .7s ease;cursor:pointer}#{$motion4Id} .cosmic-motion4-carousel img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:1.45rem}
#{$motion4Id} .cosmic-motion4-beforeafter{position:relative;aspect-ratio:4/3;overflow:hidden;border:1px solid rgba(255,255,255,.15);border-radius:2rem;box-shadow:0 2rem 5rem rgba(0,0,0,.4);touch-action:none;user-select:none}#{$motion4Id} .cosmic-motion4-beforeafter>img,#{$motion4Id} .cosmic-motion4-beforeafter .after img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}#{$motion4Id} .cosmic-motion4-beforeafter .after{position:absolute;inset-block:0;left:0;overflow:hidden}#{$motion4Id} .cosmic-motion4-beforeafter .after img{max-width:none}#{$motion4Id} .cosmic-motion4-beforeafter .divider{position:absolute;inset-block:0;width:1px;background:#fff}#{$motion4Id} .cosmic-motion4-beforeafter .divider span{position:absolute;left:50%;top:50%;display:grid;width:3rem;height:3rem;place-items:center;transform:translate(-50%,-50%);border:1px solid rgba(255,255,255,.4);border-radius:50%;background:rgba(2,6,23,.82)}#{$motion4Id} .cosmic-motion4-beforeafter .label{position:absolute;bottom:1rem;padding:.35rem .65rem;border-radius:999px;background:rgba(2,6,23,.72);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em}#{$motion4Id} .before-label{left:1rem}#{$motion4Id} .after-label{right:1rem;background:rgba(255,255,255,.9)!important;color:#0f172a}
#{$motion4Id} .cosmic-motion4-sticky{position:sticky;top:0;min-height:86svh}#{$motion4Id} .cosmic-motion4-morph{overflow:hidden;border:1px solid rgba(255,255,255,.15);border-radius:2.25rem;box-shadow:0 2rem 5rem rgba(0,0,0,.4);transform:scale(.78);transform-origin:center;will-change:transform,border-radius}#{$motion4Id} .cosmic-motion4-morph img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover}
#{$motion4Id} .cosmic-motion4-orb{position:absolute;border:1px solid rgba(255,255,255,.16);border-radius:50%;background:rgba(255,255,255,.08);backdrop-filter:blur(24px);box-shadow:inset 0 0 4rem rgba(255,255,255,.1),0 2rem 5rem var(--hero-glow-soft)}#{$motion4Id} .orb-a{right:9%;top:14%;width:16rem;height:16rem;animation:cosmicMotion4OrbA 8s ease-in-out infinite}#{$motion4Id} .orb-b{right:30%;bottom:8%;width:10rem;height:10rem;animation:cosmicMotion4OrbB 7s ease-in-out infinite}#{$motion4Id} .orb-c{left:55%;top:42%;width:6rem;height:6rem;animation:cosmicMotion4OrbA 6s ease-in-out -1.5s infinite}#{$motion4Id}[data-type='hero_glass_orb_premium']{background:radial-gradient(circle at 30% 20%,var(--hero-glow-soft),transparent 30%),radial-gradient(circle at 80% 70%,var(--hero-glow-strong),transparent 34%),#020617}@keyframes cosmicMotion4OrbA{50%{transform:translate3d(30px,-28px,0) scale(1.08)}}@keyframes cosmicMotion4OrbB{50%{transform:translate3d(-26px,24px,0) scale(.94)}}
#{$motion4Id} .cosmic-motion4-particles{position:absolute;inset:0;width:100%;height:100%;opacity:.82}#{$motion4Id}[data-type='hero_particle_constellation_premium']{background:radial-gradient(circle at 70% 25%,var(--hero-glow-soft),transparent 35%),#020617}
#{$motion4Id}.cosmic-motion4-light{background:#fff!important;color:#0f172a}#{$motion4Id}.cosmic-motion4-light .cosmic-motion4-copy>p,#{$motion4Id}.cosmic-motion4-light .cosmic-motion4-body{color:#475569}#{$motion4Id}.cosmic-motion4-light .cosmic-motion4-actions a{background:{$primaryBgHex};color:#fff}#{$motion4Id}.cosmic-motion4-light .cosmic-motion4-actions a.secondary{background:rgba(255,255,255,.82);color:#0f172a;border-color:#cbd5e1}#{$motion4Id}.cosmic-motion4-light .cosmic-motion4-shade{background:linear-gradient(90deg,rgba(255,255,255,.95),rgba(255,255,255,.86),rgba(255,255,255,.72))}#{$motion4Id}.cosmic-motion4-light .cosmic-motion4-orb{opacity:.32}#{$motion4Id}.cosmic-motion4-light .cosmic-motion4-particles{opacity:.18}
@media(max-width:900px){#{$motion4Id} .cosmic-motion4-grid{grid-template-columns:1fr;gap:2.5rem;padding:5rem 1.5rem}#{$motion4Id} .cosmic-motion4-copywrap{padding:5rem 1.5rem}#{$motion4Id} .cosmic-motion4-stack{height:25rem}#{$motion4Id} .cosmic-motion4-carousel{height:24rem}}@media(prefers-reduced-motion:reduce){#{$motion4Id} .cosmic-motion4-mask,#{$motion4Id} .cosmic-motion4-orb{animation:none!important}#{$motion4Id} .cosmic-motion4-mask{clip-path:none!important;transform:none!important}}
</style>";
                    if ($motion4Type === 'hero_stacked_cards_premium') {
                        $html .= "<script>(function(){const r=document.getElementById(" . json_encode($motion4Id) . ");if(!r)return;const s=r.querySelector('.cosmic-motion4-stack'),cards=[...s.children];let a=0;const paint=()=>cards.forEach((c,i)=>{let d=(i-a+cards.length)%cards.length;c.style.transform='translateY(calc(-50% + '+(d*26)+'px)) translateX('+(d*18)+'px) scale('+(1-d*.055)+')';c.style.opacity=1-d*.22;c.style.zIndex=10-d});paint();if(!matchMedia('(prefers-reduced-motion: reduce)').matches){let id=0;const stop=()=>{if(id){clearInterval(id);id=0}},start=()=>{if(!id)id=setInterval(()=>{a=(a+1)%cards.length;paint()},3200)};new IntersectionObserver(([e])=>e.isIntersecting?start():stop(),{rootMargin:'120px'}).observe(r)}})();</script>";
                    } elseif ($motion4Type === 'hero_perspective_carousel_premium') {
                        $html .= "<script>(function(){const r=document.getElementById(" . json_encode($motion4Id) . ");if(!r)return;const c=r.querySelector('.cosmic-motion4-carousel'),cards=[...c.children];let a=0;const paint=()=>cards.forEach((el,i)=>{let d=(i-a+3)%3;if(d===2)d=-1;el.style.transform='translate(-50%,-50%) translateX('+(d*38)+'%) translateZ('+(d===0?70:-70)+'px) rotateY('+(d*-18)+'deg) scale('+(d===0?1:.82)+')';el.style.opacity=d===0?1:.55;el.style.zIndex=d===0?20:10});cards.forEach((el,i)=>el.addEventListener('click',()=>{a=i;paint()}));paint();if(!matchMedia('(prefers-reduced-motion: reduce)').matches){let id=0;const stop=()=>{if(id){clearInterval(id);id=0}},start=()=>{if(!id)id=setInterval(()=>{a=(a+1)%3;paint()},3600)};new IntersectionObserver(([e])=>e.isIntersecting?start():stop(),{rootMargin:'120px'}).observe(r)}})();</script>";
                    } elseif ($motion4Type === 'hero_before_after_premium') {
                        $html .= "<script>(function(){const r=document.getElementById(" . json_encode($motion4Id) . ");if(!r)return;const b=r.querySelector('.cosmic-motion4-beforeafter'),after=b.querySelector('.after'),img=after.querySelector('img'),line=b.querySelector('.divider');let drag=false;const set=p=>{p=Math.max(4,Math.min(96,p));after.style.width=p+'%';img.style.width=b.clientWidth+'px';line.style.left=p+'%'};set(Number(b.dataset.split||52));const move=e=>{if(!drag)return;const q=b.getBoundingClientRect();set((e.clientX-q.left)/q.width*100)};b.addEventListener('pointerdown',e=>{drag=true;b.setPointerCapture&&b.setPointerCapture(e.pointerId);move(e)});b.addEventListener('pointermove',move);b.addEventListener('pointerup',()=>drag=false);b.addEventListener('pointercancel',()=>drag=false);addEventListener('resize',()=>set(parseFloat(after.style.width)||52),{passive:true})})();</script>";
                    } elseif ($motion4Type === 'hero_scroll_morph_premium') {
                        $html .= "<script>(function(){const r=document.getElementById(" . json_encode($motion4Id) . ");if(!r||matchMedia('(prefers-reduced-motion: reduce)').matches)return;const f=r.querySelector('.cosmic-motion4-morph'),s=Number(f.dataset.strength||28);let raf=0;const run=()=>{const b=r.getBoundingClientRect();if(b.bottom<0||b.top>innerHeight)return;cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>{const p=Math.max(0,Math.min(1,(innerHeight-b.top)/(innerHeight+b.height*.45)));f.style.transform='scale('+(.78+p*.22)+')';f.style.borderRadius=Math.max(0,36-p*s)+'px'})};run();addEventListener('scroll',run,{passive:true});addEventListener('resize',run,{passive:true})})();</script>";
                    } elseif ($motion4Type === 'hero_particle_constellation_premium') {
                        $html .= "<script>(function(){const r=document.getElementById(" . json_encode($motion4Id) . " );if(!r)return;const c=r.querySelector('.cosmic-motion4-particles'),x=c.getContext('2d'),n=Number(c.dataset.count||36),reduced=matchMedia('(prefers-reduced-motion: reduce)').matches,pts=Array.from({length:n},(_,i)=>({x:(i*73%997)/997,y:(i*137%991)/991,vx:((i%5)-2)*.00008,vy:(((i*3)%5)-2)*.00008}));let raf=0,visible=true;const draw=()=>{cancelAnimationFrame(raf);const d=Math.min(2,devicePixelRatio||1),w=c.clientWidth,h=c.clientHeight;if(!w||!h)return;if(c.width!==Math.round(w*d)||c.height!==Math.round(h*d)){c.width=Math.round(w*d);c.height=Math.round(h*d);x.setTransform(d,0,0,d,0,0)}x.clearRect(0,0,w,h);x.fillStyle='rgba(255,255,255,.7)';x.strokeStyle='rgba(148,163,184,.16)';pts.forEach(p=>{if(!reduced&&visible){p.x=(p.x+p.vx+1)%1;p.y=(p.y+p.vy+1)%1}x.beginPath();x.arc(p.x*w,p.y*h,1.4,0,Math.PI*2);x.fill()});for(let i=0;i<pts.length;i++)for(let j=i+1;j<pts.length;j++){const a=pts[i],b=pts[j],dx=(a.x-b.x)*w,dy=(a.y-b.y)*h,dist=Math.hypot(dx,dy);if(dist<130){x.globalAlpha=(1-dist/130)*.75;x.beginPath();x.moveTo(a.x*w,a.y*h);x.lineTo(b.x*w,b.y*h);x.stroke();x.globalAlpha=1}}if(!reduced&&visible)raf=requestAnimationFrame(draw)};new IntersectionObserver(([e])=>{visible=e.isIntersecting;if(visible)draw();else cancelAnimationFrame(raf)},{rootMargin:'120px'}).observe(r);draw();addEventListener('resize',()=>visible&&draw(),{passive:true})})();</script>";
                    }
                    break;

                case 'hero_spotlight_cursor_premium':
                case 'hero_floating_cards_motion_premium':
                case 'hero_3d_tilt_product_premium':
                case 'hero_infinite_marquee_premium':
                case 'hero_rotating_words_premium':
                case 'hero_typewriter_premium':
                case 'hero_curtain_reveal_premium':
                    $motion3Type = (string) ($block['type'] ?? 'hero_spotlight_cursor_premium');
                    $motion3Id = 'cosmic-motion3-hero-' . substr(sha1($motion3Type . '|' . json_encode($block) . '|' . uniqid('', true)), 0, 12);
                    $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'primary');
                    $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                    $motion3StateClass = $lightMedia ? ' cosmic-motion3-light' : '';
                    $primaryThemeForHero = self::getTheme($primaryColor ?: 'midnight');
                    $primaryBgHex = e((string) ($primaryThemeForHero['palette']['background'] ?? '#0f766e'));
                    $heroGradient = is_array($primaryThemeForHero['gradient'] ?? null) ? $primaryThemeForHero['gradient'] : ['glow'=>'#7C3AED','glowSoft'=>'rgba(124, 58, 237, 0.20)','glowStrong'=>'rgba(124, 58, 237, 0.38)'];
                    $heroGradientGlow = e((string) ($heroGradient['glow'] ?? '#7C3AED'));
                    $heroGradientGlowSoft = e((string) ($heroGradient['glowSoft'] ?? 'rgba(124, 58, 237, 0.20)'));
                    $heroGradientGlowStrong = e((string) ($heroGradient['glowStrong'] ?? 'rgba(124, 58, 237, 0.38)'));
                    $heroGradientVars = "--hero-glow:{$heroGradientGlow};--hero-glow-soft:{$heroGradientGlowSoft};--hero-glow-strong:{$heroGradientGlowStrong};";
                    $eyebrow = e((string) ($block['eyebrow'] ?? 'MOTION THAT EARNS ATTENTION'));
                    $heading = e((string) ($block['heading'] ?? 'Make the opening feel alive without slowing the page down.'));
                    $body = e((string) ($block['text'] ?? 'Premium interaction and restrained motion keep the message clear.'));
                    $primaryLabel = e((string) ($block['primary_label'] ?? 'Start a project'));
                    $primaryUrl = e((string) ($block['primary_url'] ?? '#'));
                    $secondaryLabel = e((string) ($block['secondary_label'] ?? 'Explore more'));
                    $secondaryUrl = e((string) ($block['secondary_url'] ?? '#'));
                    $overlay = max(0.20, min(0.85, ((float) ($block['overlayOpacity'] ?? 54)) / 100));
                    $pointerStrength = max(6, min(28, (int) ($block['pointerStrength'] ?? 14)));
                    $interval = max(1400, min(5000, (int) ($block['interval'] ?? 2600)));
                    $image1 = e(self::staticAssetUrl((string) ($block['image_url'] ?? '/storage/cms-images/background/background-1.avif')));
                    $image2 = e(self::staticAssetUrl((string) ($block['image_url_2'] ?? '/storage/cms-images/background/background-2.avif')));
                    $image3 = e(self::staticAssetUrl((string) ($block['image_url_3'] ?? '/storage/cms-images/background/background-3.avif')));
                    $rawWords = $block['rotating_words'] ?? ['faster','smarter','beautifully'];
                    if (is_string($rawWords)) { $rawWords = array_values(array_filter(array_map('trim', explode(',', $rawWords)))); }
                    if (!is_array($rawWords) || $rawWords === []) { $rawWords = ['faster','smarter','beautifully']; }
                    $words = array_values(array_slice(array_map(fn($word) => e((string) $word), $rawWords), 0, 6));
                    $wordsJson = json_encode($words, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                    $actions = "<div class='cosmic-motion3-actions'><a class='cosmic-motion3-primary' href='{$primaryUrl}'>{$primaryLabel}</a><a class='cosmic-motion3-secondary' href='{$secondaryUrl}'>{$secondaryLabel}</a></div>";
                    $baseCopy = "<div class='cosmic-motion3-copy'><p class='cosmic-motion3-eyebrow'>{$eyebrow}</p><h1>{$heading}</h1><p class='cosmic-motion3-body'>{$body}</p>{$actions}</div>";

                    if ($motion3Type === 'hero_spotlight_cursor_premium') {
                        $html .= "<section id='{$motion3Id}' style='{$heroGradientVars}' class='cosmic-motion3-hero cosmic-motion3-spotlight{$motion3StateClass}'><div class='cosmic-motion3-glow'></div><div class='cosmic-motion3-copy-wrap'>{$baseCopy}</div></section>";
                    } elseif ($motion3Type === 'hero_floating_cards_motion_premium') {
                        $cards = "<div class='cosmic-motion3-cards'><article><img src='{$image1}' alt='' width='900' height='560'><i></i><b></b></article><article><img src='{$image2}' alt='' width='900' height='560' loading='lazy'><i></i><b></b></article><article><img src='{$image3}' alt='' width='900' height='560' loading='lazy'><i></i><b></b></article></div>";
                        $html .= "<section id='{$motion3Id}' style='{$heroGradientVars}' class='cosmic-motion3-hero cosmic-motion3-floating{$motion3StateClass}'><div class='cosmic-motion3-bg'><img src='{$image1}' alt='' width='1920' height='1080'></div><div class='cosmic-motion3-overlay' style='opacity:{$overlay}'></div><div class='cosmic-motion3-gridwrap'><div class='cosmic-motion3-copy-wrap'>{$baseCopy}</div>{$cards}</div></section>";
                    } elseif ($motion3Type === 'hero_3d_tilt_product_premium') {
                        $product = "<div class='cosmic-motion3-product'><div class='cosmic-motion3-product-card'><img src='{$image1}' alt='' width='1200' height='750'><div class='cosmic-motion3-metrics'><span><b>98.4%</b><small>Live metric</small></span><span><b>2.4x</b><small>Live metric</small></span><span><b>24/7</b><small>Live metric</small></span></div></div></div>";
                        $html .= "<section id='{$motion3Id}' style='{$heroGradientVars}' class='cosmic-motion3-hero cosmic-motion3-tilt{$motion3StateClass}' data-pointer-strength='{$pointerStrength}'><div class='cosmic-motion3-gridwrap'><div class='cosmic-motion3-copy-wrap'>{$baseCopy}</div>{$product}</div></section>";
                    } elseif ($motion3Type === 'hero_infinite_marquee_premium') {
                        $line1 = str_repeat('BUILD • LAUNCH • GROW • ', 4); $line2 = str_repeat('DESIGN • MOTION • STORY • ', 4); $line3 = str_repeat('IDEAS • PRODUCTS • PEOPLE • ', 4);
                        $html .= "<section id='{$motion3Id}' style='{$heroGradientVars}' class='cosmic-motion3-hero cosmic-motion3-marquee{$motion3StateClass}'><div class='cosmic-motion3-marquee-bg'><div>{$line1}</div><div>{$line2}</div><div>{$line3}</div></div><div class='cosmic-motion3-copy-wrap'>{$baseCopy}</div></section>";
                    } elseif (in_array($motion3Type, ['hero_rotating_words_premium','hero_typewriter_premium'], true)) {
                        $effect = $motion3Type === 'hero_typewriter_premium' ? 'typewriter' : 'rotate';
                        $dynamicCopy = "<div class='cosmic-motion3-copy'><p class='cosmic-motion3-eyebrow'>{$eyebrow}</p><h1>{$heading} <span class='cosmic-motion3-dynamic' data-effect='{$effect}'></span></h1><p class='cosmic-motion3-body'>{$body}</p>{$actions}</div>";
                        $html .= "<section id='{$motion3Id}' style='{$heroGradientVars}' class='cosmic-motion3-hero cosmic-motion3-dynamic-hero{$motion3StateClass}' data-words='{$wordsJson}' data-interval='{$interval}'><div class='cosmic-motion3-copy-wrap'>{$dynamicCopy}</div></section>";
                    } else {
                        $html .= "<section id='{$motion3Id}' style='{$heroGradientVars}' class='cosmic-motion3-hero cosmic-motion3-curtain{$motion3StateClass}'><div class='cosmic-motion3-bg'><img src='{$image1}' alt='' width='1920' height='1080'></div><div class='cosmic-motion3-overlay' style='opacity:{$overlay}'></div><div class='cosmic-motion3-copy-wrap'>{$baseCopy}</div><div class='cosmic-motion3-curtain-left'></div><div class='cosmic-motion3-curtain-right'></div></section>";
                    }

                    $html .= "<style>
#{$motion3Id}.cosmic-motion3-hero{position:relative;isolation:isolate;overflow:hidden;min-height:86svh;background:#020617;color:#fff}#{$motion3Id} .cosmic-motion3-copy-wrap{position:relative;z-index:5;display:flex;align-items:center;min-height:86svh;width:100%;max-width:80rem;margin:0 auto;padding:6rem 3rem}#{$motion3Id} .cosmic-motion3-copy{max-width:48rem}#{$motion3Id} .cosmic-motion3-eyebrow{font-size:.75rem;letter-spacing:.32em;font-weight:800;text-transform:uppercase;color:rgba(255,255,255,.7)}#{$motion3Id} h1{margin-top:1.5rem;font-size:clamp(3rem,7vw,5.4rem);line-height:.95;letter-spacing:-.045em;font-weight:700}#{$motion3Id} .cosmic-motion3-body{margin-top:1.75rem;max-width:42rem;font-size:clamp(1rem,1.5vw,1.15rem);line-height:1.85;color:rgba(255,255,255,.75)}#{$motion3Id} .cosmic-motion3-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2.25rem}#{$motion3Id} .cosmic-motion3-actions a{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:.9rem 1.5rem;font-size:.875rem;font-weight:800;text-decoration:none}#{$motion3Id} .cosmic-motion3-primary{background:#fff;color:#0f172a}#{$motion3Id} .cosmic-motion3-secondary{border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.1);color:#fff}#{$motion3Id} .cosmic-motion3-bg{position:absolute;inset:0}#{$motion3Id} .cosmic-motion3-bg img{width:100%;height:100%;object-fit:cover}#{$motion3Id} .cosmic-motion3-overlay{position:absolute;inset:0;z-index:2;background:#020617}
#{$motion3Id}.cosmic-motion3-spotlight{background:radial-gradient(circle at 20% 20%,var(--hero-glow-soft),transparent 32%),radial-gradient(circle at 85% 70%,var(--hero-glow-strong),transparent 28%),#020617}#{$motion3Id} .cosmic-motion3-glow{position:absolute;width:22rem;height:22rem;border-radius:999px;background:var(--hero-glow-soft);filter:blur(60px);transform:translate(-50%,-50%);pointer-events:none}
#{$motion3Id} .cosmic-motion3-gridwrap{position:relative;z-index:5;display:grid;grid-template-columns:1fr .9fr;gap:4rem;align-items:center;min-height:86svh;max-width:80rem;margin:0 auto;padding:6rem 3rem}#{$motion3Id} .cosmic-motion3-gridwrap .cosmic-motion3-copy-wrap{padding:0;min-height:auto}.cosmic-motion3-cards{position:relative;height:33rem}.cosmic-motion3-cards article{position:absolute;width:72%;padding:.75rem;border:1px solid rgba(255,255,255,.15);border-radius:1.75rem;background:rgba(255,255,255,.1);backdrop-filter:blur(16px);box-shadow:0 30px 80px rgba(0,0,0,.35);animation:cosmicMotion3Float 7s ease-in-out infinite}.cosmic-motion3-cards article:nth-child(2){left:11%;top:5rem;animation-duration:8.4s}.cosmic-motion3-cards article:nth-child(3){left:22%;top:10rem;animation-duration:9.8s}.cosmic-motion3-cards img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover;border-radius:1.2rem}.cosmic-motion3-cards i,.cosmic-motion3-cards b{display:block;height:.5rem;border-radius:999px;background:rgba(255,255,255,.28);margin:1rem 1rem 0}.cosmic-motion3-cards i{width:6rem}.cosmic-motion3-cards b{width:10rem;background:rgba(255,255,255,.12);margin-bottom:.8rem}@keyframes cosmicMotion3Float{50%{transform:translateY(-18px) rotate(2deg)}}
#{$motion3Id} .cosmic-motion3-product{perspective:1200px}#{$motion3Id} .cosmic-motion3-product-card{overflow:hidden;padding:.75rem;border:1px solid rgba(255,255,255,.15);border-radius:2rem;background:rgba(255,255,255,.09);box-shadow:0 34px 90px rgba(0,0,0,.4);transition:transform .2s ease;will-change:transform}#{$motion3Id} .cosmic-motion3-product-card>img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover;border-radius:1.5rem}#{$motion3Id} .cosmic-motion3-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;padding:1rem}#{$motion3Id} .cosmic-motion3-metrics span{padding:1rem;border-radius:1rem;background:rgba(255,255,255,.08)}#{$motion3Id} .cosmic-motion3-metrics b,#{$motion3Id} .cosmic-motion3-metrics small{display:block}#{$motion3Id} .cosmic-motion3-metrics small{margin-top:.25rem;color:rgba(255,255,255,.5)}
#{$motion3Id}.cosmic-motion3-marquee .cosmic-motion3-marquee-bg{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;gap:2rem;opacity:.09;pointer-events:none}#{$motion3Id} .cosmic-motion3-marquee-bg>div{width:max-content;white-space:nowrap;font-size:clamp(5rem,14vw,13rem);line-height:.8;font-weight:900;letter-spacing:-.06em;animation:cosmicMotion3Marquee 26s linear infinite}#{$motion3Id} .cosmic-motion3-marquee-bg>div:nth-child(2){animation-direction:reverse;animation-duration:31s}@keyframes cosmicMotion3Marquee{to{transform:translateX(-50%)}}
#{$motion3Id}.cosmic-motion3-dynamic-hero{background:radial-gradient(circle at 80% 20%,var(--hero-glow-soft),transparent 34%),#020617}#{$motion3Id} .cosmic-motion3-dynamic{color:var(--hero-glow)}#{$motion3Id} .cosmic-motion3-dynamic[data-effect='typewriter']{color:var(--hero-glow)}#{$motion3Id} .cosmic-motion3-dynamic[data-effect='typewriter']::after{content:'';display:inline-block;width:3px;height:.9em;margin-left:.12em;vertical-align:-.05em;background:var(--hero-glow);animation:cosmicMotion3Blink .8s steps(1) infinite}@keyframes cosmicMotion3Blink{50%{opacity:0}}
#{$motion3Id} .cosmic-motion3-curtain-left,#{$motion3Id} .cosmic-motion3-curtain-right{position:absolute;z-index:10;top:0;bottom:0;width:50%;background:#020617;pointer-events:none}#{$motion3Id} .cosmic-motion3-curtain-left{left:0;animation:cosmicMotion3CurtainL 1.25s cubic-bezier(.76,0,.24,1) .15s both}#{$motion3Id} .cosmic-motion3-curtain-right{right:0;animation:cosmicMotion3CurtainR 1.25s cubic-bezier(.76,0,.24,1) .15s both}@keyframes cosmicMotion3CurtainL{to{transform:translateX(-102%)}}@keyframes cosmicMotion3CurtainR{to{transform:translateX(102%)}}
#{$motion3Id}.cosmic-motion3-light{background:#fff!important;color:#0f172a}#{$motion3Id}.cosmic-motion3-light .cosmic-motion3-eyebrow,#{$motion3Id}.cosmic-motion3-light .cosmic-motion3-body{color:#475569}#{$motion3Id}.cosmic-motion3-light .cosmic-motion3-primary{background:{$primaryBgHex};color:#fff}#{$motion3Id}.cosmic-motion3-light .cosmic-motion3-secondary{background:rgba(255,255,255,.82);color:#0f172a;border-color:#cbd5e1}#{$motion3Id}.cosmic-motion3-light .cosmic-motion3-overlay{background:#fff!important;opacity:.92!important}#{$motion3Id}.cosmic-motion3-light .cosmic-motion3-glow,#{$motion3Id}.cosmic-motion3-light .cosmic-motion3-marquee-bg{opacity:.12}#{$motion3Id}.cosmic-motion3-light .cosmic-motion3-curtain-left,#{$motion3Id}.cosmic-motion3-light .cosmic-motion3-curtain-right{background:#fff}
@media(max-width:900px){#{$motion3Id} .cosmic-motion3-gridwrap{grid-template-columns:1fr;padding:5rem 1.5rem}#{$motion3Id} .cosmic-motion3-cards{display:none}#{$motion3Id} .cosmic-motion3-copy-wrap{padding:5rem 1.5rem}.cosmic-motion3-product{margin-top:1rem}}@media(prefers-reduced-motion:reduce){#{$motion3Id} .cosmic-motion3-glow{display:none}.cosmic-motion3-cards article,#{$motion3Id} .cosmic-motion3-marquee-bg>div,#{$motion3Id} .cosmic-motion3-dynamic[data-effect='typewriter']::after{animation:none!important}#{$motion3Id} .cosmic-motion3-curtain-left,#{$motion3Id} .cosmic-motion3-curtain-right{display:none}}
</style>";
                    if ($motion3Type === 'hero_spotlight_cursor_premium') {
                        $html .= "<script>(function(){const r=document.getElementById(" . json_encode($motion3Id) . ");if(!r||matchMedia('(prefers-reduced-motion: reduce)').matches)return;const g=r.querySelector('.cosmic-motion3-glow');let f=0;r.addEventListener('pointermove',e=>{const b=r.getBoundingClientRect();cancelAnimationFrame(f);f=requestAnimationFrame(()=>{g.style.left=(e.clientX-b.left)+'px';g.style.top=(e.clientY-b.top)+'px';});},{passive:true});})();</script>";
                    } elseif ($motion3Type === 'hero_3d_tilt_product_premium') {
                        $html .= "<script>(function(){const r=document.getElementById(" . json_encode($motion3Id) . ");if(!r||matchMedia('(prefers-reduced-motion: reduce)').matches)return;const c=r.querySelector('.cosmic-motion3-product-card');const s=Number(r.dataset.pointerStrength||12);let f=0;r.addEventListener('pointermove',e=>{const b=r.getBoundingClientRect(),x=(e.clientX-b.left)/b.width-.5,y=(e.clientY-b.top)/b.height-.5;cancelAnimationFrame(f);f=requestAnimationFrame(()=>c.style.transform='perspective(1200px) rotateX('+(-y*s)+'deg) rotateY('+(x*s)+'deg) translateZ(18px)');},{passive:true});r.addEventListener('pointerleave',()=>c.style.transform='perspective(1200px) rotateX(0) rotateY(0)');})();</script>";
                    } elseif (in_array($motion3Type, ['hero_rotating_words_premium','hero_typewriter_premium'], true)) {
                        $html .= "<script>(function(){const r=document.getElementById(" . json_encode($motion3Id) . ");if(!r)return;let words=[];try{words=JSON.parse(r.dataset.words||'[]')}catch(e){};if(!words.length)return;const el=r.querySelector('.cosmic-motion3-dynamic');if(!el)return;if(matchMedia('(prefers-reduced-motion: reduce)').matches){el.textContent=words[0];return}const hold=Number(r.dataset.interval||2600);let visible=true,timer=0,i=0,t='',del=false;const clear=()=>{if(timer){clearTimeout(timer);timer=0}};const schedule=(fn,ms)=>{clear();if(visible)timer=setTimeout(fn,ms)};const rotate=()=>{i=(i+1)%words.length;el.animate([{opacity:0,transform:'translateY(8px)'},{opacity:1,transform:'translateY(0)'}],{duration:320});el.textContent=words[i];schedule(rotate,hold)};const type=()=>{const w=words[i];if(!del&&t===w){del=true;return schedule(type,hold)}if(del&&t===''){del=false;i=(i+1)%words.length;return schedule(type,220)}t=w.slice(0,t.length+(del?-1:1));el.textContent=t;schedule(type,del?45:70)};const run=()=>{if(el.dataset.effect==='rotate'){if(!el.textContent)el.textContent=words[0];schedule(rotate,hold)}else{if(!t&&!el.textContent)el.textContent='';schedule(type,70)}};new IntersectionObserver(([e])=>{visible=e.isIntersecting;if(visible)run();else clear()},{rootMargin:'120px'}).observe(r)})();</script>";
                    }
                    break;

                case 'hero_reveal_parallax_premium':
                case 'hero_zoom_scroll_premium':
                case 'hero_pinned_story_premium':
                case 'hero_video_cinematic_premium':
                case 'hero_video_split_premium':
                case 'hero_aurora_motion_premium':
                case 'hero_mesh_gradient_motion_premium':
                    $motionType = (string) ($block['type'] ?? 'hero_reveal_parallax_premium');
                    $motionId = 'cosmic-motion2-hero-' . substr(sha1($motionType . '|' . json_encode($block) . '|' . uniqid('', true)), 0, 12);
                    $eyebrow = e((string) ($block['eyebrow'] ?? 'MOTION, WITH PURPOSE'));
                    $heading = e((string) ($block['heading'] ?? 'Turn the first screen into an experience.'));
                    $body = e((string) ($block['text'] ?? 'Premium motion adds depth while keeping the message clear and fast.'));
                    $primaryLabel = e((string) ($block['primary_label'] ?? 'Start a project'));
                    $primaryUrl = e((string) ($block['primary_url'] ?? '#'));
                    $secondaryLabel = e((string) ($block['secondary_label'] ?? 'Explore more'));
                    $secondaryUrl = e((string) ($block['secondary_url'] ?? '#'));
                    $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'primary');
                    $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                    $overlayHex = self::mediaOverlayColor($resolvedTheme, $primaryColor);
                    $overlay = max($lightMedia ? 0.90 : 0.48, min($lightMedia ? 0.96 : 0.72, ((float) ($block['overlayOpacity'] ?? 56)) / 100));
                    $stateClass = $lightMedia ? ' cosmic-motion2-light' : '';
                    $motionStrength = max(10, min(48, (int) ($block['motionStrength'] ?? 24)));
                    $image1 = e(self::staticAssetUrl((string) ($block['image_url'] ?? '/storage/cms-images/background/background-1.avif')));
                    $image2 = e(self::staticAssetUrl((string) ($block['image_url_2'] ?? '/storage/cms-images/background/background-2.avif')));
                    $image3 = e(self::staticAssetUrl((string) ($block['image_url_3'] ?? '/storage/cms-images/background/background-3.avif')));
                    $primaryThemeForHero = self::getTheme($primaryColor ?: 'midnight');
                    $primaryBgHex = e((string) ($primaryThemeForHero['palette']['background'] ?? '#0f766e'));
                    $lightPrimaryStyle = $lightMedia ? " style='background:{$primaryBgHex};color:#fff'" : '';
                    $poster = e(self::staticAssetUrl((string) ($block['poster_image_url'] ?? $block['image_url'] ?? '/storage/cms-images/background/background-1.avif')));
                    $video = e(self::staticAssetUrl((string) ($block['video_url'] ?? '/storage/cms-videos/hero-placeholder.mp4')));
                    $copy = "<div class='cosmic-motion2-copy'><p class='cosmic-motion2-eyebrow'>{$eyebrow}</p><h1>{$heading}</h1><p class='cosmic-motion2-body'>{$body}</p><div class='cosmic-motion2-actions'><a class='cosmic-motion2-primary'{$lightPrimaryStyle} href='{$primaryUrl}'>{$primaryLabel}</a><a class='cosmic-motion2-secondary' href='{$secondaryUrl}'>{$secondaryLabel}</a></div></div>";

                    if (in_array($motionType, ['hero_aurora_motion_premium', 'hero_mesh_gradient_motion_premium'], true)) {
                        $gradientTheme = self::getTheme($primaryColor ?: 'midnight');
                        $gradient = $gradientTheme['gradient'] ?? [];
                        $gradientFrom = e((string) ($gradient['from'] ?? '#071426'));
                        $gradientVia = e((string) ($gradient['via'] ?? '#111936'));
                        $gradientTo = e((string) ($gradient['to'] ?? '#28164D'));
                        $gradientGlow = e((string) ($gradient['glow'] ?? '#7C3AED'));
                        $gradientGlowSoft = e((string) ($gradient['glowSoft'] ?? 'rgba(124, 58, 237, 0.20)'));
                        $gradientGlowStrong = e((string) ($gradient['glowStrong'] ?? 'rgba(124, 58, 237, 0.38)'));
                        $gradientAngle = max(0, min(360, (int) ($gradient['angle'] ?? 120)));
                        $gradientVars = "--cg-from:{$gradientFrom};--cg-via:{$gradientVia};--cg-to:{$gradientTo};--cg-glow:{$gradientGlow};--cg-glow-soft:{$gradientGlowSoft};--cg-glow-strong:{$gradientGlowStrong};--cg-angle:{$gradientAngle}deg";
                        $isMesh = $motionType === 'hero_mesh_gradient_motion_premium';
                        $motionLayer = $isMesh
                            ? "<div class='cosmic-motion2-mesh'></div><div class='cosmic-motion2-grid'></div>"
                            : "<div class='cosmic-motion2-aurora cosmic-motion2-aurora-a'></div><div class='cosmic-motion2-aurora cosmic-motion2-aurora-b'></div><div class='cosmic-motion2-aurora cosmic-motion2-aurora-c'></div>";
                        $html .= "<section id='{$motionId}' class='cosmic-motion2-hero cosmic-motion2-gradient{$stateClass}' style='{$gradientVars}'>{$motionLayer}<div class='cosmic-motion2-shade'></div><div class='cosmic-motion2-copy-wrap'>{$copy}</div></section>";
                    } elseif (in_array($motionType, ['hero_video_cinematic_premium', 'hero_video_split_premium'], true)) {
                        $splitClass = $motionType === 'hero_video_split_premium' ? ' cosmic-motion2-video-split' : '';
                        $media = "<div class='cosmic-motion2-video'><video autoplay muted loop playsinline preload='metadata' poster='{$poster}'><source src='{$video}'></video><div class='cosmic-motion2-overlay' style='opacity:{$overlay};background:{$overlayHex}'></div><div class='cosmic-motion2-media-gradient'></div></div>";
                        $html .= "<section id='{$motionId}' class='cosmic-motion2-hero{$splitClass}{$stateClass}'>{$media}<div class='cosmic-motion2-copy-wrap'>{$copy}</div></section>";
                    } elseif ($motionType === 'hero_pinned_story_premium') {
                        $html .= "<section id='{$motionId}' class='cosmic-motion2-pinned{$stateClass}' data-motion-strength='{$motionStrength}'><div class='cosmic-motion2-sticky'><div class='cosmic-motion2-story-media is-active' data-story='0'><img src='{$image1}' alt='' width='1920' height='1080' loading='eager' fetchpriority='high' decoding='async'></div><div class='cosmic-motion2-story-media' data-story='1'><img src='{$image2}' alt='' width='1920' height='1080' loading='lazy' decoding='async'></div><div class='cosmic-motion2-story-media' data-story='2'><img src='{$image3}' alt='' width='1920' height='1080' loading='lazy' decoding='async'></div><div class='cosmic-motion2-overlay' style='opacity:{$overlay};background:{$overlayHex}'></div><div class='cosmic-motion2-media-gradient'></div><div class='cosmic-motion2-copy-wrap'>{$copy}</div><div class='cosmic-motion2-dots'><span class='is-active'></span><span></span><span></span></div></div></section>";
                    } else {
                        $modeClass = $motionType === 'hero_reveal_parallax_premium' ? ' cosmic-motion2-reveal' : ' cosmic-motion2-zoom';
                        $html .= "<section id='{$motionId}' class='cosmic-motion2-hero{$modeClass}{$stateClass}' data-motion-strength='{$motionStrength}'><div class='cosmic-motion2-scroll-media'><img src='{$image1}' alt='' width='1920' height='1080' loading='eager' fetchpriority='high' decoding='async'></div><div class='cosmic-motion2-overlay' style='opacity:{$overlay};background:{$overlayHex}'></div><div class='cosmic-motion2-media-gradient'></div><div class='cosmic-motion2-copy-wrap'>{$copy}</div></section>";
                    }

                    $html .= "<style>
#{$motionId}.cosmic-motion2-hero{position:relative;isolation:isolate;overflow:hidden;min-height:86svh;background:#020617;color:#fff}#{$motionId} .cosmic-motion2-copy-wrap{position:relative;z-index:4;display:flex;align-items:center;min-height:86svh;width:100%;max-width:80rem;margin:0 auto;padding:6rem 3rem}#{$motionId} .cosmic-motion2-copy{max-width:48rem}#{$motionId} .cosmic-motion2-eyebrow{font-size:.75rem;letter-spacing:.32em;font-weight:800;text-transform:uppercase;color:rgba(255,255,255,.7)}#{$motionId} h1{margin-top:1.5rem;font-size:clamp(3rem,7vw,5.4rem);line-height:.95;letter-spacing:-.045em;font-weight:700}#{$motionId} .cosmic-motion2-body{margin-top:1.75rem;max-width:42rem;font-size:clamp(1rem,1.5vw,1.15rem);line-height:1.85;color:rgba(255,255,255,.75)}#{$motionId} .cosmic-motion2-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2.25rem}#{$motionId} .cosmic-motion2-actions a{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:.9rem 1.5rem;font-size:.875rem;font-weight:800;text-decoration:none}#{$motionId} .cosmic-motion2-primary{background:#fff;color:#0f172a}#{$motionId} .cosmic-motion2-secondary{border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.1);color:#fff;backdrop-filter:blur(10px)}#{$motionId} .cosmic-motion2-overlay{position:absolute;inset:0;background:#020617;z-index:2}#{$motionId} .cosmic-motion2-media-gradient{position:absolute;inset:0;z-index:3;background:linear-gradient(90deg,rgba(2,6,23,.72),rgba(2,6,23,.18),rgba(2,6,23,.04))}
#{$motionId} .cosmic-motion2-scroll-media{position:absolute;inset:0;overflow:hidden;will-change:transform;transform:scale(1.04)}#{$motionId} .cosmic-motion2-scroll-media img{width:100%;height:100%;object-fit:cover}#{$motionId}.cosmic-motion2-reveal .cosmic-motion2-scroll-media{clip-path:inset(8% 4% 8% 4% round 28px)}
#{$motionId} .cosmic-motion2-video{position:absolute;inset:0;overflow:hidden;background:#0f172a}#{$motionId} .cosmic-motion2-video video{width:100%;height:100%;object-fit:cover}#{$motionId}.cosmic-motion2-video-split{display:grid;grid-template-columns:.9fr 1.1fr;min-height:760px}#{$motionId}.cosmic-motion2-video-split .cosmic-motion2-copy-wrap{grid-column:1;grid-row:1;min-height:760px}#{$motionId}.cosmic-motion2-video-split .cosmic-motion2-video{position:relative;grid-column:2;grid-row:1;min-height:760px}#{$motionId}.cosmic-motion2-video-split .cosmic-motion2-media-gradient{background:linear-gradient(90deg,rgba(2,6,23,.25),rgba(2,6,23,.02))}
#{$motionId}.cosmic-motion2-pinned{position:relative;height:165svh;background:#020617;color:#fff}#{$motionId} .cosmic-motion2-sticky{position:sticky;top:0;height:100svh;overflow:hidden;isolation:isolate}#{$motionId} .cosmic-motion2-story-media{position:absolute;inset:0;opacity:0;transform:scale(1.04);transition:opacity .7s ease,transform .7s ease}#{$motionId} .cosmic-motion2-story-media.is-active{opacity:1;transform:scale(1)}#{$motionId} .cosmic-motion2-story-media img{width:100%;height:100%;object-fit:cover}#{$motionId} .cosmic-motion2-dots{position:absolute;right:2rem;bottom:2rem;z-index:5;display:flex;gap:.5rem}#{$motionId} .cosmic-motion2-dots span{display:block;width:20px;height:4px;border-radius:999px;background:rgba(255,255,255,.3);transition:.35s ease}#{$motionId} .cosmic-motion2-dots span.is-active{width:48px;background:#fff}
@keyframes cosmicAurora{$motionId}{0%,100%{transform:translate3d(-8%,-5%,0) rotate(-8deg) scale(1)}50%{transform:translate3d(10%,8%,0) rotate(8deg) scale(1.12)}}@keyframes cosmicMesh{$motionId}{0%,100%{transform:translate3d(-4%,-3%,0) scale(1)}33%{transform:translate3d(7%,-5%,0) scale(1.08)}66%{transform:translate3d(2%,8%,0) scale(1.13)}}#{$motionId}.cosmic-motion2-gradient{background:linear-gradient(var(--cg-angle),var(--cg-from),var(--cg-via),var(--cg-to))}#{$motionId} .cosmic-motion2-shade{position:absolute;inset:0;z-index:2;background:linear-gradient(180deg,rgba(2,6,23,.06),rgba(2,6,23,.68))}#{$motionId} .cosmic-motion2-aurora{position:absolute;border-radius:50%;filter:blur(90px);will-change:transform}#{$motionId} .cosmic-motion2-aurora-a{left:-15%;top:-35%;width:75%;height:90%;background:var(--cg-glow-strong);animation:cosmicAurora{$motionId} 14s ease-in-out infinite}#{$motionId} .cosmic-motion2-aurora-b{right:-15%;bottom:-30%;width:70%;height:85%;background:color-mix(in srgb,var(--cg-via) 72%,transparent);animation:cosmicAurora{$motionId} 18s ease-in-out infinite reverse}#{$motionId} .cosmic-motion2-aurora-c{left:40%;top:25%;width:45%;height:45%;background:var(--cg-glow-soft);filter:blur(100px)}#{$motionId} .cosmic-motion2-mesh{position:absolute;inset:-25%;opacity:.88;filter:blur(58px);background:radial-gradient(circle at 20% 25%,var(--cg-glow-strong),transparent 28%),radial-gradient(circle at 75% 20%,var(--cg-glow-soft),transparent 31%),radial-gradient(circle at 65% 78%,color-mix(in srgb,var(--cg-via) 75%,transparent),transparent 30%),radial-gradient(circle at 25% 80%,color-mix(in srgb,var(--cg-to) 72%,transparent),transparent 28%);animation:cosmicMesh{$motionId} 16s ease-in-out infinite}#{$motionId} .cosmic-motion2-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);background-size:56px 56px}
#{$motionId}.cosmic-motion2-light,#{$motionId}.cosmic-motion2-pinned.cosmic-motion2-light{background:#fff;color:#0f172a}#{$motionId}.cosmic-motion2-light .cosmic-motion2-eyebrow{color:#64748b}#{$motionId}.cosmic-motion2-light .cosmic-motion2-body{color:#475569}#{$motionId}.cosmic-motion2-light .cosmic-motion2-primary{background:var(--cosmic-primary-bg,#0f766e);color:#fff}#{$motionId}.cosmic-motion2-light .cosmic-motion2-secondary{border-color:#cbd5e1;background:rgba(255,255,255,.72);color:#0f172a}#{$motionId}.cosmic-motion2-light .cosmic-motion2-media-gradient{background:linear-gradient(90deg,rgba(255,255,255,.78),rgba(255,255,255,.34),rgba(255,255,255,.12))}#{$motionId}.cosmic-motion2-light.cosmic-motion2-gradient{background:#fff!important}#{$motionId}.cosmic-motion2-light .cosmic-motion2-aurora,#{$motionId}.cosmic-motion2-light .cosmic-motion2-mesh,#{$motionId}.cosmic-motion2-light .cosmic-motion2-grid{opacity:.10!important}#{$motionId}.cosmic-motion2-light .cosmic-motion2-shade{background:rgba(255,255,255,.92)}#{$motionId}.cosmic-motion2-light .cosmic-motion2-dots span{background:rgba(100,116,139,.35)}#{$motionId}.cosmic-motion2-light .cosmic-motion2-dots span.is-active{background:#334155}
@media(max-width:900px){#{$motionId} .cosmic-motion2-copy-wrap{padding:5rem 1.5rem}#{$motionId}.cosmic-motion2-video-split{display:block;min-height:86svh}#{$motionId}.cosmic-motion2-video-split .cosmic-motion2-video{position:absolute;inset:0;min-height:0}#{$motionId}.cosmic-motion2-video-split .cosmic-motion2-copy-wrap{min-height:86svh}#{$motionId}.cosmic-motion2-pinned{height:145svh}}
@media(prefers-reduced-motion:reduce){#{$motionId} .cosmic-motion2-scroll-media,#{$motionId} .cosmic-motion2-story-media,#{$motionId} .cosmic-motion2-aurora,#{$motionId} .cosmic-motion2-mesh{animation:none!important;transition:none!important;transform:none!important}#{$motionId}.cosmic-motion2-reveal .cosmic-motion2-scroll-media{clip-path:none!important}}
</style>";

                    if (in_array($motionType, ['hero_reveal_parallax_premium', 'hero_zoom_scroll_premium', 'hero_pinned_story_premium'], true)) {
                        $html .= "<script>(function(){const root=document.getElementById('{$motionId}');if(!root)return;const reduced=matchMedia('(prefers-reduced-motion: reduce)');const type='{$motionType}';let raf=null;function update(){if(reduced.matches)return;const r=root.getBoundingClientRect(),vh=innerHeight||1;if(type==='hero_pinned_story_premium'){const span=Math.max(1,r.height-vh),p=Math.max(0,Math.min(1,-r.top/span)),chapter=Math.min(2,Math.floor(p*3));root.querySelectorAll('[data-story]').forEach((el,i)=>el.classList.toggle('is-active',i===chapter));root.querySelectorAll('.cosmic-motion2-dots span').forEach((el,i)=>el.classList.toggle('is-active',i===chapter));return;}if(r.bottom<0||r.top>vh)return;const p=Math.max(0,Math.min(1,(vh-r.top)/(vh+r.height))),media=root.querySelector('.cosmic-motion2-scroll-media');if(!media)return;if(raf)cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>{if(type==='hero_reveal_parallax_premium'){const inset=Math.max(0,(1-p)*10);media.style.clipPath='inset('+inset+'% '+(inset*.55)+'% '+inset+'% '+(inset*.55)+'% round '+Math.max(0,(1-p)*32)+'px)';media.style.transform='translate3d(0,'+((.5-p)*{$motionStrength})+'px,0) scale('+(1.06-p*.035)+')';}else{media.style.transform='scale('+(1.02+p*({$motionStrength}/500))+') translate3d(0,'+((p-.5)*{$motionStrength}*.35)+'px,0)';}});}addEventListener('scroll',update,{passive:true});addEventListener('resize',update,{passive:true});update();})();</script>";
                    }
                    break;

                case 'hero_ken_burns_premium':
                case 'hero_crossfade_gallery_premium':
                case 'hero_cinematic_slider_premium':
                case 'hero_split_slider_premium':
                case 'hero_vertical_story_premium':
                case 'hero_parallax_layers_premium':
                case 'hero_mouse_parallax_premium':
                    $animatedType = (string) ($block['type'] ?? 'hero_ken_burns_premium');
                    $animatedId = 'cosmic-motion-hero-' . substr(sha1($animatedType . '|' . json_encode($block) . '|' . uniqid('', true)), 0, 12);
                    $eyebrow = e((string) ($block['eyebrow'] ?? 'MOTION, WITH PURPOSE'));
                    $heading = e((string) ($block['heading'] ?? 'A premium first impression that feels alive.'));
                    $body = e((string) ($block['text'] ?? 'Pair confident messaging with considered motion and a clear next step.'));
                    $primaryLabel = e((string) ($block['primary_label'] ?? 'Start a project'));
                    $primaryUrl = e((string) ($block['primary_url'] ?? '#'));
                    $secondaryLabel = e((string) ($block['secondary_label'] ?? 'Explore more'));
                    $secondaryUrl = e((string) ($block['secondary_url'] ?? '#'));
                    $images = array_values(array_filter([
                        (string) ($block['image_url'] ?? ''),
                        (string) ($block['image_url_2'] ?? ''),
                        (string) ($block['image_url_3'] ?? ''),
                    ]));
                    if ($images === []) {
                        $images = ['/storage/cms-images/background/background-1.avif'];
                    }
                    $resolvedTheme = (string) ($blockTheme ?? $block['resolvedTheme'] ?? $selectedThemeName ?? 'primary');
                    $lightMedia = self::$currentPageStyle === 'clean' || in_array($resolvedTheme, ['white', 'surface', 'stone'], true);
                    $overlayHex = self::mediaOverlayColor($resolvedTheme, $primaryColor);
                    $overlay = max($lightMedia ? 0.90 : 0.48, min($lightMedia ? 0.96 : 0.72, ((float) ($block['overlayOpacity'] ?? 58)) / 100));
                    $stateClass = $lightMedia ? ' cosmic-motion-light' : '';
                    $primaryThemeForHero = self::getTheme($primaryColor ?: 'midnight');
                    $primaryBgHex = e((string) ($primaryThemeForHero['palette']['background'] ?? '#0f766e'));
                    $lightPrimaryStyle = $lightMedia ? " style='background:{$primaryBgHex};color:#fff'" : '';
                    $interval = max(3200, (int) ($block['interval'] ?? 5000));
                    $isSplit = $animatedType === 'hero_split_slider_premium';
                    $isVertical = $animatedType === 'hero_vertical_story_premium';
                    $isKenBurns = $animatedType === 'hero_ken_burns_premium';
                    $isLayered = in_array($animatedType, ['hero_parallax_layers_premium', 'hero_mouse_parallax_premium'], true);
                    $mediaMarkup = '';
                    foreach ($images as $imageIndex => $rawImage) {
                        $image = e(self::staticAssetUrl($rawImage));
                        $activeClass = $imageIndex === 0 ? 'is-active' : '';
                        $kenClass = $isKenBurns && $imageIndex === 0 ? ' cosmic-motion-kenburns' : '';
                        $layerAttr = $isLayered ? " data-motion-layer='{$imageIndex}'" : '';
                        $mediaMarkup .= "<div class='cosmic-motion-media {$activeClass}{$kenClass}' data-slide='{$imageIndex}'{$layerAttr}><img src='{$image}' alt='' width='1920' height='1080' " . ($imageIndex === 0 ? "loading='eager' fetchpriority='high'" : "loading='lazy'") . " decoding='async'></div>";
                    }
                    $dots = '';
                    if (in_array($animatedType, ['hero_crossfade_gallery_premium','hero_cinematic_slider_premium','hero_split_slider_premium','hero_vertical_story_premium'], true) && count($images) > 1) {
                        foreach ($images as $imageIndex => $_) {
                            $dots .= "<span class='cosmic-motion-dot " . ($imageIndex === 0 ? 'is-active' : '') . "' data-dot='{$imageIndex}'></span>";
                        }
                    }
                    $layoutClass = $isSplit ? ' cosmic-motion-split' : '';
                    $html .= "
<section id='{$animatedId}' class='cosmic-motion-hero{$layoutClass}{$stateClass}' data-motion-type='{$animatedType}' data-interval='{$interval}'>
  <div class='cosmic-motion-media-wrap'>{$mediaMarkup}<div class='cosmic-motion-overlay' style='opacity:{$overlay};background:{$overlayHex}'></div><div class='cosmic-motion-gradient'></div></div>
  <div class='cosmic-motion-copy-wrap'><div class='cosmic-motion-copy'>
    <p class='cosmic-motion-eyebrow'>{$eyebrow}</p>
    <h1>{$heading}</h1>
    <p class='cosmic-motion-body'>{$body}</p>
    <div class='cosmic-motion-actions'><a href='{$primaryUrl}' class='cosmic-motion-primary'{$lightPrimaryStyle}>{$primaryLabel}</a><a href='{$secondaryUrl}' class='cosmic-motion-secondary'>{$secondaryLabel}</a></div>
    " . ($dots !== '' ? "<div class='cosmic-motion-progress'>{$dots}</div>" : '') . "
  </div></div>
</section>
<style>
#{$animatedId}{position:relative;isolation:isolate;overflow:hidden;min-height:82svh;background:#020617;color:#fff;display:flex}#{$animatedId} .cosmic-motion-media-wrap{position:absolute;inset:0;overflow:hidden}#{$animatedId} .cosmic-motion-media{position:absolute;inset:0;opacity:0;transform:scale(1.025);transition:opacity 1s ease,transform 1s ease}#{$animatedId} .cosmic-motion-media.is-active{opacity:1;transform:scale(1)}#{$animatedId} .cosmic-motion-media img{width:100%;height:100%;object-fit:cover}#{$animatedId} .cosmic-motion-overlay{position:absolute;inset:0;background:#020617}#{$animatedId} .cosmic-motion-gradient{position:absolute;inset:0;background:linear-gradient(90deg,rgba(2,6,23,.76),rgba(2,6,23,.22),rgba(2,6,23,.06))}#{$animatedId} .cosmic-motion-copy-wrap{position:relative;z-index:3;width:100%;max-width:80rem;margin:auto;display:flex;align-items:center;min-height:82svh;padding:6rem 3rem}#{$animatedId} .cosmic-motion-copy{max-width:48rem}#{$animatedId} .cosmic-motion-eyebrow{font-size:.75rem;letter-spacing:.32em;font-weight:800;text-transform:uppercase;color:rgba(255,255,255,.7)}#{$animatedId} h1{margin-top:1.5rem;font-size:clamp(3rem,7vw,5.4rem);line-height:.95;letter-spacing:-.045em;font-weight:700}#{$animatedId} .cosmic-motion-body{margin-top:1.75rem;max-width:42rem;font-size:clamp(1rem,1.5vw,1.15rem);line-height:1.85;color:rgba(255,255,255,.74)}#{$animatedId} .cosmic-motion-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2.25rem}#{$animatedId} .cosmic-motion-actions a{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:.9rem 1.5rem;font-size:.875rem;font-weight:800;text-decoration:none}#{$animatedId} .cosmic-motion-primary{background:#fff;color:#0f172a}#{$animatedId} .cosmic-motion-secondary{border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.1);color:#fff;backdrop-filter:blur(10px)}#{$animatedId} .cosmic-motion-progress{display:flex;align-items:center;gap:.5rem;margin-top:2.5rem}#{$animatedId} .cosmic-motion-dot{height:4px;width:20px;border-radius:999px;background:rgba(255,255,255,.3);transition:width .4s ease,background .4s ease}#{$animatedId} .cosmic-motion-dot.is-active{width:48px;background:#fff}
#{$animatedId}.cosmic-motion-split{display:grid;grid-template-columns:.9fr 1.1fr;min-height:720px}#{$animatedId}.cosmic-motion-split .cosmic-motion-copy-wrap{grid-column:1;grid-row:1;min-height:720px;padding:5rem 3rem}#{$animatedId}.cosmic-motion-split .cosmic-motion-media-wrap{position:relative;grid-column:2;grid-row:1;min-height:720px}#{$animatedId}.cosmic-motion-split .cosmic-motion-gradient{background:linear-gradient(90deg,rgba(2,6,23,.45),rgba(2,6,23,.04))}
#{$animatedId}.cosmic-motion-light{background:#fff;color:#0f172a}#{$animatedId}.cosmic-motion-light .cosmic-motion-eyebrow{color:#64748b}#{$animatedId}.cosmic-motion-light .cosmic-motion-body{color:#475569}#{$animatedId}.cosmic-motion-light .cosmic-motion-primary{background:var(--cosmic-primary-bg,#0f766e);color:#fff}#{$animatedId}.cosmic-motion-light .cosmic-motion-secondary{border-color:#cbd5e1;background:rgba(255,255,255,.72);color:#0f172a}#{$animatedId}.cosmic-motion-light .cosmic-motion-gradient{background:linear-gradient(90deg,rgba(255,255,255,.78),rgba(255,255,255,.34),rgba(255,255,255,.12))}#{$animatedId}.cosmic-motion-light .cosmic-motion-dot{background:rgba(100,116,139,.35)}#{$animatedId}.cosmic-motion-light .cosmic-motion-dot.is-active{background:#334155}
#{$animatedId}[data-motion-type='hero_vertical_story_premium'] .cosmic-motion-media{opacity:0;transform:translateY(100%)}#{$animatedId}[data-motion-type='hero_vertical_story_premium'] .cosmic-motion-media.is-active{opacity:1;transform:translateY(0)}
@keyframes cosmicKenBurns{$animatedId}{0%{transform:scale(1.04) translate(-.5%,-.3%)}50%{transform:scale(1.11) translate(.8%,.5%)}100%{transform:scale(1.16) translate(-.2%,.8%)}}#{$animatedId} .cosmic-motion-kenburns{opacity:1;animation:cosmicKenBurns{$animatedId} 18s ease-in-out infinite alternate;will-change:transform}
@media(max-width:900px){#{$animatedId}.cosmic-motion-split{display:block;min-height:82svh}#{$animatedId}.cosmic-motion-split .cosmic-motion-media-wrap{position:absolute;inset:0;min-height:0}#{$animatedId}.cosmic-motion-split .cosmic-motion-copy-wrap{min-height:82svh;padding:5rem 1.5rem}#{$animatedId} .cosmic-motion-copy-wrap{padding:5rem 1.5rem}}
@media(prefers-reduced-motion:reduce){#{$animatedId} .cosmic-motion-media,#{$animatedId} .cosmic-motion-kenburns{transition:none!important;animation:none!important;transform:none!important}}
</style>";
                    if (count($images) > 1 || $isLayered) {
                        $strength = $animatedType === 'hero_mouse_parallax_premium' ? max(6, min(28, (int) ($block['pointerStrength'] ?? 16))) : max(8, min(36, (int) ($block['parallaxStrength'] ?? 22)));
                        $html .= "<script>(function(){const root=document.getElementById('{$animatedId}');if(!root)return;const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');const type=root.dataset.motionType;const slides=[...root.querySelectorAll('[data-slide]')];const dots=[...root.querySelectorAll('[data-dot]')];let active=0,timer=null,raf=null;function show(i){active=(i+slides.length)%slides.length;slides.forEach((el,n)=>el.classList.toggle('is-active',n===active));dots.forEach((el,n)=>el.classList.toggle('is-active',n===active));}if(!reduced.matches&&['hero_crossfade_gallery_premium','hero_cinematic_slider_premium','hero_split_slider_premium','hero_vertical_story_premium'].includes(type)&&slides.length>1){timer=setInterval(()=>show(active+1),Number(root.dataset.interval)||5000);}function move(x,y){root.querySelectorAll('[data-motion-layer]').forEach((el,n)=>{const d=(n+1)*.38;el.style.transform='translate3d('+(x*d)+'px,'+(y*d)+'px,0) scale('+(1.04+n*.025)+')';});}function pointer(e){if(type!=='hero_mouse_parallax_premium'||reduced.matches)return;const r=root.getBoundingClientRect();const x=((e.clientX-r.left)/Math.max(1,r.width)-.5)*{$strength};const y=((e.clientY-r.top)/Math.max(1,r.height)-.5)*{$strength};if(raf)cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>move(x,y));}function scroll(){if(type!=='hero_parallax_layers_premium'||reduced.matches)return;const r=root.getBoundingClientRect(),vh=innerHeight||1;if(r.bottom<0||r.top>vh)return;const y=((vh-r.top)/(vh+r.height)-.5)*{$strength};if(raf)cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>move(0,y));}root.addEventListener('pointermove',pointer,{passive:true});root.addEventListener('pointerleave',()=>move(0,0),{passive:true});addEventListener('scroll',scroll,{passive:true});scroll();})();</script>";
                    }
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
