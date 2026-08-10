<?php

namespace App\Http\Controllers;

use App\Services\MyBrandThemeService;
use App\Models\Website;
use App\Models\Page;
use App\Models\TrialGeneration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\PagePublisher;
use App\Services\PreviewDeploymentService;
use App\Services\CreditService;
use App\Services\ThemePlanAccessService;
use App\Services\MediaAssetLifecycleService;
use App\Services\MediaAssetSafetyService;
use App\Services\TrialCreditService;
use App\Support\PageStyleRegistry;
use App\Services\BlogSparkRegistry;
use App\Cosmic\Pricing\ActionPricing;
use App\Cosmic\Pricing\BlockPricingRegistry;
use App\Cosmic\Pricing\ThemePricingRegistry;
use App\Models\CosmicUnlock;
use Throwable;

class PageController extends Controller
{
    public function index(Website $website)
    {
        $this->authorize('view', $website);

        return \Inertia\Inertia::render('Websites/Index', [
            'website' => $website,
            'pages' => $website->pages()->orderBy('parent_id')->orderBy('sort_order')->orderBy('id')->get(),
            'inquiryCount' => $website->contactSubmissions()->whereNull('archived_at')->count(),
            'recentInquiries' => $website->contactSubmissions()
                ->whereNull('archived_at')
                ->latest('received_at')
                ->limit(25)
                ->get(),
            // DIRETSO KORREKTE HANDSHAKE PACKET NGADTO SA REACT
            'globalHeaderBlock' => $website->global_header,
            'globalFooterBlock' => $website->global_footer,
        ]);
    }

    public function store(Request $request, Website $website, CreditService $credits)
    {
        $this->authorize('update', $website);

        $request->validate([
            'title' => 'required|string|max:255',
            'page_type' => 'nullable|in:standard,blog',
            'parent_id' => 'nullable|integer',
        ]);

        $slug = Str::slug($request->title);
        $pageType = $request->input('page_type', 'standard');

        $parent = null;
        if ($request->filled('parent_id')) {
            $parent = $website->pages()->with('parent')->findOrFail($request->integer('parent_id'));
            abort_if($parent->parent?->parent_id !== null, 422, 'Pages can only be nested three levels deep.');
        }

        $reference = 'add-page-' . Str::uuid();
        $credits->consume(
            $request->user(),
            ActionPricing::ADD_PAGE,
            'Add page: ' . $request->title,
            $website,
            $reference,
            ['page_type' => $pageType],
        );

        try {
            DB::transaction(function () use ($website, $request, $slug, $pageType, $parent) {
                $page = $website->pages()->create([
                    'title' => $request->title,
                    'slug' => $slug,
                    'parent_id' => $parent?->id,
                    'sort_order' => ((int) $website->pages()->where('parent_id', $parent?->id)->max('sort_order')) + 1,
                    'page_type' => $pageType,
                    'status' => 'draft',
                    'blocks' => $pageType === 'blog' ? $this->createBlogPageBlocks() : [],
                ]);

                if ($pageType === 'blog') {
                    $this->createStarterBlogPosts($website, $page);
                }
            });
        } catch (Throwable $exception) {
            $credits->refund(
                $request->user(),
                ActionPricing::ADD_PAGE,
                'Refund for failed page creation',
                $website,
                $reference . '-refund',
            );
            throw $exception;
        }

        return back()->with('success', 'Page created for ' . ActionPricing::ADD_PAGE . ' Cosmic Credits.');
    }

    public function destroy(Website $website, Page $page)
    {
        $this->authorize('update', $website);

        abort_unless($page->website_id === $website->id, 404);

        // Blog posts belong to their page. Remove posts for the whole branch
        // before the database cascade removes child pages.
        $pageIds = $this->descendantPageIds($website, $page);
        $website->blogPosts()->whereIn('page_id', $pageIds)->delete();
        $page->delete();

        return response()->json([
            'message' => 'Page deleted successfully.',
        ]);
    }

    private function descendantPageIds(Website $website, Page $page): array
    {
        $allPages = $website->pages()->get(['id', 'parent_id']);
        $ids = [$page->id];

        do {
            $before = count($ids);
            foreach ($allPages as $candidate) {
                if ($candidate->parent_id !== null && in_array($candidate->parent_id, $ids, true) && ! in_array($candidate->id, $ids, true)) {
                    $ids[] = $candidate->id;
                }
            }
        } while (count($ids) !== $before);

        return $ids;
    }

    /**
     * Give each Posts / updates page a useful, editable starting point.
     * They deliberately remain drafts: nothing reaches a live website until
     * the customer reviews and explicitly publishes it.
     */
    private function createStarterBlogPosts(Website $website, Page $page): void
    {
        $starters = [
            [
                'title' => 'A practical guide to getting started',
                'category' => 'Featured',
                'excerpt' => 'A useful first article that introduces your perspective and gives visitors a reason to explore more.',
                'content' => 'Use this featured article to share a helpful point of view, introduce an important update, or explain the value your business brings to customers.',
                'image_url' => '/storage/cms-images/background/background-1.avif',
                'is_featured' => true,
            ],
            [
                'title' => 'What customers should know first',
                'category' => 'Insights',
                'excerpt' => 'Answer a common question with a short, clear explanation your audience can trust.',
                'content' => 'Start with the context your customer needs, then explain the practical next step in plain language.',
                'image_url' => '/storage/cms-images/background/background-2.avif',
            ],
            [
                'title' => 'A closer look at our approach',
                'category' => 'How we work',
                'excerpt' => 'Share the process, standards, or ideas behind the work you do every day.',
                'content' => 'Describe your approach in a way that makes your service easier to understand and more credible.',
                'image_url' => '/storage/cms-images/background/background-3.avif',
            ],
            [
                'title' => 'Updates worth sharing',
                'category' => 'Updates',
                'excerpt' => 'Keep visitors informed with news, announcements, or practical changes from your team.',
                'content' => 'Use this post for an announcement, a timely update, or an important piece of information for your customers.',
                'image_url' => '/storage/cms-images/background/background-5.avif',
            ],
            [
                'title' => 'Ideas for your next step',
                'category' => 'Guides',
                'excerpt' => 'Offer a focused recommendation that helps readers take action with confidence.',
                'content' => 'Give readers a practical takeaway they can use right away, then invite them to contact you for help.',
                'image_url' => '/storage/cms-images/background/background-1.avif',
            ],
        ];

        foreach ($starters as $index => $starter) {
            $website->blogPosts()->create([
                ...$starter,
                'page_id' => $page->id,
                // blog_posts.slug is currently globally unique. A customer can
                // create more than one Posts / updates page, so fixed starter
                // titles must not reuse a slug left by an earlier blog hub.
                'slug' => $this->uniqueBlogPostSlug(
                    Str::slug($starter['title']) . '-' . ($index + 1)
                ),
                'tags' => [],
                'status' => 'draft',
            ]);
        }
    }

