<?php

require_once __DIR__.'/../app/Support/AiFlexComponentRegistry.php';

use App\Support\AiFlexComponentRegistry;

$checks=[];
$assert=static function(bool $ok,string $label) use (&$checks):void{$checks[]=[$ok,$label];};

$ready=AiFlexComponentRegistry::rendererReadyTypes();
foreach(['background_image','background_video','overlay'] as $type) {
    $assert(in_array($type,$ready,true), "$type is renderer-ready");
    $assert(in_array($type,AiFlexComponentRegistry::containerTypes(),true), "$type is a composable container");
}
foreach(['button_group','media_group'] as $type) $assert(AiFlexComponentRegistry::isRendererReady($type), "$type is activated by Batch 4");
$assert(AiFlexComponentRegistry::scalarKeys('background_image') === ['src','alt','image_query'], 'background image scalar contract');
$assert(in_array('autoplay',AiFlexComponentRegistry::scalarKeys('background_video'),true), 'background video playback contract');
$assert(str_contains(AiFlexComponentRegistry::promptInventory(),'background_image children=*'), 'Luna inventory exposes background image');
$assert(str_contains(AiFlexComponentRegistry::promptInventory(),'background_video children=*'), 'Luna inventory exposes background video');
$assert(str_contains(AiFlexComponentRegistry::promptInventory(),'overlay children=*'), 'Luna inventory exposes overlay');
$assert(str_contains(AiFlexComponentRegistry::promptInventory(),'slider children=slide'), 'Luna inventory exposes activated slider after Batch 3');

$service=file_get_contents(__DIR__.'/../app/Services/LunaAiFlexSparkService.php')?:'';
$react=file_get_contents(__DIR__.'/../resources/js/Pages/Websites/Blocks/General/LunaCustomSectionBlock.jsx')?:'';
$compiler=file_get_contents(__DIR__.'/../app/Helpers/CmsHtmlCompiler.php')?:'';
$structure=file_get_contents(__DIR__.'/../resources/js/Pages/Websites/Blocks/Shared/aiFlexStructureContract.js')?:'';
$structural=file_get_contents(__DIR__.'/../app/Services/LunaStructuralActionService.php')?:'';
$editor=file_get_contents(__DIR__.'/../app/Services/LunaSparkSchemaEditorService.php')?:'';

$assert(str_contains($service,"['_cosmic_id','key','text','label','url','src','poster','alt','image_query','icon','value']"),'validator preserves media query scalar');
$assert(str_contains($service,"in_array(\$type, ['video','background_video'], true)"),'validator handles background video playback');
$assert(str_contains($service,"\$type === 'background_video' ? false"),'background video controls forced off');
$assert(str_contains($service,'background_image and background_video are composable containers'),'Luna prompt teaches media container semantics');
$assert(str_contains($service,'overlay is a composable container'),'Luna prompt teaches overlay semantics');
$assert(str_contains($service,'hydrateReferenceMedia($completed, $request, $semantic, 0)'),'staged blank generation hydrates requested background images safely');
$assert(str_contains($service,"['image','background_image']"),'safe media resolver includes background_image');

foreach(['cosmic-flex-background-image','cosmic-flex-background-video','cosmic-flex-overlay'] as $marker) {
    $assert(str_contains($react,$marker), "Builder renderer has $marker");
    $assert(str_contains($compiler,$marker), "publish compiler has $marker");
}
$assert(str_contains($react,'controls={false}'),'Builder background video never exposes controls');
$assert(str_contains($react,"autoPlay={node.autoplay!==false}"),'Builder background video autoplay defaults on');
$assert(str_contains($compiler,"class='cosmic-flex-el cosmic-flex-background-video'"),'compiler emits background video wrapper');
$assert(str_contains($compiler,"pointer-events:none"),'compiler media layer is non-interactive');
$assert(str_contains($structure,"'background_image','background_video','overlay'"),'client AI Flex factory allows Batch 2 primitives');
$assert(str_contains($structure,"safe === 'background_video'"),'client factory provides safe background-video defaults');
$assert(str_contains($structural,"'background_image','background_video','overlay'"),'structural executor accepts Batch 2 primitives');
$assert(str_contains($structural,'AiFlexComponentRegistry::isRendererReady($type)'),'structural executor checks canonical registry readiness');
$assert(str_contains($editor,'background_image,background_video,overlay'),'contextual Luna structural prompt knows Batch 2 primitives');

$failed=array_values(array_filter($checks,static fn(array $c):bool=>!$c[0]));
foreach($checks as [$ok,$label]) echo ($ok?'[PASS] ':'[FAIL] ').$label.PHP_EOL;
echo PHP_EOL.count($checks).' checks, '.count($failed).' failed.'.PHP_EOL;
exit($failed===[]?0:1);
