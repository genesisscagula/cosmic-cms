<?php

namespace Tests\Unit;

use App\Services\LunaStructuralActionService;
use App\Services\NestedRepeaterMutationService;
use App\Services\SparkTailwindSchemaContract;
use App\Support\AiFlexStructureContract;
use App\Support\SparkExtrasContract;
use Tests\TestCase;

class Batch7StructuralCompatibilityTest extends TestCase
{
    private function structural(): LunaStructuralActionService
    {
        return new LunaStructuralActionService(
            new NestedRepeaterMutationService(new SparkTailwindSchemaContract)
        );
    }

    public function test_legacy_blocks_migrate_additively_and_idempotently(): void
    {
        $legacy = ['type'=>'hero','heading'=>'Legacy','private'=>['keep'=>true]];
        $once = SparkExtrasContract::normalizeBlock($legacy);
        $twice = SparkExtrasContract::normalizeBlock($once);

        $this->assertSame([], $once['field_extras']);
        $this->assertSame(['keep'=>true], $once['private']);
        $this->assertSame($once, $twice);
    }

    public function test_registered_extra_mutation_is_atomic(): void
    {
        $block = ['type'=>'hero','heading'=>'Hello','description'=>'World','field_extras'=>[]];
        $result = $this->structural()->apply($block, [
            ['action'=>'add_extra','target_field'=>'heading','placement'=>'after','extra'=>['type'=>'image','data'=>['src'=>'','alt'=>'Image']]],
            ['action'=>'add_extra','target_field'=>'missing','placement'=>'after','extra'=>['type'=>'text','data'=>['text'=>'Invalid']]],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['changed']);
        $this->assertSame($block, $result['block']);
    }

    public function test_stable_nested_mutation_remaps_extras_and_refreshes_descendant_ids(): void
    {
        $block = [
            'type'=>'services','rows'=>[['_cosmic_id'=>'row_a','columns'=>[
                ['_cosmic_id'=>'column_a','children'=>[['_cosmic_id'=>'child_a','title'=>'A']]],
            ]]],
            'field_extras'=>['rows.0.columns.0.title'=>['before'=>[],'after'=>[['id'=>'extra_a','type'=>'badge','data'=>['text'=>'A']]]]],
        ];
        $nested = new NestedRepeaterMutationService(new SparkTailwindSchemaContract);
        $result = $nested->mutate($block, 'rows.@row_a.columns', 'duplicate', 0);

        $this->assertTrue($result['changed']);
        $this->assertArrayHasKey('rows.0.columns.1.title', $result['block']['field_extras']);
        $this->assertNotSame(
            $result['block']['rows'][0]['columns'][0]['children'][0]['_cosmic_id'],
            $result['block']['rows'][0]['columns'][1]['children'][0]['_cosmic_id']
        );
    }

    public function test_acceptance_instruction_creates_exactly_three_balanced_columns(): void
    {
        $block = AiFlexStructureContract::canonicalizeBlock([
            'type'=>'luna_custom_section','elements'=>[['type'=>'heading','text'=>'Existing']],'ai_flex'=>[],
        ]);
        $result = $this->structural()->apply($block, [
            ['action'=>'add_row','collection_path'=>'rows'],
            ['action'=>'add_column','collection_path'=>'rows.1.columns'],
            ['action'=>'add_column','collection_path'=>'rows.1.columns'],
            ['action'=>'add_extra','collection_path'=>'rows.1.columns.0.extras','extra'=>['type'=>'heading','text'=>'First']],
            ['action'=>'add_extra','collection_path'=>'rows.1.columns.1.extras','extra'=>['type'=>'image','src'=>'','alt'=>'Second']],
            ['action'=>'add_extra','collection_path'=>'rows.1.columns.2.extras','extra'=>['type'=>'text','text'=>'Third']],
        ]);

        $this->assertTrue($result['ok']);
        $columns = $result['block']['elements'][1]['children'];
        $this->assertCount(3, $columns);
        $this->assertSame(['heading','image','text'], array_map(fn ($column) => $column['children'][0]['type'], $columns));
        foreach ($columns as $column) $this->assertEqualsWithDelta(33.33, $column['style']['width'], .01);
    }
}
