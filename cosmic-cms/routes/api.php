<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Website;
use App\Helpers\CmsHtmlCompiler;
use App\Services\PagePublisher;
use App\Http\Controllers\ContactSubmissionController;

Route::post('/v1/websites/{website}/contact-submissions', [ContactSubmissionController::class, 'storeFromConnector'])
    ->name('api.websites.contact-submissions.store');

if (! function_exists('cosmicWebsiteForBridge')) {
    function cosmicWebsiteForBridge(Request $request): Website|\Illuminate\Http\JsonResponse
    {
        $token = $request->header('X-Cosmic-Token');

        if (! $token) {
            return response()->json(['error' => 'Unauthorized. Missing API Token.'], 401);
        }

        $website = Website::where('api_token', $token)->first();

        return $website ?: response()->json(['error' => 'Invalid API Token.'], 401);
    }
}

// Kini nga endpoint ang tawgon sa Client Bridge ZIP gamit ang CURL
Route::get('/v1/sync', function (Request $request) {
    $website = cosmicWebsiteForBridge($request);

    if ($website instanceof \Illuminate\Http\JsonResponse) {
        return $website;
    }

    // 3. I-return ang tibuok pages ug blocks data sa user nga naka-published
    return response()->json([
        'status' => 'success',
        'website_name' => $website->name,
        'theme_settings' => $website->published_theme_settings ?? $website->theme_settings,
        'global_header' => $website->published_global_header ?? $website->global_header,
        'global_footer' => $website->published_global_footer ?? $website->global_footer,
        'pages' => $website->pages()
            ->where(function ($query) {
                $query->where('status', 'published')
                    ->orWhereNotNull('published_blocks');
            })
            ->get(['title', 'slug', 'blocks', 'published_blocks'])
            ->map(fn ($page) => [
                'title' => $page->title,
                'slug' => $page->slug,
                // Fallback preserves websites published before snapshot support existed.
                'blocks' => $page->published_blocks ?? $page->blocks,
            ]),
    ]);
});

// Static-site bridge: only exposes content from a successful publication.
Route::get('/v1/published-package', function (Request $request) {
    $website = cosmicWebsiteForBridge($request);

    if ($website instanceof \Illuminate\Http\JsonResponse) {
        return $website;
    }

    return response()->json(app(PagePublisher::class)->publishedPackage($website));
});
