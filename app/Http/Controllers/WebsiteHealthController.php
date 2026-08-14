<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\WebsiteHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteHealthController extends Controller
{
    public function show(Request $request, Website $website, WebsiteHealthService $health): JsonResponse
    {
        $this->authorize('view', $website);

        $publishingPageId = $request->integer('page_id');
        if ($publishingPageId > 0) {
            abort_unless($website->pages()->whereKey($publishingPageId)->exists(), 404);
        }

        return response()->json([
            'website' => [
                'id' => $website->id,
                'name' => $website->name ?: 'Untitled Website',
                'domain' => $website->domain ?: null,
            ],
            'health' => $health->scan($website, $publishingPageId > 0 ? ['publishing_page_id' => $publishingPageId] : []),
        ]);
    }
}
