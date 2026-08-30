<?php

namespace App\Services;

use App\Support\AiFlexComponentRegistry;
use App\Support\AiFlexStructureContract;
use App\Support\SparkExtrasContract;
use Illuminate\Support\Str;

/**
 * Batch 5 structural executor for one already-locked Spark.
 *
 * Luna may describe structural intent, but every action is revalidated against
 * the current popup draft before mutation. Registered Sparks use field_extras
 * anchors; AI Flex uses the canonical Rows -> Columns -> Extras logical tree.
 * Plans are atomic: one invalid action rolls back the whole structural batch.
 */
final class LunaStructuralActionService
{
    public const VERSION = 1;

    private const ACTIONS = [
        'add_extra','update_extra','remove_extra','move_extra','duplicate_extra',
        'add_row','remove_row','move_row','duplicate_row',
        'add_column','remove_column','move_column','duplicate_column',
    ];

    private const AI_FLEX_EXTRA_TYPES = [
        'group','grid','stack','card','heading','text','button','image','video',
        'icon','badge','list','divider','stat','spacer','form','background_image','background_video','overlay','slider','slide','button_group','media_group',
    ];

    private const AI_FLEX_STYLE_KEYS = [
        'gap','columns','width','max_width','min_height','padding','padding_x','padding_y',
        'radius','background','color','border_color','border_width','shadow','align','justify',
        'text_align','font_size','font_weight','line_height','aspect_ratio','object_fit',
        'object_position','opacity','position','top','right','bottom','left','z_index','overflow',
        'order','grow','basis','self_align','tablet_width','mobile_width','tablet_columns',
        'mobile_columns','tablet_gap','mobile_gap','tablet_padding','mobile_padding','tablet_order',
        'mobile_order','tablet_position','mobile_position','tablet_min_height','mobile_min_height',
    ];

    public function __construct(private readonly NestedRepeaterMutationService $repeaters)
    {
    }

    public function apply(array $block, array $actions, array $elementContext = []): array
    {
        $actions = array_values(array_slice(array_filter($actions, 'is_array'), 0, 12));
        if ($actions === []) return ['ok'=>false,'reason'=>'structural_actions_empty','block'=>$block,'changed'=>false,'operations'=>[]];

        $before = $this->fingerprint($block);
        $next = $block;
        $operations = [];

        foreach ($actions as $offset => $raw) {
            $result = $this->applyOne($next, $raw, $elementContext);
            if (($result['ok'] ?? false) !== true || ($result['changed'] ?? false) !== true) {
                return [
                    'ok'=>false,
                    'reason'=>$result['reason'] ?? 'structural_action_rejected',
                    'failed_action_index'=>$offset,
                    'failed_action'=>$this->compactAction($raw),
                    'block'=>$block,
                    'changed'=>false,
                    'operations'=>[],
                ];
            }
            $next = $result['block'];
            $operations[] = $result['operation'];
        }

        $next = SparkExtrasContract::normalizeBlock($next);
        $after = $this->fingerprint($next);
        if ($before === $after) return ['ok'=>false,'reason'=>'structural_no_change','block'=>$block,'changed'=>false,'operations'=>[]];

        return [
            'ok'=>true,
            'changed'=>true,
            'block'=>$next,
            'operations'=>$operations,
            'diff'=>['structural'=>$operations],
            'before_fingerprint'=>$before,
            'after_fingerprint'=>$after,
            'contract_version'=>self::VERSION,
        ];
    }

    private function applyOne(array $block, array $raw, array $context): array
    {
        $action = Str::lower(trim((string) ($raw['action'] ?? '')));
        if (! in_array($action, self::ACTIONS, true)) return ['ok'=>false,'reason'=>'structural_action_invalid'];

        if (str_ends_with($action, '_row')) return $this->applyAiFlexCollection($block, $action, 'rows', $raw, $context);
        if (str_ends_with($action, '_column')) return $this->applyAiFlexCollection($block, $action, 'columns', $raw, $context);

        $logicalPath = $this->logicalCollectionPath($raw, $context);
        if (($block['type'] ?? '') === 'luna_custom_section' && str_starts_with($logicalPath, 'rows')) {
            return $this->applyAiFlexExtra($block, $action, $raw, $context, $logicalPath);
        }
        return $this->applyFieldExtra($block, $action, $raw, $context);
    }

