<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Website;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\BlockController;

// Kini nga endpoint ang tawgon sa Client Bridge ZIP gamit ang CURL
Route::get('/v1/sync', function (Request $request) {
    // 1. Kuhaon ang Token gikan sa Request Header para sa authentication
    $token = $request->header('X-Cosmic-Token');

    if (!$token) {
        return response()->json(['error' => 'Unauthorized. Missing API Token.'], 401);
    }

    // 2. Pangitaon ang website nga naay saktong token sa database
    $website = Website::where('api_token', $token)->first();

    if (!$website) {
        return response()->json(['error' => 'Invalid API Token.'], 401);
    }

    // 3. I-return ang tibuok pages ug blocks data sa user nga naka-published
    return response()->json([
        'status' => 'success',
        'website_name' => $website->name,
        'theme_settings' => $website->theme_settings,
        'pages' => $website->pages()->where('status', 'published')->get(['title', 'slug', 'blocks'])
    ]);
});


Route::post('/upload-block-image', [ImageController::class, 'uploadImage']);

Route::post('/update-block-data', [ImageController::class, 'update']);
