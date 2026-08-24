<?php

namespace Tests\Unit;

use App\Helpers\CmsHtmlCompiler;
use App\Http\Controllers\CustomSparkController;
use App\Services\ColorContrastGuard;
use App\Services\MyBrandThemeService;
use App\Services\ThemeColorResolver;
use Tests\TestCase;

class ThemeColorResolverTest extends TestCase
{
    public function test_all_registered_families_normalize_to_the_complete_readable_contract(): void
    {
        $catalog = json_decode((string) file_get_contents(resource_path('theme/theme-families.json')), true);
        $resolver = app(ThemeColorResolver::class);
        $contrast = app(ColorContrastGuard::class);

        foreach (array_keys((array) ($catalog['families'] ?? [])) as $family) {
            $palette = $resolver->resolve($family);
            $this->assertSame($family, $palette['family'], "Family identity drifted for {$family}.");
            foreach (['primary', 'secondary', 'accent', 'surface', 'surface_alt', 'heading', 'body', 'muted', 'on_primary', 'on_secondary', 'on_accent', 'on_surface', 'button_primary', 'button_text', 'button_secondary', 'button_secondary_text'] as $role) {
                $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $palette[$role], "Invalid {$role} for {$family}.");
            }
            foreach ([
                ['on_primary', 'primary'], ['on_secondary', 'secondary'], ['on_accent', 'accent'],
                ['on_surface', 'surface'], ['heading', 'surface'], ['body', 'surface'], ['muted', 'surface'],
                ['button_text', 'button_primary'], ['button_secondary_text', 'button_secondary'],
            ] as [$foreground, $background]) {
                $this->assertGreaterThanOrEqual(
                    4.5,
                    $contrast->contrastRatio($palette[$foreground], $palette[$background]),
                    "Unsafe {$foreground}/{$background} pair for {$family}.",
                );
            }
        }
    }

    public function test_legacy_theme_aliases_resolve_through_the_same_semantic_contract(): void
    {
        $resolver = app(ThemeColorResolver::class);

        foreach (['light', 'soft', 'sky', 'cream', 'slate-light', 'slate-950'] as $alias) {
            $palette = $resolver->resolve($alias);
            $this->assertSame($alias, $palette['family']);
            $this->assertArrayHasKey('on_primary', $palette);
            $this->assertArrayHasKey('button_secondary_text', $palette);
            $this->assertArrayHasKey('surface_alt', $palette);
        }
    }

    public function test_named_family_keeps_identity_and_derives_complete_semantic_roles(): void
    {
        $palette = app(ThemeColorResolver::class)->resolve('ocean');

        $this->assertSame('#24598F', $palette['primary']);
        $this->assertSame('#2C6AA8', $palette['brand_surface']);
        $this->assertSame('#38BDF8', $palette['accent']);
        $this->assertSame('#FEFEFD', $palette['surface']);
        $this->assertSame($palette['primary_hover'], $palette['primaryHover']);
        $this->assertArrayHasKey('on_primary', $palette);
        $this->assertArrayHasKey('button_secondary_text', $palette);
        $this->assertArrayHasKey('gradient', $palette);
    }

    public function test_custom_hex_is_preserved_and_every_critical_pair_is_readable(): void
    {
        $palette = app(ThemeColorResolver::class)->resolve('#3368A0');
        $contrast = app(ColorContrastGuard::class);

        $this->assertSame('#3368A0', $palette['primary']);
        $this->assertGreaterThanOrEqual(4.5, $contrast->contrastRatio($palette['on_primary'], $palette['primary']));
        $this->assertGreaterThanOrEqual(4.5, $contrast->contrastRatio($palette['heading'], $palette['surface']));
        $this->assertGreaterThanOrEqual(4.5, $contrast->contrastRatio($palette['body'], $palette['surface']));
        $this->assertGreaterThanOrEqual(4.5, $contrast->contrastRatio($palette['muted'], $palette['surface']));
        $this->assertGreaterThanOrEqual(4.5, $contrast->contrastRatio($palette['button_text'], $palette['button_primary']));
    }

    public function test_custom_hex_derives_its_own_highlight_and_surface_roles(): void
    {
        $resolver = app(ThemeColorResolver::class);
        $blue = $resolver->resolve('#3368A0');
        $green = $resolver->resolve('#1D7A53');

        $this->assertNotSame('#30475E', $blue['brand_surface']);
        $this->assertNotSame($blue['brand_surface'], $green['brand_surface']);
        $this->assertNotSame($blue['accent'], $green['accent']);
        $this->assertNotSame($blue['surface_alt'], $green['surface_alt']);
        $this->assertSame($blue['brand_surface'], $blue['secondary']);
    }

    public function test_my_brand_keeps_base_family_and_semantic_palette_separate(): void
    {
        $theme = app(MyBrandThemeService::class)->seedFromFamily('violet');
        $palette = $theme['palette'];

        $this->assertSame('my-brand', $theme['id']);
        $this->assertSame('violet', $theme['base_family']);
        $this->assertSame($palette['background'], $palette['primary']);
        $this->assertSame($palette['brand_surface'], $palette['surface']);
        $this->assertSame('#FEFEFD', $palette['content_surface']);

        $resolved = app(ThemeColorResolver::class)->resolve('my-brand', [
            'custom_brand_theme' => $theme,
            'brand_palette' => $palette,
        ]);

        $this->assertSame($palette['primary'], $resolved['primary']);
        $this->assertSame($palette['brand_surface'], $resolved['brand_surface']);
        $this->assertSame($palette['content_surface'], $resolved['surface']);
    }

    public function test_compiler_emits_the_same_resolved_semantic_palette(): void
    {
        $resolved = app(ThemeColorResolver::class)->resolve('#3368A0');
        $html = CmsHtmlCompiler::compile([
            ['type' => 'hero_centered_cta', 'heading' => 'Semantic parity'],
        ], '#3368A0');

        $this->assertStringContainsString("--cosmic-brand-primary:{$resolved['primary']}", $html);
        $this->assertStringContainsString("--cosmic-color-heading:{$resolved['heading']}", $html);
        $this->assertStringContainsString("--cosmic-color-on-primary:{$resolved['on_primary']}", $html);
        $this->assertStringContainsString("--cosmic-color-on-accent:{$resolved['on_accent']}", $html);
        $this->assertStringContainsString("--cosmic-color-on-surface:{$resolved['on_surface']}", $html);
        $this->assertStringContainsString("--cosmic-bg-primary:{$resolved['primary']}", $html);
        $this->assertStringContainsString("--cosmic-color-surface-alt:{$resolved['surface_alt']}", $html);
    }

    public function test_luna_custom_hex_entry_point_preserves_identity_and_repairs_unsafe_proposals(): void
    {
        $controller = app(CustomSparkController::class);
        $method = new \ReflectionMethod($controller, 'lunaValidateBrandColorFamily');
        $method->setAccessible(true);
        $palette = $method->invoke($controller, '#3368A0', [
            'primary' => '#FF0000',
            'buttonText' => '#3368A0',
            'onPrimary' => '#3368A0',
        ]);

        $this->assertSame('#3368A0', $palette['primary']);
        $this->assertSame('#3368A0', $palette['sourceColor']);
        $this->assertSame('#3368A0', $palette['buttonPrimary']);
        $contrast = app(ColorContrastGuard::class);
        $this->assertGreaterThanOrEqual(4.5, $contrast->contrastRatio($palette['buttonText'], $palette['buttonPrimary']));
        $this->assertGreaterThanOrEqual(4.5, $contrast->contrastRatio($palette['onPrimary'], $palette['primary']));
    }

    public function test_builder_overlay_header_uses_the_shared_semantic_palette_contract(): void
    {
        $builder = (string) file_get_contents(resource_path('js/Pages/Websites/Builder.jsx'));

        $this->assertStringContainsString('resolveSemanticPalette', $builder);
        $this->assertStringContainsString('const overlaySemanticPalette = resolveSemanticPalette(', $builder);
        $this->assertStringContainsString('primary: overlaySemanticPalette.primary', $builder);
        $this->assertStringContainsString('surface: overlaySemanticPalette.surface', $builder);
        $this->assertStringContainsString("const overlayHeaderTone = overlayUsesPremiumLightHeader ? 'light' : 'dark'", $builder);
    }
}
