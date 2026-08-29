<?php

namespace App\Services;

use Illuminate\Support\Str;

final class SparkTailwindSchemaValidator
{
    public function __construct(private readonly SparkTailwindSchemaContract $contract)
    {
    }

    public function validate(string $sparkType, array $schema): array
    {
        $errors = [];
        $normalized = $this->contract->emptySchema($sparkType);
        $normalized['version'] = (int) ($schema['version'] ?? config('spark-tailwind-schema.version', 2));
        $normalized['spark_type'] = (string) ($schema['spark_type'] ?? $sparkType);

        if ($normalized['spark_type'] !== $sparkType) {
            $errors['spark_type'] = 'spark_type_mismatch';
        }

        $slots = $schema['slots'] ?? [];
        if (! is_array($slots)) {
            $errors['slots'] = 'slots_must_be_object';
            $slots = [];
        }
        $normalized['slots'] = $this->validateStyleMap($slots, 'slots', $errors);

        $styles = $schema['styles'] ?? [];
        if (! is_array($styles)) {
            $errors['styles'] = 'styles_must_be_object';
            $styles = [];
        }
        $normalized['styles'] = $this->validateStyleMap($styles, 'styles', $errors);

        $collections = $schema['collections'] ?? [];
        if (! is_array($collections)) {
            $errors['collections'] = 'collections_must_be_object';
            $collections = [];
        }
        $normalized['collections'] = $this->validateCollections($collections, 1, 'collections', $errors);

        return ['valid' => $errors === [], 'schema' => $normalized, 'errors' => $errors];
    }

    /**
     * @param array<string,mixed> $styles
     * @param array<string,mixed> $errors
     * @return array<string,array<string,mixed>>
     */
    private function validateStyleMap(array $styles, string $path, array &$errors): array
    {
        $normalized = [];
        $maxStyles = (int) config('spark-tailwind-schema.limits.max_styles_per_scope', config('spark-tailwind-schema.limits.max_slots', 160));
        if (count($styles) > $maxStyles) $errors[$path] = 'too_many_styles';

        foreach (array_slice($styles, 0, $maxStyles, true) as $slot => $definition) {
            if (! is_string($slot)) continue;
            $slot = $this->contract->normalizeSlot($slot);
            if (! preg_match((string) config('spark-tailwind-schema.slot_pattern'), $slot)) {
                $errors["{$path}.{$slot}"] = 'invalid_slot';
                continue;
            }

            $normalized[$slot] = $this->validateDefinition($slot, $definition, "{$path}.{$slot}", $errors);
        }

        return $normalized;
    }

    /** @param array<string,mixed> $errors */
    private function validateDefinition(string $slot, mixed $definition, string $path, array &$errors): array
    {
        $isPatch = is_array($definition) && ! array_key_exists('classes', $definition)
            && (array_key_exists('add', $definition) || array_key_exists('remove', $definition));

        $base = $this->validateClasses(is_array($definition) ? ($definition['base'] ?? []) : []);
        $protected = $this->validateClasses(is_array($definition) ? ($definition['protected'] ?? []) : []);
        if ($base['errors'] !== []) $errors["{$path}.base"] = $base['errors'];
        if ($protected['errors'] !== []) $errors["{$path}.protected"] = $protected['errors'];

        $metadata = [
            'role' => is_array($definition) && is_string($definition['role'] ?? null)
                ? Str::lower(trim($definition['role']))
                : $slot,
            'locked' => (bool) (is_array($definition) ? ($definition['locked'] ?? false) : false),
        ];
        if ($base['classes'] !== []) $metadata['base'] = $base['classes'];
        if ($protected['classes'] !== []) $metadata['protected'] = $protected['classes'];

        if ($isPatch) {
            $add = $this->validateClasses($definition['add'] ?? []);
            $remove = $this->validateClasses($definition['remove'] ?? []);
            if ($add['errors'] !== []) $errors["{$path}.add"] = $add['errors'];
            if ($remove['errors'] !== []) $errors["{$path}.remove"] = $remove['errors'];

            $protectedSet = array_flip(array_values(array_unique(array_merge($base['classes'], $protected['classes']))));
            $protectedRemoved = array_values(array_filter($remove['classes'], fn (string $token): bool => isset($protectedSet[$token])));
            if ($protectedRemoved !== []) $errors["{$path}.remove"] = array_values(array_unique(array_merge(
                (array) ($errors["{$path}.remove"] ?? []),
                array_map(fn (string $token): string => $token.':protected_class', $protectedRemoved),
            )));

            return [
                'add' => $add['classes'],
                'remove' => $remove['classes'],
            ] + $metadata;
        }

        $classes = is_array($definition) && array_key_exists('classes', $definition)
            ? $definition['classes']
            : $definition;
        $result = $this->validateClasses(is_string($classes) || is_array($classes) ? $classes : '');
        if ($result['errors'] !== []) $errors["{$path}.classes"] = $result['errors'];

        return ['classes' => $result['classes']] + $metadata;
    }

