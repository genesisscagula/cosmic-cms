<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LunaAiFlexSparkService
{
    public function __construct(private readonly LunaModelDepartmentService $models) {}

    /** Prefer a registered Spark when it is a strong semantic/layout match. */
    public function registeredFallback(string $request, array $catalog, string $excludeKey = ''): ?array
    {
        $q = Str::lower($request);
        $tokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', $q) ?: [], fn($v) => strlen($v) >= 4));
        $ranked = collect($catalog)->filter(fn($s) => is_array($s) && !empty($s['key']) && ($s['key'] ?? '') !== $excludeKey)
            ->map(function(array $s) use ($tokens) {
                $hay = Str::lower(implode(' ', array_filter([
                    $s['key'] ?? '', $s['name'] ?? '', $s['description'] ?? '', $s['category'] ?? '',
                    implode(' ', (array)($s['aliases'] ?? [])), implode(' ', (array)($s['layout'] ?? [])),
                    implode(' ', (array)($s['style'] ?? [])), implode(' ', (array)($s['intent'] ?? [])),
                    implode(' ', (array)($s['capabilities'] ?? [])),
                ])));
                $score = 0;
                foreach ($tokens as $token) if (Str::contains($hay, $token)) $score += 1;
                $s['_ai_flex_match_score'] = $score;
                return $s;
            })->sortByDesc('_ai_flex_match_score')->values();
        $best = $ranked->first();
        // Deliberately conservative: custom_spark was already chosen by API 3.
        // Only escape Flex when the library has several direct lexical matches.
        return is_array($best) && ($best['_ai_flex_match_score'] ?? 0) >= 3 ? $best : null;
    }

    /** Generate an export-safe structured Flex block. Screenshot/reference is composition-only. */
    public function generate(string $request, array $theme = [], array $pageContext = [], array $source = []): array
    {
        $apiKey = (string) config('openai.api_key');
        if ($apiKey === '') throw new \RuntimeException('OpenAI API key is not configured.');

        $system = <<<'PROMPT'
You are Sol, Cosmic CMS's senior section composer. Return JSON only with {"block":{...}}.
Create ONE structured AI Flex section using the existing luna_custom_section renderer. Never output HTML, CSS, JSX, scripts, arbitrary Tailwind classes, or executable code.
The user's current Cosmic theme is authoritative. A screenshot/reference, when mentioned, is composition inspiration only: borrow hierarchy/layout ideas but preserve the active site's palette, typography, button language, surfaces and brand character unless the user explicitly asks to change the theme.
Allowed block keys: type,custom_spark_key,custom_spark_saved,category,layout,alignment,media_position,density,accent_shape,section_mood,eyebrow,heading,heading_accent_text,text,primary_label,primary_url,secondary_label,secondary_url,image_url,items,theme,visual_style,review,form,ai_flex.
Required: type="luna_custom_section", custom_spark_saved=false, theme="auto".
Allowed layout: editorial,split,feature-grid,card-grid,media-led,stacked,centered.
Allowed alignment: left,center,right. Allowed media_position: left,right,background,top,none.
items is an array of content cards with only title,text,icon,label,url,image_url.
ai_flex is metadata only: {"version":1,"source":"sol","reference_mode":"composition_only","theme_policy":"inherit","intent":"short description"}.
visual_style may use only renderer-supported scalar design values; prefer theme inheritance and restrained values. Do not invent remote image URLs. Preserve useful source content when replacing a section.
PROMPT;
        $payload = [
            'request' => $request,
            'active_theme' => $theme,
            'page_context' => $pageContext,
            'source_section' => $source,
        ];
        $endpoint = rtrim((string)(config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/chat/completions';
        $messages = [
            ['role'=>'system','content'=>$system],
            ['role'=>'user','content'=>json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
        ];

        // AI Flex is an important mutation path, so do not silently collapse to a
        // no-op when a model/JSON-mode request has a transient compatibility issue.
        // Try Sol in strict JSON mode first, retry Sol without response_format, and
        // finally use Terra only as an availability fallback. The chosen model is
        // recorded in ai_flex metadata and logs so QA can prove which department ran.
        $attempts = [
            ['model'=>$this->models->sol(), 'json_mode'=>true,  'department'=>'sol'],
            ['model'=>$this->models->sol(), 'json_mode'=>false, 'department'=>'sol_retry'],
        ];
        if ($this->models->terra() !== $this->models->sol()) {
            $attempts[] = ['model'=>$this->models->terra(), 'json_mode'=>true, 'department'=>'terra_fallback'];
        }

        $lastError = null;
        foreach ($attempts as $attempt) {
            try {
                $body = ['model'=>$attempt['model'], 'messages'=>$messages];
                if ($attempt['json_mode']) $body['response_format'] = ['type'=>'json_object'];

                $response = Http::withToken($apiKey)->connectTimeout(30)->timeout(150)
                    ->post($endpoint, $body)->throw()->json();
                $content = (string)data_get($response,'choices.0.message.content','');
                $decoded = json_decode($content, true);
                $block = is_array($decoded['block'] ?? null) ? $decoded['block'] : [];
                if ($block === []) throw new \RuntimeException('AI Flex model returned no valid block object.');

                $validated = $this->validate($block, $source);
                $validated = $this->applyCompositionPolish($validated, $request, $source);
                $validated['ai_flex']['model'] = (string)$attempt['model'];
                $validated['ai_flex']['department'] = (string)$attempt['department'];
                Log::debug('[AiFlex] generated', [
                    'model'=>$attempt['model'], 'department'=>$attempt['department'],
                    'layout'=>$validated['layout']??null, 'media_position'=>$validated['media_position']??null,
                ]);
                return $validated;
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning('[AiFlex] generation attempt failed', [
                    'model'=>$attempt['model'], 'department'=>$attempt['department'],
                    'error'=>$e->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException('AI Flex generation failed after all model attempts.', 0, $lastError);
    }

    private function applyCompositionPolish(array $block, string $request, array $source = []): array
    {
        $haystack = Str::lower($request.' '.($block['category'] ?? '').' '.($block['ai_flex']['intent'] ?? '').' '.($source['type'] ?? ''));
        if (! Str::contains($haystack, ['hero', 'banner'])) return $block;
        $block['category'] = 'hero';
        $block['layout'] = in_array($block['layout'] ?? '', ['split','media-led'], true) ? $block['layout'] : 'split';
        if (! in_array($block['media_position'] ?? '', ['left','right'], true)) $block['media_position'] = 'right';
        $block['alignment'] = 'left';
        $v = is_array($block['visual_style'] ?? null) ? $block['visual_style'] : [];
        $v['content_max_width'] = max(1280, min(1480, (int)($v['content_max_width'] ?? 1400)));
        $v['copy_width_percent'] = 100; // Split-grid hero copy already owns its column; avoid percent-of-a-percent narrowing.
        $v['media_width_percent'] = max(52, min(62, (int)($v['media_width_percent'] ?? 56)));
        $v['heading_size'] = max(56, min(76, (int)($v['heading_size'] ?? 68)));
        $v['heading_line_height'] = max(.94, min(1.08, (float)($v['heading_line_height'] ?? .98)));
        $v['section_min_height'] = max(620, min(820, (int)($v['section_min_height'] ?? 680)));
        $v['section_padding_y'] = max(72, min(120, (int)($v['section_padding_y'] ?? 88)));
        $v['content_gap'] = max(20, min(32, (int)($v['content_gap'] ?? 24)));
        $v['card_radius'] = max(20, min(32, (int)($v['card_radius'] ?? 24)));
        $v['item_columns'] = 1;
        $v['item_card_style'] = 'card';
        $v['card_padding'] = max(20, min(28, (int)($v['card_padding'] ?? 24)));
        $block['visual_style'] = $v;
        $block['ai_flex']['composition_profile'] = 'premium_hero_v1';
        return $block;
    }

    public function validate(array $block, array $source = []): array
    {
        $allowed = ['type','custom_spark_key','custom_spark_saved','category','layout','alignment','media_position','density','accent_shape','section_mood','eyebrow','heading','heading_accent_text','text','primary_label','primary_url','secondary_label','secondary_url','image_url','items','theme','visual_style','review','form','ai_flex'];
        $block = array_intersect_key($block, array_flip($allowed));
        $block['type'] = 'luna_custom_section';
        $block['custom_spark_key'] = 'ai-flex-'.Str::lower(Str::random(12));
        $block['custom_spark_saved'] = false;
        $block['theme'] = 'auto';
        $block['layout'] = in_array($block['layout'] ?? '', ['editorial','split','feature-grid','card-grid','media-led','stacked','centered'], true) ? $block['layout'] : 'editorial';
        $block['alignment'] = in_array($block['alignment'] ?? '', ['left','center','right'], true) ? $block['alignment'] : 'left';
        $block['media_position'] = in_array($block['media_position'] ?? '', ['left','right','background','top','none'], true) ? $block['media_position'] : 'none';
        $block['items'] = collect(is_array($block['items'] ?? null) ? $block['items'] : [])->take(12)->map(fn($item) => is_array($item) ? array_intersect_key($item,array_flip(['title','text','icon','label','url','image_url'])) : [])->values()->all();
        $block['ai_flex'] = array_merge(['version'=>1,'source'=>'sol','reference_mode'=>'composition_only','theme_policy'=>'inherit'], is_array($block['ai_flex'] ?? null) ? array_intersect_key($block['ai_flex'],array_flip(['version','source','reference_mode','theme_policy','intent','model','department','composition_profile'])) : []);
        foreach (['heading','eyebrow','text','primary_label','primary_url','secondary_label','secondary_url','image_url','category','density','accent_shape','section_mood'] as $key) {
            if (isset($block[$key]) && !is_scalar($block[$key])) unset($block[$key]);
        }
        if (trim((string)($block['heading'] ?? '')) === '' && !empty($source['heading'])) $block['heading'] = $source['heading'];
        if (empty($block['text']) && !empty($source['text'])) $block['text'] = $source['text'];
        return $block;
    }
}
