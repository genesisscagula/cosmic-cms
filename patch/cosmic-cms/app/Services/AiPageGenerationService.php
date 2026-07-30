<?php

namespace App\Services;

use App\AI\Generators\ContentGenerator;
use App\AI\Generators\ImageGenerator;
use App\AI\Layouts\LayoutEngine;

class AiPageGenerationService
{
    public function generatePage(string $prompt): array
    {
        $selection = $this->selectSections($prompt);

        return [
            'sections' => $selection['sections'],
            'image_folder' => $selection['image_folder'],
            'blocks' => $this->generateBlocks(
                $prompt,
                $selection['sections'],
                $selection['image_folder']
            ),
        ];
    }

    public function generateBlocks(string $prompt, array $sections, ?string $imageFolder = null): array
    {
        $generator = new ContentGenerator();
        $imageGenerator = new ImageGenerator();
        $resolvedImageFolder = $imageFolder ?: $this->resolveLayoutFolder($prompt);

        $content = $generator->generate($prompt, $sections);
        $blocks = $content['blocks'] ?? [];

        foreach ($blocks as &$block) {
            if (($block['type'] ?? null) === 'hero_video_background') {
                $block['poster_image_url'] = $imageGenerator->generate(
                    $resolvedImageFolder,
                    $block
                );

                continue;
            }

            if (! array_key_exists('image_url', $block)) {
                continue;
            }

            $block['image_url'] = $imageGenerator->generate(
                $resolvedImageFolder,
                $block
            );
        }

        unset($block);

        return $blocks;
    }

    public function selectSections(string $prompt): array
    {
        $imageFolder = $this->resolveLayoutFolder($prompt);

        return [
            'sections' => LayoutEngine::random($imageFolder, $prompt),
            'image_folder' => $imageFolder,
        ];
    }

    public function selectSection(string $category, string $prompt): array
    {
        return [
            'section' => LayoutEngine::randomSection($category, $prompt),
            'image_folder' => $this->resolveLayoutFolder($prompt),
        ];
    }

    public function resolveLayoutFolder(string $prompt): string
    {
        $normalizedPrompt = trim($prompt);
        $normalizedPrompt = function_exists('mb_strtolower')
            ? mb_strtolower($normalizedPrompt, 'UTF-8')
            : strtolower($normalizedPrompt);

        $industryKeywords = $this->industryKeywords();

        if (preg_match('/^industry:\s*([^\r\n.]+)/mi', $prompt, $matches) === 1) {
            $profileIndustry = trim($matches[1]);
            $profileIndustry = function_exists('mb_strtolower')
                ? mb_strtolower($profileIndustry, 'UTF-8')
                : strtolower($profileIndustry);

            foreach ($industryKeywords as $folder => $keywords) {
                if ($profileIndustry === $folder || in_array($profileIndustry, $keywords, true)) {
                    return $folder;
                }
            }
        }

        foreach ($industryKeywords as $folder => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalizedPrompt, $keyword)) {
                    return $folder;
                }
            }
        }

        return 'default';
    }

    private function industryKeywords(): array
    {
        return [
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
    }
}
