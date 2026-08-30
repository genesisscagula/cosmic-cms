<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root.'/app/Support/AiFlexComponentRegistry.php';
require_once $root.'/app/Support/AiFlexStructureContract.php';
require_once $root.'/app/Support/SparkExtrasContract.php';

use App\Support\AiFlexComponentRegistry;
use App\Support\SparkExtrasContract;

$fail = 0; $checks = 0;
$check = function (bool $ok, string $label) use (&$fail, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ').$label.PHP_EOL;
    if (!$ok) $fail++;
};
$src = fn(string $path): string => file_get_contents($root.'/'.$path) ?: '';

$registry = AiFlexComponentRegistry::definitions();
foreach (['background_image','background_video','overlay','slider','slide','button_group','media_group'] as $type) {
    $check(($registry[$type]['renderer_ready'] ?? false) === true, $type.' remains renderer-ready');
}

// Persistence roundtrip: save-time normalization must preserve the complete trusted tree.
$block = [
    'type' => 'luna_custom_section',
    'custom_spark_key' => 'qa-flex-1',
    'heading' => 'QA Hero',
    'ai_flex' => ['generated'=>true,'structure_contract'=>'rows_columns_extras_v1','spark_name'=>'QA Hero Slider'],
    'elements' => [[
        'type'=>'row','_cosmic_id'=>'row_qa','children'=>[[
            'type'=>'column','_cosmic_id'=>'column_qa','children'=>[[
                'type'=>'slider','_cosmic_id'=>'extra_slider','autoplay'=>true,'children'=>[[
                    'type'=>'slide','_cosmic_id'=>'extra_slide_1','children'=>[[
                        'type'=>'background_image','_cosmic_id'=>'extra_bg_1','src'=>'/img/one.jpg','children'=>[[
                            'type'=>'overlay','_cosmic_id'=>'extra_overlay_1','children'=>[[
                                'type'=>'stack','_cosmic_id'=>'extra_stack_1','children'=>[[
                                    'type'=>'heading','_cosmic_id'=>'extra_heading_1','text'=>'First slide'
                                ],[
                                    'type'=>'button_group','_cosmic_id'=>'extra_actions_1','children'=>[[
                                        'type'=>'button','_cosmic_id'=>'extra_button_1','label'=>'Explore','url'=>'#explore'
                                    ]]
                                ]]
                            ]]
                        ]]
                    ]]
                ],[
                    'type'=>'slide','_cosmic_id'=>'extra_slide_2','children'=>[[
                        'type'=>'background_video','_cosmic_id'=>'extra_bg_video','src'=>'/video/demo.mp4','autoplay'=>true,'muted'=>true,'children'=>[[
                            'type'=>'media_group','_cosmic_id'=>'extra_media_group','children'=>[[
                                'type'=>'image','_cosmic_id'=>'extra_image_1','src'=>'/img/two.jpg'
                            ]]
                        ]]
                    ]]
                ]]
            ]]
        ]]
    ]]
];
$normalized = SparkExtrasContract::normalizeBlock($block);
$roundtrip = json_decode(json_encode($normalized, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
$json = json_encode($roundtrip, JSON_THROW_ON_ERROR);
foreach (['slider','slide','background_image','background_video','overlay','button_group','media_group'] as $type) {
    $check(str_contains($json, '"type":"'.$type.'"'), 'save/reload preserves '.$type);
}
$check(($roundtrip['ai_flex']['spark_name'] ?? null) === 'QA Hero Slider', 'save/reload preserves Luna spark_name');
$check(($roundtrip['ai_flex']['generated'] ?? null) === true, 'save/reload preserves generated marker');

$builder = $src('resources/js/Pages/Websites/Builder.jsx');
$saved = $src('app/Http/Controllers/SavedSparkController.php');
$page = $src('app/Http/Controllers/PageController.php');
$compiler = $src('app/Helpers/CmsHtmlCompiler.php');
$react = $src('resources/js/Pages/Websites/Blocks/General/LunaCustomSectionBlock.jsx');
$service = $src('app/Services/LunaAiFlexSparkService.php');
$routes = $src('routes/web.php');

$check(str_contains($builder, "session.creationSource === 'blank_luna'"), 'blank Luna auto-save is Apply-gated');
$check(str_contains($builder, 'await saveAppliedLunaSpark(appliedBlock)'), 'Apply saves exact generated Luna block');
$check(str_contains($builder, 'delete payload._saved_spark_meta'), 'Luna Spark reuse strips library-only metadata');
$check(str_contains($builder, "creationSource: savedSpark?.source === 'luna' ? 'luna_saved'"), 'Luna Spark reuse is a fresh non-overwriting draft');
$check(str_contains($saved, "'source' => ['nullable', 'string', 'in:manual,luna']"), 'Saved Spark backend accepts Luna provenance');
$check(str_contains($routes, "Route::post('/saved-sparks'"), 'Saved Sparks persistence route exists');
$check(str_contains($page, 'SparkExtrasContract::normalizeBlocks'), 'page save runs AI Flex-safe normalization');
$check(str_contains($page, 'CmsHtmlCompiler::compile($page->blocks ?? []'), 'publish compiles saved page blocks');

$check(str_contains($compiler, "data-ai-flex-slider"), 'publish renderer emits AI Flex slider');
$check(str_contains($compiler, "data-ai-flex-track"), 'publish renderer emits scoped slider track');
$check(!str_contains($compiler, "r.querySelectorAll('[data-ai-flex-slide]')"), 'published slider no longer captures nested slider slides');
$check(str_contains($compiler, "Array.prototype.slice.call(track.children)"), 'published slider scopes slides to direct track children');
$check(str_contains($compiler, '$backgroundAutoplay=($node[\'autoplay\']??true)'), 'published background video derives autoplay state');
$check(str_contains($compiler, '$backgroundAutoplay||($node[\'muted\']??true)'), 'published autoplay background video is forced muted');
$check(str_contains($react, 'muted={node.autoplay!==false?true:node.muted!==false}'), 'Builder autoplay background video is forced muted');
$check(str_contains($service, "if (\$type === 'background_video' && \$node['autoplay']) \$node['muted'] = true;"), 'AI validator normalizes autoplay background video to muted');

$check(str_contains($compiler, 'prefers-reduced-motion: reduce'), 'published interactive media respects reduced motion');
$check(str_contains($react, "prefers-reduced-motion: reduce"), 'Builder slider respects reduced motion');
$check(str_contains($compiler, "pointerdown" ) && str_contains($compiler, "pointerup"), 'published slider retains swipe/pointer navigation');
$check(str_contains($react, 'onTouchStart') && str_contains($react, 'onTouchEnd'), 'Builder slider retains swipe/touch navigation');

printf("\nBatch 6 Final Parity: %d/%d PASS\n", $checks - $fail, $checks);
exit($fail === 0 ? 0 : 1);
