<?php

namespace Tests\Unit;

use App\AI\Images\VisualQueryBuilder;
use App\Services\LunaSmartSparkEditingService;
use App\Services\LunaIntentGateway;
use App\Services\PageTemplateCatalog;
use App\Services\TemplateCompositionBalancer;
use App\Services\TemplateQualityAuditor;
use Tests\TestCase;

class LunaQualityContractsTest extends TestCase
{
    public function test_active_slider_item_limits_duplicate_value_matching_to_that_slide(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $block = [
            'type' => 'hero_slider_fade',
            'slides' => [
                ['heading' => 'A shared heading', 'text' => 'First'],
                ['heading' => 'A shared heading', 'text' => 'Second'],
            ],
        ];

        $paths = $service->resolveMatchedPaths($block, [
            'collectionKey' => 'slides',
            'itemIndex' => 1,
            'currentValue' => 'A shared heading',
            'type' => 'heading',
        ], 'Change this heading');

        $this->assertSame(['slides.1.heading'], $paths);
        $this->assertSame(
            'Done — I updated heading on Slide 2 only.',
            $service->verifiedTargetReply(['collectionKey' => 'slides', 'itemIndex' => 1, 'type' => 'heading'], 'Change this heading', 'complete')
        );
    }

    public function test_explicit_whole_collection_request_can_match_every_slide(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $block = [
            'type' => 'hero_slider_fade',
            'slides' => [
                ['heading' => 'A shared heading'],
                ['heading' => 'A shared heading'],
            ],
        ];

        $paths = $service->resolveMatchedPaths($block, [
            'collectionKey' => 'slides',
            'itemIndex' => 1,
            'currentValue' => 'A shared heading',
        ], 'Change every slide heading');

        $this->assertSame(['slides.0.heading', 'slides.1.heading'], $paths);
    }

    public function test_false_repeater_target_can_recover_a_unique_section_cta(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $block = [
            'type' => 'cta_image_split_premium',
            'button_label' => 'Book a Table',
            'button_url' => '/reservations',
            'items' => [
                ['title' => 'Plan Your Visit', 'text' => 'Choose a relaxed meal.'],
                ['title' => 'Bring the Experience Along', 'text' => 'Ask about catering.'],
            ],
        ];

        $paths = $service->resolveMatchedPaths($block, [
            'collectionKey' => 'items',
            'itemIndex' => 0,
            'currentValue' => 'Book a Table',
            'url' => '/reservations',
            'type' => 'button',
        ], 'Change this button');

        $this->assertSame(['button_label', 'button_url'], $paths);
    }

    public function test_false_repeater_target_never_falls_back_to_ambiguous_copy(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $block = [
            'type' => 'cards',
            'button_label' => 'Learn more',
            'items' => [
                ['title' => 'First', 'button_label' => 'View first'],
                ['title' => 'Second', 'button_label' => 'Learn more'],
            ],
        ];

        $paths = $service->resolveMatchedPaths($block, [
            'collectionKey' => 'items',
            'itemIndex' => 0,
            'currentValue' => 'Learn more',
            'type' => 'button',
        ], 'Change this button');

        $this->assertSame([], $paths);
    }

    public function test_concrete_can_you_theme_request_is_an_action(): void
    {
        $gateway = app(LunaIntentGateway::class);

        $this->assertSame('action', $gateway->route('Can you change the theme to charcoal?')['intent']);
        $this->assertSame('action', $gateway->route('Can you change the theme to emerald?')['intent']);
        $this->assertSame('chat', $gateway->route('What themes do you support?')['intent']);
    }

    public function test_standalone_named_theme_command_resolves_without_ai_planning(): void
    {
        $controller = app(\App\Http\Controllers\CustomSparkController::class);
        $method = new \ReflectionMethod($controller, 'lunaStandaloneNamedThemeKey');

        $this->assertSame('charcoal', $method->invoke($controller, 'Can you change the theme to charcoal?'));
        $this->assertSame('emerald', $method->invoke($controller, 'Please switch the website theme to emerald.'));
        $this->assertNull($method->invoke($controller, 'Change the theme to emerald and make the heading smaller.'));
    }

