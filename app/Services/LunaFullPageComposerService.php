<?php

namespace App\Services;

use App\Services\SparkCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sol-only full-page art director. It composes a page from registered Sparks
 * and AI Flex escape hatches; the backend still generates/validates every
 * concrete block and remains the execution authority.
 */
final class LunaFullPageComposerService
{
    public function __construct(private readonly LunaModelDepartmentService $models) {}

    public function shouldCompose(string $prompt, string $scope): bool
    {
        if ($scope !== 'page') return false;
        $q = Str::lower($prompt);
        $whole = Str::contains($q, ['full page','whole page','entire page','complete page','from scratch'])
            || (bool) preg_match('/\b(?:full|whole|entire|complete)\b.{0,60}\bpage\b/i', $prompt);
        $custom = Str::contains($q, ['custom','unique','bespoke','specific design','specific layout','redesign','design me','build me']);
        $premade = Str::contains($q, ['premade','pre-made','page composition','full-page composition','full page composition','template-style composition','page blueprint']);
        $explicitBuild = Str::contains($q, ['build the entire','build a complete','build the full','rebuild the entire','rebuild a complete','rebuild the full','rebuild this entire','rebuild this complete']);

        // Builder quick actions intentionally ask Luna for a complete premade
        // composition. Treat those as full-page composer requests too; requiring
        // the word "custom" made the quick action fall back to generic page logic.
        return $whole && ($custom || $premade || $explicitBuild);
    }

    /** @return array{slots:array<int,array<string,mixed>>,model_department:string,composer:string} */
    public function plan(string $request, array $theme, array $currentBlocks, array $bundleContext = [], ?array $allowedSparkKeys = null): array
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') throw new \RuntimeException('OpenAI API key is not configured.');

        $allowedSparkMap=is_array($allowedSparkKeys)
            ? array_flip(array_values(array_filter(array_map('strval',$allowedSparkKeys))))
            : null;
        $catalog=collect(SparkCatalog::all())
            ->filter(fn($s)=>is_array($s)&&!empty($s['key'])&&(!is_array($allowedSparkMap)||isset($allowedSparkMap[(string)$s['key']])))
            ->map(fn($s)=>array_filter([
                'key'=>$s['key']??null,'name'=>$s['name']??null,'category'=>$s['category']??null,
                'description'=>$s['description']??null,'aliases'=>$s['aliases']??null,
            ],fn($v)=>$v!==null&&$v!==''&&$v!==[]))->take(360)->values()->all();
        $current=collect($currentBlocks)->map(fn($b,$i)=>[
            'index'=>$i,'type'=>$b['type']??null,'heading'=>$b['heading']??$b['title']??null,
        ])->values()->all();

        $system=<<<'TXT'
You are Sol, Cosmic CMS's senior full-page art director. Return JSON only: {"slots":[...]}.
Design ONE cohesive page. Each slot must be either:
{"mode":"registered","spark_key":"EXACT catalog key","instruction":"content/layout intent"}
or
{"mode":"flex","instruction":"specific custom composition intent"}.
Use 5-9 slots. Prefer registered Sparks whenever they can satisfy the section. Use flex only when the requested composition is genuinely not represented well by the catalog. Keep one clear hero and end with conversion/contact when appropriate. Avoid repetitive card/grid rhythms.
The active Cosmic theme is authoritative: preserve palette, typography, button language, spacing rhythm, surfaces and brand character unless the user explicitly requests a rebrand. Screenshot/reference material is composition inspiration only, never a license to copy its brand styling.
Never output HTML/CSS/JSX/Tailwind/code. Never invent a registered spark_key. Bundle context is a design consistency guardrail, not a ban on a flex slot when the request genuinely requires one.
TXT;
        $payload=['request'=>$request,'active_theme'=>$theme,'bundle_context'=>$bundleContext,'current_page'=>$current,'registered_sparks'=>$catalog];
        $response=Http::withToken($apiKey)->connectTimeout(30)->timeout(180)->post(
            rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions',
            ['model'=>$this->models->sol(),'response_format'=>['type'=>'json_object'],'messages'=>[
                ['role'=>'system','content'=>$system],['role'=>'user','content'=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]
            ]]
        )->throw()->json();
        $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
        $keys=collect($catalog)->pluck('key')->flip();
        $slots=collect(is_array($decoded['slots']??null)?$decoded['slots']:[])->take(9)->map(function($slot)use($keys){
            if(!is_array($slot))return null;
            $mode=($slot['mode']??'')==='flex'?'flex':'registered';
            $instruction=trim((string)($slot['instruction']??''));
            if($mode==='flex') return ['mode'=>'flex','instruction'=>$instruction?:'Create a custom section that serves this point in the page journey.'];
            $key=trim((string)($slot['spark_key']??''));
            return $key!==''&&$keys->has($key)?['mode'=>'registered','spark_key'=>$key,'instruction'=>$instruction]:null;
        })->filter()->values()->all();
        if(count($slots)<3) throw new \RuntimeException('Sol did not return a valid full-page composition.');
        return ['slots'=>$slots,'model_department'=>'sol','composer'=>'sol_full_page_v1'];
    }
}
