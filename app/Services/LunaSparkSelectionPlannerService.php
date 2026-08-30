<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

/**
 * Batch 3: Luna's registered-Spark selector + customization planner.
 *
 * Contract:
 *  - the 329-Spark scan is always deterministic/local via SparkIntentMatcherService;
 *  - Luna sees only a compact shortlist (normally 8 candidates);
 *  - Luna may select only an id present in that shortlist;
 *  - no Spark schema is sent to this stage;
 *  - output is a plan only. Batch 4 owns capability-guarded execution.
 */
final class LunaSparkSelectionPlannerService
{
    public const VERSION = 2;

    public function __construct(
        private readonly SparkIntentMatcherService $matcher,
        private readonly LunaModelDepartmentService $models,
        private readonly LunaSparkCapabilityBridgeService $capabilityBridge,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function plan(string $prompt, string $branch = 'add_spark', array $context = []): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return $this->emptyPlan('empty_prompt');
        }

        $branch = in_array($branch, ['add_spark', 'change_spark'], true) ? $branch : 'add_spark';
        $currentKey = trim((string) ($context['current_spark_key'] ?? ''));
        $currentMeta = $currentKey !== '' ? (SparkCatalog::find($currentKey) ?? []) : [];

        $matcherContext = array_filter([
            'semantic_type' => $context['semantic_type'] ?? ($branch === 'change_spark' ? ($currentMeta['semantic_type'] ?? null) : null),
            'industry' => $context['industry'] ?? null,
            'style' => $context['style'] ?? null,
            'media' => $context['media'] ?? null,
            'layout' => $context['layout'] ?? null,
        ], static fn ($v): bool => $v !== null && $v !== '' && $v !== []);

        $shortlist = $this->matcher->shortlistForAi($prompt, 10, $matcherContext);
        if ($currentKey !== '') {
            $shortlist = array_values(array_filter($shortlist, static fn (array $row): bool => (string) ($row['id'] ?? '') !== $currentKey));
        }
        $shortlist = array_slice($shortlist, 0, 8);

        if ($shortlist === []) {
            return $this->emptyPlan('no_candidates');
        }

        $analysis = $this->matcher->analyze($prompt, $matcherContext);
        $fallback = $this->deterministicFallback($prompt, $branch, $shortlist, $analysis, $currentKey);

        // If the local matcher itself reports an extremely weak first candidate,
        // expose that honestly so the caller can choose a custom/AI-Flex fallback.
        $topConfidence = (string) ($shortlist[0]['confidence'] ?? 'weak');
        if ($topConfidence === 'weak' && (float) ($shortlist[0]['score'] ?? 0) <= 0) {
            $fallback['strong_match'] = false;
            $fallback['selection_source'] = 'deterministic_weak';
            return $fallback;
        }