    private function applyAiFlexCollection(array $block, string $action, string $collection, array $raw, array $context): array
    {
        if (($block['type'] ?? '') !== 'luna_custom_section') return ['ok'=>false,'reason'=>'ai_flex_structure_required'];
        $logical = $this->logicalCollectionPath($raw, $context);
        if ($collection === 'rows') $logical = 'rows';
        if ($collection === 'columns' && (! str_starts_with($logical, 'rows.') || ! str_ends_with($logical, '.columns'))) {
            $logical = $this->deriveColumnsPath($raw, $context);
        }
        if ($logical === '' || ! str_ends_with($logical, $collection)) return ['ok'=>false,'reason'=>'structural_collection_unresolved'];

        $singular = $collection === 'rows' ? 'row' : 'column';
        $verb = Str::beforeLast($action, '_'.$singular);
        $index = $this->collectionItemIndex($block, $logical, $raw, $context);
        $to = $this->toIndex($raw);

        if ($verb === 'add') {
            $item = $collection === 'rows' ? $this->newRow() : $this->newColumn();
            $result = $this->repeaters->insertItem($block, $logical, $item, $this->insertIndex($raw));
        } else {
            $mutation = match ($verb) {
                'duplicate' => 'duplicate',
                'remove' => 'remove',
                'move' => 'move',
                default => '',
            };
            if ($mutation === '') return ['ok'=>false,'reason'=>'structural_action_invalid'];
            $result = $this->repeaters->mutate($block, $logical, $mutation, $index, $to);
        }

        if (! ($result['changed'] ?? false)) return ['ok'=>false,'reason'=>$result['reason'] ?? 'structural_mutation_failed'];
        if ($collection === 'columns') {
            $rebalanced = $this->rebalanceColumns($result['block'], $logical);
            if (($rebalanced['ok'] ?? false) !== true) return ['ok'=>false,'reason'=>$rebalanced['reason'] ?? 'column_rebalance_failed'];
            $result['block'] = $rebalanced['block'];
        }
        return [
            'ok'=>true,'changed'=>true,'block'=>$result['block'],
            'operation'=>[
                'action'=>$action,'collection'=>$collection,'collection_path'=>$logical,
                'item_index'=>$result['item_index'] ?? $index,'to_index'=>$to,'verified'=>true,
            ],
        ];
    }

    private function rebalanceColumns(array $block, string $logicalPath): array
    {
        $columns = $this->repeaters->valueAtPath($block, $logicalPath);
        if (! is_array($columns) || $columns === []) return ['ok'=>false,'block'=>$block,'changed'=>false,'reason'=>'columns_unresolved'];
        $width = round(100 / count($columns), 2);
        $next = $block;
        foreach (array_values($columns) as $index => $column) {
            if (! is_array($column)) return ['ok'=>false,'block'=>$block,'changed'=>false,'reason'=>'column_invalid'];
            $column['style'] = is_array($column['style'] ?? null) ? $column['style'] : [];
            $column['style']['width'] = $width;
            $replacement = $this->repeaters->replaceItem($next, $logicalPath, $index, $column);
            if (! ($replacement['changed'] ?? false) && ($this->repeaters->valueAtPath($next, $logicalPath)[$index]['style']['width'] ?? null) !== $width) {
                return ['ok'=>false,'block'=>$block,'changed'=>false,'reason'=>$replacement['reason'] ?? 'column_rebalance_failed'];
            }
            $next = $replacement['block'] ?? $next;
        }
        return ['ok'=>true,'block'=>$next,'changed'=>$this->fingerprint($next)!==$this->fingerprint($block)];
    }

