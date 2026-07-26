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
                        <h1 class='block max-w-5xl text-5xl font-black leading-[1.03] tracking-tight sm:text-6xl lg:text-7xl {$theme['text']}'>
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

                $html .= "
                <section class='relative overflow-hidden px-7 py-16 sm:px-10 sm:py-20 lg:px-12 lg:py-24 {$theme['bg']}'>
                    <div class='pointer-events-none absolute -left-32 top-1/2 h-80 w-80 -translate-y-1/2 rounded-full {$theme['card']} opacity-[0.1] blur-[120px]'></div>
                    <div class='relative mx-auto grid max-w-7xl gap-12 lg:grid-cols-[0.88fr_1.12fr] lg:items-start lg:gap-20'>
                        <div class='max-w-xl pt-2'>
                            <p class='text-xs font-semibold uppercase tracking-[0.3em] {$theme['sub']}'>{$eyebrow}</p>
                            <h2 class='mt-5 text-4xl font-black leading-[1.06] tracking-tight sm:text-5xl lg:text-6xl {$theme['text']}'>{$heading}</h2>
                            <p class='mt-5 text-base leading-7 sm:text-lg sm:leading-8 {$theme['sub']}'>{$text}</p>
                            <div class='mt-9 space-y-4 border-t pt-7 {$theme['border']}'>
                                <div><p class='text-xs font-semibold uppercase tracking-[0.18em] {$theme['sub']}'>Email</p><p class='mt-1 text-base font-semibold {$theme['text']}'>{$email}</p></div>
                                <div><p class='text-xs font-semibold uppercase tracking-[0.18em] {$theme['sub']}'>Phone</p><p class='mt-1 text-base font-semibold {$theme['text']}'>{$phone}</p></div>
                                <div><p class='text-xs font-semibold uppercase tracking-[0.18em] {$theme['sub']}'>Visit</p><p class='mt-1 text-base font-semibold {$theme['text']}'>{$address}</p></div>
                            </div>
                        </div>
                        <form action='./cosmic-sync/contact.php' method='post' data-cosmic-contact-form class='rounded-[2rem] border p-5 shadow-2xl sm:p-8 {$theme['card']} {$theme['border']}'>
                            <div class='grid gap-5 sm:grid-cols-2'>
                                <label class='text-sm font-semibold {$theme['text']}'>Name<input name='name' required class='mt-2 h-12 w-full rounded-xl border px-4 text-sm outline-none {$inputClasses}' placeholder='Your name'></label>
                                <label class='text-sm font-semibold {$theme['text']}'>Email<input name='email' type='email' required class='mt-2 h-12 w-full rounded-xl border px-4 text-sm outline-none {$inputClasses}' placeholder='you@example.com'></label>
                            </div>
                            <label class='mt-5 block text-sm font-semibold {$theme['text']}'>Phone <span class='{$theme['sub']}'>(optional)</span><input name='phone' type='tel' class='mt-2 h-12 w-full rounded-xl border px-4 text-sm outline-none {$inputClasses}' placeholder='Your phone number'></label>
                            <label class='mt-5 block text-sm font-semibold {$theme['text']}'>How can we help?<textarea name='message' required class='mt-2 min-h-32 w-full resize-y rounded-xl border px-4 py-3 text-sm outline-none {$inputClasses}' placeholder='Tell us a little about your project'></textarea></label>
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
                            var result = await response.json();

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
                $logoText = e($block['logo_text'] ?? 'Your Website');
                $logoImageUrl = e(self::staticAssetUrl($block['logo_image_url'] ?? ''));
                $logo = $logoImageUrl !== ''
                    ? "<img src='{$logoImageUrl}' alt='{$logoText}' class='h-9 w-auto max-w-[200px] object-contain'>"
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
                    <div class='absolute inset-0 {$primaryTheme['bg']}' style='opacity:" . ($overlayOpacity / 100) . ";'></div>
                    <div class='absolute inset-0 bg-gradient-to-r from-slate-950/80 via-slate-950/40 to-transparent'></div>
                    <div class='relative z-10 mx-auto flex w-full max-w-7xl items-center px-7 py-20 sm:py-24'>
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
                            <h1 class='mt-5 text-5xl font-black leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl {$theme['text']}'>{$heading}</h1>
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
                $backgroundStyle = $backgroundImage
                    ? "background-image:url('{$backgroundImage}');background-size:cover;background-position:center;"
                    : '';

                $html .= "
                <section class='relative flex min-h-[420px] overflow-hidden sm:min-h-[460px] lg:min-h-[500px]' style=\"{$backgroundStyle}\">
                    <div class='absolute inset-0 {$primaryTheme['bg']}' style='opacity:" . ($overlayOpacity / 100) . ";'></div>
                    <div class='absolute inset-0 bg-gradient-to-r from-slate-950/65 via-slate-950/25 to-slate-950/15'></div>
                    <div class='relative z-10 mx-auto flex w-full max-w-7xl items-center justify-center px-7 py-16 text-center sm:px-10 sm:py-20'>
                        <div class='max-w-3xl'>
                            <span class='block text-xs font-semibold uppercase tracking-[0.3em] text-white/75'>{$eyebrow}</span>
                            <h2 class='mt-4 text-4xl font-black leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-6xl'>{$heading}</h2>
                            <div class='mx-auto mt-5 max-w-2xl text-base leading-7 text-white/85 sm:text-lg sm:leading-8'>{$text}</div>
                            <div class='mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:items-center'>
                                <a href='{$primaryUrl}' class='inline-flex min-h-[48px] items-center justify-center rounded-full bg-white px-7 font-bold text-slate-950'>{$primaryLabel}</a>
                                <a href='{$secondaryUrl}' class='inline-flex min-h-[48px] items-center justify-center rounded-full border border-white/45 bg-white/5 px-7 font-bold text-white'>{$secondaryLabel}</a>
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

                            <h1 class='mt-5 block text-5xl font-black leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl {$theme['text']}'>
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

                                <div class='block text-3xl font-black tracking-tight {$theme['text']}'>
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

                            <h1 class='mt-5 block text-5xl font-black leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl {$theme['text']}'>
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

                            <h1 class='mt-6 block text-5xl font-black leading-[0.98] tracking-tight text-white sm:text-6xl lg:text-8xl'>
                                {$heading}
                            </h1>

                            <p class='mt-7 block max-w-2xl text-base leading-7 text-white/75 sm:text-lg sm:leading-8'>
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
                                    class='inline-flex min-h-[52px] items-center justify-center rounded-full border border-white/30 bg-white/10 px-8 font-bold text-white backdrop-blur transition hover:bg-white/20'
                                >
                                    {$secondaryLabel}
                                </a>

                            </div>

                            <div class='mt-10 flex items-center gap-3'>

                                <div class='flex items-center gap-3 rounded-full border border-white/15 bg-slate-950/35 px-4 py-2.5 backdrop-blur'>

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

                            <div class='flex items-center gap-3 text-white/70'>

                                <span class='flex h-9 w-6 items-start justify-center rounded-full border border-white/30 p-1.5'>
                                    <span class='h-1.5 w-1.5 rounded-full bg-white'></span>
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
