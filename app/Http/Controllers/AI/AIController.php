<?php

namespace App\Http\Controllers\AI;

use App\AI\Generators\ContentGenerator;
use App\AI\Generators\ImageGenerator;
use App\AI\Layouts\LayoutEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenAI\Exceptions\TransporterException;

class AIController extends Controller
{
    public function generateContent(Request $request)
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'string'],
            'image_folder' => ['nullable', 'string'],
        ]);

        $generator = new ContentGenerator();
        $imageGenerator = new ImageGenerator();

        try {
            $content = $generator->generate(
                $validated['prompt'],
                $validated['sections']
            );

            foreach ($content['blocks'] as &$block) {

                if (!isset($block['image_url'])) {
                    continue;
                }

                $block['image_url'] = $imageGenerator->generate(
                    $validated['image_folder'] ?? $content['image_folder'],
                    $block
                );
            }
        } catch (TransporterException $exception) {
            Log::warning('OpenAI content generation request failed', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Cosmic AI is temporarily unavailable. Please try again in a moment.',
            ], 503);
        }

        return response()->json($content);
    }

    public function selectSections(Request $request)
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string'],
        ]);

        $imageFolder = $this->resolveLayoutFolder($validated['prompt']);

        return response()->json([
            'sections' => LayoutEngine::random($imageFolder),
            'image_folder' => $imageFolder,
        ]);
    }

    private function resolveLayoutFolder(string $prompt): string
    {
        $normalizedPrompt = trim($prompt);
        $normalizedPrompt = function_exists('mb_strtolower')
            ? mb_strtolower($normalizedPrompt, 'UTF-8')
            : strtolower($normalizedPrompt);

        $restaurantKeywords = [
            'restaurant',
            'dining',
            'cafe',
            'café',
            'food',
            'pizza',
            'pasta',
            'catering',
            'bakery',
            'coffee shop',
        ];

        foreach ($restaurantKeywords as $keyword) {
            if (str_contains($normalizedPrompt, $keyword)) {
                return 'restaurant';
            }
        }

        $automotiveKeywords = [
            'automotive',
            'car dealership',
            'car dealer',
            'auto repair',
            'mechanic',
            'garage',
            'car service',
            'vehicle',
            'car wash',
            'detailing',
            'tire shop',
        ];

        foreach ($automotiveKeywords as $keyword) {
            if (str_contains($normalizedPrompt, $keyword)) {
                return 'automotive';
            }
        }

        return 'default';
    }
}
