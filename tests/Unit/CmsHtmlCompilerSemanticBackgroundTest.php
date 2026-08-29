<?php

namespace Tests\Unit;

use App\Helpers\CmsHtmlCompiler;
use Tests\TestCase;

class CmsHtmlCompilerSemanticBackgroundTest extends TestCase
{
    public function test_auto_pattern_preserves_primary_white_and_surface_states_in_static_html(): void
    {
        $html = CmsHtmlCompiler::compile([
            ['type' => 'cta_glass_premium', 'theme' => 'auto'],
            ['type' => 'cta_glass_premium', 'theme' => 'auto'],
            ['type' => 'cta_glass_premium', 'theme' => 'auto'],
        ], 'ocean', ['page_style' => 'balanced']);

        preg_match_all("/<section[^>]*data-cosmic-resolved-theme='([^']+)'/i", $html, $matches);

        $this->assertSame(['primary', 'white', 'surface'], array_slice($matches[1], 0, 3));
        $this->assertStringContainsString('--cosmic-bg-white:#FEFEFD', $html);
        // #F1F5F9 is the canonical neutral surface shared by the Builder
        // preview shell and the static compiler fallback contract.
        $this->assertStringContainsString('--cosmic-bg-surface:#F1F5F9', $html);
    }

    public function test_legacy_gradient_cta_uses_plain_semantic_surface(): void
    {
        $html = CmsHtmlCompiler::compile([[
            'type' => 'cta_gradient_premium',
            'theme' => 'surface',
            'heading' => 'A practical next step',
        ]], 'ocean');

        $this->assertStringContainsString("data-cosmic-resolved-theme='surface'", $html);
        $this->assertStringContainsString('A practical next step', $html);
        $this->assertStringNotContainsString("style='background:linear-gradient", $html);
        $this->assertStringNotContainsString('radial-gradient(circle at 78% 18%', $html);
    }

    public function test_light_section_heading_rule_preserves_nested_contrast_text(): void
    {
        $html = CmsHtmlCompiler::compile([[ 
            'type' => 'about_mission_grid',
            'theme' => 'white',
        ]], 'ocean');

        $this->assertStringContainsString(":not(:where([class*='text-white'] *", $html);
        $this->assertStringContainsString("[class*='text-blue-50'] *", $html);
    }

    public function test_light_section_does_not_overwrite_highlight_card_foregrounds(): void
    {
        $html = CmsHtmlCompiler::compile([[
            'type' => 'about_mission_grid',
            'theme' => 'white',
            'vision_title' => 'Readable highlighted title',
        ]], 'midnight');

        $this->assertMatchesRegularExpression('/bg-\[#[0-9A-F]{6}\] text-slate-100/', $html);
        $this->assertStringContainsString('Readable highlighted title', $html);
        $this->assertStringContainsString("[class*='text-'][class*='-100'] *", $html);
        $this->assertStringContainsString("article:is([class~='text-white']", $html);
        $this->assertStringContainsString("[class~='text-blue-50']", $html);
        $this->assertStringContainsString('var(--cosmic-color-on-secondary,var(--cosmic-color-on-dark,#FFFFFF))', $html);
    }

    public function test_shared_builder_export_contract_repairs_legacy_highlight_card_foregrounds(): void
    {
        $css = (string) file_get_contents(resource_path('css/cosmic-render-contract.css'));
        $appCss = (string) file_get_contents(resource_path('css/app.css'));
        $exportCss = (string) file_get_contents(resource_path('css/export-tailwind.css'));

        $this->assertStringContainsString('[data-cosmic-spark="1"] article:is(', $css);
        $this->assertStringContainsString('[class~="text-blue-50"]', $css);
        $this->assertStringContainsString('--cosmic-color-on-secondary', $css);
        $this->assertStringContainsString('@import "./cosmic-render-contract.css";', $appCss);
        $this->assertStringContainsString('@import "./cosmic-render-contract.css";', $exportCss);
    }
}