    private function applyAiFlexExtra(array $block, string $action, array $raw, array $context, string $logical): array
    {
        if (! str_ends_with($logical, '.extras')) return ['ok'=>false,'reason'=>'ai_flex_extra_collection_unresolved'];
        $index = $this->collectionItemIndex($block, $logical, $raw, $context);
        $to = $this->toIndex($raw);

        if ($action === 'add_extra') {
            $extra = $this->normalizeAiFlexExtra(is_array($raw['extra'] ?? null) ? $raw['extra'] : []);
            if ($extra === null) return ['ok'=>false,'reason'=>'ai_flex_extra_invalid'];
            $result = $this->repeaters->insertItem($block, $logical, $extra, $this->insertIndex($raw));
        } elseif ($action === 'duplicate_extra') {
            $result = $this->repeaters->mutate($block, $logical, 'duplicate', $index);
        } elseif ($action === 'remove_extra') {
            if ($index === null) return ['ok'=>false,'reason'=>'extra_unresolved'];
            $result = $this->removeAiFlexExtraAllowEmpty($block, $logical, $index);
            if (($result['changed'] ?? false) === true) $result['item_index'] = $index;
        } elseif ($action === 'move_extra') {
            $destination = trim((string) ($raw['destination_collection_path'] ?? $raw['destination_path'] ?? ''));
            if ($destination !== '' && $destination !== $logical) {
                return $this->moveAiFlexExtraAcrossColumns($block, $logical, $destination, $index, $raw);
            }
            $result = $this->repeaters->mutate($block, $logical, 'move', $index, $to);
        } elseif ($action === 'update_extra') {
            $items = $this->repeaters->valueAtPath($block, $logical);
            if (! is_array($items) || ! array_is_list($items) || $index === null || ! isset($items[$index]) || ! is_array($items[$index])) return ['ok'=>false,'reason'=>'extra_unresolved'];
            $patch = is_array($raw['changes'] ?? null) ? $raw['changes'] : (is_array($raw['extra'] ?? null) ? $raw['extra'] : []);
            $merged = array_replace_recursive($items[$index], $patch);
            $extra = $this->normalizeAiFlexExtra($merged, (string) ($items[$index]['type'] ?? 'text'));
            if ($extra === null) return ['ok'=>false,'reason'=>'ai_flex_extra_invalid'];
            $result = $this->repeaters->replaceItem($block, $logical, $index, $extra);
        } else {
            return ['ok'=>false,'reason'=>'structural_action_invalid'];
        }

        if (! ($result['changed'] ?? false)) return ['ok'=>false,'reason'=>$result['reason'] ?? 'structural_mutation_failed'];
        return [
            'ok'=>true,'changed'=>true,'block'=>$result['block'],
            'operation'=>[
                'action'=>$action,'collection'=>'extras','collection_path'=>$logical,
                'item_index'=>$result['item_index'] ?? $index,'to_index'=>$to,'verified'=>true,
            ],
        ];
    }

    private function moveAiFlexExtraAcrossColumns(array $block, string $sourcePath, string $destinationPath, ?int $index, array $raw): array
    {
        if ($index === null || ! str_starts_with($destinationPath, 'rows') || ! str_ends_with($destinationPath, '.extras')) return ['ok'=>false,'reason'=>'extra_move_target_invalid'];
        $source = $this->repeaters->valueAtPath($block, $sourcePath);
        $destination = $this->repeaters->valueAtPath($block, $destinationPath);
        if (! is_array($source) || ! isset($source[$index]) || ! is_array($source[$index]) || ! is_array($destination) || ! array_is_list($destination)) return ['ok'=>false,'reason'=>'extra_unresolved'];
        if (count($source) <= 1) {
            // Extras may be empty, so remove manually via a temporary insert/move is
            // unnecessary; use the exact collection update helper below.
        }
        $moving = $source[$index];
        $removed = $this->removeAiFlexExtraAllowEmpty($block, $sourcePath, $index);
        if (! ($removed['changed'] ?? false)) return ['ok'=>false,'reason'=>$removed['reason'] ?? 'extra_remove_failed'];
        $inserted = $this->repeaters->insertItem($removed['block'], $destinationPath, $moving, $this->insertIndex($raw), true);
        if (! ($inserted['changed'] ?? false)) return ['ok'=>false,'reason'=>$inserted['reason'] ?? 'extra_insert_failed'];
        return [
            'ok'=>true,'changed'=>true,'block'=>$inserted['block'],
            'operation'=>[
                'action'=>'move_extra','collection'=>'extras','collection_path'=>$sourcePath,
                'destination_collection_path'=>$destinationPath,'item_index'=>$index,
                'to_index'=>$inserted['item_index'] ?? null,'verified'=>true,
            ],
        ];
    }