    /**
     * Keep seeded post slugs compatible with the existing global unique index.
     */
    private function uniqueBlogPostSlug(string $baseSlug): string
    {
        $slug = $baseSlug ?: 'post';
        $suffix = 2;

        while (\App\Models\BlogPost::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Keep a Posts / updates page focused and predictable. The primary mini
     * hero introduces the page, BlogHub contains the featured post and four
     * article cards, and the two blocks after it complete the editorial page.
     */
    private function createBlogPageBlocks(): array
    {
        // Randomization happens only here, during the initial creation of a
        // Posts / updates page. The selected layout_variant is persisted in
        // the block payload, so edit, save, publish, refresh, and theme changes
        // never re-roll the customer's composition.
        return [
            [
                'type' => 'blog_mini_hero',
                'theme' => 'primary',
                'layout_variant' => BlogSparkRegistry::randomVariant('blog_mini_hero')
                    ?? 'mini-header-01',
            ],
            [
                'type' => 'blog_hub',
                'theme' => 'editorial',
                'show_intro' => false,
                'layout_variant' => BlogSparkRegistry::randomVariant('blog_hub')
                    ?? 'blog-cards-01',
            ],
            [
                'type' => 'newsletter_cta',
                'theme' => 'primary',
                'layout_variant' => BlogSparkRegistry::randomVariant('newsletter_cta')
                    ?? 'newsletter-01',
            ],
            [
                'type' => 'latest_resources',
                'theme' => 'white',
                'layout_variant' => BlogSparkRegistry::randomVariant('latest_resources')
                    ?? 'resources-01',
            ],
        ];
    }

    public function builder(Request $request, Page $page)
    {
        $trial = $this->resolveTrialAccess($request, $page);
        $isTrialMode = $trial !== null;

        if (! $isTrialMode) {
            $this->authorize('view', $page->website);
        }

        $website = $page->website;

        // A public trial must render with the theme selected for that trial,
        // never with the shared demo website's current theme. Clone the model
        // so this request-only override cannot mutate Website #14.
        if ($isTrialMode) {
            $website = clone $website;
            $website->setAttribute('theme_settings', $trial->preview_theme ?? [
                'primary' => 'midnight',
                'secondary' => 'white',
                'tertiary' => 'stone',
                'auto' => true,
            ]);
        }

        $websiteMessaging = $page->website->pages()
            ->get(['title', 'blocks'])
            ->flatMap(function (Page $websitePage) {
                return collect($websitePage->blocks ?? [])
                    ->map(function ($block) {
                        if (! is_array($block)) {
                            return null;
                        }

                        return collect([
                            $block['tagline'] ?? null,
                            $block['eyebrow'] ?? null,
                            $block['heading'] ?? null,
                            $block['text'] ?? null,
                            $block['description'] ?? null,
                        ])
                            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                            ->implode(' ');
                    })
                    ->filter();
            })
            ->take(8)
            ->implode(' ');

        $profileContext = array_filter([
            $website->industry ? "Industry: {$website->industry}." : null,
            $website->location ? "Location: {$website->location}." : null,
            $website->business_description ? "Business description: {$website->business_description}" : null,
        ]);

        $websiteContext = trim(implode("\n", array_filter([
            'Generate professional website content for the following business.',
            "Business Name: {$website->name}",
            ...$profileContext,
            "Page: {$page->title}",
            'Language: English.',
            'Write naturally and professionally. Do not invent awards, certifications, employee names, years of experience, customer statistics, or other unverifiable facts.',
            $websiteMessaging !== '' ? "Existing website messaging: {$websiteMessaging}" : null,
        ])));

        $builderThemeAccess = null;
        if ($isTrialMode) {
            // H14: direct token Builder links must also always expose the
            // persistent My Brand Theme, including older trials created before H14.
            $themeSettings = is_array($trial->preview_theme) ? $trial->preview_theme : [];
            $seededThemeSettings = app(MyBrandThemeService::class)->ensureInSettings($themeSettings);
            if ($seededThemeSettings !== $themeSettings) {
                $trial->update(['preview_theme' => $seededThemeSettings]);
                $trial->setAttribute('preview_theme', $seededThemeSettings);
            }

            // Guest trials intentionally expose only three themes. Always include
            // the theme selected during generation so the active theme never
            // appears locked, then fill the remaining slots with curated defaults.
            $activeTrialTheme = (string) data_get($trial->preview_theme, 'primary', 'midnight');
            $savedTrialThemeKeys = data_get($trial->preview_theme, 'trial_theme_keys');
            $trialThemeKeys = is_array($savedTrialThemeKeys) && count($savedTrialThemeKeys) === 3
                ? array_values(array_unique(array_map('strval', $savedTrialThemeKeys)))
                : collect([$activeTrialTheme, 'midnight', 'emerald', 'ocean'])
                    ->filter()
                    ->unique()
                    ->take(3)
                    ->values()
                    ->all();

            if (! is_array($savedTrialThemeKeys) || $savedTrialThemeKeys !== $trialThemeKeys) {
                $themeSettings = is_array($trial->preview_theme) ? $trial->preview_theme : [];
                $themeSettings['trial_theme_keys'] = $trialThemeKeys;
                $trial->update(['preview_theme' => $themeSettings]);
                $trial->setAttribute('preview_theme', $themeSettings);
            }

            $customThemeExists = is_array(data_get($trial->preview_theme, 'custom_brand_theme'));
            $trialAccessibleThemeKeys = $customThemeExists
                ? array_values(array_unique(['my-brand', ...$trialThemeKeys]))
                : $trialThemeKeys;

            $builderThemeAccess = [
                'keys' => $trialAccessibleThemeKeys,
                'count' => count($trialThemeKeys),
                'unlimited' => false,
                'next_plan' => 'Sign up',
                'plan_key' => 'guest_trial',
                'trial' => true,
            ];
        } elseif ($request->user()) {
            $themeAccessService = app(ThemePlanAccessService::class);
            $effectivePlanKey = $request->user()->effectivePlanKey();
            $themeKeys = $themeAccessService->allowedThemeKeysForWebsite($effectivePlanKey, $website);
            $builderThemeAccess = [
                'keys' => $themeKeys,
                'count' => $themeKeys === null ? null : count($themeKeys),
                'unlimited' => $themeKeys === null,
                'next_plan' => in_array($effectivePlanKey, ['starter', 'agency_starter'], true)
                    ? 'Growth'
                    : (in_array($effectivePlanKey, ['growth', 'agency_growth'], true) ? 'Pro' : null),
                'plan_key' => $effectivePlanKey,
            ];
        }

        return Inertia::render('Websites/Builder', [
            // Builder is token-aware and intentionally lives outside the normal
            // authenticated route group, so pass theme access explicitly instead
            // of relying only on shared Inertia auth props.
            'themeAccess' => $builderThemeAccess,
            'page' => $page,
            'website' => $website,
            'previewUrl' => $isTrialMode || ! $website->last_preview_deployed_at
                ? null
                : app(PreviewDeploymentService::class)->urlForPage($website, $page),
            'previewDeployment' => $isTrialMode ? null : [
                'deployed_at' => $website->last_preview_deployed_at?->toISOString(),
                'error' => $website->preview_deployment_error,
                'ready' => filled($website->preview_slug) && filled($website->last_preview_deployed_at),
            ],
            'websitePages' => $isTrialMode
                ? collect($trial->menu_structure ?? [])
                    ->map(fn (array $menuPage, int $index) => [
                        'id' => ($menuPage['is_home'] ?? false) ? $page->id : null,
                        'title' => $menuPage['title'],
                        'slug' => $menuPage['slug'],
                        'parent_id' => null,
                        'is_home' => (bool) ($menuPage['is_home'] ?? false),
                        'sort_order' => $menuPage['sort_order'] ?? ($index + 1),
                    ])
                    ->values()
                : $website->pages()
                    ->orderBy('parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'title', 'slug', 'parent_id'])
                    ->map(fn (Page $websitePage) => [
                        'id' => $websitePage->id,
                        'title' => $websitePage->title,
                        'slug' => $websitePage->slug,
                        'parent_id' => $websitePage->parent_id,
                    ])
                    ->values(),
            'blogPosts' => $isTrialMode
                ? []
                : ($page->page_type === 'blog'
                    ? $website->blogPosts()
                        ->where('page_id', $page->id)
                        ->orderByDesc('is_featured')
                        ->latest()
                        ->get()
                    : []),
            'hasWebsiteContent' => $websiteMessaging !== '',
            'websiteContext' => $websiteContext,
            'pageStyle' => $page->page_style ?: 'auto',
            'pageStyleOptions' => PageStyleRegistry::suggestions($website->industry, $page->page_style),
            'trialMode' => $isTrialMode,
            'globalHeaderBlock' => $isTrialMode
                ? [
                    'type' => 'glassmorphism_header',
                    'logo_text' => $trial->business_name,
                    'logo_image_url' => $trial->logo_url ?: '/storage/branding/your-logo.png',
                    'logo_height' => 42,
                    'logo_filter_key' => data_get($trial->preview_theme, 'primary', 'midnight'),
                    'cta_label' => 'Get Started',
                    'cta_url' => '#',
                    'menu' => collect($trial->menu_structure ?? [])
                        ->map(fn (array $menuPage) => [
                            'label' => $menuPage['title'],
                            'url' => ($menuPage['is_home'] ?? false) ? 'home' : $menuPage['slug'],
                        ])
                        ->values()
                        ->all(),
                ]
                : $website->global_header,
            'globalFooterBlock' => $isTrialMode
                ? [
                    'type' => 'minimal_footer',
                    'theme' => 'white',
                    'logo_text' => $trial->business_name,
                    'logo_image_url' => $trial->logo_url ?: '/storage/branding/your-logo.png',
                    'logo_height' => 36,
                    'logo_filter_key' => data_get($trial->preview_theme, 'primary', 'midnight'),
                    'copyright' => '© '.now()->year.'. All rights reserved.',
                ]
                : $website->global_footer,
            'trialToken' => $trial?->token,
            'websiteMediaPack' => ! $isTrialMode ? [
                'status' => $website->mediaPack?->status ?? 'missing',
                'target' => (int) ($website->mediaPack?->target_image_count ?? 0),
            ] : null,
            'trialExperience' => $trial ? [
                'email' => $trial->email,
                'email_captured' => filled($trial->email),
                'regenerations_used' => 0,
                'regenerations_limit' => null,
                'regenerations_reset_at' => now()->addDay()->startOfDay()->toIso8601String(),
                'logo_regenerations_used' => DB::table('trial_logo_generations')
                    ->where('trial_generation_id', $trial->id)
                    ->where('action', 'regenerate')
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count(),
                'logo_regenerations_limit' => 2,
                'media_pack_status' => $trial->mediaPack?->status,
                'media_pack_target' => (int) ($trial->mediaPack?->target_image_count ?? 0),
                'logo_url' => $trial->logo_url,
                'logo_company_name' => $trial->logo_company_name,
                'logo_source' => $trial->logo_source,
                'logo_crop_confirmed' => (bool) data_get($trial->preview_theme, 'brand_logo_crop_confirmed', false),
                'logo_crop_dismissed' => (bool) data_get($trial->preview_theme, 'brand_logo_crop_dismissed', false),
                'logo_theme_sync_state' => $trial->logo_theme_sync_state ?: (filled($trial->logo_url) ? (in_array($trial->logo_source, ['ai', 'ai-theme-match', 'svg-theme-match'], true) ? 'synced' : 'logo_changed') : null),
                'logo_theme_sync_source' => $trial->logo_theme_sync_source,
                'logo_theme_synced_theme' => $trial->logo_theme_synced_theme,
                'guest_credits' => $trial ? app(TrialCreditService::class)->balance($trial) : 0,
            ] : null,
            'cosmicPricing' => [
                'balance' => $trial ? app(TrialCreditService::class)->balance($trial) : (int) ($request->user()?->fresh()?->credits ?? 0),
                'guest' => (bool) $trial,
                'actions' => ActionPricing::all(),
                'trial_actions' => [
                    'page_style' => TrialCreditService::PAGE_STYLE,
                    'regenerate_page' => TrialCreditService::REGENERATE_PAGE,
                    'generate_logo' => TrialCreditService::GENERATE_LOGO,
                    'match_logo_to_theme' => TrialCreditService::MATCH_LOGO_TO_THEME,
                    'match_theme_to_logo' => TrialCreditService::MATCH_THEME_TO_LOGO,
                ],
                'blocks' => BlockPricingRegistry::all(),
                'themes' => ThemePricingRegistry::all(),
            ],
            'trialCapabilities' => [
                'canNavigateAway' => ! $isTrialMode,
                'canChangeTheme' => true,
                'canGenerateAi' => ! $isTrialMode,
                'canPublish' => ! $isTrialMode,
                'canManageBlocks' => ! $isTrialMode,
                'canEditGlobalShell' => ! $isTrialMode,
                'canSave' => true,
                'canPurchase' => $isTrialMode,
            ],
        ]);
    }

   public function updateBlocks(\Illuminate\Http\Request $request, \App\Models\Page $page)
    {
        $this->authorize('update', $page->website);

        $validated = $request->validate([
            'blocks' => 'nullable|array',
            'global_header' => 'nullable|array',
        ]);

        DB::transaction(function () use ($page, $validated) {
            $page->blocks = $validated['blocks'];
            $page->save();

            if (array_key_exists('global_header', $validated)) {
                $page->website->global_header = $validated['global_header'];
                $page->website->save();
            }
        });

        return redirect()->back()->with('success', 'Page updated successfully.');
    }

    public function saveBuilder(Request $request, Page $page, CreditService $credits)
    {
        $trial = $this->resolveTrialAccess($request, $page);

        if ($trial === null) {
            $this->authorize('update', $page->website);
        }

        $rules = [
            'blocks' => ['nullable', 'array'],
            'theme_settings' => ['nullable', 'array'],
        ];
        if ($trial === null) {
            $rules['global_header'] = ['nullable', 'array'];
            $rules['global_footer'] = ['nullable', 'array'];
        }

        $validated = $request->validate($rules);
        $website = $page->website;

        if ($trial === null && array_key_exists('theme_settings', $validated)) {
            $requestedTheme = (string) data_get($validated, 'theme_settings.primary', '');
            $currentTheme = (string) data_get($website->theme_settings, 'primary', '');

            if ($requestedTheme !== '') {
                app(ThemePlanAccessService::class)->assertCanUse(
                    $request->user(),
                    $requestedTheme,
                    $currentTheme,
                    app(ThemePlanAccessService::class)->includedThemeKeyForWebsite($website),
                );
            }
        }

        if ($trial !== null && array_key_exists('theme_settings', $validated)) {
            $requestedTheme = (string) data_get($validated, 'theme_settings.primary', '');
            $activeTrialTheme = (string) data_get($trial->preview_theme, 'primary', 'midnight');
            $allowedTrialThemes = data_get($trial->preview_theme, 'trial_theme_keys');

            // trial_theme_keys contains the unlocked preset themes only.
            // My Brand Theme is a separate first-class trial theme created from
            // the user's logo and must not consume/replace a preset slot.
            if (! is_array($allowedTrialThemes) || count($allowedTrialThemes) < 1) {
                $allowedTrialThemes = collect([$activeTrialTheme, 'midnight', 'emerald', 'ocean'])
                    ->filter(fn ($theme) => filled($theme) && $theme !== 'my-brand')
                    ->unique()
                    ->take(3)
                    ->values()
                    ->all();
            }

            $submittedCustomBrandTheme = data_get($validated, 'theme_settings.custom_brand_theme');
            $savedCustomBrandTheme = data_get($trial->preview_theme, 'custom_brand_theme');
            $canUseMyBrandTheme = $requestedTheme === 'my-brand'
                && (
                    is_array($submittedCustomBrandTheme)
                    || is_array($savedCustomBrandTheme)
                );

            abort_if(
                $requestedTheme !== ''
                    && ! $canUseMyBrandTheme
                    && ! in_array($requestedTheme, $allowedTrialThemes, true),
                403,
                'Sign up to unlock more themes.'
            );
        }

        DB::transaction(function () use ($page, $website, $validated, $trial) {
            $page->blocks = $validated['blocks'] ?? [];
            $page->status = 'draft';
            $page->publish_error = null;
            $page->save();

            if ($trial !== null) {
                $trialUpdate = ['generated_blocks' => $page->blocks, 'last_saved_at' => now()];
                if (array_key_exists('theme_settings', $validated)) {
                    $trialUpdate['preview_theme'] = $validated['theme_settings'];
                }
                $trial->update($trialUpdate);
                return;
            }

            if (array_key_exists('global_header', $validated)) $website->global_header = $validated['global_header'];
            if (array_key_exists('global_footer', $validated)) $website->global_footer = $validated['global_footer'];
            if (array_key_exists('theme_settings', $validated)) $website->theme_settings = $validated['theme_settings'];
            $website->save();
        });

        if ($trial !== null) {
            app(MediaAssetLifecycleService::class)->queueTrialSave($trial->fresh(['mediaPack', 'page']));
        }

        return response()->json([
            'status' => 'success',
            'credits_spent' => 0,
            'credit_balance' => $trial === null ? $credits->balance($request->user()) : null,
            'page_status' => 'draft',
            'message' => 'Draft saved successfully. Theme credits are only charged when publishing.',
        ]);
    }

    public function applyPageStyle(Request $request, Page $page, CreditService $credits)
    {
        $this->authorize('update', $page->website);

        $validated = $request->validate([
            'style' => ['required', 'string', 'max:40'],
            'blocks' => ['required', 'array'],
        ]);

        abort_unless(PageStyleRegistry::exists($validated['style']), 422, 'That page style is not available.');

        $user = $request->user();
        $cost = PageStyleRegistry::CREDIT_COST;
        $reference = 'page-style-'.$page->id.'-'.now()->format('YmdHisv');

        $credits->consume(
            $user,
            $cost,
            'AI page style: '.$validated['style'],
            $page->website,
            $reference
        );

        try {
            $blocks = collect($validated['blocks'])
                ->map(function ($block) {
                    if (! is_array($block)) return $block;
                    $block['theme'] = 'auto';
                    unset($block['resolvedTheme']);
                    return $block;
                })
                ->values()
                ->all();

            DB::transaction(function () use ($page, $validated, $blocks) {
                $page->page_style = $validated['style'];
                $page->blocks = $blocks;
                $page->status = 'draft';
                $page->publish_error = null;
                $page->save();
            });

            return response()->json([
                'status' => 'success',
                'page_style' => $page->page_style,
                'style' => ['key' => $page->page_style, ...PageStyleRegistry::all()[$page->page_style]],
                'blocks' => $blocks,
                'page_status' => 'draft',
                'credits_spent' => $cost,
                'credit_balance' => $credits->balance($user),
                'suggestions' => PageStyleRegistry::suggestions($page->website->industry, $page->page_style),
            ]);
        } catch (\Throwable $exception) {
            $credits->refund($user, $cost, 'Refund for failed AI page style', $page->website, $reference.'-refund');
            throw $exception;
        }
    }

    public function applyTrialPageStyle(Request $request, TrialGeneration $trial, Page $page, TrialCreditService $trialCredits)
    {
        abort_unless(
            $trial->status === 'ready'
            && ! $trial->claimed_at
            && (int) $trial->page_id === (int) $page->id,
            404
        );

        $expiresAt = filled($trial->email)
            ? $trial->created_at->copy()->addDays(30)
            : $trial->created_at->copy()->addHours(24);
        abort_if($expiresAt->isPast(), 410, 'This trial link has expired.');

        $validated = $request->validate([
            'style' => ['required', 'string', 'max:40'],
            'blocks' => ['required', 'array'],
        ]);

        abort_unless(PageStyleRegistry::exists($validated['style']), 422, 'That page style is not available.');
        $trialCredits->ensureCanSpend($trial, TrialCreditService::PAGE_STYLE, 'Page Style');

        $blocks = collect($validated['blocks'])
            ->map(function ($block) {
                if (! is_array($block)) return $block;
                $block['theme'] = 'auto';
                unset($block['resolvedTheme']);
                return $block;
            })
            ->values()
            ->all();

        DB::transaction(function () use ($page, $trial, $validated, $blocks) {
            $page->page_style = $validated['style'];
            $page->blocks = $blocks;
            $page->status = 'draft';
            $page->publish_error = null;
            $page->save();

            $trial->update([
                'generated_blocks' => $blocks,
                'last_saved_at' => now(),
            ]);
        });

        $balance = $trialCredits->consume($trial, TrialCreditService::PAGE_STYLE, 'page_style', ['style' => $validated['style']]);

        return response()->json([
            'status' => 'success',
            'page_style' => $page->page_style,
            'style' => ['key' => $page->page_style, ...PageStyleRegistry::all()[$page->page_style]],
            'blocks' => $blocks,
            'page_status' => 'draft',
            'credits_spent' => TrialCreditService::PAGE_STYLE,
            'credit_balance' => $balance,
            'suggestions' => PageStyleRegistry::suggestions($page->website->industry, $page->page_style),
        ]);
    }

    public function applyTrialTheme(Request $request, TrialGeneration $trial, Page $page, TrialCreditService $trialCredits)
    {
        abort_unless(
            $trial->status === 'ready' && ! $trial->claimed_at && (int) $trial->page_id === (int) $page->id,
            404
        );

        $expiresAt = filled($trial->email) ? $trial->created_at->copy()->addDays(30) : $trial->created_at->copy()->addHours(24);
        abort_if($expiresAt->isPast(), 410, 'This trial link has expired.');

        $validated = $request->validate(['theme' => ['required', 'string', 'max:40']]);
        $requestedTheme = $validated['theme'];
        $activeTheme = (string) data_get($trial->preview_theme, 'primary', 'midnight');
        $allowedThemes = data_get($trial->preview_theme, 'trial_theme_keys');
        if (! is_array($allowedThemes) || count($allowedThemes) !== 3) {
            $allowedThemes = collect([$activeTheme, 'midnight', 'emerald', 'ocean'])->filter(fn ($key) => $key !== 'my-brand')->unique()->take(3)->values()->all();
        }
        $hasCustomBrandTheme = is_array(data_get($trial->preview_theme, 'custom_brand_theme'));
        $isCustomBrandTheme = $requestedTheme === 'my-brand' && $hasCustomBrandTheme;
        abort_unless($isCustomBrandTheme || in_array($requestedTheme, $allowedThemes, true), 403, 'Sign up to unlock more themes.');

        $syncSource = (string) $request->input('sync_source', 'manual_theme_change');

        // Applying My Brand Theme after Match to Logo may target the theme that
        // is already active. We must still persist the new logo/theme sync state;
        // otherwise the Match to Logo CTA reappears forever after refresh.
        if ($requestedTheme === $activeTheme && $syncSource !== 'theme_to_logo') {
            return response()->json(['status' => 'success', 'theme' => $requestedTheme, 'credits_spent' => 0, 'credit_balance' => $trialCredits->balance($trial)]);
        }

        $cost = $isCustomBrandTheme ? 0 : ThemePricingRegistry::cost($requestedTheme);
        if ($cost > 0) {
            $trialCredits->ensureCanSpend($trial, $cost, 'Theme change');
        }

        $themeSettings = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        $themeSettings['primary'] = $requestedTheme;
        $syncUpdate = [];
        if (filled($trial->logo_url)) {
            if ($syncSource === 'theme_to_logo') {
                if ($requestedTheme === 'my-brand' && is_array(data_get($themeSettings, 'custom_brand_theme'))) {
                    $customTheme = (array) data_get($themeSettings, 'custom_brand_theme');
                    $customTheme['source_logo_url'] = $trial->logo_url;
                    $themeSettings['custom_brand_theme'] = $customTheme;
                    $themeSettings['brand_palette'] = $customTheme['palette'] ?? data_get($themeSettings, 'brand_palette', []);
                    $themeSettings['brand_source'] = 'logo';
                }

                $syncUpdate = [
                    'logo_theme_sync_state' => 'synced',
                    'logo_theme_sync_source' => 'theme_to_logo',
                    'logo_theme_synced_theme' => $requestedTheme,
                ];
            } else {
                $syncUpdate = [
                    'logo_theme_sync_state' => 'theme_changed',
                    'logo_theme_sync_source' => 'manual_theme_change',
                    'logo_theme_synced_theme' => null,
                ];
            }
        }
        $trial->update(['preview_theme' => $themeSettings, 'last_saved_at' => now(), ...$syncUpdate]);
        $balance = $cost > 0
            ? $trialCredits->consume($trial, $cost, 'change_theme', ['theme' => $requestedTheme])
            : $trialCredits->balance($trial);

        return response()->json([
            'status' => 'success',
            'theme' => $requestedTheme,
            'credits_spent' => $cost,
            'credit_balance' => $balance,
        ]);
    }

    private function resolveTrialAccess(Request $request, Page $page): ?TrialGeneration
    {
        if ($request->user()) {
            return null;
        }

        $token = trim((string) $request->query('token', $request->input('token', '')));

        abort_if($token === '', 404);

        $trial = TrialGeneration::query()
            ->where('token', $token)
            ->where('page_id', $page->id)
            ->where('status', 'ready')
            ->whereNull('claimed_at')
            ->first();

        abort_unless($trial, 404);
        $expiresAt = filled($trial->email) ? $trial->created_at->copy()->addDays(30) : $trial->created_at->copy()->addHours(24);
        abort_if($expiresAt->isPast(), 410, 'This trial link has expired.');

        return $trial;
    }

    public function publish(Request $request, Page $page, PagePublisher $publisher, CreditService $credits)
    {
        $this->authorize('update', $page->website);

        $website = $page->website;

        $localizationQueued = app(MediaAssetLifecycleService::class)->queueWebsitePublish($website);
        if ($localizationQueued) {
            return response()->json([
                'status' => 'localizing',
                'message' => 'Securing your remote images locally before publishing.',
                'media_pack_status' => $website->mediaPack()->value('status') ?: 'queued',
                'retry_publish' => true,
            ], 202);
        }

        if (config('cosmic_media.localize_remote_images', false)) {
            $remainingRemoteUrls = app(MediaAssetSafetyService::class)->draftProviderUrls($website->fresh());
            if ($remainingRemoteUrls !== []) {
                return response()->json([
                    'status' => 'media_not_ready',
                    'message' => 'Some remote preview images are still being secured. Retry publishing in a moment.',
                    'remote_image_count' => count($remainingRemoteUrls),
                    'retry_publish' => true,
                ], 409);
            }
        }

        $themeKey = (string) data_get($website->theme_settings, 'primary', '');
        $publishedThemeKey = (string) data_get($website->published_theme_settings, 'primary', '');
        $themeCost = 0;
        $themeReference = null;

        $alreadyUnlocked = $themeKey !== '' && $request->user()->cosmicUnlocks()
            ->where('unlock_type', 'theme')
            ->where('unlock_key', $themeKey)
            ->exists();

        if ($themeKey !== '' && $themeKey !== $publishedThemeKey && ! $alreadyUnlocked) {
            $themeCost = ThemePricingRegistry::cost($themeKey);
            $themeReference = 'theme-publish-' . Str::uuid();
            $credits->consume(
                $request->user(),
                $themeCost,
                'Unlock theme on publish: ' . ThemePricingRegistry::get($themeKey)['label'],
                $website,
                $themeReference,
                ['theme' => $themeKey],
            );
        }

        try {
            $html = $publisher->publish($page, $website);

            DB::transaction(function () use ($page, $website, $html, $request, $themeKey, $themeCost) {
                $publishedAt = now();
                $page->published_blocks = $page->blocks ?? [];
                $page->published_page_style = $page->page_style;
                $page->published_html = $html;
                $page->status = 'published';
                $page->published_at ??= $publishedAt;
                $page->last_published_at = $publishedAt;
                $page->publish_error = null;
                $page->save();

                $website->published_theme_settings = $website->theme_settings;
                $website->published_global_header = $website->global_header;
                $website->published_global_footer = $website->global_footer;
                $website->save();

                if ($themeKey !== '' && $themeCost > 0) {
                    CosmicUnlock::firstOrCreate(
                        ['user_id' => $request->user()->id, 'unlock_type' => 'theme', 'unlock_key' => $themeKey],
                        ['credits_paid' => $themeCost],
                    );
                }
            });
        } catch (Throwable $exception) {
            if ($themeCost > 0) {
                $credits->refund($request->user(), $themeCost, 'Refund for failed theme publish', $website, $themeReference . '-refund');
            }

            report($exception);
            $page->publish_error = 'Publishing failed. Your previous live version is still available.';
            $page->save();

            return response()->json([
                'message' => $page->publish_error,
                'status' => $page->status,
                'credit_balance' => $credits->balance($request->user()),
            ], 502);
        }

        $previewService = app(PreviewDeploymentService::class);
        $previewUrl = $previewService->urlForPage($website->fresh(), $page->fresh());
        $previewDeploymentFailed = false;
        $previewDeploymentMessage = null;

        try {
            $previewService->deploy($website->fresh());
            $previewUrl = $previewService->urlForPage($website->fresh(), $page->fresh());
        } catch (Throwable $exception) {
            report($exception);
            $previewDeploymentFailed = true;
            $previewDeploymentMessage = 'The page was published, but the preview deployment failed. Your previous preview remains available.';
        }

        return response()->json([
            'status' => 'published',
            'published_at' => $page->published_at?->toISOString(),
            'last_published_at' => $page->last_published_at?->toISOString(),
            'preview_url' => $previewUrl,
            'preview_deployment_failed' => $previewDeploymentFailed,
            'preview_deployment_message' => $previewDeploymentMessage,
            'preview_deployed_at' => $website->fresh()->last_preview_deployed_at?->toISOString(),
            'credits_spent' => $themeCost,
            'credit_balance' => $credits->balance($request->user()),
        ]);
    }

    /**
     * I-save ang Global Header Shell gikan sa Axios call sa UI Matrix.
     */
    public function saveGlobalHeader(Request $request, Website $website, CreditService $credits)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'header_block' => 'nullable|array',
        ]);

        $countMenuItems = function (array $items) use (&$countMenuItems): int {
            return collect($items)->sum(function ($item) use (&$countMenuItems) {
                if (! is_array($item)) {
                    return 0;
                }

                $children = is_array($item['children'] ?? null) ? $item['children'] : [];

                return 1 + $countMenuItems($children);
            });
        };

        $oldMenuCount = $countMenuItems((array) data_get($website->global_header, 'menu', []));
        $newMenuCount = $countMenuItems((array) data_get($validated, 'header_block.menu', []));
        $addedItems = max(0, $newMenuCount - $oldMenuCount);
        $cost = $addedItems * ActionPricing::ADD_MENU_ITEM;
        $reference = 'menu-' . Str::uuid();

        if ($cost > 0) {
            $credits->consume(
                $request->user(),
                $cost,
                'Add ' . $addedItems . ' menu item' . ($addedItems === 1 ? '' : 's'),
                $website,
                $reference,
                ['added_items' => $addedItems],
            );
        }

        try {
            $website->update([
                'global_header' => $validated['header_block'] ?? null,
                'published_global_header' => $validated['header_block'] ?? null,
            ]);
        } catch (Throwable $exception) {
            if ($cost > 0) {
                $credits->refund($request->user(), $cost, 'Refund for failed menu update', $website, $reference . '-refund');
            }
            throw $exception;
        }

        return response()->json([
            'status' => 'success',
            'credits_spent' => $cost,
            'credit_balance' => $credits->balance($request->user()),
            'message' => $cost > 0
                ? "Global header saved. {$cost} Cosmic Credit" . ($cost === 1 ? '' : 's') . ' used for new menu items.'
                : 'Global header saved. Existing menu edits are free.',
        ]);
    }

