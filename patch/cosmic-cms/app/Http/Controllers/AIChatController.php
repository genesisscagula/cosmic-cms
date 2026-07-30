<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// Ilisi ang 'AIController' ngadto sa 'AIChatController'
class AIChatController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate(['prompt' => 'required|string']);

        try {
            return response()->json([
                'type' => 'hero',
                'heading' => 'Design para sa ' . $request->prompt,
                'subheading' => 'Kini nga section gihimo gamit ang AI.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}