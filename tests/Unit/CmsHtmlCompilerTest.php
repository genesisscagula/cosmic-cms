<?php

namespace Tests\Unit;

use App\Helpers\CmsHtmlCompiler;
use App\AI\Schemas\SchemaManager;
use Tests\TestCase;

class CmsHtmlCompilerTest extends TestCase
{
    private const ACTIVE_BLOCK_TYPES = [
        'hero_headline',
        'hero_background_image',
        'hero_editorial_overlay',
        'hero_centered_cta',
        'feature_image_left',
        'feature_image_right',
        'services_cards',
        'services_bento',
        'process_timeline',
        'testimonials_carousel',
        'pricing_cards',
        'stats_modern',
    ];
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.cosmic.asset_base_url', 'https://cms.example.test');
    }
    public function test_hero_background_image_uses_the_builder_image_url_contract(): void
    {
        $imageUrl = 'https://images.example.test/restaurant-hero.jpg';

        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'hero_background_image',
                'image_url' => $imageUrl,
                'heading' => 'Restaurant hero',
            ],
        ], 'emerald');

        $this->assertStringContainsString("background-image:url('{$imageUrl}')", $html);
    }

    public function test_hero_editorial_overlay_uses_the_builder_image_url_contract(): void
    {
        $imageUrl = 'https://images.example.test/editorial-hero.jpg';

        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'hero_editorial_overlay',
                'image_url' => $imageUrl,
                'primary_label' => 'Start',
                'secondary_label' => 'Learn more',
            ],
        ], 'violet');

        $this->assertStringContainsString("background-image:url('{$imageUrl}')", $html);
        $this->assertStringContainsString('Start', $html);
        $this->assertStringContainsString('Learn more', $html);
    }

    public function test_hero_background_image_keeps_the_legacy_background_image_fallback(): void
    {
        $imageUrl = 'https://images.example.test/legacy-hero.jpg';

        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'hero_background_image',
                'backgroundImage' => $imageUrl,
            ],
        ], 'emerald');

        $this->assertStringContainsString("background-image:url('{$imageUrl}')", $html);
    }

    public function test_relative_storage_images_use_the_cms_asset_host_in_static_html(): void
    {
        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'hero_background_image',
                'image_url' => '/storage/websites/1/hero.jpg',
            ],
        ], 'emerald');

        $this->assertStringContainsString(
            "background-image:url('https://cms.example.test/storage/websites/1/hero.jpg')",
            $html
        );
    }

    public function test_hero_background_image_uses_the_same_overlay_strength_as_the_builder(): void
    {
        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'hero_background_image',
                'overlayOpacity' => 50,
            ],
        ], 'emerald');

        $this->assertStringContainsString("style='opacity:0.83333333333333;'", $html);
    }

    public function test_legacy_xl_hero_height_matches_the_builder_height(): void
    {
        $html = CmsHtmlCompiler::compile([
            ['type' => 'hero_background_image', 'height' => 'xl'],
        ], 'emerald');

        $this->assertStringContainsString('min-h-[90vh]', $html);
    }

    public function test_featured_pricing_cards_use_a_real_theme_badge_class(): void
    {
        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'pricing_cards',
                'plans' => [[
                    'featured' => true,
                    'badge' => 'Popular',
                    'title' => 'Plan',
                    'price' => '$20',
                    'period' => '/month',
                    'description' => 'A plan.',
                    'button_label' => 'Choose',
                    'button_url' => '#',
                    'features' => [['text' => 'Feature']],
                ]],
            ],
        ], 'emerald');

        $this->assertStringNotContainsString('bg-primary', $html);
        $this->assertStringNotContainsString('ring-primary', $html);
        $this->assertStringContainsString('bg-[#0B5D4B]', $html);
        $this->assertStringContainsString('inline-flex whitespace-nowrap rounded-full', $html);
        $this->assertStringContainsString('p-7 lg:p-8', $html);
    }

    public function test_every_selectable_theme_keeps_its_own_static_compiler_family(): void
    {
        $html = CmsHtmlCompiler::compile([
            ['type' => 'hero_centered_cta', 'heading' => 'Slate test'],
        ], 'slate');

        $this->assertStringContainsString('bg-[#475569]', $html);
        $this->assertStringNotContainsString('bg-[#A16207]', $html);
    }

    public function test_every_active_builder_block_has_ai_and_compiler_coverage(): void
    {
        $this->assertEqualsCanonicalizing(self::ACTIVE_BLOCK_TYPES, array_keys(SchemaManager::map()));

        foreach (self::ACTIVE_BLOCK_TYPES as $type) {
            $html = CmsHtmlCompiler::compile([['type' => $type]], 'emerald');

            $this->assertNotSame('', $html, "{$type} must compile for the static site.");
        }
    }
}
