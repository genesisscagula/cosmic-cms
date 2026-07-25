<?php

namespace App\AI\Schemas;

class SchemaManager
{
    public static function map(): array
    {
        return [

            'hero_headline' => 'heroHeadlineSchema',

            'hero_background_image' => 'heroBackgroundImageSchema',

            'hero_editorial_overlay' => 'heroEditorialOverlaySchema',

            'feature_image_left' => 'featureImageLeftSchema',

            'feature_image_right' => 'featureImageRightSchema',

            'services_cards' => 'servicesCardsSchema',

            'services_bento' => 'servicesBentoSchema',

            'process_timeline' => 'processTimelineSchema',

            'stats_modern' => 'statsModernSchema',

            'testimonials_carousel' => 'testimonialsSchema',

            'hero_centered_cta' => 'heroCtaSchema',

            'pricing_cards' => 'pricingCardsSchema',

        ];
    }
}
