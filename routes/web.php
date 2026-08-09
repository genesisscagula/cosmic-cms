<?php

use App\Helpers\CmsHtmlCompiler;
use App\Http\Controllers\AI\AIController;
use App\Http\Controllers\PageTemplateController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ClientPreviewController;
use App\Http\Controllers\ContactSubmissionController;
use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\TrialGenerationController;
use App\Http\Controllers\TrialBrandingController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\AccountDataController;
use App\Http\Controllers\CosmicPricingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SparkController;
use App\Http\Controllers\AgencyLeadController;
use App\Http\Controllers\AgencySalesController;
use App\Http\Controllers\WorkspaceMemberController;
use App\Http\Controllers\WebsiteAssignmentController;
use App\Http\Controllers\WorkspaceInvitationAcceptanceController;
use App\Http\Controllers\WebsiteHandoffController;
use App\Http\Controllers\AgencyBrandingController;
use App\Http\Controllers\BrandedPreviewLinkController;
use App\Http\Controllers\AgencyReportController;
use App\Http\Controllers\AgencyPortalController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\AppearancePreferenceController;
use App\Http\Controllers\QueueDashboardController;
use App\Http\Controllers\CosmicPublicChatController;
use App\Http\Controllers\CosmicChatInboxController;
use App\Models\Page;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PreviewController;
use Inertia\Inertia;


// Public Cosmic CMS AI assistant. Conversation access uses an unguessable per-session token.
Route::post('/support/chat/start', [CosmicPublicChatController::class, 'start'])
    ->middleware(['throttle:10,1', \App\Http\Middleware\RejectOversizedRequest::class . ':32'])
    ->name('support.chat.start');
Route::post('/support/chat/message', [CosmicPublicChatController::class, 'message'])
    ->middleware(['throttle:20,1', \App\Http\Middleware\RejectOversizedRequest::class . ':32'])
    ->name('support.chat.message');
Route::post('/support/chat/history', [CosmicPublicChatController::class, 'history'])
    ->middleware(['throttle:60,1', \App\Http\Middleware\RejectOversizedRequest::class . ':16'])
    ->name('support.chat.history');
Route::post('/support/chat/lead', [CosmicPublicChatController::class, 'captureLead'])
    ->middleware(['throttle:10,1', \App\Http\Middleware\RejectOversizedRequest::class . ':16'])
    ->name('support.chat.lead');

Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/cookies', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::post('/legal/consent', [LegalController::class, 'consent'])
    ->middleware(['throttle:20,1', \App\Http\Middleware\RejectOversizedRequest::class . ':32'])
    ->name('legal.consent');

