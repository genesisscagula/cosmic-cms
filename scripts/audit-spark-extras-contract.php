<?php

require __DIR__.'/../app/Support/SparkExtrasContract.php';

use App\Support\SparkExtrasContract;

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(SparkExtrasContract::VERSION === 1, 'contract version');
$assert(SparkExtrasContract::PLACEMENTS === ['before', 'after'], 'placements');
$assert(count(SparkExtrasContract::TYPES) === 9, 'supported type count');
$assert(SparkExtrasContract::normalizeTargetPath('plans[0].title') === 'plans.0.title', 'indexed target path');
$assert(SparkExtrasContract::normalizeTargetPath('rows.@row_1.columns.@col_2.heading') === 'rows.@row_1.columns.@col_2.heading', 'stable-id target path');
$assert(SparkExtrasContract::normalizeTargetPath('__proto__.heading') === null, 'dangerous target rejected');

$state = SparkExtrasContract::normalizeState([
    'heading' => [
        'before' => [['id' => 'badge_1', 'type' => 'badge', 'data' => ['text' => 'Premium']]],
        'after' => [['id' => 'image_1', 'type' => 'image', 'data' => ['src' => '/image.jpg']]],
    ],
    'plans[0].title' => [
        'after' => [['id' => 'cta_1', 'type' => 'button', 'data' => ['label' => 'Choose', 'url' => '#checkout']]],
    ],
]);

$assert(isset($state['heading']['before'][0]), 'before extra preserved');
$assert(isset($state['heading']['after'][0]), 'after extra preserved');
$assert(($state['plans.0.title']['after'][0]['data']['url'] ?? null) === '#checkout', 'anchor URL preserved');

$unsafe = SparkExtrasContract::normalizeItem([
    'type' => 'button',
    'data' => ['label' => 'Run', 'url' => 'javascript:alert(1)'],
]);
$assert(($unsafe['data']['url'] ?? null) === '', 'unsafe URL rejected');

$legacy = SparkExtrasContract::normalizeBlock(['type' => 'hero_headline', 'heading' => 'Legacy']);
$assert(($legacy['field_extras'] ?? null) === [], 'legacy block gets empty field_extras');

fwrite(STDOUT, "PASS server spark extras contract v1\n");
