<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AiLibrarySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiLibrarySearchController extends Controller
{
    public function __invoke(Request $request, AiLibrarySearchService $search): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:templates,sparks'],
            'prompt' => ['required', 'string', 'min:2', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json([
            'type' => $validated['type'],
            'results' => $search->search(
                $validated['type'],
                $validated['prompt'],
                (int) ($validated['limit'] ?? 8)
            ),
        ]);
    }
}
