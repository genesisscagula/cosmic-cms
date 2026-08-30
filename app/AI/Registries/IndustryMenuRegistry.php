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
            // Business & professional
            'accounting & bookkeeping', 'financial services', 'insurance', 'mortgage & lending' => 'finance',
            'advertising & marketing', 'business consulting', 'recruitment & staffing',
            'technology & it services', 'saas / software', 'web design & development',
            'cybersecurity', 'telecommunications' => 'technology',
            'architecture', 'architecture & design' => 'construction',
            'law firm' => 'lawyer',
            'property management' => 'real-estate',

            // Construction & home services
            'glass & aluminum installation', 'hvac / air conditioning', 'painting', 'carpentry',
            'flooring', 'handyman services', 'interior design', 'garage door services' => 'construction',
            'electrical services', 'solar installation', 'security systems' => 'electrician',
            'cleaning services', 'pest control' => 'cleaning',
            'pool services' => 'plumbing',

            // Health & wellness
            'veterinary clinic', 'pharmacy', 'optometry', 'chiropractic', 'physical therapy',
            'mental health & counseling', 'wellness center', 'home healthcare', 'senior care',
            'nutrition & dietetics', 'dermatology', 'aesthetic clinic' => 'medical',
            'dental clinic' => 'dentist',
            'medical clinic' => 'medical',

            // Food, hospitality & events
            'cafe / coffee shop', 'coffee shop' => 'coffee',
            'catering', 'food delivery' => 'restaurant',
            'hotel & resort', 'hotel and resort', 'resort', 'travel agency', 'tour operator',
            'event planning', 'wedding services', 'venue & events' => 'hotel',

            // Beauty, fitness & lifestyle
            'beauty salon', 'spa', 'barbershop', 'nail salon', 'cosmetics & skincare',
            'salon & beauty', 'salon and beauty' => 'salon',
            'fitness gym', 'personal training', 'yoga / pilates', 'martial arts', 'sports club', 'dance studio' => 'fitness',

            // Retail & ecommerce
            'ecommerce store', 'fashion & apparel', 'jewelry', 'electronics', 'gifts & crafts',
            'wholesale & distribution' => 'technology',
            'furniture', 'home & living' => 'construction',
            'grocery' => 'restaurant',
            'florist' => 'landscaping',
            'pet store' => 'medical',

            // Automotive & marine
            'automotive repair', 'car dealership', 'car wash', 'auto detailing', 'motorcycle services',
            'tire shop', 'towing services', 'marine engine repair', 'boat & yacht services' => 'automotive',

            // Education & training
            'school / academy', 'college / university', 'preschool / daycare', 'tutoring',
            'training center', 'online courses', 'language school', 'driving school' => 'education',

            // Industrial & logistics
            'manufacturing', 'engineering services', 'warehousing', 'equipment rental', 'industrial supplies' => 'construction',
            'logistics & freight', 'courier & delivery' => 'technology',
            'agriculture', 'farm & agribusiness' => 'landscaping',
            'food manufacturing' => 'restaurant',

            // Creative, media, community & organizations
            'photography', 'videography', 'graphic design', 'creative agency', 'printing services',
            'music & entertainment', 'content creator', 'media production', 'portfolio / personal brand' => 'technology',
            'nonprofit organization', 'community organization', 'religious organization',
            'professional association', 'government / public service', 'charity / foundation' => 'education',

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