    private function removeAiFlexExtraAllowEmpty(array $block, string $logical, int $index): array
    {
        $storage = AiFlexStructureContract::logicalToStoragePath($logical);
        $items = $this->repeaters->valueAtPath($block, $logical);
        if ($storage === [] || ! is_array($items) || ! isset($items[$index])) return ['block'=>$block,'changed'=>false,'reason'=>'extra_unresolved'];
        // NestedRepeaterMutationService intentionally enforces >=1 for generic
        // repeaters. Extras are the exception and may become empty, so rebuild the
        // exact canonical collection via a safe structural copy.
        $items = array_values($items);
        array_splice($items, $index, 1);
        return $this->replaceCollection($block, $storage, $items);
    }

    private function replaceCollection(array $block, array $storagePath, array $items): array
    {
        $set = function (array $source, array $path, mixed $value) use (&$set): array {
            if ($path === []) return is_array($value) ? $value : $source;
            $segment = array_shift($path);
            if (array_is_list($source)) {
                $index = is_int($segment) ? $segment : (ctype_digit((string) $segment) ? (int) $segment : null);
                if ($index === null || ! isset($source[$index])) return $source;
                $source[$index] = $path === [] ? $value : $set((array) $source[$index], $path, $value);
                return array_values($source);
            }
            $key = (string) $segment;
            if (! array_key_exists($key, $source)) return $source;
            $source[$key] = $path === [] ? $value : $set((array) $source[$key], $path, $value);
            return $source;
        };
        $next = $set($block, $storagePath, array_values($items));
        $next = AiFlexStructureContract::normalizePersistedBlock($next);
        return ['block'=>$next,'changed'=>$this->fingerprint($next)!==$this->fingerprint($block)];
    }

    private function applyFieldExtra(array $block, string $action, array $raw, array $context): array
    {
        $target = SparkExtrasContract::normalizeTargetPath((string) ($raw['target_field'] ?? $raw['field_path'] ?? $raw['target'] ?? $context['fieldPath'] ?? $context['field_path'] ?? ''));
        if ($target === null || ! $this->blockPathExists($block, $target)) return ['ok'=>false,'reason'=>'field_extra_target_invalid'];
        $placement = Str::lower(trim((string) ($raw['placement'] ?? 'after')));
        if (! in_array($placement, SparkExtrasContract::PLACEMENTS, true)) return ['ok'=>false,'reason'=>'field_extra_placement_invalid'];

        $state = SparkExtrasContract::normalizeState(is_array($block[SparkExtrasContract::STORAGE_KEY] ?? null) ? $block[SparkExtrasContract::STORAGE_KEY] : []);
        $slots = is_array($state[$target] ?? null) ? $state[$target] : ['before'=>[],'after'=>[]];
        $items = array_values(is_array($slots[$placement] ?? null) ? $slots[$placement] : []);
        $index = $this->extraIndex($items, $raw, $context);

        if ($action === 'add_extra') {
            $extra = SparkExtrasContract::normalizeItem(is_array($raw['extra'] ?? null) ? $raw['extra'] : []);
            if ($extra === null) return ['ok'=>false,'reason'=>'field_extra_invalid'];
            $at = $this->insertIndex($raw);
            $at = $at === null ? count($items) : max(0, min(count($items), $at));
            array_splice($items, $at, 0, [$extra]);
            $index = $at;
        } elseif ($action === 'update_extra') {
            if ($index === null || ! isset($items[$index])) return ['ok'=>false,'reason'=>'extra_unresolved'];
            $patch = is_array($raw['changes'] ?? null) ? $raw['changes'] : (is_array($raw['extra'] ?? null) ? $raw['extra'] : []);
            $candidate = array_replace_recursive($items[$index], $patch);
            $candidate['id'] = $items[$index]['id'] ?? ($candidate['id'] ?? null);
            $extra = SparkExtrasContract::normalizeItem($candidate);
            if ($extra === null) return ['ok'=>false,'reason'=>'field_extra_invalid'];
            $items[$index] = $extra;
        } elseif ($action === 'remove_extra') {
            if ($index === null || ! isset($items[$index])) return ['ok'=>false,'reason'=>'extra_unresolved'];
            array_splice($items, $index, 1);
        } elseif ($action === 'duplicate_extra') {
            if ($index === null || ! isset($items[$index])) return ['ok'=>false,'reason'=>'extra_unresolved'];
            $copy = $items[$index]; unset($copy['id']);
            $copy = SparkExtrasContract::normalizeItem($copy);
            if ($copy === null) return ['ok'=>false,'reason'=>'field_extra_invalid'];
            array_splice($items, $index + 1, 0, [$copy]);
            $index++;
        } elseif ($action === 'move_extra') {
            return $this->moveFieldExtra($block, $target, $placement, $items, $index, $raw, $state);
        } else {
            return ['ok'=>false,'reason'=>'structural_action_invalid'];
        }

        $slots[$placement] = array_values($items);
        if (($slots['before'] ?? []) === [] && ($slots['after'] ?? []) === []) unset($state[$target]);
        else $state[$target] = $slots;
        $next = $block;
        $next[SparkExtrasContract::STORAGE_KEY] = SparkExtrasContract::normalizeState($state);
        return [
            'ok'=>true,'changed'=>$this->fingerprint($next)!==$this->fingerprint($block),'block'=>$next,
            'operation'=>[
                'action'=>$action,'target_field'=>$target,'placement'=>$placement,
                'item_index'=>$index,'extra_type'=>$items[$index]['type'] ?? ($raw['extra']['type'] ?? null),'verified'=>true,
            ],
        ];
    }

