<?php

namespace Tests\Unit;

use App\Helpers\CmsHtmlCompiler;
use App\AI\Schemas\SchemaManager;
use Tests\TestCase;

class CmsHtmlCompilerTest extends TestCase
{
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

    public function test_fluid_local_h1_override_is_preserved_in_static_output(): void
    {
        $html = CmsHtmlCompiler::compile([[
            'type' => 'hero_headline',
            'heading' => 'A smaller fluid heading',
            'luna_typography_overrides' => [
                'h1_size' => 'clamp(2.7rem,5.4vw,5.175rem)',
            ],
        ]], 'midnight');

        $this->assertStringContainsString(
            '--cosmic-local-h1-size:clamp(2.7rem,5.4vw,5.175rem);',
            $html
        );
        $this->assertStringNotContainsString(
            '.cosmic-luna-design-host h1,.cosmic-luna-design-host h2,.cosmic-luna-design-host h3{font-size:min(var(--cosmic-local-h2-size',
            $html
        );
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

    public function test_overlay_header_has_no_divider_but_solid_header_keeps_one(): void
    {
        $overlay = CmsHtmlCompiler::compile([[
            'type' => 'glassmorphism_header',
            'overlay_header_on_banner' => true,
            'menu' => [],
        ]], 'emerald');
        $solid = CmsHtmlCompiler::compile([[
            'type' => 'glassmorphism_header',
            'overlay_header_on_banner' => false,
            'menu' => [],
        ]], 'emerald');

        $this->assertMatchesRegularExpression(
            "/<header[^>]+data-cosmic-overlay-header='true'[^>]+class='[^']*border-0[^']*'/",
            $overlay
        );
        $this->assertDoesNotMatchRegularExpression(
            "/<header[^>]+data-cosmic-overlay-header='true'[^>]+class='[^']*\\bborder-b\\b[^']*'/",
            $overlay
        );
        $this->assertMatchesRegularExpression(
            "/<header[^>]+data-cosmic-overlay-header='false'[^>]+class='[^']*\\bborder-b\\b[^']*'/",
            $solid
        );
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

        $this->assertStringContainsString('opacity:0.46;', $html);
    }

    public function test_legacy_xl_hero_height_normalizes_to_the_builder_large_height(): void
    {
        $html = CmsHtmlCompiler::compile([
            ['type' => 'hero_background_image', 'height' => 'xl'],
        ], 'emerald');

        $this->assertStringContainsString('min-h-[650px]', $html);
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

        $this->assertDoesNotMatchRegularExpression('/class=[\'\"][^\'\"]*\\bbg-primary\\b[^\'\"]*[\'\"]/', $html);
        $this->assertDoesNotMatchRegularExpression('/class=[\'\"][^\'\"]*\\bring-primary\\b[^\'\"]*[\'\"]/', $html);
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
        $activeBlockTypes = array_keys(SchemaManager::map());

        $this->assertCount(329, $activeBlockTypes);

        foreach ($activeBlockTypes as $type) {
            $html = CmsHtmlCompiler::compile([['type' => $type]], 'emerald');

            $this->assertNotSame('', $html, "{$type} must compile for the static site.");
        }
    }

    public function test_local_card_radius_override_is_emitted_for_static_preview_export_and_live(): void
    {
        foreach (['services_bento', 'pricing_cards', 'team_modern'] as $type) {
            $html = CmsHtmlCompiler::compile([[
                'type' => $type,
                'luna_design_overrides' => ['card_radius' => 16],
                'luna_component_overrides' => ['card_radius' => '32px'],
            ]], 'emerald');

            $this->assertStringContainsString("data-cosmic-component-overrides='1'", $html, $type);
            $this->assertStringContainsString('--cosmic-local-card-radius:32px;', $html, $type);
            $this->assertStringContainsString('--luna-card-radius:32px;', $html, $type);
            $this->assertStringNotContainsString('--luna-card-radius:16px;', $html, $type);
        }
    }

    public function test_dark_card_surface_emits_the_same_contrast_contract_for_static_output(): void
    {
        foreach (['services_bento', 'pricing_cards', 'team_modern'] as $type) {
            $html = CmsHtmlCompiler::compile([[
                'type' => $type,
                'luna_component_overrides' => ['card_surface' => 'dark'],
            ]], 'terracotta');

            $this->assertStringContainsString("data-cosmic-card-surface='dark'", $html, $type);
            $this->assertStringContainsString('--cosmic-local-card-bg:var(--cosmic-bg-primary-surface', $html, $type);
            $this->assertStringContainsString('--cosmic-local-card-heading:var(--cosmic-color-on-dark', $html, $type);
            $this->assertStringContainsString('--cosmic-local-card-text:color-mix(', $html, $type);
            $this->assertStringContainsString('--cosmic-local-card-border:color-mix(', $html, $type);
        }
    }

    public function test_primary_faq_uses_on_primary_copy_and_on_surface_card_copy(): void
    {
        foreach (['faq_accordion', 'faq_accordion_pro'] as $type) {
            $html = CmsHtmlCompiler::compile([[ 
                'type' => $type,
                'theme' => 'primary',
                'heading' => 'Primary FAQ heading',
                'text' => 'Primary FAQ introduction',
                'faqs' => [[
                    'question' => 'Surface question',
                    'answer' => 'Surface answer',
                ]],
            ]], 'emerald');

            $this->assertStringContainsString('text-white/75', $html, $type);
            $this->assertMatchesRegularExpression(
                "/<h2 class='[^']*text-white[^']*'>Primary FAQ heading<\\/h2>/",
                $html,
                $type
            );
            $this->assertMatchesRegularExpression(
                "/<summary class='[^']*text-slate-900[^']*'>/",
                $html,
                $type
            );
            $this->assertStringContainsString('bg-[#F8F8F7]', $html, $type);
        }
    }
}
