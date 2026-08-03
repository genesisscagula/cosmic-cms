<?php

namespace App\AI\Schemas;

final class SelectedSchemaLoader
{
    /**
     * Resolve only the schema methods required by the selected Sparks.
     *
     * @return array{
     *     selected: array<int, string>,
     *     methods: array<string, string>,
     *     skipped: array<int, string>,
     *     duplicate_count: int
     * }
     */
    public function resolve(array $sections): array
    {
        $schemaMap = SchemaManager::map();
        $selected = [];
        $methods = [];
        $skipped = [];
        $seen = [];
        $duplicateCount = 0;

        foreach ($sections as $section) {
            if (! is_string($section)) {
                continue;
            }

            $section = trim($section);
            if ($section === '') {
                continue;
            }

            if (isset($seen[$section])) {
                $duplicateCount++;
                continue;
            }

            $seen[$section] = true;

            if (! isset($schemaMap[$section])) {
                $skipped[] = $section;
                continue;
            }

            $selected[] = $section;
            $methods[$section] = $schemaMap[$section];
        }

        return [
            'selected' => $selected,
            'methods' => $methods,
            'skipped' => $skipped,
            'duplicate_count' => $duplicateCount,
        ];
    }
}