    /**
     * @param array<string,mixed> $collections
     * @param array<string,mixed> $errors
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function validateCollections(array $collections, int $depth, string $path, array &$errors): array
    {
        $maxDepth = (int) config('spark-tailwind-schema.limits.max_collection_depth', 4);
        if ($depth > $maxDepth) {
            $errors[$path] = 'collection_depth_exceeded';
            return [];
        }

        $maxCollections = (int) config('spark-tailwind-schema.limits.max_collections_per_scope', 24);
        if (count($collections) > $maxCollections) $errors[$path] = 'too_many_collections';

        $normalized = [];
        foreach (array_slice($collections, 0, $maxCollections, true) as $collection => $items) {
            if (! is_string($collection)) continue;
            $collection = $this->contract->normalizeSlot($collection);
            if (! preg_match((string) config('spark-tailwind-schema.slot_pattern'), $collection)) {
                $errors["{$path}.{$collection}"] = 'invalid_collection';
                continue;
            }
            if (! is_array($items)) {
                $errors["{$path}.{$collection}"] = 'collection_must_be_array';
                continue;
            }

            $maxItems = (int) config('spark-tailwind-schema.limits.max_items_per_collection', 64);
            if (count($items) > $maxItems) $errors["{$path}.{$collection}"] = 'too_many_collection_items';
            $normalizedItems = [];

            foreach (array_slice(array_values($items), 0, $maxItems) as $index => $item) {
                if (! is_array($item)) {
                    $errors["{$path}.{$collection}.{$index}"] = 'collection_item_must_be_object';
                    continue;
                }

                $key = $item['key'] ?? null;
                if ($key !== null && ! is_string($key) && ! is_int($key)) {
                    $errors["{$path}.{$collection}.{$index}.key"] = 'invalid_item_key';
                    $key = null;
                }
                if (is_string($key)) $key = Str::limit(trim($key), 120, '');

                $itemStyles = $item['styles'] ?? [];
                if (! is_array($itemStyles)) {
                    $errors["{$path}.{$collection}.{$index}.styles"] = 'styles_must_be_object';
                    $itemStyles = [];
                }

                $childCollections = $item['collections'] ?? [];
                if (! is_array($childCollections)) {
                    $errors["{$path}.{$collection}.{$index}.collections"] = 'collections_must_be_object';
                    $childCollections = [];
                }

                $normalizedItem = [
                    'styles' => $this->validateStyleMap($itemStyles, "{$path}.{$collection}.{$index}.styles", $errors),
                    'collections' => $childCollections === [] ? [] : $this->validateCollections(
                        $childCollections,
                        $depth + 1,
                        "{$path}.{$collection}.{$index}.collections",
                        $errors,
                    ),
                ];
                if ($key !== null && $key !== '') $normalizedItem['key'] = $key;
                $normalizedItems[] = $normalizedItem;
            }

            $normalized[$collection] = $normalizedItems;
        }

        return $normalized;
    }

    public function validateClasses(string|array|null $classes): array
    {
        $errors = [];
        $valid = [];
        $tokens = $this->contract->tokenize($classes);
        $max = (int) config('spark-tailwind-schema.limits.max_classes_per_slot', 160);

        if (count($tokens) > $max) $errors[] = 'too_many_classes';

        foreach (array_slice($tokens, 0, $max) as $token) {
            $reason = $this->invalidReason($token);
            if ($reason !== null) {
                $errors[] = $token.':'.$reason;
                continue;
            }
            $valid[] = $token;
        }

        return ['classes' => array_values(array_unique($valid)), 'errors' => array_values(array_unique($errors))];
    }

    public function isProtectedUtility(string $token): bool
    {
        return $this->matchesUtilityPrefixList($token, (array) config('spark-tailwind-schema.protected_utility_prefixes', []));
    }

    /**
     * Structural classification is intentionally separate from globally
     * protected utilities. Batch migrations can copy these tokens into a style
     * definition's `base`/`protected` list without preventing explicit future
     * structural edits everywhere.
     */
    public function isStructuralUtility(string $token): bool
    {
        return $this->matchesUtilityPrefixList($token, (array) config('spark-tailwind-schema.structural_utility_prefixes', []));
    }

