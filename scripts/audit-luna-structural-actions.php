<?php

namespace Illuminate\Support {
    final class Str {
        public static function lower(mixed $value): string { return strtolower((string) $value); }
        public static function limit(mixed $value, int $limit = 100, string $end = '...'): string {
            $value = (string) $value;
            return strlen($value) <= $limit ? $value : substr($value, 0, max(0, $limit - strlen($end))).$end;
        }
        public static function beforeLast(string $subject, string $search): string {
            $pos = strrpos($subject, $search);
            return $pos === false ? $subject : substr($subject, 0, $pos);
        }
        public static function uuid(): object {
            return new class {
                public function toString(): string { return bin2hex(random_bytes(16)); }
                public function __toString(): string { return $this->toString(); }
            };
        }
    }
    final class Arr {
        public static function get(array $array, string|array|null $key, mixed $default = null): mixed {
            if ($key === null) return $array;
            $cursor = $array;
            foreach (is_array($key) ? $key : explode('.', $key) as $segment) {
                if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) return $default;
                $cursor = $cursor[$segment];
            }
            return $cursor;
        }
    }
}

namespace {
    function config(string $key, mixed $default = null): mixed { return $default; }

    require __DIR__.'/../app/Support/AiFlexStructureContract.php';
    require __DIR__.'/../app/Support/SparkExtrasContract.php';
    require __DIR__.'/../app/Services/SparkTailwindSchemaContract.php';
    require __DIR__.'/../app/Services/NestedRepeaterMutationService.php';
    require __DIR__.'/../app/Services/LunaStructuralActionService.php';

    use App\Services\LunaStructuralActionService;
    use App\Services\NestedRepeaterMutationService;
    use App\Services\SparkTailwindSchemaContract;
    use App\Support\AiFlexStructureContract;

    $assert = static function (bool $condition, string $message): void {
        if (! $condition) throw new RuntimeException($message);
    };

    $nested = new NestedRepeaterMutationService(new SparkTailwindSchemaContract());
    $service = new LunaStructuralActionService($nested);

    // Batch 7: stable collection paths must canonicalize to numeric addresses
    // before field-extras and scoped Tailwind remapping. Descendant identities
    // in duplicated registered repeater items must also be fresh.
    $nestedBlock = [
        'type'=>'qa_nested',
        'rows'=>[['_cosmic_id'=>'row_a','columns'=>[
            ['_cosmic_id'=>'col_a','heading'=>'Alpha','children'=>[['_cosmic_id'=>'child_a','title'=>'Child']]],
            ['_cosmic_id'=>'col_b','heading'=>'Beta','children'=>[['_cosmic_id'=>'child_b','title'=>'Child B']]],
        ]]],
        'field_extras'=>[
            'rows.0.columns.0.heading'=>['before'=>[],'after'=>[['id'=>'extra_alpha','type'=>'image','data'=>['src'=>'','alt'=>'Alpha']]]],
        ],
        'luna_tailwind_schema'=>[
            'version'=>2,'spark_type'=>'qa_nested','styles'=>[],'slots'=>[],
            'collections'=>['rows'=>[['styles'=>[],'collections'=>['columns'=>[
                ['styles'=>['title'=>['add'=>['text-xl']]],'collections'=>[]],
                ['styles'=>[],'collections'=>[]],
            ]]]]],
        ],
    ];
    $stableMove = $nested->mutate($nestedBlock, 'rows.@row_a.columns', 'move', 0, 1);
    $assert(($stableMove['changed'] ?? false) === true, 'stable-path nested move failed');
    $assert(isset($stableMove['block']['field_extras']['rows.0.columns.1.heading']), 'stable-path move did not remap numeric field extras');
    $assert(($stableMove['block']['luna_tailwind_schema']['collections']['rows'][0]['collections']['columns'][1]['styles']['title']['add'][0] ?? '') === 'text-xl', 'stable-path move did not remap Tailwind scope');
    $duplicateNested = $nested->mutate($nestedBlock, 'rows.@row_a.columns', 'duplicate', 0);
    $assert(($duplicateNested['changed'] ?? false) === true, 'stable-path nested duplicate failed');
    $assert(($duplicateNested['block']['rows'][0]['columns'][0]['children'][0]['_cosmic_id'] ?? '') !== ($duplicateNested['block']['rows'][0]['columns'][1]['children'][0]['_cosmic_id'] ?? ''), 'nested duplicate retained descendant stable id');

