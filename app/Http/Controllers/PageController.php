<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\Page;
use App\Models\TrialGeneration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\PagePublisher;
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

    public function store(Request $request, Website $website)
    {
        $this->authorize('update', $website);

        $request->validate([
            'title' => 'required|string|max:255',
            'page_type' => 'nullable|in:standard,blog',
            'parent_id' => 'nullable|integer',
        ]);

        $slug = \Illuminate\Support\Str::slug($request->title);

        $pageType = $request->input('page_type', 'standard');

        $parent = null;
        if ($request->filled('parent_id')) {
            $parent = $website->pages()->with('parent')->findOrFail($request->integer('parent_id'));
            abort_if($parent->parent?->parent_id !== null, 422, 'Pages can only be nested three levels deep.');
        }

        DB::transaction(function () use ($website, $request, $slug, $pageType, $parent) {
            $page = $website->pages()->create([
                'title' => $request->title,
                'slug' => $slug,
                'parent_id' => $parent?->id,
                'sort_order' => ((int) $website->pages()->where('parent_id', $parent?->id)->max('sort_order')) + 1,
                'page_type' => $pageType,
                'status' => 'draft',
                // A Posts / updates page is a complete editorial starting
                // point: a themed top banner, a deliberately neutral blog
                // hub, and a few supporting sections. Real articles live in
                // blog_posts, not inside page JSON.
                'blocks' => $pageType === 'blog' ? $this->createBlogPageBlocks() : []
            ]);

            if ($pageType === 'blog') {
                $this->createStarterBlogPosts($website, $page);
            }
        });

        return back();
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
        return [
            ['type' => 'blog_mini_hero', 'theme' => 'primary'],
            ['type' => 'blog_hub', 'theme' => 'editorial', 'show_intro' => false],
            ['type' => 'newsletter_cta', 'theme' => 'editorial'],
            ['type' => 'latest_resources', 'theme' => 'editorial'],
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
        $websiteMessaging = $website->pages()
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

        return Inertia::render('Websites/Builder', [
            'page' => $page,
            'website' => $website,
            'websitePages' => $isTrialMode
                ? [[
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'parent_id' => $page->parent_id,
                ]]
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
            'trialMode' => $isTrialMode,
            'trialToken' => $trial?->token,
            'trialCapabilities' => [
                'canNavigateAway' => ! $isTrialMode,
                'canChangeTheme' => ! $isTrialMode,
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

    public function saveBuilder(Request $request, Page $page)
    {
        $trial = $this->resolveTrialAccess($request, $page);

        if ($trial === null) {
            $this->authorize('update', $page->website);
        }

        $rules = [
            'blocks' => ['nullable', 'array'],
        ];

        if ($trial === null) {
            $rules['global_header'] = ['nullable', 'array'];
            $rules['global_footer'] = ['nullable', 'array'];
            $rules['theme_settings'] = ['nullable', 'array'];
        }

        $validated = $request->validate($rules);
        $website = $page->website;

        DB::transaction(function () use ($page, $website, $validated, $trial) {
            $page->blocks = $validated['blocks'] ?? [];
            $page->status = 'draft';
            $page->publish_error = null;
            $page->save();

            if ($trial !== null) {
                $trial->update([
                    'generated_blocks' => $page->blocks,
                ]);

                return;
            }

            if (array_key_exists('global_header', $validated)) {
                $website->global_header = $validated['global_header'];
            }

            if (array_key_exists('global_footer', $validated)) {
                $website->global_footer = $validated['global_footer'];
            }

            if (array_key_exists('theme_settings', $validated)) {
                $website->theme_settings = $validated['theme_settings'];
            }

            $website->save();
        });

        return response()->json([
            'status' => 'success',
            'page_status' => 'draft',
            'message' => 'Draft saved successfully.',
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
        abort_if($trial->created_at->lt(now()->subHours(24)), 410, 'This trial link has expired.');

        return $trial;
    }

    public function publish(Request $request, Page $page, PagePublisher $publisher)
    {
        $this->authorize('update', $page->website);

        $website = $page->website;

        try {
            $html = $publisher->publish($page, $website);

            DB::transaction(function () use ($page, $website, $html) {
                $publishedAt = now();

                $page->published_blocks = $page->blocks ?? [];
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
            });
        } catch (Throwable $exception) {
            report($exception);

            $page->publish_error = 'Publishing failed. Your previous live version is still available.';
            $page->save();

            return response()->json([
                'message' => $page->publish_error,
                'status' => $page->status,
            ], 502);
        }

        return response()->json([
            'status' => 'published',
            'published_at' => $page->published_at?->toISOString(),
            'last_published_at' => $page->last_published_at?->toISOString(),
        ]);
    }

    /**
     * I-save ang Global Header Shell gikan sa Axios call sa UI Matrix.
     */
    public function saveGlobalHeader(Request $request, Website $website)
    {
        $this->authorize('update', $website);

        $request->validate([
            'header_block' => 'nullable|array'
        ]);

        // The global shell has its own explicit save action. Keep its
        // deployment snapshot aligned so the next manual Push live update
        // exports the header the customer just approved.
        $website->update([
            'global_header' => $request->input('header_block'),
            'published_global_header' => $request->input('header_block'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Global header saved. Push a live update when you are ready to publish it.'
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
    public function updateTheme(Request $request, Website $website) {
        $this->authorize('update', $website);

        $request->validate([
            'theme_settings' => ['nullable', 'array'],
        ]);

        $website->theme_settings = $request->input('theme_settings');
        $website->save();
        
        return response()->json(['success' => true]);
    }

}
