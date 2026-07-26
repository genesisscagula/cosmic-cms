<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\Page;
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
            'pages' => $website->pages()->latest()->get(),
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
        ]);

        $slug = \Illuminate\Support\Str::slug($request->title);

        $website->pages()->create([
            'title' => $request->title,
            'slug' => $slug,
            'status' => 'draft',
            // A new page starts as an honest blank canvas. The Builder guides
            // customers to add sections or generate a real layout with AI.
            'blocks' => []
        ]);

        return back();
    }

    public function builder(\App\Models\Page $page)
    {
        $this->authorize('view', $page->website);

        $website = $page->website;
        $websiteMessaging = $website->pages()
            ->get(['title', 'blocks'])
            ->flatMap(function (Page $websitePage) {
                return collect($websitePage->blocks ?? [])
                    ->map(function ($block) {
                        if (!is_array($block)) {
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

        $websiteContext = trim(sprintf(
            'Website: %s. %s',
            $website->name,
            $websiteMessaging !== '' ? "Existing website messaging: {$websiteMessaging}" : ''
        ));

        return \Inertia\Inertia::render('Websites/Builder', [
            'page' => $page,
            'website' => $website,
            'hasWebsiteContent' => $websiteMessaging !== '',
            'websiteContext' => $websiteContext,
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
        $this->authorize('update', $page->website);

        $validated = $request->validate([
            'blocks' => ['nullable', 'array'],
            'global_header' => ['nullable', 'array'],
            'global_footer' => ['nullable', 'array'],
            'theme_settings' => ['nullable', 'array'],
        ]);

        $website = $page->website;

        DB::transaction(function () use ($page, $website, $validated) {
            $page->blocks = $validated['blocks'] ?? [];
            // Saving starts a new editable draft. The published snapshot remains
            // untouched until the customer explicitly publishes again.
            $page->status = 'draft';
            $page->publish_error = null;
            $page->save();

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
