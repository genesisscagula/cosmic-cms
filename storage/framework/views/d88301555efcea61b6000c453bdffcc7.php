<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>Cosmic CMS</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
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
        <?php echo app('Tighten\Ziggy\BladeRouteGenerator')->generate(); ?>
        <?php echo app('Illuminate\Foundation\Vite')->reactRefresh(); ?>
        <?php echo app('Illuminate\Foundation\Vite')('resources/js/app.jsx'); ?>
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
    </head>
    <body class="font-sans antialiased">
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } elseif (config('inertia.use_script_element_for_initial_page')) { ?><script data-page="app" type="application/json"><?php echo json_encode($page); ?></script><div id="app"></div><?php } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
    </body>
</html>
<?php /**PATH C:\xampp\htdocs\my-custom-cms\resources\views/app.blade.php ENDPATH**/ ?>