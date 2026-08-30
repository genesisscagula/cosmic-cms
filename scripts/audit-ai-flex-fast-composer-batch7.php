<?php
$root = dirname(__DIR__);
$service = file_get_contents($root.'/app/Services/LunaAiFlexSparkService.php');
$builder = file_get_contents($root.'/resources/js/Pages/Websites/Builder.jsx');
$checks = [
    'fast composer pass exists' => str_contains($service, 'FAST COMPOSER PASS.'),
    'single pass calls generate first' => str_contains($service, "'generation_stage' => 'fast_composer'"),
    'fast pipeline metadata stamped' => str_contains($service, "'pipeline' => 'fast_composer_v1'"),
    'fast success records no fallback' => str_contains($service, "'fallback_used' => false"),
    'fallback warning is observable' => str_contains($service, 'fast composer fell back to staged generation'),
    'structure fallback preserved' => str_contains($service, 'STRUCTURE FALLBACK PASS ONLY.'),
    'content fallback preserved' => str_contains($service, 'CONTENT FALLBACK PASS.'),
    'fallback pipeline metadata stamped' => str_contains($service, "'pipeline' => 'intent_structure_content_fallback'"),
    'fallback success recorded' => str_contains($service, "'fallback_used' => true"),
    'fast path still hydrates media' => substr_count($service, 'hydrateReferenceMedia($completed, $request, $semantic, 0)') >= 2,
    'fast path still validates' => str_contains($service, 'return $this->validate($completed);'),
    'blank Luna editor identifies pure draft' => str_contains($builder, "const isBlankLunaDraft = editSession?.creationSource === 'blank_luna';"),
    'blank Luna skips related layout lookup' => str_contains($builder, 'const layouts=isBlankLunaDraft ? [] : getCompatibleLayouts'),
    'blank Luna hides loading related layouts' => str_contains($builder, '{!isBlankLunaDraft && sparkCatalogLoading'),
    'blank Luna hides variant buttons' => str_contains($builder, '{!isBlankLunaDraft ? <div className="space-y-2">'),
];
$pass = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ').$label.PHP_EOL;
    if ($ok) $pass++;
}
echo PHP_EOL.$pass.'/'.count($checks).' PASS'.PHP_EOL;
exit($pass === count($checks) ? 0 : 1);