    // Registered Spark: exact before/after field anchors.
    $registered = ['type'=>'hero_headline','heading'=>'Build better','description'=>'Body copy'];
    $result = $service->apply($registered, [[
        'action'=>'add_extra','target_field'=>'heading','placement'=>'after',
        'extra'=>['type'=>'image','data'=>['src'=>'','alt'=>'Project image']],
    ]]);
    $assert(($result['ok'] ?? false) === true, 'registered add_extra failed');
    $registered = $result['block'];
    $assert(($registered['field_extras']['heading']['after'][0]['type'] ?? '') === 'image', 'image not anchored after heading');
    $imageId = (string) ($registered['field_extras']['heading']['after'][0]['id'] ?? '');
    $assert($imageId !== '', 'image extra missing stable id');

    $result = $service->apply($registered, [[
        'action'=>'add_extra','target_field'=>'heading','placement'=>'before',
        'extra'=>['type'=>'badge','data'=>['text'=>'Premium']],
    ]]);
    $assert(($result['ok'] ?? false) === true, 'registered before extra failed');
    $registered = $result['block'];
    $assert(count($registered['field_extras']['heading']['before'] ?? []) === 1, 'before anchor count');

    $result = $service->apply($registered, [[
        'action'=>'move_extra','target_field'=>'heading','placement'=>'after','extra_id'=>$imageId,
        'destination_target_field'=>'description','destination_placement'=>'before','to_index'=>0,
    ]]);
    $assert(($result['ok'] ?? false) === true, 'registered cross-anchor move failed');
    $registered = $result['block'];
    $assert(($registered['field_extras']['description']['before'][0]['id'] ?? '') === $imageId, 'registered move must preserve identity');

    // Invalid second action must roll back the first action atomically.
    $atomicBefore = $registered;
    $atomic = $service->apply($registered, [
        ['action'=>'add_extra','target_field'=>'heading','placement'=>'after','extra'=>['type'=>'text','data'=>['text'=>'Hello']]],
        ['action'=>'add_extra','target_field'=>'missing_field','placement'=>'after','extra'=>['type'=>'text','data'=>['text'=>'Nope']]],
    ]);
    $assert(($atomic['ok'] ?? true) === false, 'invalid structural plan should fail');
    $assert(($atomic['block'] ?? null) === $atomicBefore, 'failed structural plan must rollback atomically');

    // AI Flex: Rows -> Columns -> Extras exact structural CRUD.
    $flex = AiFlexStructureContract::canonicalizeBlock([
        'type'=>'luna_custom_section',
        'elements'=>[['type'=>'heading','text'=>'AI Flex']],
        'ai_flex'=>[],
    ]);
    $result = $service->apply($flex, [['action'=>'add_column','collection_path'=>'rows.0.columns']]);
    $assert(($result['ok'] ?? false) === true, 'add_column failed');
    $flex = $result['block'];
    $assert(count($flex['elements'][0]['children'] ?? []) === 2, 'column count should be two');
    $assert(($flex['elements'][0]['children'][0]['style']['width'] ?? null) === 50.0, 'existing column width must rebalance');
    $assert(($flex['elements'][0]['children'][1]['style']['width'] ?? null) === 50.0, 'new column width must rebalance');

    $result = $service->apply($flex, [[
        'action'=>'add_extra','collection_path'=>'rows.0.columns.1.extras',
        'extra'=>['type'=>'image','src'=>'','alt'=>'New image'],
    ]]);
    $assert(($result['ok'] ?? false) === true, 'AI Flex add image extra failed');
    $flex = $result['block'];
    $extra = $flex['elements'][0]['children'][1]['children'][0] ?? [];
    $assert(($extra['type'] ?? '') === 'image', 'AI Flex typed add must create image, not clone a sibling type');
    $stable = (string) ($extra['_cosmic_id'] ?? '');
    $assert($stable !== '', 'AI Flex extra missing stable id');