    private function moveFieldExtra(array $block, string $target, string $placement, array $items, ?int $index, array $raw, array $state): array
    {
        if ($index === null || ! isset($items[$index])) return ['ok'=>false,'reason'=>'extra_unresolved'];
        $destinationTarget = SparkExtrasContract::normalizeTargetPath((string) ($raw['destination_target_field'] ?? $raw['destination_field_path'] ?? $target));
        $destinationPlacement = Str::lower(trim((string) ($raw['destination_placement'] ?? $placement)));
        if ($destinationTarget === null || ! $this->blockPathExists($block, $destinationTarget) || ! in_array($destinationPlacement, SparkExtrasContract::PLACEMENTS, true)) return ['ok'=>false,'reason'=>'extra_move_target_invalid'];

        $moving = $items[$index];
        array_splice($items, $index, 1);
        $state[$target] = is_array($state[$target] ?? null) ? $state[$target] : ['before'=>[],'after'=>[]];
        $state[$target][$placement] = array_values($items);
        if (($state[$target]['before'] ?? []) === [] && ($state[$target]['after'] ?? []) === []) unset($state[$target]);

        $dest = is_array($state[$destinationTarget] ?? null) ? $state[$destinationTarget] : ['before'=>[],'after'=>[]];
        $destItems = array_values(is_array($dest[$destinationPlacement] ?? null) ? $dest[$destinationPlacement] : []);
        $at = $this->insertIndex($raw);
        $at = $at === null ? count($destItems) : max(0, min(count($destItems), $at));
        array_splice($destItems, $at, 0, [$moving]);
        $dest[$destinationPlacement] = $destItems;
        $state[$destinationTarget] = $dest;

        $next = $block;
        $next[SparkExtrasContract::STORAGE_KEY] = SparkExtrasContract::normalizeState($state);
        return [
            'ok'=>true,'changed'=>true,'block'=>$next,
            'operation'=>[
                'action'=>'move_extra','target_field'=>$target,'placement'=>$placement,
                'destination_target_field'=>$destinationTarget,'destination_placement'=>$destinationPlacement,
                'item_index'=>$index,'to_index'=>$at,'verified'=>true,
            ],
        ];
    }

    private function logicalCollectionPath(array $raw, array $context): string
    {
        $path = trim((string) ($raw['collection_path'] ?? $raw['logical_collection_path'] ?? $context['logicalCollectionPath'] ?? $context['logical_collection_path'] ?? ''));
        if (str_starts_with($path, 'elements')) $path = $this->storageToLogical($path);
        return $path;
    }

    private function deriveColumnsPath(array $raw, array $context): string
    {
        $logicalNode = trim((string) ($raw['target_path'] ?? $context['logicalPath'] ?? $context['logical_path'] ?? ''));
        if (preg_match('/^(rows\.(?:\d+|@[A-Za-z0-9_-]+))(?:\.|$)/', $logicalNode, $m)) return $m[1].'.columns';
        $collection = $this->logicalCollectionPath($raw, $context);
        if (preg_match('/^(rows\.(?:\d+|@[A-Za-z0-9_-]+))\.columns(?:\.|$)/', $collection, $m)) return $m[1].'.columns';
        return '';
    }

