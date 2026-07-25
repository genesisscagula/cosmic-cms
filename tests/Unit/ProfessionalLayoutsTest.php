<?php

namespace Tests\Unit;

use App\AI\Schemas\SchemaManager;
use Tests\TestCase;

class ProfessionalLayoutsTest extends TestCase
{
    public function test_professional_industries_have_three_valid_unique_layout_recipes(): void
    {
        $supportedBlocks = array_keys(SchemaManager::map());

        foreach (['LawyerLayouts.php', 'FinanceLayouts.php', 'RealEstateLayouts.php', 'TechnologyLayouts.php', 'EducationLayouts.php', 'SalonLayouts.php'] as $file) {
            $layouts = require app_path('AI/Layouts/'.$file);

            $this->assertCount(3, $layouts, $file);

            foreach ($layouts as $layout) {
                $this->assertNotEmpty($layout, $file);
                $this->assertContains($layout[0], [
                    'hero_background_image',
                    'hero_editorial_overlay',
                    'hero_split_image',
                ], $file);
                $this->assertCount(count($layout), array_unique($layout), $file);

                foreach ($layout as $blockType) {
                    $this->assertContains($blockType, $supportedBlocks, $file);
                }
            }
        }
    }
}
