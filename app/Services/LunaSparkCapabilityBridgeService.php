<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Batch 4: bridge Luna's bounded customization plan to the registered Spark
 * capability system. Luna may describe intent, but Cosmic decides whether the
 * selected Spark can safely execute it locally, requires the schema editor, or
 * must reject it. No arbitrary component tree is accepted here.
 */
final class LunaSparkCapabilityBridgeService
{
    public const VERSION = 1;

    public function __construct(private readonly SparkEditCapabilityRegistry $capabilities)
    {
    }

    /** @return array<string,mixed> */
    public function manifestForSpark(string $sparkType, array $block = []): array
    {
        $declaration = $this->capabilities->declaration($sparkType, $block);
        $modules = array_values((array) ($declaration['modules'] ?? []));
        $operations = collect((array) ($declaration['targets'] ?? []))
            ->flatMap(static fn (array $target): array => (array) ($target['operations'] ?? []))
            ->unique()->values()->all();

        return [
            'version' => self::VERSION,
            'spark_type' => $sparkType,
            'modules' => $modules,
            'operations' => $operations,
            'repeater_keys' => array_values((array) ($declaration['repeater_keys'] ?? [])),
            'preservation_policy' => $declaration['preservation_policy'] ?? [],
            'guardrails' => [
                'registered_schema_only' => true,
                'preserve_tested_structure_by_default' => true,
                'no_arbitrary_component_tree' => true,
                'unsupported_mutation' => 'reject',
                'complex_structure' => 'schema_editor_only',
            ],
        ];
    }

    /**
     * @param array<string,array<int,string>> $plan
     * @return array<string,mixed>
     */
    public function guardPlan(string $sparkType, array $plan, array $block = []): array
    {
        $manifest = $this->manifestForSpark($sparkType, $block);
        $modules = (array) ($manifest['modules'] ?? []);
        $safe = ['content' => [], 'media' => [], 'visual' => [], 'structure' => []];
        $rejected = ['content' => [], 'media' => [], 'visual' => [], 'structure' => []];
        $local = [];
        $schema = [];

        foreach (array_keys($safe) as $bucket) {
            foreach ((array) ($plan[$bucket] ?? []) as $raw) {
                $item = trim(is_scalar($raw) ? (string) $raw : '');
                if ($item === '') continue;

                $decision = $this->classify($bucket, $item, $modules);
                if (! ($decision['allowed'] ?? false)) {
                    $rejected[$bucket][] = ['item' => $item, 'reason' => $decision['reason'] ?? 'unsupported_capability'];
                    continue;
                }

                $safe[$bucket][] = $item;
                $record = [
                    'bucket' => $bucket,
                    'item' => $item,
                    'required_modules' => $decision['required_modules'] ?? [],
                    'mode' => $decision['mode'] ?? 'local',
                ];
                if (($decision['mode'] ?? 'local') === 'schema_editor') $schema[] = $record;
                else $local[] = $record;
            }
        }

        $safe = collect($safe)->map(static fn (array $items): array => array_values(array_unique($items)))->all();
        $executionHint = $schema !== [] ? 'schema_editor' : 'local';

        return [
            'version' => self::VERSION,
            'spark_type' => $sparkType,
            'capability_manifest' => $manifest,
            'customization_plan' => $safe,
            'local_items' => $local,
            'schema_editor_items' => $schema,
            'rejected_items' => $rejected,
            'execution_hint' => $executionHint,
            'has_rejections' => collect($rejected)->contains(static fn (array $items): bool => $items !== []),
        ];
    }

    /** @return array<string,mixed> */
    private function classify(string $bucket, string $item, array $modules): array
    {
        $text = Str::lower($item);
        $requires = [];
        $mode = 'local';

        // Content field rewrites are schema-safe for a registered Spark. Button
        // requests are capability checked separately because CTA mechanics vary.
        if ($bucket === 'content') {
            if (preg_match('/\b(button|cta|call to action|link)\b/', $text)) $requires[] = 'Buttons';
            if (preg_match('/\b(add|remove|duplicate|reorder)\b.*\b(item|card|service|feature|slide|testimonial|faq|plan)\b/', $text)) {
                $requires[] = 'ItemCollection';
                $mode = 'schema_editor';
            }
        }

        if ($bucket === 'media') {
            if (preg_match('/\b(image|photo|video|media|gallery|logo|avatar|poster|background)\b/', $text)) $requires[] = 'Media';
            if (preg_match('/\boverlay\b/', $text)) $requires[] = 'Overlay';
            if (preg_match('/\b(add|remove|duplicate|reorder)\b.*\b(slide|image|gallery item)\b/', $text)) {
                $requires[] = 'ItemCollection';
                $mode = 'schema_editor';
            }
        }

        if ($bucket === 'visual') {
            if (preg_match('/\b(font|typography|heading|text|letter|line height|align)\b/', $text)) $requires[] = 'Typography';
            if (preg_match('/\b(padding|spacing|gap|height|container)\b/', $text)) $requires[] = 'Spacing';
            if (preg_match('/\b(background|surface|border|radius|rounded|shadow|color|colour)\b/', $text)) $requires[] = 'Surface';
            if (preg_match('/\boverlay\b/', $text)) $requires[] = 'Overlay';
            if (preg_match('/\b(column|grid|bento|split|layout|alignment)\b/', $text)) $requires[] = 'GridLayout';
            if (preg_match('/\b(button|cta)\b/', $text)) $requires[] = 'Buttons';
        }

        if ($bucket === 'structure') {
            $mode = 'schema_editor';
            if (preg_match('/\b(slide|card|service|feature|item|testimonial|faq|plan|repeat|reorder|duplicate|remove|add)\b/', $text)) $requires[] = 'ItemCollection';
            if (preg_match('/\b(column|grid|bento|split|layout|row)\b/', $text)) $requires[] = 'GridLayout';
            if (preg_match('/\b(image|video|media|gallery|background)\b/', $text)) $requires[] = 'Media';
            // Structural intent with no known registered capability is never a
            // license to invent nesting. Require at least one structural module.
            if ($requires === []) $requires[] = 'GridLayout';
        }

        $requires = array_values(array_unique($requires));
        $missing = array_values(array_diff($requires, $modules));
        if ($missing !== []) {
            return [
                'allowed' => false,
                'reason' => 'missing_modules:'.implode(',', $missing),
                'required_modules' => $requires,
                'mode' => $mode,
            ];
        }

        return ['allowed' => true, 'required_modules' => $requires, 'mode' => $mode];
    }
}
