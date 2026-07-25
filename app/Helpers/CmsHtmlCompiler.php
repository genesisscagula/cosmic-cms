<?php

namespace App\Helpers;

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

   public static function compile(array $blocks, string $primaryColor = null): string
    {
        $html = "";
    
        // 2. Mapping


        foreach ($blocks as $index => $block) {


            $pattern = [
                "primary",
                "white",
                "surface",
                "white"
            ];

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
                case 'hero_centered_cta':
                $tagline = e($block['tagline'] ?? 'LOREM IPSUM DOLOR');
                $heading = e($block['heading'] ?? '');
                $subheading = e($block['subheading'] ?? $block['text'] ?? '');
                $btnLabel = e($block['button_label'] ?? 'Get Started');
                $btnUrl = e($block['button_url'] ?? '#');

                $html .= "
                <section class='w-full py-24 px-7 md:px-8 text-center {$theme['bg']} relative overflow-hidden border-b {$theme['border']} transition-colors duration-500'>
                    <div class='max-w-4xl mx-auto space-y-6 relative z-10 flex flex-col items-center'>
                        <span class='text-xs font-bold tracking-widest uppercase block opacity-80 {$theme['text']}'>
                            {$tagline}
                        </span>
                        <h1 class='text-4xl md:text-5xl font-extrabold leading-tight {$theme['text']}'>
                            {$heading}
                        </h1>
                        <p class='text-base md:text-lg {$theme['sub']} max-w-2xl mx-auto leading-relaxed'>
                            {$subheading}
                        </p>
                        <a href='{$btnUrl}' class='inline-block {$theme['text']} {$theme['bg']} border {$theme['border']} px-8 py-3 rounded-full font-bold shadow-lg hover:opacity-90 transition'>
                            {$btnLabel}
                        </a>
                    </div>
                </section>";

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
                            <h2 class='mt-5 text-5xl md:text-6xl font-bold tracking-tight leading-tight {$theme['text']}'>
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
                $logo = e($block['logo_text'] ?? 'Your Website');
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

                $navHtml = "";

                foreach ($menuItems as $item) {
                    $url = e($item['url'] ?? '#');
                    $label = e($item['label'] ?? '');

                    $navHtml .= "
                        <li>
                            <a href='{$url}' class='{$menuText} hover:text-slate-900 transition'>
                                {$label}
                            </a>
                        </li>
                    ";
                }

                $html .= "
                <header class='w-full {$headerBg} flex flex-wrap items-center justify-between gap-4 border-b {$headerBorder} px-6 py-4 sm:px-[8%] sm:py-6 sticky top-0 z-50 shadow-sm'>
                    <div class='text-xl font-extrabold tracking-wide {$headerText}'>
                        {$logo}
                    </div>

                    <nav class='flex w-full items-center justify-between gap-4 sm:w-auto sm:justify-start sm:gap-10'>
                        <ul class='flex flex-wrap list-none gap-x-4 gap-y-2 sm:gap-x-[40px] m-0 p-0'>
                            {$navHtml}
                        </ul>

                        <a
                            href='{$ctaUrl}'
                            class='{$buttonBg} {$buttonText} shrink-0 px-[22px] py-[10px] rounded-full text-sm font-semibold hover:opacity-90 transition'
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
                            <h2 class='block text-5xl md:text-6xl font-bold leading-tight tracking-tight {$theme['text']}'>
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
                            <h2 class='block text-5xl md:text-6xl font-bold leading-tight tracking-tight {$theme['text']}'>
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

                            <h2 class='mt-5 text-5xl md:text-6xl font-bold tracking-tight leading-tight {$theme['text']}'>
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

                            <h2 class='block text-5xl md:text-6xl font-bold leading-tight tracking-tight {$theme['text']}'>
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
                            <h2 class='block text-3xl font-bold tracking-tight sm:text-4xl {$theme['text']}'>{$heading}</h2>";

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
                                <div class='block text-4xl font-bold tracking-tight sm:text-5xl {$theme['text']}'>{$value}</div>
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

                            <h2 class='block mt-5 text-5xl md:text-6xl font-bold leading-tight tracking-tight {$theme['text']}'>
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
                // Match HeroBackgroundImageBlock: the overlay uses the
                // website primary theme at overlayOpacity / 60.
                $overlayStrength = min(1, $overlayOpacity / 60);
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
                        class='absolute inset-0 {$primaryOverlayTheme['bg']}'
                        style='opacity:{$overlayStrength};'>
                    </div>

                    <div class='relative z-10 w-full max-w-7xl mx-auto px-6 py-20 sm:px-[8%] sm:py-24 flex flex-col justify-center {$alignment}'>

                        <span class='text-sm uppercase tracking-[0.35em] font-semibold text-white/80 block'>
                            {$tagline}
                        </span>

                        <h1 class='mt-6 text-4xl sm:text-5xl md:text-7xl font-black leading-tight break-words text-white block'>
                            {$heading}
                        </h1>

                        <div class='mt-6 max-w-2xl text-base leading-7 sm:mt-8 sm:text-xl sm:leading-8 text-white/80'>
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
                $backgroundStyle = $backgroundImage
                    ? "background-image:url('{$backgroundImage}');background-size:cover;background-position:center;"
                    : '';

                $html .= "
                <section class='relative flex overflow-hidden {$heroHeight}' style=\"{$backgroundStyle}\">
                    <div class='absolute inset-0 bg-slate-950' style='opacity:" . ($overlayOpacity / 100) . ";'></div>
                    <div class='absolute inset-0 bg-gradient-to-r from-slate-950/80 via-slate-950/40 to-transparent'></div>
                    <div class='relative z-10 mx-auto flex w-full max-w-7xl items-center px-6 py-20 sm:px-[8%] sm:py-24'>
                        <div class='max-w-3xl'>
                            <span class='block text-xs font-semibold uppercase tracking-[0.3em] text-white/75'>{$tagline}</span>
                            <h1 class='mt-5 text-5xl font-black leading-[1.03] tracking-tight text-white sm:text-6xl md:text-7xl lg:text-8xl'>{$heading}</h1>
                            <div class='mt-6 max-w-2xl text-base leading-7 text-white/80 sm:text-lg sm:leading-8'>{$text}</div>
                            <div class='mt-8 flex flex-col gap-3 sm:flex-row sm:items-center'>
                                <a href='{$primaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold {$primaryTheme['bg']} {$primaryTheme['text']}'>{$primaryLabel}</a>
                                <a href='{$secondaryUrl}' class='inline-flex min-h-[50px] items-center justify-center rounded-full border border-white/40 bg-white/5 px-7 font-bold text-white'>{$secondaryLabel}</a>
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

                            <h2 class='block mt-5 text-5xl md:text-6xl font-bold leading-tight tracking-tight {$theme['text']}'>
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
            }
        }
        return $html;
    }
}
