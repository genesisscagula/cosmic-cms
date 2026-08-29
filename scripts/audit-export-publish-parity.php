<?php

// Focused Batch 6 runtime audit; intentionally independent from Laravel/vendor.
if (! function_exists('e')) {
    function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}
if (! function_exists('config')) {
    function config($key, $default = null) {
        $values = [
            'services.cosmic.asset_base_url' => 'https://cms.example.test',
            'app.url' => 'https://cms.example.test',
        ];
        return $values[$key] ?? $default;
    }
}

require_once __DIR__.'/../app/Support/SparkExtrasContract.php';
require_once __DIR__.'/../app/Helpers/CmsHtmlCompiler.php';

use App\Helpers\CmsHtmlCompiler;

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) $failures[] = $message;
};

$compiler = new ReflectionClass(CmsHtmlCompiler::class);
$apply = $compiler->getMethod('applySparkFieldExtrasToFragment');
$apply->setAccessible(true);
$flex = $compiler->getMethod('lunaCustomSectionHtml');
$flex->setAccessible(true);

$fragment = "<section><div><span>Premium</span><h2>From inspection to finish</h2><p>Clear project delivery.</p><a href='/quote'>Plan Your Project</a></div></section>";
$block = [
    'type' => 'hero_test',
    'tagline' => 'Premium',
    'heading' => 'From inspection to finish',
    'description' => 'Clear project delivery.',
    'button_label' => 'Plan Your Project',
    'button_url' => '/quote',
    'field_extras' => [
        'heading' => [
            'before' => [['id'=>'extra_badge','type'=>'badge','data'=>['text'=>'Featured']]],
            'after' => [['id'=>'extra_image','type'=>'image','data'=>['src'=>'/storage/media/hero.jpg','alt'=>'Hero detail']]],
        ],
        'description' => [
            'before' => [['id'=>'extra_text','type'=>'text','data'=>['text'=>'Intro note']]],
            'after' => [],
        ],
        'button_label' => [
            'before' => [],
            'after' => [['id'=>'extra_button','type'=>'button','data'=>['label'=>'Learn More','url'=>'/learn','target'=>'_self']]],
        ],
    ],
];
$out = $apply->invoke(null, $fragment, $block);

$check(strpos($out, "data-cosmic-field-extra-for='heading'") !== false, 'heading extras were not rendered');
$check(strpos($out, "https://cms.example.test/storage/media/hero.jpg") !== false, 'extra image was not export-normalized');
$check(strpos($out, "data-cosmic-field-extra-for='description'") < strpos($out, '<p>Clear project delivery.</p>'), 'description before-extra is not before its field');
$check(strpos($out, '<h2>From inspection to finish</h2>') < strpos($out, "data-cosmic-extra-id='extra_image'"), 'heading after-extra is not after heading');
$check(strpos($out, 'Plan Your Project</a>') < strpos($out, "data-cosmic-extra-id='extra_button'"), 'button after-extra is not after the CTA');

$nestedFragment = "<section><article><h3>Alpha</h3></article><article><h3>Beta</h3></article></section>";
$nestedBlock = [
    'type'=>'services_test',
    'items'=>[
        ['_cosmic_id'=>'card-a','title'=>'Alpha'],
        ['_cosmic_id'=>'card-b','title'=>'Beta'],
    ],
    'field_extras'=>[
        'items.@card-b.title'=>[
            'before'=>[],
            'after'=>[['id'=>'extra_beta','type'=>'divider','data'=>['orientation'=>'horizontal']]],
        ],
    ],
];
$nestedOut = $apply->invoke(null, $nestedFragment, $nestedBlock);
$check(strpos($nestedOut, 'Beta</h3>') < strpos($nestedOut, "data-cosmic-extra-id='extra_beta'"), 'stable-id nested extra did not follow Beta');
$check(strpos($nestedOut, "data-cosmic-extra-id='extra_beta'") > strpos($nestedOut, 'Alpha</h3>'), 'nested stable-id extra attached before wrong repeated item');

$emptyMediaBlock = $block;
$emptyMediaBlock['field_extras'] = [
    'heading'=>[
        'before'=>[],
        'after'=>[
            ['id'=>'extra_empty_image','type'=>'image','data'=>['src'=>'','alt'=>'']],
            ['id'=>'extra_empty_video','type'=>'video','data'=>['src'=>'','poster'=>'']],
        ],
    ],
];
$emptyOut = $apply->invoke(null, $fragment, $emptyMediaBlock);
$check(strpos($emptyOut, "data-cosmic-extra-empty-media='image'") !== false, 'empty image placeholder missing in static parity');
$check(strpos($emptyOut, "data-cosmic-extra-empty-media='video'") !== false, 'empty video placeholder missing in static parity');

$flexBlock = [
    'type'=>'luna_custom_section',
    'semantic_type'=>'hero',
    'custom_spark_key'=>'batch6-audit',
    'heading'=>'Unused fallback',
    'elements'=>[
        [
            'type'=>'row','_cosmic_id'=>'row_1','children'=>[
                [
                    'type'=>'column','_cosmic_id'=>'column_1','style'=>['width'=>100],'children'=>[
                        ['type'=>'heading','_cosmic_id'=>'extra_heading','text'=>'AI Flex Heading'],
                        ['type'=>'image','_cosmic_id'=>'extra_image_blank','src'=>'','alt'=>''],
                        ['type'=>'video','_cosmic_id'=>'extra_video_blank','src'=>''],
                    ],
                ],
            ],
        ],
    ],
    'visual_style'=>[],
    'style_overrides'=>[],
];
$flexOut = $flex->invoke(null, $flexBlock, ['bg'=>'bg-white','text'=>'text-slate-950']);
$check(strpos($flexOut, "data-cosmic-ai-flex-structure='rows-columns-extras'") !== false, 'AI Flex structure marker missing');
$check(strpos($flexOut, "data-cosmic-ai-flex-logical-path='rows.0'") !== false, 'AI Flex row logical path missing');
$check(strpos($flexOut, "data-cosmic-ai-flex-logical-path='rows.0.columns.0'") !== false, 'AI Flex column logical path missing');
$check(strpos($flexOut, "data-cosmic-ai-flex-logical-path='rows.0.columns.0.extras.0'") !== false, 'AI Flex extra logical path missing');
$check(strpos($flexOut, "data-cosmic-ai-flex-stable-id='extra_heading'") !== false, 'AI Flex stable id missing');
$check(strpos($flexOut, "data-cosmic-field-path='elements.0.children.0.children.0.text'") !== false, 'AI Flex storage field path missing');
$check(substr_count($flexOut, "data-cosmic-extra-empty-media=") >= 2, 'AI Flex empty media parity placeholders missing');

if ($failures !== []) {
    fwrite(STDERR, "Batch 6 export/publish parity audit FAILED\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "Batch 6 export/publish parity audit PASS\n";
echo " - registered Spark before/after extras: PASS\n";
echo " - nested stable-id field anchor: PASS\n";
echo " - media normalization/placeholders: PASS\n";
echo " - AI Flex rows/columns/extras export paths: PASS\n";
