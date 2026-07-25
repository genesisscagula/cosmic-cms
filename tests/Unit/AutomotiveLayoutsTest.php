<?php

namespace Tests\Unit;

use App\AI\Schemas\SchemaManager;
use Tests\TestCase;

class AutomotiveLayoutsTest extends TestCase
{
    public function test_automotive_layouts_are_valid_unique_page_recipes(): void
    {
        $layouts = require app_path('AI/Layouts/AutomotiveLayouts.php');
        $supportedBlocks = array_keys(SchemaManager::map());

        $this->assertCount(3, $layouts);

        foreach ($layouts as $layout) {
            $this->assertContains($layout[0], [
                'hero_background_image',
                'hero_editorial_overlay',
                'hero_split_image',
            ]);
            $this->assertCount(count($layout), array_unique($layout));
            $this->assertNotEmpty($layout);

            foreach ($layout as $blockType) {
                $this->assertContains($blockType, $supportedBlocks);
            }
        }
    }
}
