<?php

namespace Tests\Unit;

use App\Services\LunaSmartSparkEditingService;
use Tests\TestCase;

class LunaSmartSparkCardinalityTest extends TestCase
{
    public function test_exact_and_follow_up_decrease_work_for_counted_sparks(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $block = ['type' => 'services_sticky_scroll', 'service_count' => 6];

        $exact = $service->applyCardinalityIntent('make it just 4 service cards', $block);
        $this->assertSame(4, $exact['block']['service_count']);
        $this->assertSame('repeater_decrease', $exact['action']);

        $followUp = $service->applyCardinalityIntent('remove the 2 existing', $block);
        $this->assertSame(4, $followUp['block']['service_count']);
        $this->assertSame(6, $followUp['count_before']);
        $this->assertSame(4, $followUp['count_after']);
    }

    public function test_array_cards_can_increase_without_blank_items(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $result = $service->applyCardinalityIntent('add 2 cards', [
            'type' => 'example_cards',
            'items' => [['title' => 'A'], ['title' => 'B']],
        ]);

        $this->assertCount(4, $result['block']['items']);
        $this->assertSame('B', $result['block']['items'][2]['title']);
        $this->assertSame('B', $result['block']['items'][3]['title']);
    }

    public function test_per_spark_maximum_is_enforced(): void
    {
        $service = app(LunaSmartSparkEditingService::class);
        $result = $service->applyCardinalityIntent('increase by 8 service cards', [
            'type' => 'services_sticky_scroll',
            'service_count' => 5,
        ]);

        $this->assertSame(6, $result['block']['service_count']);
    }
}