    public function test_restaurant_evening_template_has_a_balanced_image_content_rhythm(): void
    {
        $template = PageTemplateCatalog::find('restaurant-evening-story');
        $this->assertNotNull($template);

        $audit = app(TemplateQualityAuditor::class)->audit($template);

        $this->assertLessThanOrEqual(0.60, $audit['image_heavy_ratio']);
        $this->assertLessThanOrEqual(1, $audit['max_consecutive_image_heavy']);
        $this->assertGreaterThanOrEqual(4, $audit['content_mode_count']);
        $this->assertSame([], $audit['invalid_sections']);
    }

    public function test_image_only_compositions_are_flagged_for_rhythm_review(): void
    {
        $audit = app(TemplateQualityAuditor::class)->audit([
            'style' => ['cinematic', 'image-led'],
            'industry' => ['hospitality'],
            'sections' => [
                'hero_ken_burns_premium',
                'restaurant_signature_dishes_premium',
                'restaurant_story_menu_premium',
                'restaurant_atmosphere_gallery_premium',
                'testimonials_featured_story_premium',
                'restaurant_reservation_cta_premium',
            ],
        ]);

        $this->assertGreaterThan(0.60, $audit['image_heavy_ratio']);
        $this->assertGreaterThan(1, $audit['max_consecutive_image_heavy']);
        $this->assertNotEmpty($audit['composition_issues']);
    }

    public function test_registry_description_marks_non_obvious_image_rich_sparks_as_visual(): void
    {
        $auditor = app(TemplateQualityAuditor::class);

        $this->assertTrue($auditor->isImageHeavySection('brand_value_cards_premium'));
        $this->assertTrue($auditor->isImageHeavySection('testimonials_editorial_quotes_premium'));
        $this->assertTrue($auditor->isImageHeavySection('restaurant_reservation_cta_premium'));
    }

    public function test_catalog_balancer_breaks_visual_runs_without_replacing_the_hero(): void
    {
        $template = app(TemplateCompositionBalancer::class)->balance([
            'key' => 'synthetic-restaurant-run',
            'style' => ['cinematic', 'image-led'],
            'industry' => ['restaurant'],
            'sections' => [
                'hero_ken_burns_premium',
                'restaurant_story_menu_premium',
                'brand_value_cards_premium',
                'testimonials_editorial_quotes_premium',
                'restaurant_reservation_cta_premium',
            ],
        ]);

        $profile = app(TemplateQualityAuditor::class)->imageProfile($template['sections']);

        $this->assertSame('hero_ken_burns_premium', $template['sections'][0]);
        $this->assertLessThanOrEqual(1, $profile['max_consecutive']);
        $this->assertTrue($template['auto_balanced']);
        $this->assertNotEmpty($template['balanced_replacements']);
    }

    public function test_coffee_template_alternates_visual_and_non_visual_sections(): void
    {
        $template = PageTemplateCatalog::find('coffee-brand-atmosphere');
        $profile = app(TemplateQualityAuditor::class)->imageProfile($template['sections']);

        $this->assertLessThanOrEqual(0.60, $profile['ratio']);
        $this->assertLessThanOrEqual(1, $profile['max_consecutive']);
        $this->assertSame([
            'hero_ken_burns_premium',
            'services_minimal_luxury',
            'restaurant_story_menu_premium',
            'about_mission_grid',
            'gallery_image_rail_premium',
            'cta_gradient_premium',
        ], $template['sections']);
    }

    public function test_restaurant_visual_queries_change_subject_by_section_role(): void
    {
        $builder = app(VisualQueryBuilder::class);
        $prompt = 'Premium restaurant website for Milagrina in Ormoc City';

        $hero = $builder->build($prompt, ['heading' => 'Welcome'], 'hero_ken_burns_premium', 'restaurant');
        $menu = $builder->build($prompt, ['heading' => 'Our menu'], 'restaurant_story_menu_premium', 'restaurant');
        $location = $builder->build($prompt, ['heading' => 'Plan your visit'], 'location_photo_cards_premium', 'restaurant');

        $this->assertNotSame($hero, $menu);
        $this->assertNotSame($menu, $location);
        $this->assertStringContainsString('cuisine', $menu);
        $this->assertStringContainsString('storefront', $location);
    }
}