    $result = $service->apply($flex, [[
        'action'=>'move_extra','collection_path'=>'rows.0.columns.1.extras','stable_id'=>$stable,
        'destination_collection_path'=>'rows.0.columns.0.extras','to_index'=>0,
    ]]);
    $assert(($result['ok'] ?? false) === true, 'AI Flex cross-column move failed');
    $flex = $result['block'];
    $moved = $flex['elements'][0]['children'][0]['children'][0] ?? [];
    $assert(($moved['_cosmic_id'] ?? '') === $stable, 'cross-column move must preserve stable identity');

    $result = $service->apply($flex, [[
        'action'=>'remove_extra','collection_path'=>'rows.0.columns.0.extras','stable_id'=>$stable,
    ]]);
    $assert(($result['ok'] ?? false) === true, 'AI Flex remove last extra failed');
    $flex = $result['block'];
    $assert(count($flex['elements'][0]['children'][0]['children'] ?? []) === 1, 'column 0 should retain its original heading after image removal');

    $result = $service->apply($flex, [['action'=>'add_row','collection_path'=>'rows']]);
    $assert(($result['ok'] ?? false) === true, 'add_row failed');
    $flex = $result['block'];
    $assert(count($flex['elements'] ?? []) === 2, 'row count should be two');

    $rowId = (string) ($flex['elements'][1]['_cosmic_id'] ?? '');
    $result = $service->apply($flex, [['action'=>'duplicate_row','collection_path'=>'rows','stable_id'=>$rowId]]);
    $assert(($result['ok'] ?? false) === true, 'duplicate_row via stable id failed');
    $flex = $result['block'];
    $assert(count($flex['elements'] ?? []) === 3, 'row duplicate count');
    $ids = array_map(fn($row)=>(string)($row['_cosmic_id']??''), $flex['elements']);
    $assert(count($ids) === count(array_unique($ids)), 'duplicated rows must have unique ids');

    // Batch 7 acceptance: one atomic instruction creates a new row with exactly
    // three balanced columns and typed content in every column.
    $acceptance = AiFlexStructureContract::canonicalizeBlock([
        'type'=>'luna_custom_section',
        'elements'=>[['type'=>'heading','text'=>'Existing content']],
        'ai_flex'=>[],
    ]);
    $result = $service->apply($acceptance, [
        ['action'=>'add_row','collection_path'=>'rows'],
        ['action'=>'add_column','collection_path'=>'rows.1.columns'],
        ['action'=>'add_column','collection_path'=>'rows.1.columns'],
        ['action'=>'add_extra','collection_path'=>'rows.1.columns.0.extras','extra'=>['type'=>'heading','text'=>'First']],
        ['action'=>'add_extra','collection_path'=>'rows.1.columns.1.extras','extra'=>['type'=>'image','src'=>'','alt'=>'Second']],
        ['action'=>'add_extra','collection_path'=>'rows.1.columns.2.extras','extra'=>['type'=>'text','text'=>'Third']],
    ]);
    $assert(($result['ok'] ?? false) === true, 'Batch 7 multi-action acceptance failed');
    $acceptance = $result['block'];
    $columns = $acceptance['elements'][1]['children'] ?? [];
    $assert(count($columns) === 3, 'acceptance row must contain exactly three columns');
    $assert(array_map(fn($column)=>$column['children'][0]['type'] ?? null, $columns) === ['heading','image','text'], 'acceptance content types/order mismatch');
    $assert(count(array_filter($columns, fn($column)=>abs((float)($column['style']['width'] ?? 0)-33.33)<0.01)) === 3, 'acceptance columns must be balanced');

    echo "Luna Batch 5 structural actions PASS\n";
    echo "Registered field extras + AI Flex Rows/Columns/Extras CRUD + stable IDs + atomic rollback PASS\n";
}
