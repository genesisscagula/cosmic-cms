<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\Page;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index(Website $website)
    {
        if ($website->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        return \Inertia\Inertia::render('Websites/Index', [
            'website' => $website,
            'pages' => $website->pages()->latest()->get(),
            // DIRETSO KORREKTE HANDSHAKE PACKET NGADTO SA REACT
            'globalHeaderBlock' => $website->global_header 
        ]);
    }

    public function store(Request $request, Website $website)
    {
        if ($website->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $slug = \Illuminate\Support\Str::slug($request->title);

        $dummyBlocks = [
            [
                'type' => 'hero',
                'heading' => 'Welcome to ' . $request->title,
                'subheading' => 'Custom crafted solutions via Cosmic CMS for ' . $website->name,
                'bg_color' => '#1e1b4b'
            ],
            [
                'type' => 'content',
                'text' => 'Kani nga content gikan ni sa imong Headless CMS database. Dynamic kini nga gi-compile ug gi-sopsop pinaagi sa JSON API bridge handshake.'
            ]
        ];

        $website->pages()->create([
            'title' => $request->title,
            'slug' => $slug,
            'status' => 'published',
            'blocks' => $dummyBlocks
        ]);

        return back();
    }

    public function builder(\App\Models\Page $page)
    {
        if ($page->website->user_id !== auth()->id()) {
            abort(403);
        }

        return \Inertia\Inertia::render('Websites/Builder', [
            'page' => $page,
            'website' => $page->website,
        ]);
    }

    public function updateBlocks(\Illuminate\Http\Request $request, \App\Models\Page $page)
    {
        if ($page->website->user_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'blocks' => 'required|array',
            'global_header' => 'nullable|array' // I-validate ang global_header
        ]);

        // 1. I-update ang blocks sa page
        $page->update(['blocks' => $request->blocks]);

        // 2. I-update ang global_header sa website table
        $page->website->update(['global_header' => $request->global_header]);

        $liveDomain = rtrim($page->website->domain, '/'); 

        if (!empty($liveDomain)) {
            $ch = curl_init($liveDomain . '/index.php?webhook=true');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        }

        return back();
    }

    /**
     * I-save ang Global Header Shell gikan sa Axios call sa UI Matrix.
     */
    public function saveGlobalHeader(Request $request, $websiteId)
    {
        $website = Website::findOrFail($websiteId);

        if ($website->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'header_block' => 'nullable|array'
        ]);

        // Gi-save ang block layout properties ngadto sa Database matrix
        $website->update([
            'global_header' => $request->input('header_block')
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Global Header configuration synchronized completely, Bai!'
        ]);
    }


    public function saveFooter(Request $request, $websiteId)
    {
        $website = Website::findOrFail($websiteId);

        if ($website->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'footer_block' => 'nullable|array'
        ]);

        // I-update ang global_footer column sa database matrix
        $website->update([
            'global_footer' => $request->input('footer_block')
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Global Footer configuration synchronized completely, Bai!'
        ]);
    }

    public function update(Request $request, Page $page)
    {

        dd($request->all());

        // 1. Siguraduhon nato nga limpyo ug validated ang arrays nga gikan sa useForm sa React
        $request->validate([
            'blocks' => 'nullable|array',
            'global_header' => 'nullable|array'
        ]);

        // 2. I-update ang inner layout blocks sa database page column (Naka cast ni as array/json sa Model)
        $page->update([
            'blocks' => $request->blocks
        ]);

        // 3. I-update ang Global Header diretso sa website configuration data packet
        if ($request->has('global_header')) {
            $page->website->update([
                'global_header' => $request->global_header
            ]);
        }

        // 4. Trigger Webhook para sa imuhang live raw HTML/CSS/JS site para mo pull sa pinaka-fresh nga data
        $liveDomain = rtrim($page->website->domain, '/'); 
        if (!empty($liveDomain)) {
            $ch = curl_init($liveDomain . '/index.php?webhook=true');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        }

        // 5. I-redirect balik sa active workspace react stage
        return back();
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
    public function updateTheme(Request $request, $id) {
        $website = Website::find($id);
        // Direkta na i-save ang array, ang Laravel/Model ang bahala sa JSON
        $website->theme_settings = $request->input('theme_settings');
        $website->save();
        
        return response()->json(['success' => true]);
    }

}