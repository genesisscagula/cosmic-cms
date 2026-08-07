<?php

namespace App\AI\Schemas;

use App\AI\Cache\AiCacheManager;

final class SelectedSchemaLoader
{
    public function __construct(private readonly ?AiCacheManager $cache = null)
    {
    }
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
        $cache = $this->cache ?? app(AiCacheManager::class);
        $schemaMap = SchemaManager::map();
        $cached = $cache->remember(
            'schema-selection',
            [
                'sections' => array_values($sections),
                'schema_map_hash' => hash('sha256', json_encode($schemaMap) ?: serialize($schemaMap)),
                'version' => '16.4.0',
            ],
            (int) config('openai.schema_cache_ttl', 86400),
            fn () => $this->resolveUncached($sections, $schemaMap),
            (bool) config('openai.schema_cache_enabled', true),
        );

        $result = is_array($cached['value']) ? $cached['value'] : [];
        $result['cache'] = $cached['cache'];
        $result['cache_key'] = $cache->shortKey($cached['key']);

        return $result;
    }

    private function resolveUncached(array $sections, array $schemaMap): array
    {
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
