<?php

namespace Tests\Unit;

use App\Helpers\CmsHtmlCompiler;
use Tests\TestCase;

class PageBuilderTypographyPublishTest extends TestCase
{
    public function test_surface_selection_owns_export_semantic_theme(): void
    {
        foreach (['white', 'primary', 'slate'] as $surface) {
            $html = CmsHtmlCompiler::compile([[
                'type' => 'luna_custom_section',
                'theme' => 'primary',
                'section_surface' => $surface,
                'ai_flex' => ['source' => 'build_your_own'],
                'elements' => [['type' => 'row', 'children' => [
                    ['type' => 'column', 'style' => ['width' => 50], 'children' => [
                        ['type' => 'heading', 'text' => 'Surface heading'],
                    ]],
                    ['type' => 'column', 'style' => ['width' => 50], 'children' => []],
                ]]],
            ]], 'midnight');

            $this->assertStringContainsString("data-cosmic-resolved-theme='{$surface}'", $html);
            $this->assertStringContainsString("data-cosmic-lego-surface='{$surface}'", $html);
            $this->assertStringContainsString('box-sizing:border-box;width:100%!important;min-width:0;max-width:none', $html);
            $this->assertStringContainsString('aspect-ratio:var(--cosmic-lego-media-ratio,16/10)', $html);
            $this->assertStringContainsString('background:var(--cosmic-lego-button-bg)!important;color:var(--cosmic-lego-button-text)!important', $html);
            $this->assertStringContainsString(".cosmic-flex-heading:not([data-cosmic-style-mode='custom']){color:var(--cosmic-lego-heading,", $html);
        }
    }

    public function test_optional_typography_roles_compile_without_warnings(): void
    {
        foreach ([[], ['_cosmic_typography_role' => null], ['_cosmic_typography_role' => 'invalid'], ['_cosmic_typography_role' => 'h4']] as $metadata) {
            $html = CmsHtmlCompiler::compile([[
                'type' => 'luna_custom_section',
                'ai_flex' => ['source' => 'build_your_own'],
                'elements' => [[
                    'type' => 'row',
                    'children' => [[
                        'type' => 'column',
                        'children' => [
                            array_merge(['type' => 'heading', 'text' => 'Publish regression heading'], $metadata),
                            ['type' => 'text', 'text' => 'Publish regression body'],
                        ],
                    ]],
                ]],
            ]], 'midnight');

            $tag = ($metadata['_cosmic_typography_role'] ?? '') === 'h4' ? 'h4' : 'h2';
            $this->assertMatchesRegularExpression('/<'.$tag.'\b[^>]*>Publish regression heading<\/'.$tag.'>/', $html);
            $this->assertStringContainsString('Publish regression body', $html);
        }
    }
}
