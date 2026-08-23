<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LunaKnowledgeRouter
{
    public function __construct(
        private readonly LunaCapabilityRegistry $capabilities,
        private readonly LunaIntentIndex $intents
    ) {}

    private const CATEGORY_DOCS = [
        'conversation' => ['PRODUCT.md','CAPABILITIES.md','CONVERSATION.md','LIMITATIONS.md'],
        'builder' => ['BUILDER.md','DESIGN_SYSTEM.md','CAPABILITIES.md'],
        'design_system' => ['DESIGN_SYSTEM.md','CAPABILITIES.md','GUARDRAILS.md'],
        'header' => ['HEADER_FOOTER.md','CAPABILITIES.md','LIMITATIONS.md'],
        'footer' => ['HEADER_FOOTER.md','CAPABILITIES.md','LIMITATIONS.md'],
        'media' => ['MEDIA.md','CAPABILITIES.md','CREDITS.md'],
        'pages' => ['PAGES.md','CAPABILITIES.md','CONVERSATION.md'],
        'publishing' => ['PUBLISHING.md','CAPABILITIES.md','QA.md'],
    ];

    private const TERM_ALIASES = [
        'header' => ['header','navigation','nav','menu','submenu','logo','cta'],
        'footer' => ['footer','mega footer','copyright','privacy','terms','social'],
        'media' => ['image','photo','media','logo','video','pexels','upload','library'],
        'pages' => ['page','pages','website','site','homepage','home page','build website','generate website'],
        'builder' => ['section','spark','builder','card','heading','text','button','edit','redesign','layout'],
        'design_system' => ['theme','brand','colour','color','palette','hex','typography','font','radius','spacing','design system'],
        'publishing' => ['publish','published','live','export','deployment','preview'],
        'conversation' => ['what can you do','can you','are you able','is it possible','do you support','supported','available','limitation','limit'],
    ];

    /**
     * Returns a compact, request-specific knowledge packet for the LLM.
     * This is grounding context only; server-side execution guards remain authoritative.
     */
    public function contextFor(string $prompt, ?string $scope = null): array
    {
        $query = Str::lower(trim($prompt));
        $intentScan=$this->intents->classify($prompt);
        $intentCategory=(string)($intentScan['intent']['category']??'');

        $capabilities = collect($this->capabilities->all())
            ->map(function (array $capability) use ($query, $scope, $intentCategory) {
                $score = $this->capabilityScore($query, $capability, $scope, $intentCategory);
                return ['score'=>$score, 'capability'=>$this->capabilities->searchableSummary($capability)];
            })
            ->filter(fn (array $item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take(6)
            ->values();

        if ($capabilities->isEmpty()) {
            $capabilities = collect($this->capabilities->all())
                ->filter(fn (array $capability) => in_array(($capability['category'] ?? ''), ['conversation','builder'], true))
                ->take(4)
                ->map(fn (array $capability) => ['score'=>1, 'capability'=>$this->capabilities->searchableSummary($capability)])
                ->values();
        }

        $categories = $capabilities
            ->pluck('capability.category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $documentHints=$capabilities
            ->flatMap(fn(array $item)=>$item['capability']['doc_refs']??[])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $documents = $this->documentsFor($categories,$documentHints);

        return [
            'query_type' => $this->isCapabilityQuestion($prompt) ? 'capability_question' : 'action_or_general',
            'intent' => $intentScan['intent'] ?? null,
            'scope' => $scope,
            'capabilities' => $capabilities->pluck('capability')->all(),
            'documents' => $documents,
            'grounding_rule' => 'Use this packet only to ground Cosmic CMS capability/product claims. The canonical Chat vs Action router owns whether the turn is conversational or executable; this knowledge packet must not reinterpret that top-level route. If support is not established here, do not invent it. Keep customer-facing replies free of internal implementation terminology. Runtime/security/validation/billing guards remain authoritative.',
        ];
    }

    public function isCapabilityQuestion(string $prompt): bool
    {
        if($this->intents->isCapabilityQuestion($prompt)) return true;

        $q = Str::lower(trim($prompt));
        if ($q === '') return false;

        // Capability questions often arrive with a conversational greeting:
        // "Hello Luna, what can you do?" should remain informational.
        $q = preg_replace(
            '/^(?:(?:hello|hi|hey|good\s+(?:morning|afternoon|evening))\b[\s,!.:-]*(?:luna\b[\s,!.:-]*)?)+/i',
            '',
            $q
        ) ?? $q;
        $q = trim($q);
        if ($q === '') return false;

        $patterns = [
            '/^(what|which)\s+(?:all\s+)?(?:can|could)\s+(?:you|luna)\b/i',
            '/^(do|does)\s+(?:you|luna|cosmic cms)\s+(?:support|allow|have)\b/i',
            '/^(is|are)\s+(?:it|this|there)\s+(?:possible|supported|available)\b/i',
            '/\bwhat (?:can|could) luna do\b/i',
            '/\bwhat are (?:your|luna.?s) (?:capabilities|limits|limitations)\b/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $q)) return true;
        }

        return false;
    }

    private function capabilityScore(string $query, array $capability, ?string $scope, string $intentCategory=''): int
    {
        $score = 0;
        $haystack = Str::lower(implode(' ', array_filter([
            $capability['id'] ?? '',
            $capability['name'] ?? '',
            $capability['category'] ?? '',
            implode(' ', $capability['can_do'] ?? []),
            implode(' ', $capability['cannot_do'] ?? []),
            is_array($capability['limits'] ?? null) ? json_encode($capability['limits']) : '',
            implode(' ', $capability['tags'] ?? []),
            implode(' ', $capability['aliases'] ?? []),
            implode(' ', $capability['keywords'] ?? []),
        ])));

        $words = preg_split('/[^a-z0-9#_-]+/i', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach (array_unique($words) as $word) {
            if (strlen($word) < 3) continue;
            if (Str::contains($haystack, $word)) $score += 2;
        }

        $category = (string)($capability['category'] ?? '');
        if($intentCategory!=='' && $category===$intentCategory) $score += 12;

        foreach($capability['aliases']??[] as $alias){
            $alias=Str::lower(trim((string)$alias));
            if($alias!=='' && Str::contains($query,$alias)) $score += 10;
        }
        foreach($capability['tags']??[] as $tag){
            $tag=Str::lower(trim((string)$tag));
            if(strlen($tag)>=3 && Str::contains($query,$tag)) $score += 4;
        }

        foreach (self::TERM_ALIASES[$category] ?? [] as $term) {
            if (Str::contains($query, $term)) $score += 5;
        }

        if ($scope && in_array($scope, $capability['scopes'] ?? [], true)) $score += 3;
        if ($this->isCapabilityQuestion($query) && $category === 'conversation') $score += 4;

        return $score;
    }

    private function documentsFor(array $categories, array $documentHints=[]): array
    {
        $names = collect($documentHints)
            ->merge(collect($categories)->flatMap(fn (string $category) => self::CATEGORY_DOCS[$category] ?? []))
            ->push('GUARDRAILS.md')
            ->unique()
            ->take(6)
            ->values()
            ->all();

        return collect($names)
            ->map(function (string $name) {
                $text = $this->readDoc($name);
                if ($text === '') return null;

                return [
                    'file' => $name,
                    'excerpt' => Str::limit($text, 4200, "\n...[documentation excerpt truncated]"),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function readDoc(string $name): string
    {
        $path = base_path('docs/luna/'.$name);
        if (!is_file($path)) return '';

        $version = (string)(filemtime($path) ?: 0);
        return Cache::rememberForever('luna-doc:'.sha1($name.'|'.$version), function () use ($path) {
            return trim((string) file_get_contents($path));
        });
    }
}
