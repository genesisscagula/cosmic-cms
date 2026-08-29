<?php

namespace Tests\Unit;

use App\Services\LunaSmartSparkEditingService;
use Tests\TestCase;

final class LunaSmartSparkRepeaterV2Test extends TestCase
{
    public function test_deleting_an_ordinal_item_removes_its_scoped_tailwind_override(): void
    {
        $block = $this->block();

        $result = app(LunaSmartSparkEditingService::class)
            ->applyRepeaterIntent('Delete the second service.', $block);

        $this->assertSame(['One', 'Three'], array_column($result['block']['services'], 'title'));
        $this->assertSame(
            ['bg-one', 'bg-three'],
            array_map(fn (array $scope) => $scope['styles']['card']['classes'][0], $result['block']['luna_tailwind_schema']['collections']['services'])
        );
    }

    public function test_duplicating_an_ordinal_item_keeps_its_scoped_tailwind_contract(): void
    {
        $block = $this->block();

        $result = app(LunaSmartSparkEditingService::class)
            ->applyRepeaterIntent('Duplicate the second service.', $block);

        $this->assertSame(['One', 'Two', 'Two', 'Three'], array_column($result['block']['services'], 'title'));
        $this->assertSame('repeater_duplicate', $result['action']);
        $this->assertSame(
            ['bg-one', 'bg-two', 'bg-two', 'bg-three'],
            array_map(fn (array $scope) => $scope['styles']['card']['classes'][0], $result['block']['luna_tailwind_schema']['collections']['services'])
        );
    }

    public function test_adding_an_item_does_not_leak_the_previous_items_override(): void
    {
        $result = app(LunaSmartSparkEditingService::class)
            ->applyRepeaterIntent('Add another service.', $this->block());

        $scopes = $result['block']['luna_tailwind_schema']['collections']['services'];
        $this->assertCount(4, $scopes);
        $this->assertSame([], $scopes[3]['styles']);
    }

    private function block(): array
    {
        return [
            'type' => 'services_cards',
            'services' => [
                ['title' => 'One'],
                ['title' => 'Two'],
                ['title' => 'Three'],
            ],
            'luna_tailwind_schema' => [
                'version' => 2,
                'spark_type' => 'services_cards',
                'styles' => [],
                'slots' => [],
                'collections' => [
                    'services' => [
                        ['styles' => ['card' => ['classes' => ['bg-one']]], 'collections' => []],
                        ['styles' => ['card' => ['classes' => ['bg-two']]], 'collections' => []],
                        ['styles' => ['card' => ['classes' => ['bg-three']]], 'collections' => []],
                    ],
                ],
            ],
        ];
    }
}