        try {
            $planned = $this->planWithLuna($prompt, $branch, $shortlist, $analysis, $currentMeta);
            return $this->sanitizePlan($planned, $fallback, $shortlist, $analysis);
        } catch (Throwable $e) {
            Log::warning('[Luna Spark Selector] AI selection failed; using deterministic winner.', [
                'error' => $e->getMessage(),
                'branch' => $branch,
                'current_spark_key' => $currentKey,
            ]);
            report($e);
            return $fallback;
        }
    }

    /** @return array<string,mixed> */
    private function planWithLuna(string $prompt, string $branch, array $shortlist, array $analysis, array $currentMeta): array
    {
        $candidates = array_map(static fn (array $row): array => [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'semantic_type' => (string) ($row['semantic_type'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'media' => $row['media'] ?? null,
            'layout' => array_slice((array) ($row['layout'] ?? []), 0, 8),
            'style_traits' => array_slice((array) ($row['style_traits'] ?? []), 0, 8),
            'visual_traits' => array_slice((array) ($row['visual_traits'] ?? []), 0, 8),
            'industry_fit' => array_slice((array) ($row['industry_fit'] ?? []), 0, 8),
            'capabilities' => array_slice((array) ($row['capabilities'] ?? []), 0, 16),
            'capability_manifest' => $this->capabilityBridge->manifestForSpark((string) ($row['id'] ?? '')),
            'local_score' => $row['score'] ?? null,
            'local_confidence' => $row['confidence'] ?? null,
            'local_reason' => $row['reason'] ?? null,
        ], $shortlist);

        $payload = [
            'request' => $prompt,
            'branch' => $branch,
            'detected_intent' => [
                'semantic' => $analysis['semantic'] ?? [],
                'media' => $analysis['media'] ?? [],
                'layout' => $analysis['layout'] ?? [],
                'style' => $analysis['style'] ?? [],
                'industry' => $analysis['industry'] ?? [],
                'intent' => $analysis['intent'] ?? [],
            ],
            'current_spark' => $currentMeta === [] ? null : [
                'id' => $currentMeta['key'] ?? null,
                'name' => $currentMeta['name'] ?? null,
                'semantic_type' => $currentMeta['semantic_type'] ?? null,
            ],
            'candidates' => $candidates,
        ];

        $system = <<<'PROMPT'
You are Luna's registered Cosmic Spark selector and customization planner.
Cosmic already scanned the full Spark library locally. You receive only the best candidates.
Choose the best proven Spark foundation for the user's complete request, then describe the requested tweaks.

Return ONLY valid JSON with this shape:
{
  "selected_spark_id":"candidate_id",
  "confidence":"high|medium|low",
  "reason":"short selection reason",
  "customization_plan":{
    "content":[],
    "media":[],
    "visual":[],
    "structure":[]
  },
  "execution_hint":"local|schema_editor",
  "alternate_spark_ids":[]
}

Rules:
- selected_spark_id and alternates MUST be ids from CANDIDATES only.
- Never invent a Spark id or schema field.
- Do not generate JSX, HTML, Tailwind or a component tree here.
- Keep the selected Spark's tested foundation; describe only the user's requested differences.
- Respect each candidate capability_manifest. Never request a mutation outside its modules/operations.
- Prefer local for content/media/style changes that the capability manifest supports.
- Use schema_editor for supported repeater/layout structural changes only. Never invent component nesting.
- For change_spark, preserve the current section purpose unless the user explicitly changes it.
- Put at most 3 alternates, ordered best first.
- Keep each plan item concise and actionable.
PROMPT;

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode Luna Spark selection payload.');
        }

        $response = OpenAI::chat()->create([
            'model' => $this->models->luna(),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $json],
            ],
        ]);

        $raw = trim((string) ($response->choices[0]->message->content ?? ''));
        $raw = preg_replace('/^```json\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/^```\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/```\s*$/i', '', $raw) ?? $raw;
        $decoded = json_decode(trim($raw), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Luna Spark selector returned invalid JSON.');
        }

        return $decoded;
    }

    /** @return array<string,mixed> */
    private function sanitizePlan(array $planned, array $fallback, array $shortlist, array $analysis): array
    {
        $allowed = array_values(array_filter(array_map(static fn (array $r): string => (string) ($r['id'] ?? ''), $shortlist)));
        $selected = (string) ($planned['selected_spark_id'] ?? '');
        if ($selected === '' || ! in_array($selected, $allowed, true)) {
            throw new RuntimeException('Luna selected an unsupported Spark id.');
        }

        $byId = [];
        foreach ($shortlist as $row) {
            $byId[(string) ($row['id'] ?? '')] = $row;
        }
        $selectedRow = $byId[$selected] ?? null;
        if (! is_array($selectedRow)) {
            throw new RuntimeException('Selected Spark disappeared from shortlist.');
        }

        $plan = is_array($planned['customization_plan'] ?? null) ? $planned['customization_plan'] : [];
        $normalizedPlan = [];
        foreach (['content', 'media', 'visual', 'structure'] as $bucket) {
            $items = is_array($plan[$bucket] ?? null) ? $plan[$bucket] : [];
            $normalizedPlan[$bucket] = array_values(array_slice(array_filter(array_map(
                static fn ($item): string => trim(is_scalar($item) ? (string) $item : ''),
                $items
            )), 0, 10));
        }

        $alternates = [];
        foreach ((array) ($planned['alternate_spark_ids'] ?? []) as $id) {
            $id = (string) $id;
            if ($id !== '' && $id !== $selected && in_array($id, $allowed, true) && ! in_array($id, $alternates, true)) {
                $alternates[] = $id;
            }
            if (count($alternates) >= 3) break;
        }

        $confidence = strtolower((string) ($planned['confidence'] ?? $selectedRow['confidence'] ?? 'medium'));
        if (! in_array($confidence, ['high', 'medium', 'low'], true)) $confidence = 'medium';
        $executionHint = strtolower((string) ($planned['execution_hint'] ?? 'local'));
        if (! in_array($executionHint, ['local', 'schema_editor'], true)) $executionHint = 'local';

        // Batch 4: Luna's prose plan is advisory. Cosmic's registered capability
        // contract is authoritative and removes unsupported mutations before any
        // selected Spark reaches generation/execution.
        $guard = $this->capabilityBridge->guardPlan($selected, $normalizedPlan);
        $normalizedPlan = $guard['customization_plan'];
        $executionHint = (string) ($guard['execution_hint'] ?? $executionHint);

        return [
            'version' => self::VERSION,
            'selected_spark_id' => $selected,
            'selected_spark' => SparkCatalog::find($selected),
            'confidence' => $confidence,
            'strong_match' => in_array($confidence, ['high', 'medium'], true),
            'reason' => trim((string) ($planned['reason'] ?? $selectedRow['reason'] ?? 'Matched by Luna.')),
            'customization_plan' => $normalizedPlan,
            'execution_hint' => $executionHint,
            'capability_guard' => $guard,
            'alternate_spark_ids' => $alternates,
            'shortlist' => $shortlist,
            'intent_analysis' => $analysis,
            'selection_source' => 'luna_shortlist',
        ];
    }

    /** @return array<string,mixed> */
    private function deterministicFallback(string $prompt, string $branch, array $shortlist, array $analysis, string $currentKey): array
    {
        $best = $shortlist[0];
        $selected = (string) ($best['id'] ?? '');
        $alternates = array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            array_slice($shortlist, 1, 3)
        )));
        $confidence = (string) ($best['confidence'] ?? 'low');
        if (! in_array($confidence, ['high', 'medium', 'low', 'weak'], true)) $confidence = 'low';
        $fallbackPlan = [
            'content' => ['Rewrite/fill content to match the user request and current page context.'],
            'media' => [], 'visual' => [], 'structure' => [],
        ];
        $guard = $selected !== '' ? $this->capabilityBridge->guardPlan($selected, $fallbackPlan) : null;

        return [
            'version' => self::VERSION,
            'selected_spark_id' => $selected,
            'selected_spark' => $selected !== '' ? SparkCatalog::find($selected) : null,
            'confidence' => $confidence,
            'strong_match' => in_array($confidence, ['high', 'medium'], true),
            'reason' => (string) ($best['reason'] ?? 'Best deterministic Spark match.'),
            'customization_plan' => is_array($guard) ? $guard['customization_plan'] : $fallbackPlan,
            'execution_hint' => is_array($guard) ? ($guard['execution_hint'] ?? 'local') : 'local',
            'capability_guard' => $guard,
            'alternate_spark_ids' => $alternates,
            'shortlist' => $shortlist,
            'intent_analysis' => $analysis,
            'selection_source' => 'deterministic_fallback',
            'branch' => $branch,
            'current_spark_key' => $currentKey,
            'request' => $prompt,
        ];
    }

    /** @return array<string,mixed> */
    private function emptyPlan(string $reason): array
    {
        return [
            'version' => self::VERSION,
            'selected_spark_id' => '',
            'selected_spark' => null,
            'confidence' => 'weak',
            'strong_match' => false,
            'reason' => $reason,
            'customization_plan' => ['content' => [], 'media' => [], 'visual' => [], 'structure' => []],
            'execution_hint' => 'local',
            'capability_guard' => null,
            'alternate_spark_ids' => [],
            'shortlist' => [],
            'intent_analysis' => [],
            'selection_source' => 'none',
        ];
    }
}
