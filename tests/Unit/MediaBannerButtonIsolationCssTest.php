<?php

namespace Tests\Unit;

use Tests\TestCase;

class MediaBannerButtonIsolationCssTest extends TestCase
{
    public function test_light_dialog_rules_preserve_editable_media_banner_buttons(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('[data-cosmic-luna-display="button"])[class~="bg-white"]', $css);
        $this->assertStringContainsString('[data-cosmic-luna-display="button"])[class~="bg-white/5"]', $css);
        $this->assertStringContainsString('background-color: rgb(255 255 255 / .05) !important;', $css);
        $this->assertStringContainsString('-webkit-text-fill-color: #0f172a !important;', $css);
    }
}
