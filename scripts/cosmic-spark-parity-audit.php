<?php
$root = dirname(__DIR__);
$compiler = @file_get_contents($root.'/app/Helpers/CmsHtmlCompiler.php') ?: '';
$builder = @file_get_contents($root.'/resources/js/Pages/Websites/Builder.jsx') ?: '';
$sparkRegistry = @file_get_contents($root.'/resources/js/Pages/Websites/Components/SparkRegistry.jsx') ?: '';
$blockRegistry = @file_get_contents($root.'/resources/js/Pages/Websites/BlockRegistry.jsx') ?: '';
$compiled = [];
if (preg_match_all('/case\s+["\']([a-z0-9_\-]+)["\']\s*:/i', $compiler, $m)) {
    $compiled = array_values(array_unique($m[1]));
}
$declared = [];
foreach ([$sparkRegistry,$blockRegistry] as $source) {
    if (preg_match_all('/\b(?:type|key)\s*:\s*["\']([a-z0-9_\-]+)["\']/i', $source, $m)) {
        $declared = array_merge($declared,$m[1]);
    }
}
$declared = array_values(array_unique($declared));
$checks = [
    'builder_render_shell' => str_contains($builder, 'data-cosmic-render-shell="1"'),
    'builder_block_type' => str_contains($builder, 'data-cosmic-block-type={block.type}'),
    'builder_block_index' => str_contains($builder, 'data-cosmic-block-index={index}'),
    'compiler_no_global_nested_tagging' => !str_contains($compiler, "preg_replace('/<section(?![^>]*data-cosmic-spark)/i', '<section data-cosmic-spark'"),
    'compiler_root_index' => str_contains($compiler, 'data-cosmic-block-index=\'{$semanticIndex}\''),
    'compiler_root_type' => str_contains($compiler, 'data-cosmic-block-type=\'{$semanticType}\''),
];
echo json_encode([
    'declared_types_detected' => count($declared),
    'compiler_cases_detected' => count($compiled),
    'declared_not_seen_as_compiler_case' => array_values(array_slice(array_diff($declared,$compiled),0,80)),
    'checks' => $checks,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit(in_array(false,$checks,true)?1:0);
