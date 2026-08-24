<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class SparkTailwindSchemaContract
{
    public function storageKey(): string
    {
        return (string) config('spark-tailwind-schema.storage_key', 'luna_tailwind_schema');
    }

    public function emptySchema(string $sparkType): array
    {
        return [
            'version' => (int) config('spark-tailwind-schema.version', 1),
            'spark_type' => $sparkType,
            'slots' => [],
        ];
    }

    /**
     * Return a normalized per-Spark-instance Tailwind schema without mutating
     * the block. Missing schemas are expected during the migration period.
     */
    public function fromBlock(string $sparkType, array $block): array
    {
        $raw = Arr::get($block, $this->storageKey());
        if (! is_array($raw)) {
            return $this->emptySchema($sparkType);
        }

        $raw['version'] = (int) ($raw['version'] ?? config('spark-tailwind-schema.version', 1));
        $raw['spark_type'] = (string) ($raw['spark_type'] ?? $sparkType);
        $raw['slots'] = is_array($raw['slots'] ?? null) ? $raw['slots'] : [];

        return $raw;
    }

    public function declaration(string $sparkType, array $block = []): array
    {
        $schema = $this->fromBlock($sparkType, $block);

        return [
            'version' => (int) config('spark-tailwind-schema.version', 1),
            'storage_key' => $this->storageKey(),
            'scope' => 'spark_instance',
            'spark_type' => $sparkType,
            'slots' => array_keys((array) ($schema['slots'] ?? [])),
            'canonical_slots' => (array) config('spark-tailwind-schema.canonical_slots', []),
            'supports_responsive_variants' => true,
            'supports_state_variants' => true,
            'migration_state' => ($schema['slots'] ?? []) === [] ? 'legacy_fallback' : 'schema_backed',
        ];
    }

    /** @return array<int,string> */
    public function tokenize(string|array|null $classes): array
    {
        if (is_array($classes)) {
            $classes = implode(' ', array_filter($classes, 'is_string'));
        }
        if (! is_string($classes) || trim($classes) === '') return [];

        // Tailwind arbitrary values may contain spaces inside square brackets,
        // so split only on whitespace while bracket depth is zero.
        $tokens = [];
        $buffer = '';
        $depth = 0;
        $escaped = false;
        $length = strlen($classes);

        for ($i = 0; $i < $length; $i++) {
            $char = $classes[$i];
            if ($escaped) {
                $buffer .= $char;
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $buffer .= $char;
                $escaped = true;
                continue;
            }
            if ($char === '[') $depth++;
            if ($char === ']' && $depth > 0) $depth--;

            if (ctype_space($char) && $depth === 0) {
                if ($buffer !== '') $tokens[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if ($buffer !== '') $tokens[] = $buffer;

        return array_values(array_unique(array_filter(array_map('trim', $tokens))));
    }

    public function normalizeSlot(string $slot): string
    {
        return Str::of($slot)->trim()->lower()->replace(['-', ' '], '_')->toString();
    }

    /**
     * Resolve the complete class string owned by a schema slot. During migration
     * a missing slot falls back to the Spark's legacy hard-coded class string.
     * An explicitly present empty slot intentionally resolves to an empty string.
     */
    public function resolveSlot(array $block, string $slot, string|array|null $fallback = ''): string
    {
        $sparkType = (string) ($block['type'] ?? '');
        $schema = $this->fromBlock($sparkType, $block);
        $slot = $this->normalizeSlot($slot);

        if (! array_key_exists($slot, $schema['slots'])) {
            return implode(' ', $this->tokenize($fallback));
        }

        $definition = $schema['slots'][$slot];
        if (is_array($definition) && ! array_key_exists('classes', $definition)
            && (array_key_exists('add', $definition) || array_key_exists('remove', $definition))) {
            $tokens = $this->tokenize($fallback);
            $remove = array_flip($this->tokenize($definition['remove'] ?? []));
            $tokens = array_values(array_filter($tokens, fn (string $token): bool => ! isset($remove[$token])));
            foreach ($this->tokenize($definition['add'] ?? []) as $token) {
                if (! in_array($token, $tokens, true)) $tokens[] = $token;
            }
            return implode(' ', $tokens);
        }

        $classes = is_array($definition) && array_key_exists('classes', $definition)
            ? $definition['classes']
            : $definition;

        return implode(' ', $this->tokenize(is_string($classes) || is_array($classes) ? $classes : ''));
    }

    public function hasSlot(array $block, string $slot): bool
    {
        $sparkType = (string) ($block['type'] ?? '');
        $schema = $this->fromBlock($sparkType, $block);

        return array_key_exists($this->normalizeSlot($slot), $schema['slots']);
    }
}
