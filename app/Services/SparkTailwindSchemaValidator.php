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
        $normalized['version'] = (int) ($schema['version'] ?? config('spark-tailwind-schema.version', 1));
        $normalized['spark_type'] = (string) ($schema['spark_type'] ?? $sparkType);

        if ($normalized['spark_type'] !== $sparkType) {
            $errors['spark_type'] = 'spark_type_mismatch';
        }

        $slots = $schema['slots'] ?? [];
        if (! is_array($slots)) {
            return ['valid' => false, 'schema' => $normalized, 'errors' => ['slots' => 'slots_must_be_object']];
        }

        $maxSlots = (int) config('spark-tailwind-schema.limits.max_slots', 160);
        if (count($slots) > $maxSlots) $errors['slots'] = 'too_many_slots';

        foreach (array_slice($slots, 0, $maxSlots, true) as $slot => $definition) {
            if (! is_string($slot)) continue;
            $slot = $this->contract->normalizeSlot($slot);
            if (! preg_match((string) config('spark-tailwind-schema.slot_pattern'), $slot)) {
                $errors["slots.{$slot}"] = 'invalid_slot';
                continue;
            }

            $isPatch = is_array($definition) && ! array_key_exists('classes', $definition)
                && (array_key_exists('add', $definition) || array_key_exists('remove', $definition));
            if ($isPatch) {
                $add = $this->validateClasses($definition['add'] ?? []);
                $remove = $this->validateClasses($definition['remove'] ?? []);
                if ($add['errors'] !== []) $errors["slots.{$slot}.add"] = $add['errors'];
                if ($remove['errors'] !== []) $errors["slots.{$slot}.remove"] = $remove['errors'];
                $normalized['slots'][$slot] = [
                    'add' => $add['classes'],
                    'remove' => $remove['classes'],
                    'locked' => (bool) ($definition['locked'] ?? false),
                    'role' => is_string($definition['role'] ?? null) ? Str::lower(trim($definition['role'])) : $slot,
                ];
                continue;
            }

            $classes = is_array($definition) && array_key_exists('classes', $definition)
                ? $definition['classes']
                : $definition;
            $result = $this->validateClasses($classes);
            if ($result['errors'] !== []) {
                $errors["slots.{$slot}.classes"] = $result['errors'];
            }

            $normalized['slots'][$slot] = [
                'classes' => $result['classes'],
                'locked' => (bool) (is_array($definition) ? ($definition['locked'] ?? false) : false),
                'role' => is_array($definition) && is_string($definition['role'] ?? null)
                    ? Str::lower(trim($definition['role']))
                    : $slot,
            ];
        }

        return ['valid' => $errors === [], 'schema' => $normalized, 'errors' => $errors];
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

        foreach ((array) config('spark-tailwind-schema.protected_utility_prefixes', []) as $prefix) {
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
