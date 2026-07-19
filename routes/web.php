<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\PageController; // <--- KANI NGA LINYA ANG NA-MISSING, BAY!
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Website;
use App\Helpers\CmsHtmlCompiler;

// Welcome Page
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Siguroha nga WebsiteController ug dili function closure ang nagkupot niini, bay!
    Route::get('/dashboard', [WebsiteController::class, 'index'])->name('dashboard');
    Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
    Route::get('/download-bridge', [WebsiteController::class, 'downloadBridge'])->name('bridge.download');
    
    // Pages Management routes
    Route::get('/websites/{website}/pages', [PageController::class, 'index'])->name('pages.index');
    Route::post('/websites/{website}/pages', [PageController::class, 'store'])->name('pages.store');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Route para sa pag-view sa block builder interface
    Route::get('/pages/{page}/builder', [PageController::class, 'builder'])->name('pages.builder');

    // Route para sa pag-save sa gi-update nga blocks json packet
    Route::post('/pages/{page}/builder', [PageController::class, 'updateBlocks'])->name('pages.builder.update');

    // Siguroha nga kini ang naa sa web.php
    Route::post('/ai/generate', [\App\Http\Controllers\AIChatController::class, 'generate'])->middleware(['auth']);

    // Siguroha nga husto ang URL pattern ug nakatunong sa saktong controller method
    Route::post('/websites/{website}/global-header/save', [WebsiteController::class, 'saveHeader'])->name('websites.global-header.save');

    Route::post('/websites/{website}/global-footer/save', [WebsiteController::class, 'saveFooter'])->name('websites.global-footer.save');


    Route::get('/debug-db-all', [App\Http\Controllers\PageController::class, 'debugApiAllPages']);

});



// 1. ENDPOINT PARA SA MGA PAHIYAM (PAGES)
Route::get('/debug-db/{page_id}', function ($page_id, Request $request) {
    // Pangitaa ang page base sa ID
    $page = \DB::table('pages')->where('id', $page_id)->first();
    if (!$page) return response()->json(['error' => 'Page not found'], 404);

    // Kuhaa ang sekretong token sa website nga nakakonektar sa maong page
    $website = \DB::table('websites')->where('id', $page->website_id)->first();
    $serverToken = $request->header('X-Bridge-Token');

    // I-validate kung match ba ang token
    if (!$website || empty($serverToken) || $website->api_secret_key !== $serverToken) {
        return response()->json(['message' => 'Unauthorized Bridge Token, Bai!'], 401);
    }

    // Kung pasar ang token validation, i-execute ang naandan nga controller logic
    return app(\App\Http\Controllers\PageController::class)->debugApiHandshake($page_id);
});

Route::get('/debug-header/{website_id}', [App\Http\Controllers\PageController::class, 'debugHeaderHandshake']);

Route::get('/pipeline-compile-package', function (Request $request) {
    $serverToken = $request->header('X-Bridge-Token');
    $website = \DB::table('websites')->where('api_token', $serverToken)->first();
    if (!$website) return response()->json(['message' => 'Unauthorized'], 401);

    // FIX 1: I-decode ang theme settings aron naay sulod ang $settings
    $settings = json_decode($website->theme_settings, true) ?? [];
    $primaryColor = $settings['primary'] ?? 'forest'; // Gamita ang gikan sa DB, fallback sa 'forest'

    $headerData = json_decode($website->global_header, true);
    $footerData = json_decode($website->global_footer, true); 

    $primaryColor = $settings['primary'] ?? 'espresso';
    
    // I-pass isip array para ma-loop sa Compiler
    $compiledHeaderHtml = \App\Helpers\CmsHtmlCompiler::compile([$headerData], $primaryColor);
    $compiledFooterHtml = \App\Helpers\CmsHtmlCompiler::compile([$footerData], $primaryColor);
    
    $pages = \DB::table('pages')->where('website_id', $website->id)->get();
    $payload = [
        'global_header' => $compiledHeaderHtml,
        'global_footer' => $compiledFooterHtml,
        'pages' => []
    ];

    foreach ($pages as $page) {
        // I-pass ang $primaryColor sa debugApiHandshake (dapat updated na pud ni nga function)
        $renderData = app(\App\Http\Controllers\PageController::class)
            ->debugApiHandshake($page->id, $primaryColor) 
            ->getData(true);
            
        $payload['pages'][] = [
            'slug' => !empty($page->slug) ? $page->slug : 'index',
            'html' => $renderData['html'] ?? ''
        ];
    }

    return response()->json($payload);
});


Route::post('/websites/{website}/update-theme', [PageController::class, 'updateTheme'])
    ->name('websites.update-theme');


Route::get('/debug-db-all-websites', function () {
    $websites = \DB::table('websites')->get();
    
    // I-check nato ang raw string ug ang decoded result para sa usa ka website
    foreach ($websites as $website) {
        $raw = $website->theme_settings;
        $decoded = json_decode($raw, true);
        
        dump([
            'id' => $website->id,
            'raw_string' => $raw,
            'json_last_error' => json_last_error_msg(), // Makita kung ngano error
            'decoded_result' => $decoded
        ]);
    }
    dd("Done Debugging");
});

Route::get('/debug-db-all-blocks', function () {
    $blocks = \DB::table('pages')->get();
    return response()->json($blocks);
});


require __DIR__.'/auth.php';