    private function storageToLogical(string $path): string
    {
        $parts = array_values(array_filter(explode('.', trim($path, '.')), fn ($v) => $v !== ''));
        if (($parts[0] ?? '') !== 'elements') return '';
        $out = ['rows'];
        if (isset($parts[1])) $out[] = $parts[1];
        if (($parts[2] ?? null) === 'children') $out[] = 'columns';
        if (isset($parts[3])) $out[] = $parts[3];
        if (($parts[4] ?? null) === 'children') $out[] = 'extras';
        foreach (array_slice($parts, 5) as $part) $out[] = $part;
        return implode('.', $out);
    }

    private function index(array $raw, array $context): ?int
    {
        foreach (['item_index','index','source_index'] as $key) if (isset($raw[$key]) && is_numeric($raw[$key])) return max(0, (int) $raw[$key]);
        foreach (['itemIndex','item_index','collectionIndex','collection_index'] as $key) if (isset($context[$key]) && is_numeric($context[$key])) return max(0, (int) $context[$key]);
        return null;
    }

    private function collectionItemIndex(array $block, string $collectionPath, array $raw, array $context): ?int
    {
        $numeric = $this->index($raw, $context);
        if ($numeric !== null) return $numeric;
        $stable = trim((string) ($raw['stable_id'] ?? $raw['item_id'] ?? $context['stableId'] ?? $context['stable_id'] ?? ''));
        if ($stable === '') return null;
        $items = $this->repeaters->valueAtPath($block, $collectionPath);
        if (! is_array($items)) return null;
        foreach ($items as $i => $item) {
            if (! is_array($item)) continue;
            foreach (['_cosmic_id','id','_id','uuid','key'] as $key) {
                if ((string) ($item[$key] ?? '') === $stable) return (int) $i;
            }
        }
        return null;
    }

    private function toIndex(array $raw): ?int
    {
        foreach (['to_index','destination_index'] as $key) if (isset($raw[$key]) && is_numeric($raw[$key])) return max(0, (int) $raw[$key]);
        return null;
    }

    private function insertIndex(array $raw): ?int
    {
        foreach (['insert_index','to_index','destination_index'] as $key) if (isset($raw[$key]) && is_numeric($raw[$key])) return max(0, (int) $raw[$key]);
        return null;
    }

    private function extraIndex(array $items, array $raw, array $context): ?int
    {
        $id = trim((string) ($raw['extra_id'] ?? $raw['item_id'] ?? $context['extraId'] ?? $context['extra_id'] ?? ''));
        if ($id !== '') foreach ($items as $i => $item) if (is_array($item) && (string) ($item['id'] ?? '') === $id) return $i;
        return $this->index($raw, $context);
    }

