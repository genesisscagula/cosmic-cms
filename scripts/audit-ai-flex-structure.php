<?php

namespace Illuminate\Support {
    final class Str {
        public static function uuid(): object {
            return new class {
                public function toString(): string { return bin2hex(random_bytes(16)); }
            };
        }
    }
}

namespace {
    require __DIR__.'/../app/Support/AiFlexStructureContract.php';

    use App\Support\AiFlexStructureContract;

    $legacy = [
        ['type'=>'heading','text'=>'Title'],
        ['type'=>'text','text'=>'Body'],
    ];
    $elements = AiFlexStructureContract::canonicalizeElements($legacy);
    if (count($elements) !== 1 || ($elements[0]['type'] ?? '') !== 'row') throw new RuntimeException('row canonicalization failed');
    if (($elements[0]['children'][0]['type'] ?? '') !== 'column') throw new RuntimeException('column canonicalization failed');
    if (count($elements[0]['children'][0]['children'] ?? []) !== 2) throw new RuntimeException('extras canonicalization failed');
    if (! AiFlexStructureContract::isCanonical($elements)) throw new RuntimeException('canonical check failed');

    $path = AiFlexStructureContract::logicalToStoragePath('rows.0.columns.1.extras');
    if (implode('.', array_map('strval', $path)) !== 'elements.0.children.1.children') throw new RuntimeException('logical path mapping failed');

    $block = AiFlexStructureContract::canonicalizeBlock(['type'=>'luna_custom_section','elements'=>$legacy,'ai_flex'=>[]]);
    if (($block['ai_flex']['structure_contract'] ?? '') !== AiFlexStructureContract::CONTRACT) throw new RuntimeException('metadata contract failed');

    $ids = [];
    $walk = function(array $nodes) use (&$walk, &$ids): void {
        foreach ($nodes as $node) {
            if (! is_array($node)) continue;
            $id = (string)($node[AiFlexStructureContract::ID_KEY] ?? '');
            if ($id === '') throw new RuntimeException('missing stable id');
            if (isset($ids[$id])) throw new RuntimeException('duplicate stable id');
            $ids[$id] = true;
            if (is_array($node['children'] ?? null)) $walk($node['children']);
        }
    };
    $walk($block['elements']);

    echo "AI Flex Batch 4 PHP contract PASS\n";
}
