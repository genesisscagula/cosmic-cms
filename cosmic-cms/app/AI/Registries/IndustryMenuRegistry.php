<?php

namespace App\AI\Registries;

use Illuminate\Support\Str;

final class IndustryMenuRegistry
{
    /**
     * Return the default page structure for an AI-selected industry.
     *
     * Each entry is intentionally structured so future patches can add page
     * templates, AI prompts, icons, or access rules without changing callers.
     */
    public static function for(string $industry): array
    {
        $key = self::normalizeIndustry($industry);
        $pages = self::menus()[$key] ?? self::menus()['default'];

        return collect($pages)
            ->values()
            ->map(function (string $title, int $index): array {
                $isHome = $index === 0;

                return [
                    'title' => $title,
                    'slug' => $isHome ? 'home' : (Str::slug($title) ?: 'page-'.($index + 1)),
                    'is_home' => $isHome,
                    'sort_order' => $index + 1,
                    'page_type' => 'standard',
                ];
            })
            ->all();
    }

    public static function normalizeIndustry(string $industry): string
    {
        $normalized = Str::of($industry)->lower()->trim()->toString();

        return match ($normalized) {
            'coffee shop' => 'coffee',
            'dental clinic' => 'dentist',
            'medical clinic' => 'medical',
            'law firm' => 'lawyer',
            'hotel & resort', 'hotel and resort' => 'hotel',
            'salon & beauty', 'salon and beauty' => 'salon',
            default => Str::slug($normalized),
        };
    }

    private static function menus(): array
    {
        return [
            'construction' => ['Home', 'About', 'Services', 'Projects', 'Contact'],
            'restaurant' => ['Home', 'About', 'Menu', 'Gallery', 'Contact'],
            'coffee' => ['Home', 'About', 'Menu', 'Our Coffee', 'Contact'],
            'bakery' => ['Home', 'About', 'Products', 'Gallery', 'Contact'],
            'dentist' => ['Home', 'About', 'Services', 'Testimonials', 'Contact'],
            'medical' => ['Home', 'About', 'Services', 'Doctors', 'Contact'],
            'lawyer' => ['Home', 'About', 'Practice Areas', 'Team', 'Contact'],
            'fitness' => ['Home', 'About', 'Programs', 'Trainers', 'Contact'],
            'real-estate' => ['Home', 'Properties', 'About', 'Agents', 'Contact'],
            'hotel' => ['Home', 'Rooms', 'Amenities', 'Gallery', 'Contact'],
            'travel' => ['Home', 'Destinations', 'Packages', 'Gallery', 'Contact'],
            'technology' => ['Home', 'Solutions', 'About', 'Pricing', 'Contact'],
            'education' => ['Home', 'Courses', 'About', 'Admissions', 'Contact'],
            'finance' => ['Home', 'Services', 'About', 'Resources', 'Contact'],
            'electrician' => ['Home', 'Services', 'Projects', 'About', 'Contact'],
            'plumbing' => ['Home', 'Services', 'Emergency', 'About', 'Contact'],
            'cleaning' => ['Home', 'Services', 'Pricing', 'About', 'Contact'],
            'landscaping' => ['Home', 'Services', 'Projects', 'Gallery', 'Contact'],
            'automotive' => ['Home', 'Services', 'Inventory', 'About', 'Contact'],
            'salon' => ['Home', 'Services', 'Gallery', 'Pricing', 'Contact'],
            'roofing' => ['Home', 'Services', 'Projects', 'About', 'Contact'],
            'default' => ['Home', 'About', 'Services', 'Contact'],
        ];
    }
}
