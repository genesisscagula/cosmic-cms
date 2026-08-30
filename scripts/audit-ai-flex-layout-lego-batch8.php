<?php
require_once __DIR__.'/../app/Support/AiFlexComponentRegistry.php';
require_once __DIR__.'/../app/Support/AiFlexLayoutRecipeRegistry.php';
require_once __DIR__.'/../app/Support/AiFlexComposerGuide.php';

use App\Support\AiFlexComposerGuide;
use App\Support\AiFlexLayoutRecipeRegistry;

$pass=0; $fail=0;
$assert=function(bool $ok,string $label) use (&$pass,&$fail){ echo ($ok?'PASS':'FAIL')." - {$label}\n"; $ok?$pass++:$fail++; };

$defs=AiFlexLayoutRecipeRegistry::definitions();
$expected=['single_centered','content_narrow','two_equal','two_40_60','two_60_40','three_equal','four_equal','sidebar_left','sidebar_right','split_media_left','split_media_right','bento_2x2','bento_featured_left','bento_featured_right'];
foreach($expected as $key) $assert(isset($defs[$key]),"layout Lego registered: {$key}");

$cases=[
 'Make a two column about section'=>'two_equal',
 'Use a 40/60 split with copy and media'=>'two_40_60',
 'Use a 60/40 layout'=>'two_60_40',
 'Create 3 columns of services'=>'three_equal',
 'Create four columns for features'=>'four_equal',
 'Add a left sidebar'=>'sidebar_left',
 'Put the image on the right'=>'split_media_right',
 'Use media left with content right'=>'split_media_left',
 'Create a bento 2x2 services layout'=>'bento_2x2',
 'Create a bento with a large card left'=>'bento_featured_left',
 'Use narrow content for the intro'=>'content_narrow',
];
foreach($cases as $prompt=>$key){
 $found=AiFlexLayoutRecipeRegistry::detect($prompt);
 $assert(($found['key']??null)===$key,"detects {$key}");
 $plan=AiFlexComposerGuide::forRequest($prompt);
 $assert(($plan['layout_recipe']['key']??null)===$key,"composer carries {$key}");
}

$two=AiFlexLayoutRecipeRegistry::get('two_equal');
$assert(($two['desktop_widths']??[])===[50,50],'two_equal preserves semantic 50/50 ratio');
$three=AiFlexLayoutRecipeRegistry::get('three_equal');
$assert(count($three['desktop_widths']??[])===3,'three_equal has three deterministic lanes');
$bento=AiFlexLayoutRecipeRegistry::get('bento_2x2');
$assert(($bento['kind']??null)==='grid' && ($bento['desktop_columns']??null)===2,'bento 2x2 is deterministic 2-column grid');
$assert(($bento['mobile_columns']??null)===1,'bento collapses to one mobile column');
$assert(AiFlexLayoutRecipeRegistry::CONTRACT==='ai_flex_layout_recipes_v1','layout Lego contract is versioned');
$assert(!str_contains(file_get_contents(__DIR__.'/../app/Support/AiFlexLayoutRecipeRegistry.php'),'registeredFallback'),'layout Lego registry never searches Sparks');

$service=file_get_contents(__DIR__.'/../app/Services/LunaAiFlexSparkService.php');
$assert(str_contains($service,'AI FLEX LAYOUT LEGO REGISTRY'),'Luna receives layout Lego inventory');
$assert(str_contains($service,'applyLayoutRecipeGeometry'),'recipe geometry is engine-normalized after generation');
$assert(str_contains($service,'assertLayoutRecipeSatisfied'),'recipe shape is contract-validated');
$assert(str_contains($service,"'layout_recipe_contract'"),'layout recipe contract persists in AI Flex metadata');
$assert(str_contains($service,'$usable = $n === 2 ? 96.0'),'row widths reserve deterministic desktop gap budget');
$assert(str_contains($service, "\$col['style']['tablet_width'] = 100") && str_contains($service, "\$col['style']['mobile_width'] = 100"),'row recipes collapse safely on smaller screens');

printf("\nBatch 8 Layout Lego: %d/%d PASS\n",$pass,$pass+$fail);
exit($fail===0?0:1);
