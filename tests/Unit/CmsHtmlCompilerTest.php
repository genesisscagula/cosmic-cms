<?php

namespace Tests\Unit;

use App\Helpers\CmsHtmlCompiler;
use App\AI\Schemas\SchemaManager;
use Tests\TestCase;

class CmsHtmlCompilerTest extends TestCase
{
    private const ACTIVE_BLOCK_TYPES = [
        'contact_form_modern',
        'hero_headline',
        'hero_floating_cards',
        'hero_background_image',
        'hero_editorial_overlay',
        'hero_split_image',
        'hero_video_style',
        'hero_video_background',
        'image_cta_banner',
        'hero_centered_cta',
        'feature_image_left',
        'feature_image_right',
        'services_cards',
        'services_bento',
        'process_timeline',
        'testimonials_carousel',
        'pricing_cards',
        'stats_modern',
        'team_modern',
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

    public function test_hero_video_background_uses_a_poster_fallback_and_compiles_youtube_as_a_background_embed(): void
    {
        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'hero_video_background',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'poster_image_url' => '',
            ],
        ], 'emerald');

        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('https://cms.example.test/storage/cms-images/background/background-1.avif', $html);
    }

    public function test_hero_video_background_compiles_vimeo_as_a_background_embed(): void
    {
        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'hero_video_background',
                'video_url' => 'https://vimeo.com/76979871',
            ],
        ], 'emerald');

        $this->assertStringContainsString('https://player.vimeo.com/video/76979871', $html);
    }

    public function test_global_header_uses_an_uploaded_logo_image_when_available(): void
    {
        $logoUrl = 'storage/websites/1/logos/brand-mark.png';

        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'glassmorphism_header',
                'logo_text' => 'North Star Studio',
                'logo_image_url' => $logoUrl,
                'menu' => [],
            ],
        ], 'emerald');

        $this->assertStringContainsString('src=\'https://cms.example.test/storage/websites/1/logos/brand-mark.png\'', $html);
        $this->assertStringContainsString('alt=\'North Star Studio\'', $html);
    }

    public function test_blog_hub_uses_static_asset_urls_for_default_and_published_card_images(): void
    {
        $defaultHtml = CmsHtmlCompiler::compile([['type' => 'blog_hub']], 'emerald');
        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'blog_hub',
                'featured' => [
                    'image_url' => '/storage/cms-images/background/background-1.avif',
                ],
            ],
        ], 'emerald', [
            'blog_posts' => [[
                'title' => 'Published article',
                'category' => 'Updates',
                'excerpt' => 'A published card.',
                'image_url' => '/storage/cms-images/background/background-2.avif',
                'url' => 'journal/published-article',
            ]],
        ]);

        $this->assertStringContainsString('https://cms.example.test/storage/cms-images/background/background-1.avif', $defaultHtml);
        $this->assertStringContainsString('https://cms.example.test/storage/cms-images/background/background-2.avif', $html);
        $this->assertStringContainsString("href='journal/published-article'", $html);
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

    public function test_hero_split_image_uses_the_builder_image_url_contract(): void
    {
        $imageUrl = 'https://images.example.test/split-hero.jpg';

        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'hero_split_image',
                'image_url' => $imageUrl,
                'primary_label' => 'Start now',
                'secondary_label' => 'See services',
            ],
        ], 'emerald');

        $this->assertStringContainsString("background-image:url('{$imageUrl}')", $html);
        $this->assertStringContainsString('Start now', $html);
        $this->assertStringContainsString('See services', $html);
    }

    public function test_primary_hero_split_image_uses_a_white_primary_button(): void
    {
        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'hero_split_image',
                'resolvedTheme' => 'primary',
            ],
        ], 'midnight');

        $this->assertStringContainsString('bg-white text-slate-950', $html);
    }

    public function test_image_cta_banner_uses_the_builder_image_url_contract(): void
    {
        $imageUrl = 'https://images.example.test/cta-banner.jpg';

        $html = CmsHtmlCompiler::compile([
            [
                'type' => 'image_cta_banner',
                'image_url' => $imageUrl,
                'primary_label' => 'Book now',
                'secondary_label' => 'View menu',
            ],
        ], 'emerald');

        $this->assertStringContainsString("background-image:url('{$imageUrl}')", $html);
        $this->assertStringContainsString('Book now', $html);
        $this->assertStringContainsString('View menu', $html);
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

      public function test_team_modern_compiles_the_editable_member_contract(): void
      {
          $html = CmsHtmlCompiler::compile([[
              'type' => 'team_modern',
              'heading' => 'Meet our specialists',
              'members' => [
                  ['name' => 'Avery Stone', 'role' => 'Studio Director', 'bio' => 'Keeps every client project moving clearly.', 'image_url' => '/storage/cms-images/avatars/avatar-1.jpg'],
                  ['name' => 'Jordan Lee', 'role' => 'Project Lead', 'bio' => 'Keeps project details clear.', 'image_url' => '/storage/cms-images/avatars/avatar-2.jpg'],
                  ['name' => 'Taylor Brooks', 'role' => 'Creative Lead', 'bio' => 'Shapes polished digital experiences.', 'image_url' => '/storage/cms-images/avatars/avatar-3.jpg'],
                  ['name' => 'Casey Rivera', 'role' => 'Operations Manager', 'bio' => 'Keeps delivery moving smoothly.', 'image_url' => '/storage/cms-images/avatars/avatar-4.jpg'],
                  ['name' => 'Morgan Chen', 'role' => 'Strategy Lead', 'bio' => 'Connects goals to a useful plan.', 'image_url' => '/storage/cms-images/avatars/avatar-5.jpg'],
              ],
          ]], 'emerald');

          $this->assertStringContainsString('Meet our specialists', $html);
          $this->assertStringContainsString('Avery Stone', $html);
          $this->assertStringContainsString('Morgan Chen', $html);
          $this->assertStringContainsString('https://cms.example.test/storage/cms-images/avatars/avatar-1.jpg', $html);
          $this->assertStringContainsString("</div><div class='grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4'>", $html);
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
