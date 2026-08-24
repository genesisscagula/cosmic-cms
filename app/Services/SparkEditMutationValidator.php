<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class SparkEditMutationValidator
{
    public function __construct(private readonly SparkEditCapabilityRegistry $capabilities)
    {
    }

    public function validateChanges(string $sparkType, array $block, array $changes, ?string $target = null): array
    {
        $declaration = $this->capabilities->declaration($sparkType, $block);
        $protected = (array) config('spark-edit-capabilities.protected_fields', []);
        $knownLeaves = $this->leafKeys($block);
        $allowed = [];
        $rejected = [];

        if ($target !== null && ! isset($declaration['targets'][$this->normalizeTarget($target)])) {
            return [
                'changes' => [],
                'rejected' => ['_target' => 'unsupported_target'],
                'capability' => $declaration,
            ];
        }

        foreach ($changes as $key => $value) {
            if (! is_string($key) || in_array($key, $protected, true)) {
                $rejected[(string) $key] = 'protected_field';
                continue;
            }

            $topLevel = Str::before($key, '.');
            $leaf = Str::afterLast($key, '.');
            $knownOverride = in_array($topLevel, [
                'luna_typography_overrides', 'luna_section_overrides', 'luna_background_overrides',
                'luna_component_overrides', 'luna_design_overrides', 'luna_visibility_overrides',
            ], true);
            $knownField = array_key_exists($topLevel, $block)
                || in_array($topLevel, $declaration['writable_fields'], true)
                || in_array($leaf, $knownLeaves, true);

            if (! $knownOverride && ! $knownField) {
                $rejected[$key] = 'unknown_field';
                continue;
            }

            if (in_array($key, ['theme', 'resolvedTheme'], true) && ! $this->validTreatment((string) $value)) {
                $rejected[$key] = 'invalid_semantic_treatment';
                continue;
            }

            $allowed[$key] = $value;
        }

        return ['changes' => $allowed, 'rejected' => $rejected, 'capability' => $declaration];
    }

    /** Validate one deterministic capability operation before execution. */
    public function validateMutation(string $sparkType, array $block, string $target, string $operation): array
    {
        $declaration = $this->capabilities->declaration($sparkType, $block);
        $target = $this->normalizeTarget($target);
        $operation = Str::lower(trim($operation));
        $targetContract = $declaration['targets'][$target] ?? null;

        if ($targetContract === null) {
            return ['valid' => false, 'reason' => 'unsupported_target', 'capability' => $declaration];
        }
        if (! in_array($operation, (array) ($targetContract['operations'] ?? []), true)) {
            return ['valid' => false, 'reason' => 'unsupported_property', 'capability' => $declaration];
        }

        return ['valid' => true, 'reason' => null, 'capability' => $declaration];
    }

    public function validTreatment(string $treatment): bool
    {
        $treatment = Str::lower(trim($treatment));
        if (in_array($treatment, (array) config('spark-edit-capabilities.semantic_treatments', []), true)) return true;

        $path = resource_path('theme/theme-families.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        return isset($decoded['families'][$treatment]);
    }

    /**
     * Deterministic relative mutation for capability properties that have a
     * real persisted Builder/compiler token. It never invents CSS fields.
     */
    public function applyRelative(array $block, string $property, string $direction, array $context = []): array
    {
        $property = Str::lower(trim($property));
        $direction = in_array(Str::lower(trim($direction)), ['increase', 'larger', 'more', 'up'], true) ? 1 : -1;
        $type = (string) ($block['type'] ?? '');
        $definition = $this->capabilities->mutationContract($type, $block, $property, $context);
        if ($definition === null) {
            return ['applied' => false, 'block' => $block, 'reason' => 'unsupported_capability'];
        }

        $path = $definition['storage_path'];
        $beforeRaw = Arr::get($block, $path);
        if ($beforeRaw === null) {
            foreach ((array) ($definition['fallback_paths'] ?? []) as $fallbackPath) {
                $beforeRaw = Arr::get($block, $fallbackPath);
                if ($beforeRaw !== null) break;
            }
        }
        $beforeRaw ??= $context['current_value'] ?? $definition['default'] ?? null;
        if ($beforeRaw === null || $this->number($beforeRaw) === null) {
            return ['applied' => false, 'block' => $block, 'reason' => 'current_value_required', 'path' => $path];
        }
        $before = $this->number($beforeRaw);
        $after = max($definition['min'], min($definition['max'], $before + ($direction * $definition['step'])));
        $stored = $definition['unit'] !== '' ? $after.$definition['unit'] : (int) round($after);
        Arr::set($block, $path, $stored);

        return [
            'applied' => true,
            'block' => $block,
            'module' => $definition['module'],
            'property' => $property,
            'path' => $path,
            'render_token' => $definition['render_token'] ?? null,
            'before' => $beforeRaw,
            'after' => $stored,
            'direction' => $direction > 0 ? 'increase' : 'decrease',
        ];
    }

    /** Apply one finite semantic capability value to its canonical path. */
    public function applySet(array $block, string $property, mixed $value, array $context = []): array
    {
        $property = Str::lower(trim($property));
        $type = (string) ($block['type'] ?? '');
        $definition = $this->capabilities->mutationContract($type, $block, $property, $context);
        if ($definition === null) {
            return ['applied' => false, 'block' => $block, 'reason' => 'unsupported_capability'];
        }

        $allowed = (array) ($definition['allowed'] ?? []);
        $stored = is_string($value) ? Str::lower(trim($value)) : $value;
        if ($allowed !== [] && ! in_array($stored, $allowed, true)) {
            return ['applied' => false, 'block' => $block, 'reason' => 'invalid_value'];
        }

        $path = (string) $definition['storage_path'];
        $before = Arr::get($block, $path);
        $before ??= $context['current_value'] ?? $definition['default'] ?? null;
        Arr::set($block, $path, $stored);

        return [
            'applied' => true,
            'changed' => $before !== $stored,
            'block' => $block,
            'module' => $definition['module'],
            'property' => $property,
            'path' => $path,
            'render_token' => $definition['render_token'] ?? null,
            'before' => $before,
            'after' => $stored,
        ];
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : (preg_match('/^-?[0-9.]+(?:px|%)?$/', trim((string) $value), $match) ? (float) $match[0] : null);
    }

    private function normalizeTarget(string $target): string
    {
        $target = Str::lower(trim($target));
        return match ($target) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'title' => 'heading',
            'body', 'paragraph', 'copy', 'label', 'content', 'element' => 'text',
            'image', 'video', 'logo', 'photo', 'background image' => 'media',
            'link', 'cta', 'control' => 'button',
            'card', 'slide', 'service', 'feature', 'testimonial', 'faq', 'plan' => 'item',
            'cards', 'slides', 'services', 'features', 'testimonials', 'faqs', 'plans', 'items' => 'collection',
            default => $target,
        };
    }

    private function leafKeys(array $value): array
    {
        $leaves = [];
        $walk = function (array $items) use (&$walk, &$leaves): void {
            foreach ($items as $key => $item) {
                if (is_array($item)) $walk($item);
                elseif (is_string($key)) $leaves[] = $key;
            }
        };
        $walk($value);
        return array_values(array_unique($leaves));
    }
}
