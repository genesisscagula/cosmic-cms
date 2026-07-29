<?php

use App\Helpers\CmsHtmlCompiler;
use App\Http\Controllers\AI\AIController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ContactSubmissionController;
use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\TrialGenerationController;
use App\Models\Page;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/start', [TrialGenerationController::class, 'create'])->name('start');
Route::post('/start', [TrialGenerationController::class, 'store'])->middleware('throttle:6,1')->name('trial-generations.store');
Route::post('/start/{trial:token}/plan', [TrialGenerationController::class, 'selectPlan'])
    ->middleware('throttle:12,1')
    ->name('trial-generations.plan.select');


// Token-aware Builder routes. Signed-in users keep normal policy checks;
// logged-out visitors need a valid token that belongs to the requested page.
Route::get('/pages/{page}/builder', [PageController::class, 'builder'])->name('pages.builder');
Route::post('/pages/{page}/builder/save', [PageController::class, 'saveBuilder'])
    ->middleware('throttle:60,1')
    ->name('pages.builder.save');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [WebsiteController::class, 'index'])->name('dashboard');
    Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
    Route::delete('/websites/{website}', [WebsiteController::class, 'destroy'])->name('websites.destroy');
    Route::get('/websites/{website}/deployment-connector', [WebsiteController::class, 'downloadDeploymentConnector'])->name('websites.deployment-connector.download');
    Route::post('/websites/{website}/deployment-connector/verify', [WebsiteController::class, 'verifyDeploymentConnector'])->name('websites.deployment-connector.verify');
    Route::post('/websites/{website}/deployment-connector/push', [WebsiteController::class, 'pushLiveUpdate'])->name('websites.deployment-connector.push');
    Route::put('/websites/{website}/settings', [WebsiteController::class, 'updateSettings'])->name('websites.settings.update');
    Route::put('/websites/{website}/profile', [WebsiteController::class, 'updateProfile'])->name('websites.profile.update');
    Route::get('/download-bridge', [WebsiteController::class, 'downloadBridge'])->name('bridge.download');

    Route::get('/websites/{website}/pages', [PageController::class, 'index'])->name('pages.index');
    Route::get('/websites/{website}/inquiries', [ContactSubmissionController::class, 'index'])->name('websites.inquiries.index');
    Route::patch('/websites/{website}/inquiries/{submission}', [ContactSubmissionController::class, 'update'])->name('websites.inquiries.update');
    Route::post('/websites/{website}/pages', [PageController::class, 'store'])->name('pages.store');
    Route::delete('/websites/{website}/pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');

    // The legacy endpoint remains for compatibility with older clients.
    Route::post('/pages/{page}/builder', [PageController::class, 'updateBlocks'])->name('pages.builder.update');
    Route::post('/pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
    Route::post('/websites/{website}/pages/{page}/blog-posts', [BlogPostController::class, 'store'])->name('blog-posts.store');
    Route::put('/websites/{website}/pages/{page}/blog-posts/{blogPost}', [BlogPostController::class, 'update'])->name('blog-posts.update');
    Route::delete('/websites/{website}/pages/{page}/blog-posts/{blogPost}', [BlogPostController::class, 'destroy'])->name('blog-posts.destroy');

    Route::post('/websites/{website}/global-header/save', [PageController::class, 'saveGlobalHeader'])->name('websites.global-header.save');
    Route::post('/websites/{website}/global-footer/save', [WebsiteController::class, 'saveFooter'])->name('websites.global-footer.save');
    Route::post('/websites/{website}/update-theme', [PageController::class, 'updateTheme'])->name('websites.update-theme');

    Route::post('/ai/generate', [\App\Http\Controllers\AIChatController::class, 'generate'])->name('ai.generate');
    Route::post('/ai/select-sections', [AIController::class, 'selectSections'])->name('ai.select-sections');
    Route::post('/ai/select-section', [AIController::class, 'selectSection'])->name('ai.select-section');
    Route::post('/ai/generate-content', [AIController::class, 'generateContent'])->name('ai.generate-content');

    // Keep the existing browser-session image URLs while protecting the writes.
    Route::post('/api/upload-block-image', [ImageController::class, 'uploadImage']);
    Route::post('/api/upload-logo', [ImageController::class, 'uploadLogo'])->name('websites.logo.upload');
    Route::post('/api/update-block-data', [ImageController::class, 'update']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
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