// Public search-engine discovery endpoints. Keep these outside auth middleware.
Route::get('/robots.txt', function () {
    $baseUrl = rtrim(config('cosmic-seo.base_url'), '/');

    $robots = [
        'User-agent: *',
        'Allow: /',
        'Disallow: /dashboard',
        'Disallow: /profile',
        'Disallow: /api/',
        'Disallow: /admin/',
        'Disallow: /builder/',
        'Disallow: /pages/',
        'Disallow: /websites/',
        'Disallow: /checkout/',
        'Disallow: /payment/',
        '',
        "Sitemap: {$baseUrl}/sitemap.xml",
        "Host: " . parse_url($baseUrl, PHP_URL_HOST),
        '',
    ];

    return response(implode("\n", $robots), 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('seo.robots');

Route::get('/sitemap.xml', function () {
    $baseUrl = rtrim(config('cosmic-seo.base_url'), '/');
    $pages = [
        ['path' => '/', 'priority' => '1.0', 'frequency' => 'weekly'],
        ['path' => '/start', 'priority' => '0.9', 'frequency' => 'weekly'],
        ['path' => '/pricing', 'priority' => '0.8', 'frequency' => 'monthly'],
        ['path' => '/ai-website-builder', 'priority' => '0.9', 'frequency' => 'monthly'],
        ['path' => '/ai-website-generator', 'priority' => '0.9', 'frequency' => 'monthly'],
        ['path' => '/modern-website-builder', 'priority' => '0.8', 'frequency' => 'monthly'],
        ['path' => '/website-builder-for-small-business', 'priority' => '0.8', 'frequency' => 'monthly'],
        ['path' => '/no-code-website-builder', 'priority' => '0.8', 'frequency' => 'monthly'],
        ['path' => '/terms', 'priority' => '0.2', 'frequency' => 'yearly'],
        ['path' => '/privacy', 'priority' => '0.2', 'frequency' => 'yearly'],
        ['path' => '/cookies', 'priority' => '0.2', 'frequency' => 'yearly'],
    ];

    $urls = collect($pages)->map(function ($page) use ($baseUrl) {
        $loc = htmlspecialchars($baseUrl . $page['path'], ENT_XML1);
        return "  <url>\n    <loc>{$loc}</loc>\n    <changefreq>{$page['frequency']}</changefreq>\n    <priority>{$page['priority']}</priority>\n  </url>";
    })->implode("\n");

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}\n</urlset>\n";

    return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('seo.sitemap');


// SEO A6: lightweight production diagnostics. No credentials or tracking IDs are exposed.
Route::get('/seo-health', function () {
    $baseUrl = rtrim((string) config('cosmic-seo.base_url'), '/');
    $host = parse_url($baseUrl, PHP_URL_HOST);
    $production = app()->environment('production');

    $checks = [
        'https_base_url' => str_starts_with($baseUrl, 'https://'),
        'canonical_host' => $host === 'www.cosmiccms.com',
        'default_title' => filled(config('cosmic-seo.default_title')),
        'default_description' => filled(config('cosmic-seo.default_description')),
    ];

    $healthy = !in_array(false, $checks, true);

    return response()->json([
        'status' => $healthy ? 'ok' : 'attention',
        'environment' => app()->environment(),
        'indexing_expected' => $production && $healthy,
        'checks' => $checks,
        'sitemap' => "{$baseUrl}/sitemap.xml",
        'robots' => "{$baseUrl}/robots.txt",
    ], $healthy ? 200 : 503)
        ->header('X-Robots-Tag', 'noindex, nofollow');
})->name('seo.health');


// Public UUID preview links must be registered before the generic local site preview.
// The UUID constraint prevents a normal slug such as /preview/my-coffee-shop from
// being mistaken for an Agency branded preview token.
Route::get('/preview/{token}', [BrandedPreviewLinkController::class, 'show'])
    ->whereUuid('token')
    ->middleware(['throttle:cosmic-public-preview', \App\Http\Middleware\AddSecurityHeaders::class])
    ->name('preview-links.show');

// Cosmic published previews intentionally have one public surface per environment:
// local/path mode uses /preview/{slug}; production/subdomain mode uses the wildcard host.
// This prevents production sites from also being reachable through the main app domain.
if (config('cosmic_preview.mode') === 'local') {
    Route::get('/preview/{slug}/{path?}', [PreviewController::class, 'local'])
        ->where('slug', '[a-z0-9][a-z0-9-]{0,59}')
        ->where('path', '.*')
        ->name('preview.local');
}

// In production one wildcard DNS record (*.cosmiccms.com) points to this Laravel app.
// No DigitalOcean DNS API request is needed when an individual website is published.
if (config('cosmic_preview.mode') === 'subdomain' && filled(config('cosmic_preview.domain'))) {
    $previewDomain = config('cosmic_preview.domain');

    // Keep www reserved for the main Cosmic CMS website. Without this explicit
    // route, the wildcard preview host would interpret "www" as a website slug.
    Route::domain('www.'.$previewDomain)
        ->get('/{path?}', function (?string $path = null) use ($previewDomain) {
            $url = config('cosmic_preview.scheme', 'https').'://'.$previewDomain;

            if (filled($path)) {
                $url .= '/'.ltrim($path, '/');
            }

            if ($query = request()->getQueryString()) {
                $url .= '?'.$query;
            }

            return redirect()->away($url, 301);
        })
        ->where('path', '.*')
        ->name('cosmic.www.redirect');

    $reservedPreviewSlugs = collect(config('cosmic_preview.reserved_slugs', []))
        ->filter(fn ($slug) => is_string($slug) && $slug !== '')
        ->map(fn ($slug) => preg_quote(strtolower($slug), '/'))
        ->implode('|');

    $previewSlugPattern = $reservedPreviewSlugs !== ''
        ? '(?!(?:'.$reservedPreviewSlugs.')\\.)[a-z0-9][a-z0-9-]{0,59}'
        : '[a-z0-9][a-z0-9-]{0,59}';

    Route::domain('{preview}.'.$previewDomain)
        ->get('/{path?}', [PreviewController::class, 'subdomain'])
        ->where('preview', $previewSlugPattern)
        ->where('path', '.*')
        ->name('preview.subdomain');
}

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/pricing', fn (\Illuminate\Http\Request $request) => Inertia::render('Pricing', ['trialToken' => $request->query('token')]))->name('pricing');


// SEO A2: focused, useful search landing pages. One intent per URL prevents keyword cannibalization.
$seoLandingPages = [
    '/ai-website-builder' => [
        'title' => 'AI Website Builder for Modern Business Websites | Cosmic CMS',
        'description' => 'Use Cosmic CMS to generate a modern business website with AI, then edit the content, sections, images, and design in a visual builder.',
        'eyebrow' => 'AI Website Builder',
        'h1' => 'Build a modern business website with AI',
        'intro' => 'Cosmic CMS turns a short business brief into a structured, responsive website starting point that stays fully editable.',
        'body' => 'Instead of beginning with an empty canvas, start with AI-assisted page structure, content direction, imagery, and design. Cosmic CMS is built for businesses and creators who want a faster first draft without giving up control of the finished website.',
        'benefits' => ['Generate a complete website starting point from a business brief','Edit sections, copy, imagery, calls to action, headers, and footers','Preview responsive layouts before publishing','Use reusable Sparks to expand and refine pages'],
        'faqs' => [
            ['q'=>'What is an AI website builder?','a'=>'An AI website builder uses artificial intelligence to help create a website structure, content, and design from information you provide about a business or project.'],
            ['q'=>'Can I edit the website after AI generates it?','a'=>'Yes. Cosmic CMS creates an editable starting point, so you can refine content, images, sections, themes, and other website elements.'],
            ['q'=>'Is Cosmic CMS suitable for business websites?','a'=>'Cosmic CMS is designed around modern business websites, including service businesses, agencies, restaurants, professional services, and other small-business use cases.'],
        ],
    ],
    '/ai-website-generator' => [
        'title' => 'AI Website Generator — Create an Editable Website | Cosmic CMS',
        'description' => 'Generate an editable business website from a prompt with Cosmic CMS. Start with AI-created structure and content, then customize it visually.',
        'eyebrow' => 'AI Website Generator',
        'h1' => 'Generate an editable website from your business idea',
        'intro' => 'Describe what your business does and let Cosmic create a polished website concept instead of starting from a blank page.',
        'body' => 'Cosmic combines AI-assisted planning with a visual builder. The generated result is a starting point rather than a locked image: you can continue changing the copy, layout, images, calls to action, and site-wide design.',
        'benefits' => ['Prompt-based website generation','Business-focused page structure','Responsive website output','Visual editing after generation'],
        'faqs' => [
            ['q'=>'How does an AI website generator work?','a'=>'You describe the website you need. The generator uses that context to create a suitable structure and initial content that can then be reviewed and edited.'],
            ['q'=>'Do I need to write all of the website copy first?','a'=>'No. Cosmic can create an initial content direction from your brief, and you can replace or refine the copy afterward.'],
            ['q'=>'Can I change the generated design?','a'=>'Yes. The generated website is intended to be customized in the Cosmic CMS builder.'],
        ],
    ],
    '/modern-website-builder' => [
        'title' => 'Modern Website Builder for Responsive Business Sites | Cosmic CMS',
        'description' => 'Create responsive, modern business websites with Cosmic CMS. Start faster with AI assistance and customize your website in a visual builder.',
        'eyebrow' => 'Modern Website Builder',
        'h1' => 'A modern website builder built for faster launches',
        'intro' => 'Create a clean, responsive business website with an AI-assisted workflow and an editor designed to keep customization straightforward.',
        'body' => 'Modern websites need more than attractive colors. Cosmic CMS focuses on responsive layouts, clear content hierarchy, reusable sections, and an efficient workflow from generation through editing and launch preparation.',
        'benefits' => ['Responsive layouts for desktop, tablet, and mobile','Reusable section system for consistent pages','Modern visual themes and typography','AI-assisted content and website planning'],
        'faqs' => [
            ['q'=>'What makes a website builder modern?','a'=>'A modern website builder should support responsive layouts, fast editing, reusable design systems, accessible content structure, and efficient publishing workflows.'],
            ['q'=>'Can I use Cosmic CMS without starting from scratch?','a'=>'Yes. Cosmic can generate a website starting point based on your business description.'],
            ['q'=>'Are generated websites responsive?','a'=>'Cosmic CMS is designed around responsive website layouts that can be reviewed across common device sizes in the builder.'],
        ],
    ],
    '/website-builder-for-small-business' => [
        'title' => 'AI Website Builder for Small Business | Cosmic CMS',
        'description' => 'Build a professional small-business website faster with Cosmic CMS. Generate a starting point with AI, customize it, and prepare it for launch.',
        'eyebrow' => 'For Small Business',
        'h1' => 'Build your small-business website without the blank-canvas work',
        'intro' => 'Give Cosmic a description of your business and get a professional website concept you can customize around your services, customers, and brand.',
        'body' => 'Small businesses need clear pages, strong calls to action, mobile-friendly design, and an easy way to keep information current. Cosmic CMS brings those pieces into one workflow while using AI to speed up the first draft.',
        'benefits' => ['Built for service and local-business websites','Create service-focused page structures','Edit calls to action and business information visually','Start with AI instead of an empty template'],
        'faqs' => [
            ['q'=>'Is Cosmic CMS suitable for a small business?','a'=>'Yes. The workflow is designed to help small businesses create professional websites without beginning every page from an empty canvas.'],
            ['q'=>'What information should I provide to generate a website?','a'=>'A short description of your business, services, audience, and preferred style gives the AI useful context for the first draft.'],
            ['q'=>'Can I customize the website for my brand?','a'=>'Yes. You can refine content, imagery, themes, and other visual elements after generation.'],
        ],
    ],
    '/no-code-website-builder' => [
        'title' => 'No-Code AI Website Builder for Business | Cosmic CMS',
        'description' => 'Create and customize a business website visually with Cosmic CMS. AI helps produce the starting point so you can focus on content and design.',
        'eyebrow' => 'Visual Website Builder',
        'h1' => 'Create a business website visually, with AI doing the first-draft work',
        'intro' => 'Cosmic CMS combines AI generation with a visual editing workflow so routine website creation does not have to begin with code.',
        'body' => 'Use AI to establish the initial website direction, then work through editable sections and site settings. The goal is to make common business-site changes approachable while preserving a structured website underneath.',
        'benefits' => ['Visual editing for common website content','AI-generated starting structure','Reusable Sparks instead of rebuilding sections','Responsive preview workflow'],
        'faqs' => [
            ['q'=>'Do I need to code to create a website with Cosmic CMS?','a'=>'The core website creation and editing workflow is visual, so common content and design changes do not require writing code.'],
            ['q'=>'What can I edit visually?','a'=>'Depending on the page and section, you can edit content, imagery, calls to action, sections, and site-wide design settings.'],
            ['q'=>'Does no-code mean I lose control of the design?','a'=>'No. Cosmic uses structured sections and themes while still allowing you to customize the generated starting point.'],
        ],
    ],
];

foreach ($seoLandingPages as $path => $page) {
    Route::get($path, fn () => Inertia::render('Public/SeoLanding', [
        'page' => array_merge($page, ['path' => $path]),
    ]));
}

Route::get('/start', [TrialGenerationController::class, 'create'])->name('start');
Route::post('/start', [TrialGenerationController::class, 'store'])->middleware('throttle:6,1')->name('trial-generations.store');
Route::post('/start/{trial:token}/plan', [TrialGenerationController::class, 'selectPlan'])
    ->middleware('throttle:12,1')
    ->name('trial-generations.plan.select');
Route::post('/trials/{trial:token}/email', [TrialGenerationController::class, 'captureEmail'])
    ->middleware('throttle:5,1')
    ->name('trial-generations.email.capture');
Route::post('/trials/{trial:token}/regenerate', [TrialGenerationController::class, 'regenerate'])
    ->middleware('throttle:3,1')
    ->name('trial-generations.regenerate');
Route::get('/trials/{trial:token}/media-pack', [TrialGenerationController::class, 'mediaPackStatus'])
    ->middleware('throttle:30,1')
    ->name('trial-generations.media-pack.status');
Route::post('/trials/{trial:token}/branding/logo/upload', [TrialBrandingController::class, 'uploadLogo'])
    ->middleware(['throttle:10,1', \App\Http\Middleware\RejectOversizedRequest::class . ':3072'])
    ->name('trial-branding.logo.upload');
Route::post('/trials/{trial:token}/branding/logo/generate', [TrialBrandingController::class, 'generateLogo'])
    ->middleware('throttle:4,1')
    ->name('trial-branding.logo.generate');
Route::post('/trials/{trial:token}/branding/logo/crop', [TrialBrandingController::class, 'cropLogo'])
    ->middleware('throttle:30,1')
    ->name('trial-branding.logo.crop');
Route::post('/trials/{trial:token}/branding/logo/match-theme', [TrialBrandingController::class, 'matchLogoToTheme'])
    ->middleware('throttle:4,1')
    ->name('trial-branding.logo.match-theme');
Route::post('/trials/{trial:token}/branding/theme/match-logo', [TrialBrandingController::class, 'matchThemeToLogo'])
    ->middleware('throttle:4,1')
    ->name('trial-branding.theme.match-logo');
Route::post('/trials/{trial:token}/pages/{page}/style', [PageController::class, 'applyTrialPageStyle'])
    ->middleware(['throttle:20,1', \App\Http\Middleware\RejectOversizedRequest::class . ':1024'])
    ->name('trial-pages.style.apply');
Route::post('/trials/{trial:token}/pages/{page}/theme', [PageController::class, 'applyTrialTheme'])
    ->middleware(['throttle:30,1', \App\Http\Middleware\RejectOversizedRequest::class . ':64'])
    ->name('trial-pages.theme.apply');

Route::get('/workspace-invitations/{token}', [WorkspaceInvitationAcceptanceController::class, 'show'])
    ->middleware(['throttle:30,1', \App\Http\Middleware\AddSecurityHeaders::class])
    ->name('workspace-invitations.show');
Route::post('/workspace-invitations/{token}/accept', [WorkspaceInvitationAcceptanceController::class, 'accept'])
    ->middleware(['throttle:10,1', \App\Http\Middleware\AddSecurityHeaders::class, \App\Http\Middleware\RejectOversizedRequest::class . ':64'])
    ->name('workspace-invitations.accept');

// Token-aware Builder routes. Signed-in users keep normal policy checks;
// logged-out visitors need a valid token that belongs to the requested page.
Route::get('/pages/{page}/builder', [PageController::class, 'builder'])->name('pages.builder');
Route::post('/pages/{page}/builder/save', [PageController::class, 'saveBuilder'])
    ->middleware(['throttle:60,1', \App\Http\Middleware\RejectOversizedRequest::class . ':1024'])
    ->name('pages.builder.save');

Route::middleware(['auth', 'verified', \App\Http\Middleware\EnsureOnboardingComplete::class])->group(function () {
    Route::prefix('admin/chat')->middleware(\App\Http\Middleware\EnsurePlatformOwner::class)->group(function () {
        Route::get('/', [CosmicChatInboxController::class, 'index'])->name('admin.chat.index');
        Route::get('/{conversation}', [CosmicChatInboxController::class, 'show'])->name('admin.chat.show');
        Route::patch('/{conversation}', [CosmicChatInboxController::class, 'update'])
            ->middleware('throttle:60,1')
            ->name('admin.chat.update');
        Route::post('/{conversation}/reply', [CosmicChatInboxController::class, 'reply'])
            ->middleware('throttle:30,1')
            ->name('admin.chat.reply');
        Route::patch('/{conversation}/takeover', [CosmicChatInboxController::class, 'takeover'])
            ->middleware('throttle:30,1')
            ->name('admin.chat.takeover');
    });
    Route::get('/admin/queues', [QueueDashboardController::class, 'index'])->name('admin.queues.index');
    Route::get('/admin/queues/status', [QueueDashboardController::class, 'status'])->name('admin.queues.status');
    Route::post('/admin/queues/retry-failed', [QueueDashboardController::class, 'retryFailed'])->middleware('throttle:10,1')->name('admin.queues.retry-failed');
    Route::delete('/admin/queues/failed', [QueueDashboardController::class, 'forgetFailed'])->middleware('throttle:5,1')->name('admin.queues.forget-failed');
    Route::get('/dashboard', [WebsiteController::class, 'index'])->name('dashboard');
    Route::get('/websites/{website}/media-pack/status', [WebsiteController::class, 'mediaPackStatus'])
        ->middleware('throttle:30,1')
        ->name('websites.media-pack.status');
    Route::patch('/appearance', [AppearancePreferenceController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('appearance.update');
    Route::get('/sales', [SalesController::class, 'index'])
        ->middleware(\App\Http\Middleware\EnsurePlatformOwner::class)
        ->name('sales.index');
    Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
    Route::get('/credits/balance', [CreditController::class, 'balance'])->name('credits.balance');
    Route::get('/account-data/{section}', [AccountDataController::class, 'show'])
        ->whereIn('section', ['credits', 'subscription', 'workspace', 'profile', 'settings'])
        ->middleware('throttle:60,1')
        ->name('account-data.show');
    Route::post('/credits/purchase', [CreditController::class, 'purchase'])->name('credits.purchase');
    Route::get('/payments/provider', [PaymentController::class, 'provider'])
        ->middleware('throttle:60,1')
        ->name('payments.provider');
    Route::post('/payments/checkout', [PaymentController::class, 'checkout'])
        ->middleware('throttle:10,1')
        ->name('payments.checkout');
    Route::post('/payments/subscription/cancel', [PaymentController::class, 'cancelSubscription'])
        ->middleware('throttle:5,1')
        ->name('payments.subscription.cancel');
    Route::post('/payments/subscription/sync', [PaymentController::class, 'syncSubscription'])
        ->middleware('throttle:10,1')
        ->name('payments.subscription.sync');
    Route::post('/payments/subscription/recover', [PaymentController::class, 'recoverSubscription'])
        ->middleware('throttle:5,1')
        ->name('payments.subscription.recover');
    Route::get('/cosmic-pricing', [CosmicPricingController::class, 'index'])->name('cosmic-pricing.index');
    Route::get('/page-templates/catalog', [PageTemplateController::class, 'catalog'])->name('page-templates.catalog');
    Route::post('/page-templates/{key}/unlock', [PageTemplateController::class, 'unlock'])->name('page-templates.unlock');
    Route::post('/page-templates/{key}/favorite', [PageTemplateController::class, 'toggleFavorite'])->name('page-templates.favorite.toggle');

    Route::get('/sparks', [SparkController::class, 'index'])->name('sparks.index');
    Route::get('/sparks/catalog', [SparkController::class, 'catalog'])->name('sparks.catalog');
    Route::post('/sparks/{key}/unlock', [SparkController::class, 'unlockKey'])->name('sparks.unlock');
    Route::delete('/sparks/{key}/owned', [SparkController::class, 'removeOwned'])->name('sparks.owned.destroy');
    Route::post('/sparks/{key}/favorite', [SparkController::class, 'toggleFavorite'])->name('sparks.favorite.toggle');
    Route::post('/sparks/{key}/share', [SparkController::class, 'share'])->middleware('throttle:30,1')->name('sparks.share');
    Route::delete('/sparks/{key}/share', [SparkController::class, 'unshare'])->middleware('throttle:30,1')->name('sparks.unshare');
    Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
    Route::post('/websites/bulk-action', [WebsiteController::class, 'bulkAction'])
        ->middleware('throttle:10,1')
        ->name('websites.bulk-action');
    Route::post('/websites/{website}/duplicate', [WebsiteController::class, 'duplicate'])->name('websites.duplicate');
    Route::post('/websites/{website}/transfer-ownership', [WebsiteController::class, 'transferOwnership'])->name('websites.transfer-ownership');
    Route::get('/website-handoffs/{token}', [WebsiteHandoffController::class, 'show'])->name('website-handoffs.show');
    Route::post('/website-handoffs/{token}/accept', [WebsiteHandoffController::class, 'accept'])->middleware('throttle:10,1')->name('website-handoffs.accept');
    Route::delete('/website-handoffs/{handoff}', [WebsiteHandoffController::class, 'cancel'])->name('website-handoffs.cancel');
    Route::delete('/websites/{website}', [WebsiteController::class, 'destroy'])->name('websites.destroy');
    Route::get('/websites/{website}/deployment-connector', [WebsiteController::class, 'downloadDeploymentConnector'])->name('websites.deployment-connector.download');
    Route::post('/websites/{website}/deployment-connector/verify', [WebsiteController::class, 'verifyDeploymentConnector'])->name('websites.deployment-connector.verify');
    Route::post('/websites/{website}/deployment-connector/push', [WebsiteController::class, 'pushLiveUpdate'])->name('websites.deployment-connector.push');
    Route::put('/websites/{website}/settings', [WebsiteController::class, 'updateSettings'])->name('websites.settings.update');
    Route::put('/websites/{website}/profile', [WebsiteController::class, 'updateProfile'])->name('websites.profile.update');
    Route::get('/download-bridge', [WebsiteController::class, 'downloadBridge'])->name('bridge.download');

    Route::patch('/agency-insights/leads/{lead}', [AgencyLeadController::class, 'update'])->name('agency-insights.leads.update');
    Route::get('/agency-insights/leads/export', [AgencyLeadController::class, 'export'])->name('agency-insights.leads.export');
    Route::post('/agency-insights/sales', [AgencySalesController::class, 'store'])->name('agency-insights.sales.store');
    Route::get('/agency-insights/sales/export', [AgencySalesController::class, 'export'])->name('agency-insights.sales.export');
    Route::post('/workspace/members', [WorkspaceMemberController::class, 'store'])->middleware('throttle:cosmic-workspace-write')->name('workspace.members.store');
    Route::patch('/workspace/members/{member}/role', [WorkspaceMemberController::class, 'updateRole'])->middleware('throttle:cosmic-workspace-write')->name('workspace.members.role.update');
    Route::delete('/workspace/members/{member}', [WorkspaceMemberController::class, 'destroy'])->middleware('throttle:cosmic-workspace-write')->name('workspace.members.destroy');
    Route::post('/workspace/invitations/{invitation}/resend', [WorkspaceMemberController::class, 'resend'])->middleware('throttle:10,1')->name('workspace.invitations.resend');
    Route::delete('/workspace/invitations/{invitation}', [WorkspaceMemberController::class, 'cancel'])->name('workspace.invitations.destroy');
    Route::put('/workspace/members/{member}/website-assignments', [WebsiteAssignmentController::class, 'update'])->middleware('throttle:cosmic-workspace-write')->name('workspace.members.website-assignments.update');
    Route::post('/workspaces/{workspace}/branding', [AgencyBrandingController::class, 'update'])->middleware('throttle:20,1')->name('workspace.branding.update');
    Route::delete('/workspaces/{workspace}/branding', [AgencyBrandingController::class, 'reset'])->middleware('throttle:10,1')->name('workspace.branding.reset');
    Route::get('/agency-reports', [AgencyReportController::class, 'index'])->name('agency-reports.index');
    Route::get('/agency-portal', [AgencyPortalController::class, 'index'])->name('agency-portal.index');
    Route::post('/websites/{website}/preview-links', [BrandedPreviewLinkController::class, 'store'])->middleware('throttle:20,1')->name('preview-links.store');
    Route::delete('/preview-links/{previewLink}', [BrandedPreviewLinkController::class, 'revoke'])->middleware('throttle:20,1')->name('preview-links.revoke');
    Route::get('/client/websites/{website}/preview', [ClientPreviewController::class, 'show'])->name('client.websites.preview');
    Route::get('/websites/{website}/pages', [PageController::class, 'index'])->name('pages.index');
    Route::get('/websites/{website}/inquiries', [ContactSubmissionController::class, 'index'])->name('websites.inquiries.index');
    Route::patch('/websites/{website}/inquiries/{submission}', [ContactSubmissionController::class, 'update'])->name('websites.inquiries.update');
    Route::post('/websites/{website}/pages', [PageController::class, 'store'])->name('pages.store');
    Route::delete('/websites/{website}/pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');

    // The legacy endpoint remains for compatibility with older clients.
    Route::post('/pages/{page}/builder', [PageController::class, 'updateBlocks'])->name('pages.builder.update');
    Route::post('/pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
    Route::post('/pages/{page}/style', [PageController::class, 'applyPageStyle'])->name('pages.style.apply');
    Route::post('/websites/{website}/pages/{page}/blog-posts/generate', [BlogPostController::class, 'generate'])->name('blog-posts.generate');
    Route::post('/websites/{website}/pages/{page}/blog-posts', [BlogPostController::class, 'store'])->name('blog-posts.store');
    Route::put('/websites/{website}/pages/{page}/blog-posts/{blogPost}', [BlogPostController::class, 'update'])->name('blog-posts.update');
    Route::delete('/websites/{website}/pages/{page}/blog-posts/{blogPost}', [BlogPostController::class, 'destroy'])->name('blog-posts.destroy');

    Route::post('/websites/{website}/global-header/save', [PageController::class, 'saveGlobalHeader'])->name('websites.global-header.save');
    Route::post('/websites/{website}/global-footer/save', [WebsiteController::class, 'saveFooter'])->name('websites.global-footer.save');
    Route::post('/websites/{website}/update-theme', [PageController::class, 'updateTheme'])->name('websites.update-theme');

    Route::post('/ai/generate', [\App\Http\Controllers\AIChatController::class, 'generate'])->middleware('throttle:cosmic-ai')->name('ai.generate');
    Route::post('/ai/select-sections', [AIController::class, 'selectSections'])->middleware('throttle:cosmic-ai')->name('ai.select-sections');
    Route::post('/ai/select-section', [AIController::class, 'selectSection'])->middleware('throttle:cosmic-ai')->name('ai.select-section');
    Route::post('/ai/generate-content', [AIController::class, 'generateContent'])->middleware('throttle:cosmic-ai')->name('ai.generate-content');

    // Keep the existing browser-session image URLs while protecting the writes.
    Route::post('/api/websites/{website}/remote-image', [ImageController::class, 'remoteImage'])->middleware('throttle:cosmic-ai')->name('websites.remote-image');
    Route::post('/api/upload-block-image', [ImageController::class, 'uploadImage'])->middleware('throttle:cosmic-upload');
    Route::post('/api/upload-logo', [ImageController::class, 'uploadLogo'])->middleware('throttle:cosmic-upload')->name('websites.logo.upload');
    Route::post('/websites/{website}/branding/logo/generate', [ImageController::class, 'generateLogo'])->middleware('throttle:4,1')->name('websites.logo.generate');
    Route::post('/websites/{website}/branding/logo/crop', [ImageController::class, 'cropLogo'])->middleware('throttle:30,1')->name('websites.logo.crop');
    Route::post('/websites/{website}/branding/logo/match-theme', [ImageController::class, 'matchLogoToTheme'])->middleware('throttle:4,1')->name('websites.logo.match-theme');
    Route::post('/websites/{website}/branding/theme/match-logo', [ImageController::class, 'matchThemeToLogo'])->middleware('throttle:4,1')->name('websites.theme.match-logo');
    Route::post('/api/update-block-data', [ImageController::class, 'update'])->middleware('throttle:cosmic-upload');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/notifications', [NotificationPreferenceController::class, 'update'])->name('profile.notifications.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

if (app()->environment('local')) {
    Route::get('/debug-compiler/{page}', function ($pageId) {
        $page = Page::findOrFail($pageId);
        $blocks = is_array($page->blocks) ? $page->blocks : json_decode($page->blocks, true);

        return response()->json([
            'page_id' => $page->id,
            'slug' => $page->slug,
            'blocks_count' => count($blocks),
            'blocks' => $blocks,
            'compiled_html' => CmsHtmlCompiler::compile($blocks, 'espresso'),
        ]);
    });

    Route::get('/debug-db/{page_id}', function ($pageId, Request $request) {
        $page = Page::find($pageId);

        if (!$page || $request->header('X-Bridge-Token') !== $page->website->api_secret_key) {
            return response()->json(['message' => 'Unauthorized Bridge Token.'], 401);
        }

        return app(PageController::class)->debugApiHandshake($pageId);
    });

    Route::get('/debug-header/{website_id}', [PageController::class, 'debugHeaderHandshake']);
    Route::get('/debug-db-all', [PageController::class, 'debugApiAllPages']);
    Route::get('/debug-db-all-blocks', fn () => response()->json(Page::all()));
}

require __DIR__.'/auth.php';
