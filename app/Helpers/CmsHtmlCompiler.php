<?php

namespace App\Helpers;

use App\Support\PageStyleRegistry;

class CmsHtmlCompiler
{
    private static ?array $themeCatalog = null;

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
            return $themes[$key];
        }

        return $themes['amber'];
    }

    /**
     * Static sites live outside Laravel's public directory, so relative CMS
     * storage paths must resolve back to the CMS asset host.
     */
    private static function staticAssetUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '' || preg_match('/^(?:https?:)?\/\//i', $url) || str_starts_with($url, 'data:')) {
            return $url;
        }

        $baseUrl = rtrim((string) config('services.cosmic.asset_base_url', config('app.url')), '/');

        return $baseUrl . '/' . ltrim($url, '/');
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
                $options = "<option value='' disabled selected style='background-color:#334b67;color:#cbd5e1'>" . e($field['placeholder'] ?: 'Select an option') . '</option>';
                foreach ($field['options'] ?: ['Option one', 'Option two'] as $option) {
                    $option = e($option);
                    $options .= "<option value='{$option}' style='background-color:#334b67;color:#f8fafc'>{$option}</option>";
                }
                $markup .= "<label class='block text-sm font-semibold {$theme['text']}'>{$label}{$requiredMark}<select name='{$name}'{$required} style='color-scheme:{$nativeColorScheme};background-color:#334b67;color:#f8fafc' class='mt-2 h-12 w-full rounded-xl border px-4 text-sm outline-none {$inputClasses}'>{$options}</select></label>";
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

    /** Keep AI/editor rich text safe while preserving the same structure shown in Builder. */
    private static function blogPostContentHtml(?string $content, string $fallback = ''): string
    {
        $content = trim((string) $content);

        if ($content === '') {
            return $fallback !== '' ? '<p>' . e($fallback) . '</p>' : '';
        }

        if (preg_match('/<(?:p|h2|h3|ul|ol|li|blockquote|strong|em|a)\b/i', $content) === 1) {
            return strip_tags($content, '<h2><h3><p><ul><ol><li><strong><em><blockquote><a>');
        }

        $paragraphs = preg_split('/\R{2,}/', $content) ?: [];

        return collect($paragraphs)
            ->map(fn ($paragraph) => trim((string) $paragraph))
            ->filter()
            ->map(fn ($paragraph) => '<p>' . nl2br(e($paragraph)) . '</p>')
            ->implode("\n");
    }

    /** Compile one published blog post using the same visual hierarchy as Builder. */
    public static function compileBlogPost(array $post, string $primaryColor = null, string $backUrl = 'blog/'): string
    {
        $theme = self::getTheme('white');
        $title = e($post['title'] ?? 'Untitled article');
        $category = e($post['category'] ?? 'Article');
        $excerpt = e($post['excerpt'] ?? '');
        $content = self::blogPostContentHtml($post['content'] ?? '', $post['excerpt'] ?? '');
        $image = e(self::staticAssetUrl($post['image_url'] ?? '/storage/cms-images/background/background-1.avif'));
        $backUrl = e($backUrl);
        $tags = is_array($post['tags'] ?? null) ? array_slice($post['tags'], 0, 8) : [];
        $tagMarkup = collect($tags)
            ->map(fn ($tag) => "<span class='rounded-full border px-3 py-1 text-xs {$theme['border']} {$theme['sub']}'>#" . e((string) $tag) . '</span>')
            ->implode('');

        return "<article class='bg-[#fcfcfb] px-6 py-16 sm:px-8 lg:px-12 lg:py-24'><div class='mx-auto w-full max-w-6xl'><a href='{$backUrl}' class='mb-5 inline-block text-sm font-semibold text-slate-900 hover:underline'>← Back to all posts</a><img src='{$image}' alt='{$title}' class='max-h-[620px] w-full rounded-[15px] object-cover'><div class='mx-auto max-w-4xl py-10 sm:py-14'><p class='text-xs font-semibold uppercase tracking-[0.22em] text-slate-600'>{$category}</p><h1 class='mt-4 text-4xl font-bold leading-tight tracking-tight text-slate-900 sm:text-5xl'>{$title}</h1>" . ($excerpt !== '' ? "<p class='mt-5 text-lg leading-8 text-slate-600'>{$excerpt}</p>" : '') . "<div class='cosmic-blog-content mt-9 max-w-none text-base leading-8 text-slate-900 [&_p]:mb-5 [&_h2]:mb-4 [&_h2]:mt-9 [&_h2]:text-3xl [&_h3]:mb-3 [&_h3]:mt-7 [&_h3]:text-2xl [&_ul]:mb-5 [&_ul]:pl-6 [&_ol]:mb-5 [&_ol]:pl-6 [&_blockquote]:my-6'>{$content}</div>" . ($tagMarkup !== '' ? "<div class='mt-10 flex flex-wrap gap-2'>{$tagMarkup}</div>" : '') . "</div></div></article>";
    }

   public static function compile(array $blocks, string $primaryColor = null, array $context = []): string
    {
        $html = "";
    
        // 2. Mapping


        foreach ($blocks as $index => $block) {


            $pattern = PageStyleRegistry::pattern($context['page_style'] ?? null);

            $blockTheme = $block['theme'] ?? "auto";

            if ($blockTheme === "auto") {
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
                case 'newsletter_cta':
                // v2.7.4: Newsletter Sparks always inherit the active website
                // primary color instead of using a hardcoded midnight panel.
                $theme = self::getTheme($primaryColor);
                $variant = $block['layout_variant'] ?? 'newsletter-01';
                $eyebrow = e($block['eyebrow'] ?? 'Stay in the loop');
                $heading = e($block['heading'] ?? 'Get weekly insights');
                $text = e($block['text'] ?? 'Practical ideas, useful updates, and new resources delivered occasionally.');
                $placeholder = e($block['placeholder'] ?? 'Your email address');
                $buttonLabel = e($block['button_label'] ?? 'Subscribe');
                $disclaimer = e($block['disclaimer'] ?? 'No spam. Unsubscribe anytime.');
                $panelLayout = $variant === 'newsletter-02'
                    ? 'text-center'
                    : ($variant === 'newsletter-03' ? 'grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-center' : 'lg:flex lg:items-center lg:justify-between lg:gap-12');
                $copyLayout = $variant === 'newsletter-02' ? 'mx-auto max-w-2xl' : 'max-w-2xl';
                $formLayout = $variant === 'newsletter-02' ? 'mx-auto mt-8' : ($variant === 'newsletter-03' ? '' : 'mt-8 lg:mt-0');
                $html .= "<section class='bg-[#fcfcfb] px-6 py-14 sm:px-8 lg:px-12 lg:py-20'><div class='mx-auto max-w-7xl'><div class='rounded-3xl border px-6 py-10 shadow-[0_24px_70px_rgba(15,23,42,0.16)] sm:px-10 lg:px-14 lg:py-12 {$panelLayout} {$theme['bg']} {$theme['border']} {$theme['text']}'><div class='{$copyLayout}'><p class='text-xs font-semibold uppercase tracking-[0.28em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-3xl font-bold leading-[1.05] tracking-tight sm:text-4xl'>{$heading}</h2><p class='mt-4 max-w-xl text-base leading-7 {$theme['sub']}'>{$text}</p></div><form class='{$formLayout} w-full max-w-md' onsubmit='return false'><div class='flex flex-col gap-3 sm:flex-row'><input type='email' aria-label='Email address' placeholder='{$placeholder}' class='min-h-[50px] flex-1 rounded-xl border px-4 text-sm outline-none {$theme['card']} {$theme['border']} {$theme['text']}'><button type='submit' class='min-h-[50px] rounded-xl px-6 text-sm font-bold {$theme['card']} {$theme['text']}'>{$buttonLabel}</button></div><p class='mt-3 text-xs {$theme['sub']}'>{$disclaimer}</p></form></div></div></section>";
                break;

                case 'latest_resources':
                $eyebrow = e($block['eyebrow'] ?? 'Keep exploring');
                $heading = e($block['heading'] ?? 'Latest resources');
                $text = e($block['text'] ?? 'Helpful next reads for visitors who want to learn more.');
                $variant = $block['layout_variant'] ?? 'resources-01';
                $resources = is_array($block['resources'] ?? null) ? array_slice($block['resources'], 0, 2) : [
                    ['eyebrow' => 'Guide', 'title' => 'A practical checklist for your next step', 'text' => 'A concise starting point for making a clearer, more confident decision.', 'cta_label' => 'Read the guide', 'cta_url' => '#'],
                    ['eyebrow' => 'Resource', 'title' => 'Questions worth asking before you begin', 'text' => 'Use this focused resource to prepare for a better conversation with your team.', 'cta_label' => 'Explore resource', 'cta_url' => '#'],
                ];
                $resourceMarkup = '';
                $resourceCardClass = $variant === 'resources-02' ? 'grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-[130px_1fr]' : 'group rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg sm:p-8';
                foreach ($resources as $resource) {
                    if (!is_array($resource)) continue;
                    $resourceMarkup .= "<article class='{$resourceCardClass}'><p class='text-[11px] font-semibold uppercase tracking-[0.22em] text-violet-700'>" . e($resource['eyebrow'] ?? 'Resource') . "</p><h3 class='mt-4 text-2xl font-bold leading-tight tracking-tight text-slate-900'>" . e($resource['title'] ?? '') . "</h3><p class='mt-4 text-sm leading-6 text-slate-600'>" . e($resource['text'] ?? '') . "</p><a href='" . e($resource['cta_url'] ?? '#') . "' class='mt-7 inline-flex text-sm font-semibold text-slate-900 underline decoration-slate-300 underline-offset-4 transition group-hover:decoration-slate-900'>" . e($resource['cta_label'] ?? 'Read more') . "</a></article>";
                }
                $resourcesHeaderClass = $variant === 'resources-02' ? 'mx-auto max-w-3xl text-center' : 'max-w-3xl';
                $resourcesGridClass = $variant === 'resources-02' ? 'mx-auto mt-10 grid max-w-4xl gap-5' : ($variant === 'resources-03' ? 'mt-10 grid gap-5 lg:grid-cols-2' : 'mt-10 grid gap-5 md:grid-cols-2');
                $html .= "<section class='bg-[#fcfcfb] px-6 py-16 sm:px-8 lg:px-12 lg:py-24'><div class='mx-auto max-w-7xl'><div class='{$resourcesHeaderClass}'><p class='text-xs font-semibold uppercase tracking-[0.28em] text-slate-500'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight text-slate-900 sm:text-5xl lg:text-[3.75rem]'>{$heading}</h2><p class='mt-5 max-w-2xl text-base leading-7 text-slate-600'>{$text}</p></div><div class='{$resourcesGridClass}'>{$resourceMarkup}</div></div></section>";
                break;

                case 'blog_mini_hero':
                $eyebrow = e($block['eyebrow'] ?? 'Latest insights');
                $heading = e($block['heading'] ?? 'Ideas for building a better business');
                $text = e($block['text'] ?? 'Practical notes, useful perspectives, and updates from our team.');
                $variant = $block['layout_variant'] ?? 'mini-header-01';
                $contentLayout = $variant === 'mini-header-02' ? 'mx-auto max-w-4xl text-center' : ($variant === 'mini-header-03' ? 'grid items-end gap-8 lg:grid-cols-[1.15fr_.85fr]' : 'max-w-3xl');
                $html .= "<section class='relative overflow-hidden border-b px-6 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-24 {$theme['bg']} {$theme['border']}'><div class='pointer-events-none absolute -right-24 -top-28 h-72 w-72 rounded-full {$theme['card']} opacity-10 blur-3xl'></div><div class='relative mx-auto max-w-7xl'><div class='{$contentLayout}'><p class='text-xs font-semibold uppercase tracking-[0.28em] {$theme['sub']}'>{$eyebrow}</p><h1 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h1><p class='mt-5 max-w-2xl text-base leading-7 sm:text-lg {$theme['sub']}'>{$text}</p></div></div></section>";
                break;

                case 'blog_hub':
                if (is_array($context['single_blog_post'] ?? null)) {
                    $html .= self::compileBlogPost(
                        $context['single_blog_post'],
                        $primaryColor,
                        (string) ($context['blog_index_url'] ?? 'blog/')
                    );
                    break;
                }

                // Editorial hubs are intentionally neutral. Unlike surrounding
                // hero/supporting sections, their reading surface never inherits
                // the website's primary color.
                $theme = self::getTheme('white');
                $eyebrow = e($block['eyebrow'] ?? 'Latest insights');
                $heading = e($block['heading'] ?? 'Ideas for building a better business');
                $text = e($block['text'] ?? 'Practical notes, useful perspectives, and updates from our team.');
                $showIntro = ($block['show_intro'] ?? true) !== false;
                $variant = $block['layout_variant'] ?? 'blog-cards-01';
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
                    $postMarkup .= "<article class='overflow-hidden rounded-2xl border {$theme['border']} {$theme['card']}'><img src='" . e(self::staticAssetUrl($post['image_url'] ?? '/storage/cms-images/background/background-1.avif')) . "' alt='" . e($post['title'] ?? 'Article image') . "' class='h-44 w-full object-cover'><div class='p-5'><p class='text-[11px] font-semibold uppercase tracking-[0.2em] {$theme['sub']}'>" . e($post['category'] ?? 'Article') . "</p><h3 class='mt-3 text-lg font-bold leading-snug {$theme['text']}'>" . e($post['title'] ?? '') . "</h3><p class='mt-3 text-sm leading-6 {$theme['sub']}'>" . e($post['excerpt'] ?? '') . "</p><a href='{$postUrl}' class='mt-4 inline-block text-sm font-semibold {$theme['text']} hover:underline'>Read article</a></div></article>";
                }
                $introMarkup = $showIntro
                    ? "<div class='max-w-3xl'><p class='text-xs font-semibold uppercase tracking-[0.28em] {$theme['sub']}'>{$eyebrow}</p><h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] {$theme['text']}'>{$heading}</h2><p class='mt-5 max-w-2xl text-base leading-7 {$theme['sub']}'>{$text}</p></div>"
                    : '';
                $featuredSpacing = $showIntro ? 'mt-12' : '';
                $featuredGridClass = $variant === 'blog-cards-02' ? 'md:grid-cols-[.8fr_1.2fr]' : ($variant === 'blog-cards-03' ? 'md:grid-cols-1' : 'md:grid-cols-2');
                $featuredImageClass = $variant === 'blog-cards-03'
                    ? 'h-[240px] sm:h-[340px] lg:h-[420px]'
                    : 'min-h-[260px] h-full';
                $postGridClass = $variant === 'blog-cards-02' ? 'lg:grid-cols-2' : ($variant === 'blog-cards-03' ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2 lg:grid-cols-4');
                $html .= "<section class='px-6 py-16 sm:px-8 lg:px-12 lg:py-24 {$theme['bg']}'><div class='mx-auto max-w-7xl'>{$introMarkup}<article class='{$featuredSpacing} grid overflow-hidden rounded-3xl border {$theme['border']} {$theme['card']} {$featuredGridClass}'><img src='{$featuredImage}' alt='{$featuredTitle}' class='{$featuredImageClass} w-full object-cover'><div class='flex min-h-[260px] flex-col justify-center p-7 sm:p-10'><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$featuredCategory}</p><h3 class='mt-4 text-3xl font-bold tracking-tight {$theme['text']}'>{$featuredTitle}</h3><p class='mt-4 text-base leading-7 {$theme['sub']}'>{$featuredExcerpt}</p><a href='{$featuredUrl}' class='mt-7 text-sm font-semibold {$theme['text']} hover:underline'>{$featuredCta}</a></div></article><div class='mt-7 grid gap-5 {$postGridClass}'>{$postMarkup}</div></div></section>";
                break;

                case 'hero_centered_cta':
                $tagline = e($block['tagline'] ?? 'LOREM IPSUM DOLOR');
                $heading = e($block['heading'] ?? '');
                $subheading = e($block['subheading'] ?? $block['text'] ?? '');
                $btnLabel = e($block['button_label'] ?? 'Get Started');
                $btnUrl = e($block['button_url'] ?? '#');
                $buttonClasses = $blockTheme === 'primary'
                    ? 'bg-white text-slate-950'
                    : "{$theme['card']} {$theme['text']}";
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
                    $studyMarkup .= "<article class='group overflow-hidden rounded-3xl border {$theme['border']} {$theme['card']} {$featured}'><img src='{$imageUrl}' alt='' class='w-full object-cover {$imageHeight}'><div class='flex flex-col justify-center p-6 sm:p-8 " . ($index === 0 ? 'lg:p-10' : '') . "'><p class='text-xs font-semibold uppercase tracking-[0.22em] {$theme['sub']}'>{$category}</p><h3 class='mt-4 text-2xl font-bold leading-tight tracking-tight {$theme['text']}'>{$title}</h3><p class='mt-4 text-sm leading-6 {$theme['sub']}'>{$summary}</p><p class='mt-6 text-sm font-semibold {$theme['text']}'>{$result}</p><span class='mt-5 text-sm font-semibold {$theme['text']}'>{$linkLabel}</span></div></article>";
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
                $buttonClasses = $blockTheme === 'primary'
                    ? 'bg-white text-slate-950'
                    : "{$theme['card']} {$theme['text']}";
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
                            var response = await fetch(form.action, {
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
                    $icon = $icons[$i % count($icons)];
                    $ctaLabel = e($card['cta_label'] ?? 'Learn More');
                    $ctaUrl = e($card['cta_url'] ?? '#');

                    $cardHtml .= "
                    <div class='{$theme['card']} border {$theme['border']} rounded-3xl p-8 h-full flex flex-col transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl'>
                        <div class='w-16 h-16 rounded-2xl border {$theme['border']} bg-white/5 flex items-center justify-center text-2xl mb-6'>
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
                            <a href='{$ctaUrl}' class='inline-flex items-center gap-2 text-sm font-semibold {$theme['text']} opacity-80 transition-all duration-300 hover:gap-3'>
                                {$ctaLabel}
                                <span>→</span>
                            </a>
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
                $logoImageUrl = e(self::staticAssetUrl($block['logo_image_url'] ?? ''));
                $logoHeight = min(60, max(24, (int) ($block['logo_height'] ?? 40)));
                $logoMaxWidth = min(250, max(120, (int) ($block['logo_max_width'] ?? 250)));
                $logo = $logoImageUrl !== ''
                    ? "<img src='{$logoImageUrl}' alt='{$logoText}' style='height: {$logoHeight}px; max-height: 60px; max-width: {$logoMaxWidth}px' class='w-auto object-contain'>"
                    : $logoText;
                $ctaLabel = e($block['cta_label'] ?? 'Get Started');
                $ctaUrl = e($block['cta_url'] ?? '#');
                $menuItems = $block['menu'] ?? [];

                // Header always white
                $headerBg = 'bg-white';
                $headerBorder = 'border-slate-200';
                $headerText = 'text-slate-900';
                $menuText = 'text-slate-600';

                // CTA Button follows PRIMARY THEME
                $buttonBg = $theme['bg'];
                $buttonText = $theme['text'];

                $renderMenu = function (array $items, int $depth = 0) use (&$renderMenu, $menuText): string {
                    $itemsHtml = '';

                    foreach ($items as $item) {
                        if (! is_array($item)) continue;
                        $url = e($item['url'] ?? '#');
                        $label = e($item['label'] ?? '');
                        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
                        $hasChildren = count($children) > 0;
                        $dropdown = '';

                        if ($hasChildren) {
                            // The wrapper touches its parent. The inner padding creates visual
                            // space without a hover gap that would close the submenu.
                            $dropdownPosition = $depth > 0
                                ? 'left-full top-0 pl-2'
                                : 'left-0 top-full pt-2';
                            $borderClass = $depth > 0 ? 'border-slate-300' : 'border-slate-200';
                            $dropdown = "<div class='menu-dropdown absolute {$dropdownPosition} z-50 min-w-52'>"
                                . "<ul class='list-none rounded-xl border {$borderClass} bg-white p-2 shadow-2xl ring-1 ring-slate-950/5'>"
                                . $renderMenu($children, $depth + 1)
                                . "</ul></div>";
                        }

                        $menuClass = $hasChildren ? "menu-node menu-depth-{$depth} relative" : 'menu-leaf';
                        $itemsHtml .= "<li class='{$menuClass}'>"
                            . "<a href='{$url}' class='{$menuText} flex items-center gap-1 whitespace-nowrap hover:text-slate-900 transition'>{$label}" . ($hasChildren ? "<span aria-hidden='true' class='text-xs'>⌄</span>" : '') . "</a>"
                            . $dropdown
                            . "</li>";
                    }

                    return $itemsHtml;
                };

                $navHtml = $renderMenu(is_array($menuItems) ? $menuItems : []);

                $html .= "
                <style>
                    .cosmic-static-header .menu-node > a > span[aria-hidden='true'] { display: none; }
                    .cosmic-static-header .menu-node > a::after {
                        content: '';
                        width: .42rem;
                        height: .42rem;
                        margin-left: .15rem;
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
                        padding: .55rem .75rem;
                        border-radius: .5rem;
                    }
                    .cosmic-static-header .menu-dropdown > ul > li > a:hover {
                        background: rgb(241 245 249);
                    }
                </style>
                <header class='cosmic-static-header w-full {$headerBg} flex flex-wrap items-center justify-between gap-4 border-b {$headerBorder} px-5 py-4 sm:px-6 sm:py-5 sticky top-0 z-50 shadow-sm'>
                    <div class='text-xl font-extrabold tracking-wide {$headerText}'>
                        {$logo}
                    </div>

                    <nav class='flex w-full items-center justify-between gap-4 sm:w-auto sm:justify-start sm:gap-8'>
                        <ul class='flex flex-wrap list-none gap-x-5 gap-y-2 sm:gap-x-8 m-0 p-0'>
                            {$navHtml}
                        </ul>

                        <a
                            href='{$ctaUrl}'
                            class='{$buttonBg} {$buttonText} shrink-0 px-7 py-3 rounded-full text-sm font-semibold hover:opacity-90 transition'
                        >
                            {$ctaLabel}
                        </a>
                    </nav>
                </header>";
                break;

                case 'minimal_footer':
                $brand = e($block['logo_text'] ?? 'CosmicCMS');
                $copy = e($block['copyright'] ?? '© ' . date('Y') . '. All rights reserved.');
                $stoneTheme = self::getTheme('stone'); // Hardcoded stone theme
                
                $html .= "
                <footer class='w-full {$stoneTheme['bg']} {$stoneTheme['sub']} flex flex-col items-start gap-3 border-t {$stoneTheme['border']} px-6 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-12'>
                    <div class='font-bold text-lg {$stoneTheme['text']}'>{$brand}</div>
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
                                <img src='{$imageUrl}' alt='Feature Image' class='w-full h-auto object-cover'>
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
                                <img src='{$imageUrl}' alt='Feature Image' class='w-full h-auto object-cover'>
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
                    $ctaLabel = e($service['cta_label'] ?? 'Learn More');
                    $ctaUrl = e($service['cta_url'] ?? '#');

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
                                <a href='{$ctaUrl}' class='inline-flex items-center gap-2 text-sm font-semibold {$theme['text']}'>
                                    {$ctaLabel} →
                                </a>
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
                            <img src='{$imageUrl}' alt='{$name}' class='aspect-[4/3] w-full object-cover' loading='lazy'>
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
                                    class='w-14 h-14 rounded-full object-cover'
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


                case 'hero_parallax':

                $eyebrow = e($block['eyebrow'] ?? 'INTRODUCING A NEW PERSPECTIVE');
                $heading = e($block['heading'] ?? 'Move beyond the ordinary.');
                $text = e($block['text'] ?? 'Create a memorable first impression with cinematic depth, confident typography, and a clear next step.');
                $primaryLabel = e($block['primary_label'] ?? 'Start a project');
                $primaryUrl = e($block['primary_url'] ?? '#');
                $secondaryLabel = e($block['secondary_label'] ?? 'Explore our work');
                $secondaryUrl = e($block['secondary_url'] ?? '#');
                $scrollLabel = e($block['scroll_label'] ?? 'Scroll to explore');
                $backgroundImage = e(self::staticAssetUrl($block['image_url'] ?? ''));
                $overlayOpacity = max(20, min(90, intval($block['overlayOpacity'] ?? 64)));
                $parallaxSpeed = max(8, min(40, intval($block['parallaxSpeed'] ?? 24)));
                $contentAlign = $block['contentAlign'] ?? 'left';
                $heroHeight = ($block['height'] ?? 'screen') === 'large'
                    ? 'min-h-[720px]'
                    : 'min-h-[88svh] lg:min-h-screen';
                $primaryTheme = self::getTheme($primaryColor);
                $isLightMedia = in_array($selectedThemeName, ['white', 'stone']);
                $mediaOverlay = $isLightMedia ? 'bg-white' : 'bg-slate-950';
                $mediaGradient = $isLightMedia ? 'from-white/95 via-white/55 to-white/25' : 'from-slate-950/85 via-slate-950/20 to-slate-950/25';
                $mediaBadge = $isLightMedia ? 'border-slate-900/15 bg-white/60' : 'border-white/20 bg-white/10';
                $mediaEyebrow = $isLightMedia ? 'text-slate-700' : 'text-white/85';
                $mediaHeading = $isLightMedia ? 'text-slate-950' : 'text-white';
                $mediaBody = $isLightMedia ? 'text-slate-700' : 'text-white/75';
                $mediaSecondary = $isLightMedia ? 'border-slate-900/20 bg-white/50 text-slate-950' : 'border-white/30 bg-white/10 text-white';
                $mediaScroll = $isLightMedia ? 'text-slate-700' : 'text-white/65';
                $mediaScrollLine = $isLightMedia ? 'bg-slate-900/25' : 'bg-white/25';
                $mediaScrollDot = $isLightMedia ? 'bg-slate-900' : 'bg-white';
                $parallaxId = 'cosmic-parallax-' . substr(md5(json_encode($block) . uniqid('', true)), 0, 12);

                $alignment = match ($contentAlign) {
                    'center' => 'items-center text-center',
                    'right' => 'items-end text-right',
                    default => 'items-start text-left',
                };
                $contentWidth = $contentAlign === 'center' ? 'max-w-4xl' : 'max-w-3xl';
                $buttonAlignment = match ($contentAlign) {
                    'center' => 'justify-center',
                    'right' => 'justify-end',
                    default => 'justify-start',
                };
                $gradientDirection = match ($contentAlign) {
                    'center' => 'bg-gradient-to-t',
                    'right' => 'bg-gradient-to-l',
                    default => 'bg-gradient-to-r',
                };
                $backgroundStyle = $backgroundImage
                    ? "background-image:url('{$backgroundImage}');background-size:cover;background-position:center;"
                    : '';

                $html .= "
                <section id='{$parallaxId}' class='relative isolate flex overflow-hidden px-4 py-12 sm:px-6 sm:py-16 lg:px-8 lg:py-20 {$heroHeight}' data-parallax-speed='{$parallaxSpeed}'>
                    <div class='cosmic-parallax-media absolute -inset-y-[12%] inset-x-0 z-0 will-change-transform' style=\"{$backgroundStyle}transform:translate3d(0,0,0) scale(1.08);\"></div>
                    <div class='absolute inset-0 z-10 {$mediaOverlay}' style='opacity:" . ($overlayOpacity / 100) . ";'></div>
                    <div class='absolute inset-0 z-10 {$gradientDirection} {$mediaGradient}'></div>

                    <div class='cosmic-parallax-content relative z-20 mx-auto flex w-full max-w-7xl flex-col justify-center {$alignment}' style='transform:translate3d(0,0,0);will-change:transform,opacity;'>
                        <div class='{$contentWidth}'>
                            <div class='inline-flex items-center gap-3 rounded-full border px-4 py-2 {$mediaBadge} backdrop-blur-md'>
                                <span class='h-2 w-2 rounded-full {$primaryTheme['bg']}'></span>
                                <span class='text-xs font-bold uppercase tracking-[0.28em] {$mediaEyebrow}'>{$eyebrow}</span>
                            </div>

                            <h1 class='mt-7 text-5xl font-semibold leading-[0.96] tracking-[-0.045em] {$mediaHeading} sm:text-6xl md:text-7xl lg:text-[6.5rem]'>{$heading}</h1>
                            <div class='mt-7 max-w-2xl text-base leading-8 {$mediaBody} sm:text-lg'>{$text}</div>

                            <div class='mt-10 flex w-full flex-col gap-3 sm:w-auto sm:flex-row {$buttonAlignment}'>
                                <a href='{$primaryUrl}' class='inline-flex min-h-[54px] items-center justify-center rounded-full px-8 font-bold transition {$primaryTheme['bg']} {$primaryTheme['text']}'>{$primaryLabel}</a>
                                <a href='{$secondaryUrl}' class='inline-flex min-h-[54px] items-center justify-center rounded-full border px-8 font-bold {$mediaSecondary} backdrop-blur-md transition'>{$secondaryLabel}</a>
                            </div>
                        </div>
                    </div>

                    <div class='pointer-events-none absolute bottom-7 left-1/2 z-20 hidden -translate-x-1/2 flex-col items-center gap-3 {$mediaScroll} sm:flex'>
                        <span class='text-[10px] font-bold uppercase tracking-[0.32em]'>{$scrollLabel}</span>
                        <span class='relative h-10 w-px overflow-hidden {$mediaScrollLine}'><span class='absolute left-0 top-0 h-4 w-px animate-bounce {$mediaScrollDot}'></span></span>
                    </div>
                </section>
                <script>
                (() => {
                    const section = document.getElementById('{$parallaxId}');
                    if (!section || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    const media = section.querySelector('.cosmic-parallax-media');
                    const content = section.querySelector('.cosmic-parallax-content');
                    const speed = Number(section.dataset.parallaxSpeed || 24);
                    let frame = null;
                    const update = () => {
                        frame = null;
                        const rect = section.getBoundingClientRect();
                        const viewport = window.innerHeight || 1;
                        if (rect.bottom <= 0 || rect.top >= viewport) return;
                        const progress = Math.max(0, Math.min(1, (viewport - rect.top) / (viewport + rect.height)));
                        const centered = progress - 0.5;
                        const mediaOffset = centered * speed * 7;
                        const contentOffset = centered * speed * -1.7;
                        const contentOpacity = Math.max(0.35, 1 - Math.abs(centered) * 0.75);
                        media.style.transform = `translate3d(0, \${mediaOffset}px, 0) scale(1.14)`;
                        if (content) {
                            content.style.transform = `translate3d(0, \${contentOffset}px, 0)`;
                            content.style.opacity = String(contentOpacity);
                        }
                    };
                    const requestUpdate = () => {
                        if (frame === null) frame = window.requestAnimationFrame(update);
                    };
                    update();
                    document.addEventListener('scroll', requestUpdate, true);
                    window.addEventListener('resize', requestUpdate);
                })();
                </script>";

                break;

            case 'hero_slider_fade':
                $sliderId = 'cosmic-slider-' . uniqid();
                $slides = is_array($block['slides'] ?? null) ? array_values($block['slides']) : [];
                $autoplayInterval = max(3000, intval($block['autoplay_interval'] ?? $block['interval'] ?? 6000));

                if ($slides === []) {
                    $slides = [[
                        'image_url' => '',
                        'eyebrow' => 'Built for what is next',
                        'heading' => 'A stronger first impression',
                        'description' => 'Introduce your business with a focused message and a clear next step.',
                        'button_1_text' => 'Get started',
                        'button_1_url' => '#',
                        'button_2_text' => 'Explore services',
                        'button_2_url' => '#',
                        'button_3_text' => 'View our work',
                        'button_3_url' => '#',
                        'button_4_text' => 'Learn more',
                        'button_4_url' => '#',
                    ]];
                }

                $slideMarkup = '';
                $dotMarkup = '';

                foreach ($slides as $index => $slide) {
                    $imageSource = self::staticAssetUrl($slide['image_url'] ?? $slide['image'] ?? $slide['background_image'] ?? '');
                    $image = htmlspecialchars((string) $imageSource, ENT_QUOTES, 'UTF-8');
                    $eyebrow = htmlspecialchars((string) ($slide['eyebrow'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $heading = htmlspecialchars((string) ($slide['heading'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $description = htmlspecialchars((string) ($slide['description'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $activeClass = $index === 0 ? ' is-active' : '';
                    $backgroundStyle = $image !== '' ? "background-image:url('{$image}')" : '';
                    $ariaHidden = $index === 0 ? 'false' : 'true';
                    $buttonMarkup = '';
                    $floatingButtonMarkup = '';
                    $buttonDefaults = [
                        2 => 'Explore services',
                        3 => 'View our work',
                        4 => 'Learn more',
                    ];

                    for ($buttonIndex = 1; $buttonIndex <= 4; $buttonIndex++) {
                        $legacyText = $buttonIndex === 1 ? ($slide['button_text'] ?? '') : '';
                        $legacyUrl = $buttonIndex === 1 ? ($slide['button_url'] ?? '#') : '#';
                        $textKey = "button_{$buttonIndex}_text";
                        $urlKey = "button_{$buttonIndex}_url";
                        $rawText = array_key_exists($textKey, $slide)
                            ? $slide[$textKey]
                            : ($buttonDefaults[$buttonIndex] ?? $legacyText);
                        $buttonText = trim((string) $rawText);

                        if ($buttonText === '') {
                            continue;
                        }

                        $buttonUrl = htmlspecialchars((string) ($slide[$urlKey] ?? $legacyUrl), ENT_QUOTES, 'UTF-8');
                        $safeButtonText = htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8');
                        $markup = '<a class="cosmic-fade-slide__button cosmic-fade-slide__button--' . $buttonIndex . '" href="' . $buttonUrl . '">' . $safeButtonText . '</a>';

                        if ($buttonIndex === 4) {
                            $floatingButtonMarkup = $markup;
                        } else {
                            $buttonMarkup .= $markup;
                        }
                    }

                    $slideMarkup .= <<<HTML
                        <article class="cosmic-fade-slide{$activeClass}" data-slider-slide style="{$backgroundStyle}" aria-hidden="{$ariaHidden}">
                            <div class="cosmic-fade-slide__overlay"></div>
                            <div class="cosmic-fade-slide__content">
                                <p class="cosmic-fade-slide__eyebrow">{$eyebrow}</p>
                                <h1>{$heading}</h1>
                                <p class="cosmic-fade-slide__description">{$description}</p>
                                <div class="cosmic-fade-slide__actions">{$buttonMarkup}</div>
                            </div>
                            <div class="cosmic-fade-slide__floating-action">{$floatingButtonMarkup}</div>
                        </article>
                    HTML;

                    $dotMarkup .= '<button type="button" class="cosmic-fade-slider__dot' . ($index === 0 ? ' is-active' : '') . '" data-slider-dot="' . $index . '" aria-label="Show slide ' . ($index + 1) . '" aria-current="' . ($index === 0 ? 'true' : 'false') . '"></button>';
                }

                return <<<HTML
                    <section id="{$sliderId}" class="cosmic-fade-slider" data-cosmic-fade-slider data-autoplay="{$autoplayInterval}" aria-roledescription="carousel">
                        <div class="cosmic-fade-slider__viewport">{$slideMarkup}</div>
                        <button type="button" class="cosmic-fade-slider__arrow cosmic-fade-slider__arrow--previous" data-slider-previous aria-label="Previous slide">&#8592;</button>
                        <button type="button" class="cosmic-fade-slider__arrow cosmic-fade-slider__arrow--next" data-slider-next aria-label="Next slide">&#8594;</button>
                        <div class="cosmic-fade-slider__dots" aria-label="Choose slide">{$dotMarkup}</div>
                    </section>
                    <style>
                        #{$sliderId}{position:relative;min-height:clamp(34rem,72vh,52rem);overflow:hidden;background:#111827;color:#fff}
                        #{$sliderId} .cosmic-fade-slider__viewport,#{$sliderId} .cosmic-fade-slide{position:absolute;inset:0}
                        #{$sliderId} .cosmic-fade-slide{display:grid;align-items:center;background-position:center;background-size:cover;opacity:0;visibility:hidden;transition:opacity .7s ease,visibility .7s ease}
                        #{$sliderId} .cosmic-fade-slide.is-active{opacity:1;visibility:visible;z-index:1}
                        #{$sliderId} .cosmic-fade-slide__overlay{position:absolute;inset:0;background:linear-gradient(90deg,rgba(2,6,23,.9) 0%,rgba(2,6,23,.66) 48%,rgba(2,6,23,.3) 100%)}
                        #{$sliderId} .cosmic-fade-slide__content{position:relative;z-index:2;width:min(100% - 3rem,82rem);margin-inline:auto;padding-block:7rem;max-width:82rem}
                        #{$sliderId} .cosmic-fade-slide__eyebrow{margin:0 0 1rem;font-size:.75rem;font-weight:700;letter-spacing:.24em;text-transform:uppercase;color:#c4b5fd}
                        #{$sliderId} h1{max-width:13ch;margin:0;font-size:clamp(2.75rem,6vw,5.75rem);font-weight:700;line-height:.98;letter-spacing:-.045em;color:#fff}
                        #{$sliderId} .cosmic-fade-slide__description{max-width:42rem;margin:1.5rem 0 0;font-size:clamp(1rem,1.5vw,1.2rem);line-height:1.7;color:#dbe4f0}
                        #{$sliderId} .cosmic-fade-slide__actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2rem}
                        #{$sliderId} .cosmic-fade-slide__button{display:inline-flex;align-items:center;justify-content:center;padding:.85rem 1.35rem;border:1px solid rgba(255,255,255,.35);border-radius:999px;background:rgba(2,6,23,.2);color:#fff;text-decoration:none;font-weight:700;backdrop-filter:blur(12px);transition:background .2s ease,border-color .2s ease,transform .2s ease}
                        #{$sliderId} .cosmic-fade-slide__button:hover{border-color:rgba(255,255,255,.65);background:rgba(2,6,23,.42);transform:translateY(-1px)}
                        #{$sliderId} .cosmic-fade-slide__button--1{border-color:#fff;background:#fff;color:#0f172a}
                        #{$sliderId} .cosmic-fade-slide__button--1:hover{background:rgba(255,255,255,.9)}
                        #{$sliderId} .cosmic-fade-slide__button--4{padding:.68rem 1rem;border-color:rgba(255,255,255,.25);background:rgba(2,6,23,.36);font-size:.8rem}
                        #{$sliderId} .cosmic-fade-slide__floating-action{position:absolute;z-index:4;right:8.25rem;bottom:1.5rem}
                        #{$sliderId} .cosmic-fade-slider__arrow{position:absolute;z-index:4;bottom:1.5rem;width:2.75rem;height:2.75rem;border:1px solid rgba(255,255,255,.32);border-radius:999px;background:rgba(15,23,42,.62);color:#fff;cursor:pointer;backdrop-filter:blur(12px)}
                        #{$sliderId} .cosmic-fade-slider__arrow--previous{right:4.75rem}#{$sliderId} .cosmic-fade-slider__arrow--next{right:1.25rem}
                        #{$sliderId} .cosmic-fade-slider__dots{position:absolute;z-index:4;left:clamp(1.5rem,calc((100% - 82rem)/2),5rem);bottom:1.75rem;display:flex;gap:.55rem}
                        #{$sliderId} .cosmic-fade-slider__dot{width:.55rem;height:.55rem;padding:0;border:0;border-radius:999px;background:rgba(255,255,255,.4);cursor:pointer;transition:width .25s ease,background .25s ease}
                        #{$sliderId} .cosmic-fade-slider__dot.is-active{width:1.8rem;background:#fff}
                        @media(max-width:640px){#{$sliderId}{min-height:42rem}#{$sliderId} .cosmic-fade-slide__content{width:min(100% - 2rem,82rem);padding:5rem 1rem 8rem}#{$sliderId} .cosmic-fade-slide__actions{gap:.5rem}#{$sliderId} .cosmic-fade-slide__button{padding:.72rem 1rem;font-size:.82rem}#{$sliderId} .cosmic-fade-slide__floating-action{right:7.25rem;bottom:1.5rem;max-width:calc(100% - 9rem)}#{$sliderId} .cosmic-fade-slide__floating-action .cosmic-fade-slide__button{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}#{$sliderId} .cosmic-fade-slider__dots{left:1rem;bottom:1.6rem}#{$sliderId} .cosmic-fade-slider__arrow--previous{right:4rem}#{$sliderId} .cosmic-fade-slider__arrow--next{right:.75rem}}
                        @media(prefers-reduced-motion:reduce){#{$sliderId} .cosmic-fade-slide,#{$sliderId} .cosmic-fade-slider__dot,#{$sliderId} .cosmic-fade-slide__button{transition:none}}
                    </style>
                    <script>
                        (()=>{const root=document.getElementById('{$sliderId}');if(!root)return;const slides=[...root.querySelectorAll('[data-slider-slide]')],dots=[...root.querySelectorAll('[data-slider-dot]')];if(slides.length<2)return;const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;let index=0,timer=null,paused=false;const show=(next)=>{index=(next+slides.length)%slides.length;slides.forEach((slide,i)=>{const active=i===index;slide.classList.toggle('is-active',active);slide.setAttribute('aria-hidden',active?'false':'true')});dots.forEach((dot,i)=>{const active=i===index;dot.classList.toggle('is-active',active);dot.setAttribute('aria-current',active?'true':'false')})};const stop=()=>{if(timer){clearInterval(timer);timer=null}};const start=()=>{stop();if(!reduced&&!paused)timer=setInterval(()=>show(index+1),Number(root.dataset.autoplay)||6000)};root.querySelector('[data-slider-previous]')?.addEventListener('click',()=>{show(index-1);start()});root.querySelector('[data-slider-next]')?.addEventListener('click',()=>{show(index+1);start()});dots.forEach((dot,i)=>dot.addEventListener('click',()=>{show(i);start()}));root.addEventListener('mouseenter',()=>{paused=true;stop()});root.addEventListener('mouseleave',()=>{paused=false;start()});root.addEventListener('focusin',()=>{paused=true;stop()});root.addEventListener('focusout',event=>{if(!root.contains(event.relatedTarget)){paused=false;start()}});show(0);start()})();
                    </script>
                HTML;

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
                $overlayStrength = $overlayOpacity / 100;
                $primaryOverlayTheme = self::getTheme($primaryColor);
                $textAlign = $block['textAlign'] ?? 'center';
                $height = $block['height'] ?? 'screen';

                // Button Logic
                $isLight = in_array($selectedThemeName, ['white', 'stone']);

                $btnBg = $isLight
                    ? self::getTheme($primaryColor)['bg']
                    : 'bg-white';

                $btnText = $isLight
                    ? self::getTheme($primaryColor)['text']
                    : 'text-slate-900';
                $mediaOverlay = $isLight ? 'bg-white' : 'bg-slate-950';
                $mediaTagline = $isLight ? 'text-slate-700' : 'text-white/80';
                $mediaHeading = $isLight ? 'text-slate-950' : 'text-white';
                $mediaBody = $isLight ? 'text-slate-700' : 'text-white/80';

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
                        class='absolute inset-0 {$mediaOverlay}'
                        style='opacity:{$overlayStrength};'>
                    </div>

                    <div class='relative z-10 w-full max-w-7xl mx-auto px-6 py-20 sm:px-[8%] sm:py-24 flex flex-col justify-center {$alignment}'>

                        <span class='text-sm uppercase tracking-[0.35em] font-semibold {$mediaTagline} block'>
                            {$tagline}
                        </span>

                        <h1 class='mt-6 text-4xl sm:text-5xl md:text-7xl font-bold leading-tight break-words {$mediaHeading} block'>
                            {$heading}
                        </h1>

                        <div class='mt-6 max-w-2xl text-base leading-7 sm:mt-8 sm:text-xl sm:leading-8 {$mediaBody}'>
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
                $heroHeight = match ($block['height'] ?? 'large') {
                    'medium' => 'min-h-[520px]',
                    'screen' => 'min-h-[72svh] sm:min-h-[80vh] md:min-h-[85vh] lg:min-h-[90vh]',
                    default => 'min-h-[650px]',
                };
                $primaryTheme = self::getTheme($primaryColor);
                $isLightMedia = in_array($selectedThemeName, ['white', 'stone']);
                $mediaOverlay = $isLightMedia ? 'bg-white' : 'bg-slate-950';
                $mediaGradient = $isLightMedia ? 'from-white/95 via-white/60 to-transparent' : '{$mediaGradient}';
                $mediaTagline = $isLightMedia ? 'text-slate-700' : 'text-white/75';
                $mediaHeading = $isLightMedia ? 'text-slate-950' : 'text-white';
                $mediaBody = $isLightMedia ? 'text-slate-700' : 'text-white/80';
                $mediaSecondary = $isLightMedia ? 'border-slate-900/20 bg-white/50 text-slate-950' : 'border-white/40 bg-white/5 text-white';
                $backgroundStyle = $backgroundImage
                    ? "background-image:url('{$backgroundImage}');background-size:cover;background-position:center;"
                    : '';

                $html .= "
                <section class='relative flex overflow-hidden {$heroHeight}' style=\"{$backgroundStyle}\">
                    <div class='absolute inset-0 {$mediaOverlay}' style='opacity:" . ($overlayOpacity / 100) . ";'></div>
                    <div class='absolute inset-0 bg-gradient-to-r from-slate-950/80 via-slate-950/40 to-transparent'></div>
                    <div class='relative z-10 mx-auto flex w-full max-w-7xl items-center px-7 py-20 sm:py-24'>
                        <div class='max-w-3xl'>
                            <span class='block text-xs font-semibold uppercase tracking-[0.3em] {$mediaTagline}'>{$tagline}</span>
                            <h1 class='mt-5 text-5xl font-bold leading-[1.03] tracking-tight {$mediaHeading} sm:text-6xl md:text-7xl lg:text-8xl'>{$heading}</h1>
                            <div class='mt-6 max-w-2xl text-base leading-7 {$mediaBody} sm:text-lg sm:leading-8'>{$text}</div>
                            <div class='mt-8 flex flex-col gap-3 sm:flex-row sm:items-center'>
                                <a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryTheme['bg']} {$primaryTheme['text']}'>{$primaryLabel}</a>
                                <a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold {$mediaSecondary}'>{$secondaryLabel}</a>
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
                $primaryButtonText = $isPrimarySection ? 'text-slate-950' : $primaryTheme['text'];
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
                $isLightMedia = in_array($selectedThemeName, ['white', 'stone']);
                $mediaOverlay = $isLightMedia ? 'bg-white' : 'bg-slate-950';
                $mediaGradient = $isLightMedia ? 'from-white/95 via-white/70 to-white/35' : 'from-slate-950/65 via-slate-950/25 to-slate-950/15';
                $mediaEyebrow = $isLightMedia ? 'text-slate-700' : 'text-white/75';
                $mediaHeading = $isLightMedia ? 'text-slate-950' : 'text-white';
                $mediaBody = $isLightMedia ? 'text-slate-700' : 'text-white/85';
                $mediaPrimary = $isLightMedia ? $primaryTheme['bg'] . ' ' . $primaryTheme['text'] : 'bg-white text-slate-950';
                $mediaSecondary = $isLightMedia ? 'border-slate-900/20 bg-white/50 text-slate-950' : 'border-white/45 bg-white/5 text-white';
                $backgroundStyle = $backgroundImage
                    ? "background-image:url('{$backgroundImage}');background-size:cover;background-position:center;"
                    : '';

                $html .= "
                <section class='relative flex min-h-[420px] overflow-hidden sm:min-h-[460px] lg:min-h-[500px]' style=\"{$backgroundStyle}\">
                    <div class='absolute inset-0 {$mediaOverlay}' style='opacity:" . ($overlayOpacity / 100) . ";'></div>
                    <div class='absolute inset-0 bg-gradient-to-r {$mediaGradient}'></div>
                    <div class='relative z-10 mx-auto flex w-full max-w-7xl items-center justify-center px-7 py-16 text-center sm:px-10 sm:py-20'>
                        <div class='max-w-3xl'>
                            <span class='block text-xs font-semibold uppercase tracking-[0.3em] {$mediaEyebrow}'>{$eyebrow}</span>
                            <h2 class='mt-4 text-4xl font-bold leading-[1.05] tracking-tight {$mediaHeading} sm:text-5xl lg:text-[3.75rem]'>{$heading}</h2>
                            <div class='mx-auto mt-5 max-w-2xl text-base leading-7 {$mediaBody} sm:text-lg sm:leading-8'>{$text}</div>
                            <div class='mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:items-center'>
                                <a href='{$primaryUrl}' class='inline-flex min-h-[48px] items-center justify-center rounded-full px-7 font-bold {$mediaPrimary}'>{$primaryLabel}</a>
                                <a href='{$secondaryUrl}' class='inline-flex min-h-[48px] items-center justify-center rounded-full border px-7 font-bold {$mediaSecondary}'>{$secondaryLabel}</a>
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
                                    class='aspect-[4/3] w-full object-cover'
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
                            <iframe
                                src='" . e($videoEmbedUrl) . "'
                                title='Video preview'
                                allow='autoplay; fullscreen; picture-in-picture'
                                class='absolute inset-0 h-full w-full border-0 pointer-events-none'
                            ></iframe>
                        </div>"
                    : ($rawVideoUrl !== '' && $rawVideoUrl !== '#'
                        ? "<video autoplay muted loop playsinline preload='metadata' poster='{$imageUrl}' class='w-full object-cover' style='aspect-ratio: 16 / 9;'>
                                <source src='{$staticVideoUrl}' type='video/mp4'>
                            </video>"
                        : "<img src='{$imageUrl}' alt='{$heading}' class='w-full object-cover transition duration-500 group-hover:scale-[1.03]' style='aspect-ratio: 16 / 9;'>");

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
                $isLightMedia = in_array($selectedThemeName, ['white', 'stone']);
                $mediaOverlay = $isLightMedia ? 'bg-white/75' : '{$mediaOverlay}';
                $mediaGradientX = $isLightMedia ? 'from-white/95 via-white/65 to-white/20' : '{$mediaGradientX}';
                $mediaGradientY = $isLightMedia ? 'from-white/65 via-transparent to-white/20' : '{$mediaGradientY}';
                $mediaTagline = $isLightMedia ? 'text-slate-700' : '{$mediaTagline}';
                $mediaHeading = $isLightMedia ? 'text-slate-950' : 'text-white';
                $mediaBody = $isLightMedia ? 'text-slate-700' : 'text-white/75';
                $mediaSecondary = $isLightMedia ? 'border-slate-900/20 bg-white/50 text-slate-950' : 'border-white/30 bg-white/10 text-white';
                $mediaPill = $isLightMedia ? 'border-slate-900/15 bg-white/55 text-slate-900' : 'border-white/15 bg-slate-950/35 text-white';
                $mediaScroll = $isLightMedia ? 'text-slate-700' : 'text-white/70';
                $mediaScrollBorder = $isLightMedia ? 'border-slate-900/30' : 'border-white/30';
                $mediaScrollDot = $isLightMedia ? 'bg-slate-900' : 'bg-white';

                $backgroundMedia = $backgroundVideoEmbedUrl
                    ? "<div class='absolute inset-0 overflow-hidden'>
                            <iframe
                                src='" . e($backgroundVideoEmbedUrl) . "'
                                title='Background video'
                                allow='autoplay; fullscreen; picture-in-picture'
                                class='pointer-events-none absolute left-1/2 top-1/2 h-[56.25vw] min-h-full w-[177.78vh] min-w-full -translate-x-1/2 -translate-y-1/2 border-0'
                            ></iframe>
                        </div>"
                    : "<video
                            autoplay
                            muted
                            loop
                            playsinline
                            preload='metadata'
                            poster='{$posterImageUrl}'
                            class='h-full w-full object-cover'
                        >
                            <source
                                src='{$videoUrl}'
                                type='video/mp4'
                            >
                        </video>";

                $html .= "
                <section class='relative isolate min-h-[680px] overflow-hidden {$theme['bg']}'>

                    <div class='absolute inset-0'>

                        {$backgroundMedia}

                        <div class='absolute inset-0 bg-slate-950/65'></div>

                        <div class='absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/65 to-slate-950/20'></div>

                        <div class='absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-slate-950/20'></div>

                    </div>

                    <div class='pointer-events-none absolute -left-40 top-16 h-96 w-96 rounded-full {$primaryTheme['bg']} opacity-[0.18] blur-[150px]'></div>

                    <div class='relative z-10 mx-auto flex min-h-[680px] max-w-7xl items-center px-7 py-24 sm:px-10 lg:px-12'>

                        <div class='max-w-3xl'>

                            <span class='block text-xs font-semibold uppercase tracking-[0.34em] text-white/70'>
                                {$tagline}
                            </span>

                            <h1 class='mt-6 block text-5xl font-bold leading-[0.98] tracking-tight {$mediaHeading} sm:text-6xl lg:text-8xl'>
                                {$heading}
                            </h1>

                            <p class='mt-7 block max-w-2xl text-base leading-7 {$mediaBody} sm:text-lg sm:leading-8'>
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
                                    class='inline-flex min-h-[52px] items-center justify-center rounded-full border px-8 font-bold {$mediaSecondary} backdrop-blur transition hover:bg-white/20'
                                >
                                    {$secondaryLabel}
                                </a>

                            </div>

                            <div class='mt-10 flex items-center gap-3'>

                                <div class='flex items-center gap-3 rounded-full border px-4 py-2.5 {$mediaPill} backdrop-blur'>

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

                            <div class='flex items-center gap-3 {$mediaScroll}'>

                                <span class='flex h-9 w-6 items-start justify-center rounded-full border p-1.5 {$mediaScrollBorder}'>
                                    <span class='h-1.5 w-1.5 rounded-full {$mediaScrollDot}'></span>
                                </span>

                                <span class='text-xs font-semibold uppercase tracking-[0.24em]'>
                                    {$scrollLabel}
                                </span>

                            </div>

                            <div class='hidden w-48 overflow-hidden rounded-2xl border border-white/20 bg-slate-950/35 shadow-2xl backdrop-blur sm:block'>

                                <img
                                    src='{$posterImageUrl}'
                                    alt='{$heading}'
                                    class='aspect-video w-full object-cover opacity-80'
                                >

                            </div>

                        </div>

                    </div>

                </section>";

                break;
            }
        }
        return $html;
    }
}
