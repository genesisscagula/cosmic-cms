<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('cosmic-seo.default_title') }}</title>
        <meta name="description" content="{{ config('cosmic-seo.default_description') }}">
        @if (app()->environment('production'))
            <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
        @else
            <meta name="robots" content="noindex,nofollow,noarchive">
        @endif
        <meta property="og:site_name" content="Cosmic CMS">
        <meta property="og:type" content="website">
        @if (config('cosmic-tracking.google_site_verification'))
            <meta name="google-site-verification" content="{{ config('cosmic-tracking.google_site_verification') }}">
        @endif

        <!-- Cosmic CMS browser branding -->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=3">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=3">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=3">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=3">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=3">
        <meta name="theme-color" content="#16a34a">

        <!-- Fonts -->
        <link rel="dns-prefetch" href="//fonts.bunny.net">
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Apply the saved appearance before CSS/React paint to prevent flashes. -->
        <script>
            (() => {
                const allowed = ['light', 'dark', 'system'];
                const publicPaths = [
                    '/', '/login', '/register', '/forgot-password', '/reset-password',
                    '/verify-email', '/confirm-password', '/pricing', '/start',
                    '/privacy', '/terms', '/cookies', '/legal', '/client', '/preview',
                ];
                const path = window.location.pathname || '/';
                const isPublicSurface = publicPaths.some((prefix) =>
                    prefix === '/' ? path === '/' : (path === prefix || path.startsWith(`${prefix}/`))
                );

                let mode = 'light';

                if (!isPublicSurface) {
                    try {
                        const stored = window.localStorage.getItem('cosmic.appearance');
                        if (allowed.includes(stored)) mode = stored;
                    } catch (_) {}
                }

                const resolved = mode === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : mode;

                const root = document.documentElement;
                root.dataset.appearance = mode;
                root.dataset.theme = resolved;
                root.classList.toggle('dark', resolved === 'dark');
                root.style.colorScheme = resolved;
            })();
        </script>

        <!-- Scripts: app.jsx is the only Vite entry. Inertia resolves pages lazily. -->
        @routes
        @viteReactRefresh
        @vite('resources/js/app.jsx')
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
