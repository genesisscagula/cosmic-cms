<?php

namespace App\AI\Layouts;

class LayoutEngine
{
    public static function random(string $folder, ?string $prompt = null): array
    {
        $layoutFiles = [
            'restaurant' => 'RestaurantLayouts.php',
            'coffee' => 'CoffeeLayouts.php',
            'bakery' => 'BakeryLayouts.php',
            'hotel' => 'HotelLayouts.php',
            'travel' => 'TravelLayouts.php',
            'automotive' => 'AutomotiveLayouts.php',
            'construction' => 'ConstructionLayouts.php',
            'electrician' => 'ElectricianLayouts.php',
            'plumbing' => 'PlumbingLayouts.php',
            'roofing' => 'RoofingLayouts.php',
            'dentist' => 'DentistLayouts.php',
            'medical' => 'MedicalLayouts.php',
            'fitness' => 'FitnessLayouts.php',
            'cleaning' => 'CleaningLayouts.php',
            'landscaping' => 'LandscapingLayouts.php',
            'lawyer' => 'LawyerLayouts.php',
            'finance' => 'FinanceLayouts.php',
            'real-estate' => 'RealEstateLayouts.php',
            'technology' => 'TechnologyLayouts.php',
            'education' => 'EducationLayouts.php',
            'salon' => 'SalonLayouts.php',
        ];

        $layoutFile = $layoutFiles[$folder] ?? 'DefaultLayouts.php';
        $layouts = require __DIR__.'/'.$layoutFile;

        // Video is an explicit visual request. Prefer only layout variations
        // that already include the dedicated video hero, so PHP keeps control
        // of the complete block order and does not inject a surprise section.
        if ($prompt !== null && preg_match('/\bvideo\b/i', $prompt) === 1) {
            $videoLayouts = array_values(array_filter(
                $layouts,
                static fn (array $layout): bool => in_array('hero_video_background', $layout, true)
            ));

            if ($videoLayouts !== []) {
                return $videoLayouts[array_rand($videoLayouts)];
            }
        }

        return $layouts[array_rand($layouts)];
    }

    /**
     * Pick a supported visual variation for one user-selected section purpose.
     * Structure remains deterministic in PHP; AI only fills the chosen schema.
     */
    public static function randomSection(string $category, ?string $prompt = null): string
    {
        // A video request is an explicit visual requirement, not a random variation.
        // Keep the rest of the selection deterministic and lightweight in PHP.
        if ($prompt !== null && preg_match('/\bvideo\b/i', $prompt) === 1) {
            return 'hero_video_background';
        }

        $sections = [
            'hero' => [
                'hero_headline',
                'hero_background_image',
                'hero_editorial_overlay',
                'hero_split_image',
                'hero_video_background',
                'hero_floating_cards',
            ],
            'services' => ['services_cards', 'services_bento'],
            'feature' => ['feature_image_left', 'feature_image_right'],
            'pricing' => ['pricing_cards'],
            'testimonials' => ['testimonials_carousel'],
            'process' => ['process_timeline'],
            'stats' => ['stats_modern'],
            'cta' => ['hero_centered_cta', 'image_cta_banner'],
            'contact' => ['contact_form_modern'],
        ];

        $candidates = $sections[$category] ?? [];

        if ($candidates === []) {
            throw new \InvalidArgumentException('Unsupported section category.');
        }

        return $candidates[array_rand($candidates)];
    }
}
