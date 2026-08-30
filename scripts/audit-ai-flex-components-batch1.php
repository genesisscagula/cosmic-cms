<?php

require_once __DIR__.'/../app/Support/AiFlexComponentRegistry.php';

use App\Support\AiFlexComponentRegistry;

$checks = [];
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks[] = [$ok, $label];
};

$ready = AiFlexComponentRegistry::rendererReadyTypes();
$planned = AiFlexComponentRegistry::plannedTypes();
$expectedReady = ['group','row','column','grid','stack','card','heading','text','button','image','video','icon','badge','list','divider','stat','spacer','form','background_image','background_video','overlay','slider','slide','button_group','media_group'];
$expectedPlanned = [];

$assert(AiFlexComponentRegistry::CONTRACT === 'ai_flex_components_v1', 'component contract id');
$assert(AiFlexComponentRegistry::VERSION === 1, 'component contract version');
$assert($ready === $expectedReady, 'renderer-ready primitives match current activated contract');
$assert($planned === $expectedPlanned, 'all planned primitives are now activated through Batch 4');
$assert(AiFlexComponentRegistry::allowedChildren('row') === ['column'], 'row accepts columns only');
$assert(AiFlexComponentRegistry::allowedChildren('slider') === ['slide'], 'slider contract reserves slide children');
$assert(AiFlexComponentRegistry::allowedChildren('button_group') === ['button'], 'button group contract reserves buttons');
$assert(AiFlexComponentRegistry::isRendererReady('video'), 'existing video remains renderer-ready');
$assert(AiFlexComponentRegistry::isRendererReady('background_video'), 'background video activated in Batch 2');
$assert(str_contains(AiFlexComponentRegistry::promptInventory(), 'row children=column'), 'prompt inventory exposes structural rule');
$assert(str_contains(AiFlexComponentRegistry::promptInventory(), 'slider children=slide'), 'prompt inventory exposes activated slider contract');

$service = file_get_contents(__DIR__.'/../app/Services/LunaAiFlexSparkService.php') ?: '';
$assert(str_contains($service, 'AiFlexComponentRegistry::rendererReadyTypes()'), 'sanitizer reads allowed types from registry');
$assert(str_contains($service, 'AiFlexComponentRegistry::containerTypes()'), 'sanitizer reads container types from registry');
$assert(str_contains($service, 'AiFlexComponentRegistry::styleKeys()'), 'sanitizer reads style vocabulary from registry');
$assert(str_contains($service, "'component_contract'=>AiFlexComponentRegistry::CONTRACT"), 'validated blocks record component contract');
$assert(str_contains($service, 'AiFlexComponentRegistry::promptInventory()'), 'Luna prompt receives renderer-ready inventory');

$failed = array_values(array_filter($checks, static fn (array $check): bool => ! $check[0]));
foreach ($checks as [$ok, $label]) echo ($ok ? '[PASS] ' : '[FAIL] ').$label.PHP_EOL;
echo PHP_EOL.count($checks).' checks, '.count($failed).' failed.'.PHP_EOL;
exit($failed === [] ? 0 : 1);
