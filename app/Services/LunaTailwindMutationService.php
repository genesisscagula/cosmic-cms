<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class LunaTailwindMutationService
{
    public function __construct(
        private readonly SparkTailwindSchemaContract $contract,
        private readonly SparkTailwindSchemaValidator $validator,
        private readonly TailwindUtilityConflictResolver $conflicts,
    ) {}

    /** Compact, request-scoped context only. Never expose the whole Spark library. */
    public function plannerContext(array $block, array $elementContext = []): array
    {
        $inventory = $elementContext['tailwindInventory'] ?? $elementContext['tailwind_inventory'] ?? [];
        if (! is_array($inventory)) $inventory = [];
        $clean = [];
        foreach (array_slice($inventory, 0, 80) as $row) {
            if (! is_array($row)) continue;
            $slot = $this->contract->normalizeSlot((string) ($row['slot'] ?? ''));
            if ($slot === '' || ! preg_match((string) config('spark-tailwind-schema.slot_pattern'), $slot)) continue;
            $classResult = $this->validator->validateClasses((string) ($row['classes'] ?? ''));
            $clean[] = [
                'slot' => $slot,
                'tag' => Str::lower(substr((string) ($row['tag'] ?? ''), 0, 20)),
                'role' => Str::lower(substr((string) ($row['role'] ?? ''), 0, 30)),
                'classes' => implode(' ', $classResult['classes']),
                'text' => Str::limit(trim(preg_replace('/\s+/', ' ', (string) ($row['text'] ?? ''))), 90, ''),
            ];
        }

        return [
            'mode' => 'tailwind_token_patch',
            'scope' => 'selected_spark_instance_only',
            'spark_type' => (string) ($block['type'] ?? ''),
            'clicked_slot' => $this->contract->normalizeSlot((string) ($elementContext['tailwindSlot'] ?? $elementContext['tailwind_slot'] ?? '')),
            'current_schema' => $this->contract->fromBlock((string) ($block['type'] ?? ''), $block),
            'rendered_slots' => $clean,
            'mutation_shape' => [['slot' => 'auto_1', 'add' => ['text-4xl'], 'remove' => ['text-5xl']]],
            'variant_contract' => [
                'base_and_breakpoints_are_independent' => true,
                'state_variants_are_independent' => true,
                'global_visual_request' => 'When the same property exists at base and responsive breakpoints, update each relevant existing variant unless the user explicitly scopes the request to one breakpoint.',
                'scoped_visual_request' => 'For mobile/tablet/desktop/hover/focus-only requests, mutate only the matching variant and preserve all others.',
            ],
            'rules' => [
                'Patch only slots present in rendered_slots or clicked_slot.',
                'Use remove for conflicting current utilities and add for replacements. The server also deterministically removes same-family conflicts at the exact same variant scope.',
                'Preserve unrelated utilities, responsive variants, theme classes, and layout structure.',
                'For relative follow-ups such as slightly/little more, inspect rendered current classes and move one restrained Tailwind step from the CURRENT rendered value, never from the original Spark default.',
                'Do not remove a base utility when replacing only lg:/md:/sm:/hover:/focus: (or vice versa). Variants are independent.',
                'Do not emit CSS, HTML, JavaScript, style attributes, or class strings outside add/remove token arrays.',
            ],
        ];
    }

    public function apply(array $block, array $mutations, array $elementContext = []): array
    {
        $sparkType = (string) ($block['type'] ?? '');
        if ($sparkType === '') return ['block' => $block, 'applied' => [], 'rejected' => ['_spark' => 'missing_type']];

        $allowedInventory = $elementContext['tailwindInventory'] ?? $elementContext['tailwind_inventory'] ?? [];
        $allowedSlots = [];
        foreach (is_array($allowedInventory) ? $allowedInventory : [] as $row) {
            if (is_array($row) && is_string($row['slot'] ?? null)) $allowedSlots[$this->contract->normalizeSlot($row['slot'])] = true;
        }
        $clicked = $this->contract->normalizeSlot((string) ($elementContext['tailwindSlot'] ?? $elementContext['tailwind_slot'] ?? ''));
        if ($clicked !== '') $allowedSlots[$clicked] = true;

        // Inventory contains the effective CURRENT classes rendered in the
        // browser (legacy fallback + previous schema patches). It is the source
        // of truth for relative follow-ups and deterministic conflict cleanup.
        $renderedBySlot = [];
        foreach (is_array($allowedInventory) ? $allowedInventory : [] as $row) {
            if (! is_array($row) || ! is_string($row['slot'] ?? null)) continue;
            $slotKey = $this->contract->normalizeSlot((string) $row['slot']);
            if ($slotKey === '') continue;
            $validatedClasses = $this->validator->validateClasses($row['classes'] ?? '');
            $renderedBySlot[$slotKey] = $validatedClasses['classes'];
        }

        $schema = $this->contract->fromBlock($sparkType, $block);
        $applied = [];
        $rejected = [];
        foreach (array_slice($mutations, 0, 24) as $index => $mutation) {
            if (! is_array($mutation)) { $rejected[$index] = 'invalid_mutation'; continue; }
            $slot = $this->contract->normalizeSlot((string) ($mutation['slot'] ?? ''));
            if ($slot === '' || ! isset($allowedSlots[$slot])) { $rejected[$index] = 'slot_not_in_rendered_context'; continue; }
            if (is_array($schema['slots'][$slot] ?? null) && ($schema['slots'][$slot]['locked'] ?? false)) { $rejected[$index] = 'slot_locked'; continue; }

            $add = $this->validator->validateClasses($mutation['add'] ?? []);
            $remove = $this->validator->validateClasses($mutation['remove'] ?? []);
            if ($add['errors'] !== [] || $remove['errors'] !== []) { $rejected[$index] = 'invalid_tailwind_token'; continue; }

            $existing = is_array($schema['slots'][$slot] ?? null) ? $schema['slots'][$slot] : [];
            $existingAdd = $this->contract->tokenize($existing['add'] ?? []);
            $existingRemove = $this->contract->tokenize($existing['remove'] ?? []);

            // Last requested utility wins inside one mutation when Luna emits
            // two utilities from the same Tailwind family/variant. Also remove
            // stale effective classes from the current rendered slot so the
            // schema never relies on CSS source-order accidents.
            $requestedAdds = [];
            foreach ($add['classes'] as $token) {
                foreach ($requestedAdds as $priorIndex => $priorToken) {
                    if ($this->conflicts->conflictsFor($token, [$priorToken]) !== []) {
                        unset($requestedAdds[$priorIndex]);
                    }
                }
                $requestedAdds[] = $token;
            }
            $requestedAdds = array_values($requestedAdds);

            $autoRemove = [];
            $currentTokens = array_values(array_unique(array_merge($renderedBySlot[$slot] ?? [], $existingAdd)));
            foreach ($requestedAdds as $token) {
                foreach ($this->conflicts->conflictsFor($token, $currentTokens) as $conflict) {
                    if (! in_array($conflict, $autoRemove, true)) $autoRemove[] = $conflict;
                }
            }
            $removeClasses = array_values(array_unique(array_merge($remove['classes'], $autoRemove)));
            $protectedRemovals = array_values(array_filter(
                $removeClasses,
                fn (string $token): bool => $this->validator->isProtectedUtility($token)
            ));
            if ($protectedRemovals !== []) {
                $rejected[$index] = ['protected_utility_removal' => $protectedRemovals];
                continue;
            }

            foreach ($removeClasses as $token) {
                $existingAdd = array_values(array_filter($existingAdd, fn (string $v): bool => $v !== $token));
                if (! in_array($token, $existingRemove, true)) $existingRemove[] = $token;
            }
            foreach ($requestedAdds as $token) {
                $existingRemove = array_values(array_filter($existingRemove, fn (string $v): bool => $v !== $token));
                if (! in_array($token, $existingAdd, true)) $existingAdd[] = $token;
            }

            $schema['slots'][$slot] = [
                'add' => $existingAdd,
                'remove' => $existingRemove,
                'role' => Str::lower(trim((string) ($mutation['role'] ?? $existing['role'] ?? $slot))),
                'locked' => false,
            ];
            $applied[] = [
                'slot' => $slot,
                'add' => $requestedAdds,
                'remove' => $removeClasses,
                'auto_removed_conflicts' => $autoRemove,
            ];
        }

        $validated = $this->validator->validate($sparkType, $schema);
        if (! $validated['valid']) return ['block' => $block, 'applied' => [], 'rejected' => ['_schema' => $validated['errors']]];
        Arr::set($block, $this->contract->storageKey(), $validated['schema']);

        return ['block' => $block, 'applied' => $applied, 'rejected' => $rejected];
    }
}