    /** @return array<int,string> */
    public function structuralUtilities(string|array|null $classes): array
    {
        return array_values(array_filter(
            $this->contract->tokenize($classes),
            fn (string $token): bool => $this->isStructuralUtility($token),
        ));
    }

    private function matchesUtilityPrefixList(string $token, array $prefixes): bool
    {
        $descriptor = $token;
        // Strip ordinary Tailwind variants while respecting arbitrary variant
        // brackets. We only need the terminal utility for prefix protection.
        $square = 0;
        $round = 0;
        $lastColon = -1;
        for ($i = 0, $length = strlen($descriptor); $i < $length; $i++) {
            $char = $descriptor[$i];
            if ($char === '[') $square++;
            elseif ($char === ']' && $square > 0) $square--;
            elseif ($char === '(') $round++;
            elseif ($char === ')' && $round > 0) $round--;
            elseif ($char === ':' && $square === 0 && $round === 0) $lastColon = $i;
        }
        $utility = $lastColon >= 0 ? substr($descriptor, $lastColon + 1) : $descriptor;
        $utility = ltrim((string) $utility, '!-');

        foreach ($prefixes as $prefix) {
            $prefix = trim((string) $prefix);
            if ($prefix !== '' && ($utility === $prefix || str_starts_with($utility, $prefix.'-'))) return true;
        }

        return false;
    }

    private function invalidReason(string $token): ?string
    {
        if ($token === '') return 'empty_class';
        if (strlen($token) > (int) config('spark-tailwind-schema.limits.max_class_length', 180)) return 'class_too_long';

        $lower = Str::lower($token);
        foreach ((array) config('spark-tailwind-schema.forbidden_fragments', []) as $fragment) {
            if ($fragment !== '' && str_contains($lower, Str::lower((string) $fragment))) return 'unsafe_fragment';
        }

        // Tailwind utilities may include letters, digits, slash, %, decimal,
        // brackets, parentheses, commas, hashes, colons and common operators.
        // Whitespace is only allowed inside a balanced arbitrary-value block.
        if (! preg_match('/^[A-Za-z0-9_:\-\/\.\[\]\(\),#%!=&+*?@]+(?: [A-Za-z0-9_:\-\/\.\[\]\(\),#%!=&+*?@]+)*$/', $token)) {
            return 'invalid_characters';
        }
        if (substr_count($token, '[') !== substr_count($token, ']')) return 'unbalanced_arbitrary_value';

        return null;
    }
}
