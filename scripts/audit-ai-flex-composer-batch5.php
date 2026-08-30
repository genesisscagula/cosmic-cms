<?php

require_once __DIR__.'/../app/Support/AiFlexComponentRegistry.php';
require_once __DIR__.'/../app/Support/AiFlexLayoutRecipeRegistry.php';
require_once __DIR__.'/../app/Support/AiFlexComposerGuide.php';

use App\Support\AiFlexComposerGuide;
use App\Support\AiFlexComponentRegistry;

$pass=0; $fail=0;
$assert=function(bool $ok,string $label) use (&$pass,&$fail){ echo ($ok?'PASS':'FAIL')." - {$label}\n"; $ok?$pass++:$fail++; };

$slider=AiFlexComposerGuide::forRequest('Create a premium hero slider with background images and two CTA buttons');
$assert(in_array('slider',$slider['required_components'],true),'slider request requires slider primitive');
$assert(in_array('slide',$slider['required_components'],true),'slider request requires slide primitive');
$assert(in_array('background_image',$slider['required_components'],true),'explicit background image requires background_image');
$assert(in_array('overlay',$slider['required_components'],true),'background media requires overlay');
$assert(in_array('button_group',$slider['required_components'],true),'explicit paired CTA request requires button_group');
$assert(str_contains($slider['recipe'],'slider'),'slider recipe is primitive-led');

$video=AiFlexComposerGuide::forRequest('Build a hero with a background video and headline');
$assert(in_array('background_video',$video['required_components'],true),'background video is a required trusted primitive');
$assert(in_array('overlay',$video['required_components'],true),'background video composition requires overlay');
$assert(!in_array('slider',$video['required_components'],true),'non-slider video request does not invent slider');

$gallery=AiFlexComposerGuide::forRequest('Create an editorial image gallery');
$assert(in_array('media_group',$gallery['required_components'],true),'gallery requires media_group');
$assert(in_array('image',$gallery['preferred_components'],true),'gallery prefers image leaves');

$services=AiFlexComposerGuide::forRequest('Premium services section with six feature cards');
$assert(in_array('grid',$services['preferred_components'],true),'services prefer grid');
$assert(in_array('card',$services['preferred_components'],true),'services prefer cards');
$assert($services['semantic_hint']==='services','services semantic hint retained');

$plain=AiFlexComposerGuide::forRequest('Simple centered introduction with a heading and paragraph');
$assert($plain['required_components']===[],'plain content does not over-constrain required components');
$assert($plain['profile']===AiFlexComposerGuide::PROFILE,'composer profile versioned');

foreach(array_merge($slider['required_components'],$slider['preferred_components']) as $type){
    $assert(AiFlexComponentRegistry::isRendererReady($type),"composer only recommends renderer-ready {$type}");
}

$service=file_get_contents(__DIR__.'/../app/Services/LunaAiFlexSparkService.php');
$assert(str_contains($service,'AiFlexComposerGuide::forRequest($request)'),'generation invokes composer guide');
$assert(str_contains($service,"'composer_plan' => \$composerPlan") || str_contains($service,"'composer_plan'=>\$composerPlan"),'composer plan is sent to Luna');
$assert(str_contains($service,'assertComposerPlanSatisfied'),'required primitive contract is post-validated');
$assert(str_contains($service,'stampComposerMetadata'),'composer metadata is stamped');
$assert(!str_contains(file_get_contents(__DIR__.'/../app/Support/AiFlexComposerGuide.php'),'registeredFallback'),'composer guide never invokes Spark matcher');

printf("\nBatch 5 Composer: %d/%d PASS\n",$pass,$pass+$fail);
exit($fail===0?0:1);
