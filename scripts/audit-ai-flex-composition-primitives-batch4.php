<?php
require_once __DIR__.'/../app/Support/AiFlexComponentRegistry.php';
use App\Support\AiFlexComponentRegistry;
$checks=[];$assert=static function(bool $ok,string $label)use(&$checks):void{$checks[]=[$ok,$label];};
$ready=AiFlexComponentRegistry::rendererReadyTypes();
foreach(['button_group','media_group'] as $type){$assert(in_array($type,$ready,true),"$type is renderer-ready");$assert(in_array($type,AiFlexComponentRegistry::containerTypes(),true),"$type is a container");}
$assert(AiFlexComponentRegistry::allowedChildren('button_group')===['button'],'button_group accepts buttons only');
$assert(AiFlexComponentRegistry::allowedChildren('media_group')===['image','video'],'media_group accepts image/video only');
$inventory=AiFlexComponentRegistry::promptInventory();
$assert(str_contains($inventory,'button_group children=button'),'registry prompt exposes button_group contract');
$assert(str_contains($inventory,'media_group children=image|video'),'registry prompt exposes media_group contract');
$service=file_get_contents(__DIR__.'/../app/Services/LunaAiFlexSparkService.php')?:'';
$react=file_get_contents(__DIR__.'/../resources/js/Pages/Websites/Blocks/General/LunaCustomSectionBlock.jsx')?:'';
$compiler=file_get_contents(__DIR__.'/../app/Helpers/CmsHtmlCompiler.php')?:'';
$structure=file_get_contents(__DIR__.'/../resources/js/Pages/Websites/Blocks/Shared/aiFlexStructureContract.js')?:'';
$structural=file_get_contents(__DIR__.'/../app/Services/LunaStructuralActionService.php')?:'';
$editor=file_get_contents(__DIR__.'/../app/Services/LunaSparkSchemaEditorService.php')?:'';
$assert(str_contains($service,'button_group is an action container'),'Luna prompt teaches grouped CTA semantics');
$assert(str_contains($service,'media_group is a media composition container'),'Luna prompt teaches media composition semantics');
$assert(str_contains($service,'AiFlexComponentRegistry::allowedChildren($type)'),'validator enforces registry child contracts');
$assert(str_contains($service,"['button_group','media_group']"),'validator caps new composition groups');
foreach(['cosmic-flex-button-group','cosmic-flex-media-group'] as $marker){$assert(str_contains($react,$marker),"Builder renderer has $marker");$assert(str_contains($compiler,$marker),"publish compiler has $marker");}
$assert(str_contains($react,"if(type==='button_group')"),'Builder style engine owns button-group flex mechanics');
$assert(str_contains($react,"type==='media_group'"),'Builder style engine owns media-group grid mechanics');
$assert(str_contains($react,'.cosmic-flex-button-group>.cosmic-flex-button'),'Builder stacks grouped CTAs on mobile');
$assert(str_contains($compiler,'.cosmic-flex-button-group>.cosmic-flex-button'),'published CSS stacks grouped CTAs on mobile');
$assert(str_contains($compiler,"elseif(\$type==='media_group')"),'publish compiler owns media-group columns');
$assert(str_contains($structure,"'button_group','media_group'"),'client factory allowlist includes Batch 4 primitives');
$assert(str_contains($structure,"safe === 'button_group'"),'client factory provides button-group default');
$assert(str_contains($structure,"safe === 'media_group'"),'client factory provides media-group default');
$assert(str_contains($structural,"'button_group','media_group'"),'structural executor accepts Batch 4 primitives');
$assert(str_contains($structural,"['button_group','media_group']"),'structural executor filters Batch 4 children');
$assert(str_contains($editor,'button_group,media_group'),'contextual Luna editor knows Batch 4 primitives');
$failed=array_values(array_filter($checks,static fn(array $c):bool=>!$c[0]));foreach($checks as[$ok,$label])echo($ok?'[PASS] ':'[FAIL] ').$label.PHP_EOL;echo PHP_EOL.count($checks).' checks, '.count($failed).' failed.'.PHP_EOL;exit($failed===[]?0:1);
