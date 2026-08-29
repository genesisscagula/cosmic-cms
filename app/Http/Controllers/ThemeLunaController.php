<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\ThemePopupLunaService;
use Illuminate\Http\Request;

final class ThemeLunaController extends Controller
{
    public function intent(Request $request, Website $website, ThemePopupLunaService $luna)
    {
        $this->authorize('update', $website);
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'theme_ids' => ['nullable', 'array', 'max:100'],
            'theme_ids.*' => ['string', 'max:80'],
        ]);

        return response()->json($luna->classify(
            (string) $validated['prompt'],
            (array) ($validated['theme_ids'] ?? []),
        ));
    }

    public function generate(Request $request, Website $website, ThemePopupLunaService $luna)
    {
        $this->authorize('update', $website);
        $validated = $request->validate([
            'direction' => ['required', 'string', 'max:2000'],
            'seed_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_family_schema' => ['required', 'array'],
            'base_theme' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json($luna->generate(
            (string) $validated['direction'],
            $validated['seed_color'] ?? null,
            (array) $validated['color_family_schema'],
            (string) ($validated['base_theme'] ?? 'midnight'),
        ));
    }
}
