<?php
require_once __DIR__.'/../app/Support/AiFlexComponentRegistry.php';
require_once __DIR__.'/../app/Support/AiFlexLayoutRecipeRegistry.php';
require_once __DIR__.'/../app/Support/AiFlexComposerGuide.php';

use App\Support\AiFlexComposerGuide;

$pass=0; $fail=0;
$assert=function(bool $ok,string $label) use (&$pass,&$fail){ echo ($ok?'PASS':'FAIL')." - {$label}\n"; $ok?$pass++:$fail++; };

$hero='Create a premium architectural hero slider with 3 slides. Each slide should have a different background image with a dark gradient overlay, eyebrow text, large heading, short description, and two CTA buttons. Show arrows, dots and slide counter.';
$plan=AiFlexComposerGuide::forRequest($hero);
foreach(['slider','slide','background_image','overlay','button_group'] as $type){
    $assert(in_array($type,$plan['required_components']??[],true),"mixed hero requires {$type}");
}
$assert(($plan['requested_slide_count']??null)===3,'explicit 3-slide quantity captured');
$assert(($plan['slider_controls']['show_arrows']??false)===true,'arrows intent captured');
$assert(($plan['slider_controls']['show_dots']??false)===true,'dots intent captured');
$assert(($plan['slider_controls']['show_counter']??false)===true,'counter intent captured');
$assert(AiFlexComposerGuide::VERSION>=3,'composer contract version bumped');

$four=AiFlexComposerGuide::forRequest('Create a 4 slide carousel with arrows');
$assert(($four['requested_slide_count']??null)===4,'4-slide quantity captured');
$assert(($four['slider_controls']['show_arrows']??false)===true,'single requested slider control captured');
$assert(($four['slider_controls']['show_dots']??false)===false,'unrequested dots remain optional');

$service=file_get_contents(__DIR__.'/../app/Services/LunaAiFlexSparkService.php');
$checks=[
 'fast lane detected from page context'=>str_contains($service,'$fastComposer = (bool) ($pageContext[\'fast_composer\'] ?? false)'),
 'fast lane skips compatibility retry'=>str_contains($service,'if (! $fastComposer)'),
 'fast timeout is bounded/configurable'=>str_contains($service,"config('openai.ai_flex_fast_timeout', 75)"),
 'fast connect timeout reduced'=>str_contains($service,'$connectTimeout = $fastComposer ? 15 : 30'),
 'slider control normalization exists'=>str_contains($service,'applyComposerIntentContracts'),
 'slide quantity assertion exists'=>str_contains($service,'assertComposerQuantitiesSatisfied'),
 'requested slide metadata persists'=>str_contains($service,"'requested_slide_count'"),
 'requested slider control metadata persists'=>str_contains($service,"'requested_slider_controls'"),
 'quantity failure is explicit'=>str_contains($service,'slides; requested'),
 'staged fallback remains'=>str_contains($service,'STRUCTURE FALLBACK PASS ONLY.'),
 'content fallback remains'=>str_contains($service,'CONTENT FALLBACK PASS.'),
];
foreach($checks as $label=>$ok) $assert($ok,$label);

$registry=file_get_contents(__DIR__.'/../app/Support/AiFlexComposerGuide.php');
$assert(str_contains($registry,'inside every slide'),'composer explicitly asks for per-slide background media/overlay');
$assert(!str_contains($registry,'registeredFallback'),'smart composer remains Spark-search free');

printf("\nBatch 9 Smart Composer Torture: %d/%d PASS\n",$pass,$pass+$fail);
exit($fail===0?0:1);
