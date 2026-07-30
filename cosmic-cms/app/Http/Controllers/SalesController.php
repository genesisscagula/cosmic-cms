<?php

namespace App\Http\Controllers;

use App\Models\TrialGeneration;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(): Response
    {
        $demos = TrialGeneration::query()
            ->where('status', 'ready')
            ->whereNotNull('page_id')
            ->latest('updated_at')
            ->limit(24)
            ->get()
            ->map(fn (TrialGeneration $trial) => [
                'id' => $trial->id,
                'business_name' => $trial->business_name ?: 'Untitled demo',
                'industry' => $trial->industry ?: 'General Business',
                'location' => $trial->location ?: 'Location not specified',
                'theme' => data_get($trial->preview_theme, 'primary', 'midnight'),
                'blocks_count' => is_array($trial->generated_blocks)
                    ? count($trial->generated_blocks)
                    : 0,
                'updated_at' => $trial->updated_at?->diffForHumans(),
                'demo_url' => route('pages.builder', [
                    'page' => $trial->page_id,
                    'token' => $trial->token,
                ]),
            ])
            ->values();

        return Inertia::render('Sales/Index', [
            'demos' => $demos,
        ]);
    }
}
