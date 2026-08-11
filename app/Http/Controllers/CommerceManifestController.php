<?php

namespace App\Http\Controllers;

use App\Models\WebsiteCommerceSetting;
use App\Services\CommerceCapabilityService;
use Illuminate\Http\JsonResponse;

class CommerceManifestController extends Controller
{
    public function show(string $publicKey, CommerceCapabilityService $commerce): JsonResponse
    {
        $settings = WebsiteCommerceSetting::query()
            ->where('public_key', $publicKey)
            ->with('website.user')
            ->firstOrFail();

        return response()->json($commerce->manifest($settings->website))
            ->header('Cache-Control', 'no-store, private');
    }
}