    public function saveFooter(Request $request, Website $website)
    {
        $this->authorize('update', $website);

        $request->validate([
            'footer_block' => 'nullable|array'
        ]);

        // Footer changes follow the same explicit global-shell workflow as
        // headers: save now, then deploy only when Push live update is used.
        $website->update([
            'global_footer' => $request->input('footer_block'),
            'published_global_footer' => $request->input('footer_block'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Global footer saved. Push a live update when you are ready to publish it.'
        ]);
    }

    public function update(Request $request, Page $page)
    {
        return $this->updateBlocks($request, $page);
    }


    public function debugApiHandshake($page_id, $primaryColor = 'espresso') // I-add ang $primaryColor
    {
        $page = \App\Models\Page::find($page_id);

        if (!$page) {
            return response()->json(['html' => '<p>Page Node Error</p>'], 404);
        }

        // Ipasa ang $primaryColor ngadto sa compiler
        $htmlCompiledOutput = \App\Helpers\CmsHtmlCompiler::compile($page->blocks ?? [], $primaryColor);

        return response()->json([
            'status' => 'compiled_render_online',
            'slug' => $page->slug,
            'html' => $htmlCompiledOutput
        ], 200);
    }


    // public function debugHeaderHandshake($website_id)
    // {
    //     // Query tanan data sa websites table para makita nato ang structure
    //     $allWebsites = \App\Models\Website::all();
        
    //     // Pangitaa ang target website
    //     $website = $allWebsites->where('id', $website_id)->first();

    //     if (!$website) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Website not found',
    //             'all_websites_in_db' => $allWebsites
    //         ], 404);
    //     }

    //     // I-return ang tanan nga data para ma-debug
    //     return response()->json([
    //         'status' => 'debug_mode',
    //         'target_website_id' => $website_id,
    //         'global_header_raw' => $website->global_header, // Tan-awa kung unsa gyud ang sulod
    //         'parsed_data' => json_decode($website->global_header, true),
    //         'all_websites_table' => $allWebsites
    //     ], 200);
    // }


    public function debugHeaderHandshake($website_id)
    {
        $website = \App\Models\Website::find($website_id);
        if (!$website || !$website->global_header) {
            return response()->json(['html' => '<p>Header Data Empty</p>'], 404);
        }

        // Ang imong data kay flat JSON (diretso na ang type), dili na "blocks" array
        $headerData = json_decode($website->global_header, true);
        
        // I-pass ang tibuok array ngadto sa Compiler
        $compiledHtml = \App\Helpers\CmsHtmlCompiler::compile([$headerData]);

        return response()->json([
            'status' => 'compiled_render_online',
            'html' => $compiledHtml
        ], 200);
    }


    public function getPipelinePackage(Request $request)
    {
        $serverToken = $request->header('X-Bridge-Token');
        $website = \App\Models\Website::where('api_token', $serverToken)->first();
        if (!$website) return response()->json(['message' => 'Unauthorized'], 401);

        // Kausa ra ni i-decode para sa tibuok request
        $themeSettings = json_decode($website->theme_settings, true) ?? [];
        $primaryColor = $themeSettings['primary'] ?? 'espresso';

        // 1. Compile Header
        $headerBlocks = json_decode($website->global_header, true) ?? [];
        $compiledHeader = \App\Helpers\CmsHtmlCompiler::compile($headerBlocks['blocks'] ?? [], $primaryColor);

        // 2. Compile Pages
        $pages = $website->pages()->get();
        $pagePayload = [];

        foreach ($pages as $page) {
            $pagePayload[] = [
                'slug' => $page->slug,
                'html' => \App\Helpers\CmsHtmlCompiler::compile($page->blocks ?? [], $primaryColor)
            ];
        }

        return response()->json([
            'header' => $compiledHeader,
            'pages'  => $pagePayload
        ]);
    }


    /**
     * Gitigom nga HTML compiler output collection para sa automatic deployment handshake, Bai!
     */
    public function debugApiAllPages()
    {
        // Kuhaon ang tanang pages sa database
        $pages = \App\Models\Page::all();
        
        $payload = [];
        
        foreach ($pages as $page) {
            $slug = !empty($page->slug) ? $page->slug : 'home';
            
            // I-compile ang HTML blocks gamit ang atong Helper class
            $payload[$slug] = \App\Helpers\CmsHtmlCompiler::compile($page->blocks ?? []);
        }
        
        return response()->json([
            'status' => 'compiled_render_online',
            'pages' => $payload
        ], 200);
    }

    // Sa imong Controller update-theme function
    public function updateTheme(Request $request, Website $website, CreditService $credits)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'theme_settings' => ['nullable', 'array'],
            'theme_settings.primary' => ['required', 'string', 'max:120'],
        ]);

        $theme = $validated['theme_settings']['primary'];
        $alreadyUnlocked = $request->user()->cosmicUnlocks()
            ->where('unlock_type', 'theme')
            ->where('unlock_key', $theme)
            ->exists();
        $currentTheme = (string) data_get($website->theme_settings, 'primary', '');
        app(ThemePlanAccessService::class)->assertCanUse(
            $request->user(),
            $theme,
            $currentTheme,
            app(ThemePlanAccessService::class)->includedThemeKeyForWebsite($website),
        );
        $cost = ($alreadyUnlocked || $currentTheme === $theme) ? 0 : ThemePricingRegistry::cost($theme);
        $reference = 'theme-' . Str::uuid();

        if ($cost > 0) {
            $credits->consume(
                $request->user(),
                $cost,
                'Unlock theme: ' . ThemePricingRegistry::get($theme)['label'],
                $website,
                $reference,
                ['theme' => $theme],
            );
        }

        try {
            DB::transaction(function () use ($website, $validated, $request, $theme, $cost) {
                $website->update(['theme_settings' => $validated['theme_settings']]);

                if (! $request->user()->cosmicUnlocks()->where('unlock_type', 'theme')->where('unlock_key', $theme)->exists()) {
                    CosmicUnlock::firstOrCreate(
                        [
                            'user_id' => $request->user()->id,
                            'unlock_type' => 'theme',
                            'unlock_key' => $theme,
                        ],
                        ['credits_paid' => $cost],
                    );
                }
            });
        } catch (Throwable $exception) {
            if ($cost > 0) {
                $credits->refund($request->user(), $cost, 'Refund for failed theme unlock', $website, $reference . '-refund');
            }
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'unlocked' => $cost > 0,
            'credits_spent' => $cost,
            'credit_balance' => $credits->balance($request->user()),
        ]);
    }

}
