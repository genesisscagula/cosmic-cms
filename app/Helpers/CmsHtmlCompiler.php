<?php

namespace App\Helpers;

class CmsHtmlCompiler
{
    private static function getTheme($key)
    {
        $themes = [
        // Primary (Dark/Bold)
        'emerald'    => ['bg' => 'bg-[#0B5D4B]', 'text' => 'text-emerald-50', 'sub' => 'text-emerald-200', 'card' => 'bg-[#14735D]', 'border' => 'border-emerald-900'],
        'coffee'     => ['bg' => 'bg-[#4A2A14]', 'text' => 'text-amber-50', 'sub' => 'text-amber-200', 'card' => 'bg-[#5A341A]', 'border' => 'border-amber-900'],
        'rose'       => ['bg' => 'bg-[#A13D63]', 'text' => 'text-rose-50', 'sub' => 'text-rose-200', 'card' => 'bg-[#8C3156]', 'border' => 'border-rose-900'],
        'dark'       => ['bg' => 'bg-[#1F2937]', 'text' => 'text-slate-50', 'sub' => 'text-slate-400', 'card' => 'bg-[#374151]', 'border' => 'border-slate-700'],
        'ocean'      => ['bg' => 'bg-[#24598F]', 'text' => 'text-blue-50', 'sub' => 'text-blue-200', 'card' => 'bg-[#2C6AA8]', 'border' => 'border-blue-900'],
        'indigo'     => ['bg' => 'bg-[#4F46A5]', 'text' => 'text-indigo-50', 'sub' => 'text-indigo-200', 'card' => 'bg-[#5B55B8]', 'border' => 'border-indigo-900'],
        'amber'      => ['bg' => 'bg-[#A16207]', 'text' => 'text-amber-50', 'sub' => 'text-amber-200', 'card' => 'bg-[#B7791F]', 'border' => 'border-amber-900'],
        'charcoal'   => ['bg' => 'bg-[#3A3A3A]', 'text' => 'text-slate-50', 'sub' => 'text-slate-400', 'card' => 'bg-[#4A4A4A]', 'border' => 'border-slate-700'],
        'violet'     => ['bg' => 'bg-[#5B3FA3]', 'text' => 'text-violet-50', 'sub' => 'text-violet-200', 'card' => 'bg-[#6C4DB6]', 'border' => 'border-violet-900'],
        'teal'       => ['bg' => 'bg-[#186B66]', 'text' => 'text-teal-50', 'sub' => 'text-teal-200', 'card' => 'bg-[#217C76]', 'border' => 'border-teal-900'],
        'ruby'       => ['bg' => 'bg-[#A12649]', 'text' => 'text-rose-50', 'sub' => 'text-rose-200', 'card' => 'bg-[#8C1E3F]', 'border' => 'border-rose-900'],
        'forest'     => ['bg' => 'bg-[#2E5E3E]', 'text' => 'text-emerald-50', 'sub' => 'text-emerald-200', 'card' => 'bg-[#3A714C]', 'border' => 'border-emerald-900'],
        'midnight'   => ['bg' => 'bg-[#243447]', 'text' => 'text-slate-100', 'sub' => 'text-slate-400', 'card' => 'bg-[#30475E]', 'border' => 'border-slate-700'],
        'obsidian'   => ['bg' => 'bg-[#171717]', 'text' => 'text-neutral-100', 'sub' => 'text-neutral-400', 'card' => 'bg-[#262626]', 'border' => 'border-neutral-800'],
        'navy'       => ['bg' => 'bg-[#214B7A]', 'text' => 'text-blue-50', 'sub' => 'text-blue-200', 'card' => 'bg-[#295C95]', 'border' => 'border-blue-900'],
        'void'       => ['bg' => 'bg-[#111827]', 'text' => 'text-slate-50', 'sub' => 'text-slate-400', 'card' => 'bg-[#1F2937]', 'border' => 'border-slate-800'],
        'espresso'   => ['bg' => 'bg-[#4B2E1E]', 'text' => 'text-orange-50', 'sub' => 'text-orange-200', 'card' => 'bg-[#5B3825]', 'border' => 'border-orange-900'],
        'terracotta' => ['bg' => 'bg-[#A04A2C]', 'text' => 'text-orange-50', 'sub' => 'text-orange-200', 'card' => 'bg-[#B25A39]', 'border' => 'border-orange-800'],
        'asphalt'    => ['bg' => 'bg-[#2A2A2A]', 'text' => 'text-slate-200', 'sub' => 'text-slate-500', 'card' => 'bg-[#3A3A3A]', 'border' => 'border-slate-700'],
        'sapphire' => [
            'bg' => 'bg-[#0F4C81]',
            'text' => 'text-blue-50',
            'sub' => 'text-blue-200',
            'card' => 'bg-[#1B5FA7]',
            'border' => 'border-blue-900'
        ],

        'plum' => [
            'bg' => 'bg-[#5B214A]',
            'text' => 'text-fuchsia-50',
            'sub' => 'text-fuchsia-200',
            'card' => 'bg-[#6E2959]',
            'border' => 'border-fuchsia-900'
        ],

        'olive' => [
            'bg' => 'bg-[#4D5D2D]',
            'text' => 'text-lime-50',
            'sub' => 'text-lime-200',
            'card' => 'bg-[#5E7037]',
            'border' => 'border-lime-900'
        ],
        
        // Light Colors
        'stone' => [
            'bg' => 'bg-[#F7F7F5]',
            'text' => 'text-slate-800',
            'sub' => 'text-slate-500',
            'card' => 'bg-white',
            'border' => 'border-stone-200'
        ],

        'white' => [
            'bg' => 'bg-[#FEFEFD]',
            'text' => 'text-slate-900',
            'sub' => 'text-slate-600',
            'card' => 'bg-[#F8F8F7]',
            'border' => 'border-stone-200'
        ],

        ];
        return $themes[$key] ?? $themes['amber'];
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
                $logo = e($block['logo_text'] ?? 'DesignKaBai');
                $ctaLabel = e($block['cta_label'] ?? 'Get Started');
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
                <header class='w-full {$headerBg} py-6 px-[8%] flex justify-between items-center border-b {$headerBorder} sticky top-0 z-50 shadow-sm'>
                    <div class='text-xl font-extrabold tracking-wide {$headerText}'>
                        {$logo}
                    </div>

                    <nav class='flex items-center gap-10'>
                        <ul class='flex list-none gap-[40px] m-0 p-0'>
                            {$navHtml}
                        </ul>

                        <a
                            href='#'
                            class='{$buttonBg} {$buttonText} px-[22px] py-[10px] rounded-full text-sm font-semibold hover:opacity-90 transition'
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
                <footer class='w-full {$stoneTheme['bg']} {$stoneTheme['sub']} py-12 px-8 flex justify-between items-center border-t {$stoneTheme['border']}'>
                    <div class='font-bold text-lg {$stoneTheme['text']}'>{$brand}</div>
                    <div class='text-sm'>{$copy}</div>
                </footer>";
                break;



                case 'feature_image_left':
                $category = e($block['category'] ?? 'CATEGORY');
                $heading = e($block['heading'] ?? 'Heading Title');
                $text = e($block['text'] ?? 'Add your description here...');
                $btnLabel = e($block['button_label'] ?? 'Read More');
                $btnUrl = e($block['button_url'] ?? '#');
                $imageUrl = e($block['image_url'] ?? 'https://picsum.photos/800/600');

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
                $imageUrl = e($block['image_url'] ?? 'https://picsum.photos/800/600');

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
                $text = e($block['text'] ?? 'Focus sa logic, biya-i ang manual coding. Ang imong website, automated na sa atong custom CMS logic.');
                
                // Button Logic
                $isLight = in_array($selectedThemeName, ['white', 'stone']);

                $btnBg = $isLight
                    ? self::getTheme($primaryColor)['bg']
                    : 'bg-white';

                $btnText = $isLight
                    ? self::getTheme($primaryColor)['text']
                    : 'text-slate-900';


                $html .= "
                <section class='relative w-full py-24 px-[8%] {$theme['bg']} overflow-hidden transition-colors duration-500'>
                    <div class='absolute top-0 right-0 w-[500px] h-[500px] bg-gradient-to-br from-indigo-500 to-transparent opacity-30 blur-[120px] rounded-full'></div>
                    
                    <div class='relative z-10 max-w-4xl'>
                        <span class='font-bold tracking-widest uppercase text-sm block {$theme['sub']}'>{$subtitle}</span>
                        <h1 class='text-6xl md:text-8xl font-extrabold mt-6 leading-[1.1] block {$theme['text']}'>{$heading}</h1>
                        <div class='mt-8 text-xl max-w-2xl {$theme['sub']}'>{$text}</div>

                        <div class='mt-12 flex gap-4'>
                            <a href='#' class='px-8 py-4 rounded-full font-bold transition !opacity-100 {$btnBg} {$btnText}'>
                                " . e($block['btn1_label'] ?? 'Get Started') . "
                            </a>
                            <a href='#' class='border px-8 py-4 rounded-full font-bold transition {$theme['border']} {$theme['text']}'>
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
                        $avatar = asset('storage/cms-images/avatars/avatar-1.jpg');
                    } elseif (!preg_match('/^https?:\/\//', $avatar)) {
                        $avatar = asset(ltrim($avatar, '/'));
                    }

                    $avatar = e($avatar);
                    
                    $name    = e($item['name'] ?? 'John Smith');
                    $company = e($item['company'] ?? 'Company');
                    $quote   = e($item['quote'] ?? '');
                    $rating  = (int)($item['rating'] ?? 5);

                    $stars = str_repeat('★', max(0, min($rating, 5)));

                    $html .= "
                        <div class='{$theme['card']} border {$theme['border']} rounded-3xl p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl'>

                            <div class='text-yellow-400 text-xl mb-6'>
                                {$stars}
                            </div>

                            <p class='italic leading-8 {$theme['sub']}'>
                                {$quote}
                            </p>

                            <div class='flex items-center gap-4 mt-8'>

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
            }
        }
        return $html;
    }
}