<?php

namespace App\Services;

use App\AI\Clients\OpenAIClient;
use App\AI\Registries\SparkPlannerRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiLibrarySearchService
{
    public function __construct(private readonly OpenAIClient $openAI)
    {
    }

    public function search(string $type, string $prompt, int $limit = 8): array
    {
        $type = strtolower(trim($type));
        if (! in_array($type, ['templates', 'sparks'], true)) {
            throw new RuntimeException('Unsupported AI library search type.');
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            return [];
        }

        $limit = max(1, min($limit, 12));
        $cacheKey = 'ai-library-search:v1:'.sha1($type.'|'.Str::lower($prompt).'|'.$limit);

        return Cache::remember($cacheKey, now()->addMinutes(20), function () use ($type, $prompt, $limit) {
            $catalog = $type === 'templates' ? $this->templateCatalog() : $this->sparkCatalog();
            $shortlist = $this->localShortlist($catalog, $prompt, 28);

            if ($shortlist === []) {
                return [];
            }

            try {
                return $this->rankWithAi($type, $prompt, $shortlist, $limit);
            } catch (Throwable $e) {
                report($e);

                return array_slice(array_map(
                    static fn (array $item): array => [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'score' => $item['_score'] ?? 0,
                        'reason' => 'Matched by Cosmic local search.',
                    ],
                    $shortlist
                ), 0, $limit);
            }
        });
    }

    private function templateCatalog(): array
    {
        return array_map(static function (array $template): array {
            return [
                'id' => (string) ($template['key'] ?? ''),
                'name' => (string) ($template['name'] ?? $template['key'] ?? ''),
                'description' => (string) ($template['description'] ?? ''),
                'terms' => array_values(array_filter(array_merge(
                    (array) ($template['aliases'] ?? []),
                    (array) ($template['industry'] ?? []),
                    (array) ($template['intent'] ?? []),
                    (array) ($template['audience'] ?? []),
                    (array) ($template['style'] ?? []),
                    (array) ($template['features'] ?? []),
                    (array) ($template['tags'] ?? [])
                ))),
            ];
        }, PageTemplateCatalog::all());
    }

    private function sparkCatalog(): array
    {
        return array_map(static fn (array $spark): array => [
            'id' => (string) ($spark['slug'] ?? ''),
            'name' => Str::headline((string) ($spark['slug'] ?? '')),
            'description' => (string) ($spark['description'] ?? ''),
            'terms' => array_values(array_filter([
                (string) ($spark['category'] ?? ''),
                str_replace('_', ' ', (string) ($spark['slug'] ?? '')),
                (string) ($spark['description'] ?? ''),
            ])),
        ], SparkPlannerRegistry::all());
    }

    private function localShortlist(array $catalog, string $prompt, int $max): array
    {
        $query = $this->tokens($prompt);
        $phrase = Str::lower($prompt);

        foreach ($catalog as &$item) {
            $haystackParts = array_merge([$item['id'], $item['name'], $item['description']], $item['terms']);
            $haystack = Str::lower(implode(' ', $haystackParts));
            $score = 0;

            foreach ($query as $token) {
                if (Str::contains(Str::lower($item['name']), $token)) {
                    $score += 6;
                }
                if (Str::contains(Str::lower($item['id']), $token)) {
                    $score += 5;
                }
                if (Str::contains($haystack, $token)) {
                    $score += 2;
                }
            }

            foreach ((array) $item['terms'] as $term) {
                $term = Str::lower((string) $term);
                if ($term !== '' && (Str::contains($phrase, $term) || Str::contains($term, $phrase))) {
                    $score += 8;
                }
            }

            $item['_score'] = $score;
        }
        unset($item);

        usort($catalog, static fn (array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: strcmp($a['name'], $b['name']));

        $positive = array_values(array_filter($catalog, static fn (array $item): bool => ($item['_score'] ?? 0) > 0));
        $pool = $positive !== [] ? $positive : $catalog;

        return array_slice($pool, 0, $max);
    }

    private function rankWithAi(string $type, string $prompt, array $shortlist, int $limit): array
    {
        $allowed = array_column($shortlist, 'id');
        $payload = array_map(static fn (array $item): array => [
            'id' => $item['id'],
            'name' => $item['name'],
            'description' => $item['description'],
            'terms' => array_slice($item['terms'], 0, 24),
        ], $shortlist);

        $system = <<<PROMPT
You are Luna's Cosmic Library Search ranker.
Rank the supplied {$type} for the user's request.
Return ONLY valid JSON in this exact shape: {"results":[{"id":"candidate_id","reason":"short reason"}]}.
Rules:
- Use only candidate ids from CANDIDATES.
- Return at most {$limit} results.
- Order best match first.
- Prefer intent, industry, audience, visual style and requested features over superficial word overlap.
- Never invent ids.
- Keep each reason under 18 words.
PROMPT;

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode AI library shortlist.');
        }

        $raw = $this->openAI->chat($system, "USER REQUEST\n{$prompt}\n\nCANDIDATES\n{$json}");
        $raw = trim((string) $raw);
        $raw = preg_replace('/^```json\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/^```\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/```\s*$/i', '', $raw) ?? $raw;
        $decoded = json_decode(trim($raw), true);

        if (! is_array($decoded) || ! is_array($decoded['results'] ?? null)) {
            throw new RuntimeException('AI library search returned invalid JSON.');
        }

        $byId = [];
        foreach ($shortlist as $item) {
            $byId[$item['id']] = $item;
        }

        $results = [];
        foreach ($decoded['results'] as $row) {
            $id = is_array($row) ? (string) ($row['id'] ?? '') : '';
            if ($id === '' || ! in_array($id, $allowed, true) || isset($results[$id])) {
                continue;
            }
            $item = $byId[$id];
            $results[$id] = [
                'id' => $id,
                'name' => $item['name'],
                'score' => $item['_score'] ?? 0,
                'reason' => trim((string) ($row['reason'] ?? 'Recommended by Luna.')),
            ];
            if (count($results) >= $limit) {
                break;
            }
        }

        if ($results === []) {
            throw new RuntimeException('AI library search returned no supported results.');
        }

        return array_values($results);
    }

    private function tokens(string $value): array
    {
        $value = preg_replace('/[^\pL\pN]+/u', ' ', Str::lower($value)) ?? '';
        $stop = ['the','a','an','and','or','for','to','of','with','in','on','my','me','i','we','our','website','site','page','template','spark','section','need','want','make','build'];

        return array_values(array_unique(array_filter(
            preg_split('/\s+/u', trim($value)) ?: [],
            static fn (string $token): bool => mb_strlen($token) >= 2 && ! in_array($token, $stop, true)
        )));
    }
}