    private function normalizeAiFlexExtra(array $raw, string $fallbackType = 'text'): ?array
    {
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $merged = array_replace($data, $raw);
        unset($merged['data']);
        $type = Str::lower(trim((string) ($merged['type'] ?? $fallbackType)));
        if (! in_array($type, self::AI_FLEX_EXTRA_TYPES, true) || ! AiFlexComponentRegistry::isRendererReady($type)) return null;

        $out = ['type'=>$type];
        foreach (['_cosmic_id','text','label','url','src','poster','alt','image_query','icon','value'] as $key) {
            if (! array_key_exists($key, $merged)) continue;
            $value = trim(str_replace("\0", '', (string) $merged[$key]));
            if (in_array($key, ['url','src','poster'], true) && ! $this->safeUrl($value, $key !== 'url')) $value = '';
            $out[$key] = Str::limit($value, $key === 'text' ? 5000 : 2048, '');
        }
        foreach (['controls','autoplay','muted','loop','plays_inline'] as $key) if (array_key_exists($key, $merged)) $out[$key] = (bool) $merged[$key];
        if (is_array($merged['style'] ?? null)) {
            $style=[];
            foreach (self::AI_FLEX_STYLE_KEYS as $key) if (array_key_exists($key, $merged['style']) && (is_scalar($merged['style'][$key]) || $merged['style'][$key] === null)) $style[$key] = $merged['style'][$key];
            if ($style !== []) $out['style']=$style;
        }
        if ($type === 'list' && is_array($merged['items'] ?? null)) $out['items'] = array_slice(array_values($merged['items']), 0, 24);
        if ($type === 'form' && is_array($merged['fields'] ?? null)) $out['fields'] = array_slice(array_values($merged['fields']), 0, 20);
        if (in_array($type, ['group','grid','stack','card','background_image','background_video','overlay','slider','slide','button_group','media_group'], true)) $out['children'] = is_array($merged['children'] ?? null) ? array_slice(array_values($merged['children']), 0, 40) : [];
        if ($type === 'background_video') { $out['controls']=false; $out['autoplay']=$merged['autoplay'] ?? true; $out['muted']=$merged['muted'] ?? true; $out['loop']=$merged['loop'] ?? true; $out['plays_inline']=$merged['plays_inline'] ?? true; }
        if (in_array($type, ['button_group','media_group'], true) && is_array($out['children'] ?? null)) {
            $allowedChildren = AiFlexComponentRegistry::allowedChildren($type);
            $out['children'] = array_values(array_filter($out['children'], static fn ($child): bool => is_array($child) && in_array(Str::lower(trim((string) ($child['type'] ?? ''))), $allowedChildren, true)));
            $out['children'] = array_slice($out['children'], 0, 12);
        }
        if ($type === 'slider') {
            $out['autoplay'] = array_key_exists('autoplay', $merged) ? (bool) $merged['autoplay'] : true;
            $out['interval'] = max(2000, min(15000, (int) ($merged['interval'] ?? 5000)));
            $out['loop'] = array_key_exists('loop', $merged) ? (bool) $merged['loop'] : true;
            $out['show_arrows'] = array_key_exists('show_arrows', $merged) ? (bool) $merged['show_arrows'] : true;
            $out['show_dots'] = array_key_exists('show_dots', $merged) ? (bool) $merged['show_dots'] : true;
            $out['show_counter'] = array_key_exists('show_counter', $merged) ? (bool) $merged['show_counter'] : false;
            $out['transition'] = in_array((string) ($merged['transition'] ?? 'fade'), ['fade','slide'], true) ? (string) $merged['transition'] : 'fade';
            $out['children'] = array_values(array_filter($out['children'], static fn ($child): bool => is_array($child) && (($child['type'] ?? '') === 'slide')));
            $out['children'] = array_slice($out['children'], 0, 8);
        }
        if (! isset($out['_cosmic_id'])) $out['_cosmic_id'] = 'extra_'.Str::uuid()->toString();
        return $out;
    }

    private function newRow(): array
    {
        return [
            'type'=>'row','_cosmic_id'=>'row_'.Str::uuid()->toString(),'style'=>['gap'=>24],
            'children'=>[$this->newColumn()],
        ];
    }

    private function newColumn(): array
    {
        return ['type'=>'column','_cosmic_id'=>'column_'.Str::uuid()->toString(),'style'=>['width'=>100],'children'=>[]];
    }

    private function blockPathExists(array $block, string $path): bool
    {
        $cursor = $block;
        foreach (explode('.', $path) as $segment) {
            if (is_array($cursor) && array_is_list($cursor)) {
                if (ctype_digit($segment)) { $i=(int)$segment; if (! array_key_exists($i,$cursor)) return false; $cursor=$cursor[$i]; continue; }
                if (str_starts_with($segment,'@')) {
                    $id=substr($segment,1); $found=false;
                    foreach ($cursor as $item) if (is_array($item) && in_array($id, array_map('strval', array_filter([$item['_cosmic_id']??null,$item['id']??null,$item['_id']??null,$item['uuid']??null,$item['key']??null], fn($v)=>$v!==null)), true)) { $cursor=$item; $found=true; break; }
                    if (! $found) return false; continue;
                }
                return false;
            }
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) return false;
            $cursor = $cursor[$segment];
        }
        return true;
    }

    private function safeUrl(string $url, bool $media): bool
    {
        if ($url === '' || preg_match('/^(?:\/|\.\/|\.\.\/|#)/', $url)) return true;
        if (preg_match('/^(?:javascript|vbscript|data):/i', $url)) return false;
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, $media ? ['http','https'] : ['http','https','mailto','tel'], true);
    }

    private function compactAction(array $action): array
    {
        return array_intersect_key($action, array_flip(['action','target_field','placement','collection_path','item_index','to_index','destination_collection_path']));
    }

    private function fingerprint(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '');
    }
}
