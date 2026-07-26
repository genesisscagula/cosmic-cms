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

                // Video heroes use a poster image instead of the standard image_url
                // contract. Give them the same local image selection treatment.
                if (($block['type'] ?? null) === 'hero_video_background') {
                    $block['poster_image_url'] = $imageGenerator->generate(
                        $validated['image_folder'] ?? $content['image_folder'],
                        $block
                    );

                    continue;
                }

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
            'sections' => LayoutEngine::random($imageFolder, $validated['prompt']),
            'image_folder' => $imageFolder,
        ]);
    }

    public function selectSection(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:hero,services,feature,pricing,testimonials,process,stats,cta,contact'],
            'prompt' => ['required', 'string'],
        ]);

        $imageFolder = $this->resolveLayoutFolder($validated['prompt']);

        return response()->json([
            'section' => LayoutEngine::randomSection($validated['category'], $validated['prompt']),
            'image_folder' => $imageFolder,
        ]);
    }

    private function resolveLayoutFolder(string $prompt): string
    {
        $normalizedPrompt = trim($prompt);
        $normalizedPrompt = function_exists('mb_strtolower')
            ? mb_strtolower($normalizedPrompt, 'UTF-8')
            : strtolower($normalizedPrompt);

        $industryKeywords = [
            'bakery' => ['bakery', 'pastry', 'pastries', 'bread', 'cake', 'cakes', 'dessert', 'desserts'],
            'coffee' => ['coffee', 'coffee shop', 'cafe', 'café', 'espresso', 'roastery'],
            'hotel' => ['hotel', 'resort', 'accommodation', 'lodging', 'boutique hotel'],
            'travel' => ['travel', 'tour', 'tourism', 'vacation', 'holiday', 'destination'],
            'restaurant' => ['restaurant', 'dining', 'food', 'pizza', 'pasta', 'catering', 'bistro'],
            'dentist' => ['dentist', 'dental', 'orthodontist', 'orthodontic', 'teeth whitening'],
            'medical' => ['medical', 'healthcare', 'health care', 'clinic', 'doctor', 'physician', 'wellness center'],
            'fitness' => ['fitness', 'gym', 'personal trainer', 'workout', 'crossfit', 'yoga studio'],
            'cleaning' => ['cleaning', 'house cleaning', 'commercial cleaning', 'janitorial', 'maid service'],
            'landscaping' => ['landscaping', 'landscape', 'lawn care', 'garden design', 'tree service'],
            'lawyer' => ['lawyer', 'law firm', 'attorney', 'legal services', 'legal counsel'],
            'finance' => ['finance', 'financial advisor', 'accounting', 'accountant', 'bookkeeping', 'wealth management'],
            'real-estate' => ['real estate', 'realtor', 'property listing', 'property management', 'realty'],
            'technology' => ['technology', 'software', 'saas', 'tech startup', 'it services', 'web development'],
            'education' => ['education', 'school', 'academy', 'tutoring', 'training center', 'online course'],
            'salon' => ['salon', 'beauty', 'hair stylist', 'barber', 'spa', 'nail studio'],
            'roofing' => ['roofing', 'roofer', 'roof repair', 'roof replacement'],
            'electrician' => ['electrician', 'electrical', 'wiring', 'electric service'],
            'plumbing' => ['plumbing', 'plumber', 'drain cleaning', 'water heater', 'pipe repair'],
            'construction' => ['construction', 'contractor', 'home builder', 'renovation', 'remodeling', 'remodelling'],
            'automotive' => [
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
            ],
        ];

        foreach ($industryKeywords as $folder => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalizedPrompt, $keyword)) {
                    return $folder;
                }
            }
        }

        return 'default';
    }
}
