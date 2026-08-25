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
Allowed block keys: type,custom_spark_key,custom_spark_saved,semantic_type,source_type,category,layout,alignment,media_position,density,accent_shape,section_mood,eyebrow,heading,heading_accent_text,text,primary_label,primary_url,secondary_label,secondary_url,image_url,items,theme,visual_style,review,form,ai_flex.
Required: type="luna_custom_section", custom_spark_saved=false, theme="auto". semantic_type is the logical section role (hero, services, testimonials, faq, contact, pricing, cta, content, etc.). When replacing a source section, preserve/inherit its logical role.
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

    /** Convert an uploaded screenshot/reference into one validated AI Flex block. */
    public function generateFromReference($file, string $request, array $theme = [], array $pageContext = [], array $source = [], string $referenceMode = 'layout_only'): array
    {
        $apiKey = (string) config('openai.api_key');
        if ($apiKey === '') throw new \RuntimeException('OpenAI API key is not configured.');
        if (!$file || !method_exists($file, 'getRealPath')) throw new \InvalidArgumentException('A valid reference image is required.');

        $bytes = @file_get_contents($file->getRealPath());
        if ($bytes === false || $bytes === '') throw new \RuntimeException('Reference image could not be read.');
        $mime = (string)($file->getMimeType() ?: 'image/png');
        $dataUri = 'data:'.$mime.';base64,'.base64_encode($bytes);
        $dimensions = @getimagesize($file->getRealPath());
        $ratio = is_array($dimensions) && ($dimensions[0] ?? 0) > 0 && ($dimensions[1] ?? 0) > 0
            ? round(((float)$dimensions[0]) / ((float)$dimensions[1]), 5) : null;
        $referenceMode = $referenceMode === 'layout_and_theme' ? 'layout_and_theme' : 'layout_only';
        $themePolicy = $referenceMode === 'layout_and_theme' ? 'derive_from_reference' : 'inherit';

        $system = <<<'PROMPT'
You are Sol, Cosmic CMS's visual-reference section architect. Analyze ONE supplied website-section screenshot and return JSON only as {"block":{...}}.
Output ONE structured luna_custom_section. Never output HTML, CSS, JSX, scripts, raw Tailwind, or executable code.
Preserve the screenshot's composition: hierarchy, proportions, alignment, media placement, card geometry, density, spacing rhythm and visible UI structure.
If reference_mode=layout_only, the ACTIVE COSMIC THEME IS AUTHORITATIVE: do not copy screenshot colors/fonts/brand styling; use theme inheritance.
If reference_mode=layout_and_theme, you may infer visual colors/style from the screenshot into renderer-supported visual_style values; do not invent a separate arbitrary design system.
When replacing a source section, preserve its semantic role and useful current copy/CTA meaning unless the user explicitly asks to replace content.
Allowed block keys: type,custom_spark_key,custom_spark_saved,semantic_type,source_type,category,layout,alignment,media_position,density,accent_shape,section_mood,eyebrow,heading,heading_accent_text,text,primary_label,primary_url,secondary_label,secondary_url,image_url,items,theme,visual_style,review,form,ai_flex.
Allowed layout: editorial,split,feature-grid,card-grid,media-led,stacked,centered. media_position: left,right,background,top,none. alignment: left,center,right.
Never invent a remote image URL. image_url must remain empty unless a source section already has a usable image URL.
ai_flex metadata must identify source=sol, reference_mode, theme_policy and a short intent.
PROMPT;
        $payload = [
            'request'=>$request,
            'reference_mode'=>$referenceMode,
            'theme_policy'=>$themePolicy,
            'reference_aspect_ratio'=>$ratio,
            'active_theme'=>$theme,
            'page_context'=>$pageContext,
            'source_section'=>$source,
        ];
        $messages = [
            ['role'=>'system','content'=>$system],
            ['role'=>'user','content'=>[
                ['type'=>'text','text'=>json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                ['type'=>'text','text'=>'REFERENCE SCREENSHOT'],
                ['type'=>'image_url','image_url'=>['url'=>$dataUri,'detail'=>'high']],
            ]],
        ];
        $endpoint = rtrim((string)(config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/chat/completions';
        $attempts = [
            ['model'=>$this->models->sol(),'json_mode'=>true,'department'=>'sol_reference'],
            ['model'=>$this->models->sol(),'json_mode'=>false,'department'=>'sol_reference_retry'],
        ];
        $lastError = null;
        foreach ($attempts as $attempt) {
            try {
                $body=['model'=>$attempt['model'],'messages'=>$messages];
                if($attempt['json_mode']) $body['response_format']=['type'=>'json_object'];
                $response=Http::withToken($apiKey)->connectTimeout(30)->timeout(180)->post($endpoint,$body)->throw()->json();
                $decoded=json_decode((string)data_get($response,'choices.0.message.content',''),true);
                $raw=is_array($decoded['block']??null)?$decoded['block']:[];
                if($raw===[]) throw new \RuntimeException('Sol returned no valid reference block.');
                // A screenshot must never silently discard a recoverable current media asset.
                if(trim((string)($raw['image_url']??''))==='' && trim((string)($source['image_url']??''))!=='') $raw['image_url']=$source['image_url'];
                $block=$this->validate($raw,$source);
                $block=$this->applyCompositionPolish($block,$request,$source);
                $block['ai_flex']['reference_mode']=$referenceMode;
                $block['ai_flex']['theme_policy']=$themePolicy;
                $block['ai_flex']['model']=(string)$attempt['model'];
                $block['ai_flex']['department']=(string)$attempt['department'];
                if($ratio!==null){
                    $block['visual_style']=is_array($block['visual_style']??null)?$block['visual_style']:[];
                    $block['visual_style']['reference_aspect_ratio']=$ratio;
                }
                Log::debug('[AiFlex] reference generated',['model'=>$attempt['model'],'reference_mode'=>$referenceMode,'semantic_type'=>$block['semantic_type']??null,'layout'=>$block['layout']??null]);
                return $block;
            } catch (\Throwable $e) {
                $lastError=$e;
                Log::warning('[AiFlex] reference generation attempt failed',['model'=>$attempt['model'],'error'=>$e->getMessage()]);
            }
        }
        throw new \RuntimeException('AI Flex reference generation failed after Sol retries.',0,$lastError);
    }

    /** Derive a restrained Cosmic semantic color family from a visual reference. */
    public function generateThemeFromReference($file, string $request, array $currentTheme = []): array
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') throw new \RuntimeException('OpenAI API key is not configured.');
        if(!$file || !method_exists($file,'getRealPath')) throw new \InvalidArgumentException('A valid reference image is required.');
        $bytes=@file_get_contents($file->getRealPath());
        if($bytes===false || $bytes==='') throw new \RuntimeException('Reference image could not be read.');
        $mime=(string)($file->getMimeType() ?: 'image/png');
        $dataUri='data:'.$mime.';base64,'.base64_encode($bytes);
        $system=<<<'PROMPT'
You are Sol, Cosmic CMS's screenshot theme analyst. Return JSON only as {"brand_color_family":{...}}.
Infer the screenshot's dominant brand/accent color and translate its visible design language into ONE restrained, contrast-safe Cosmic semantic color family. Do not return HTML/CSS/Tailwind or font files.
Use exactly these keys: sourceColor,primary,primaryHover,primarySoft,secondary,accent,background,surface,surfaceMuted,heading,text,muted,border,buttonPrimary,buttonText,buttonSecondary,buttonSecondaryText,success,warning,error,onPrimary,onDark,gradient:{from,via,to,glow,angle}.
Every color value must be #RRGGBB. primary and buttonPrimary must equal sourceColor. Prefer a clearly intentional accent/brand color visible in the screenshot rather than a neutral page background. Keep surfaces harmonious and text readable. gradient.angle is 0-360.
PROMPT;
        $payload=['request'=>$request,'current_theme'=>$currentTheme,'policy'=>'derive_cosmic_theme_from_reference'];
        $messages=[['role'=>'system','content'=>$system],['role'=>'user','content'=>[
            ['type'=>'text','text'=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
            ['type'=>'image_url','image_url'=>['url'=>$dataUri,'detail'=>'high']],
        ]]];
        $endpoint=rtrim((string)(config('openai.base_uri') ?: 'https://api.openai.com/v1'),'/').'/chat/completions';
        $lastError=null;
        foreach([true,false] as $jsonMode){
            try{
                $body=['model'=>$this->models->sol(),'messages'=>$messages]; if($jsonMode) $body['response_format']=['type'=>'json_object'];
                $response=Http::withToken($apiKey)->connectTimeout(30)->timeout(180)->post($endpoint,$body)->throw()->json();
                $decoded=json_decode((string)data_get($response,'choices.0.message.content',''),true);
                $family=is_array($decoded['brand_color_family']??null)?$decoded['brand_color_family']:[];
                $primary=strtoupper(trim((string)($family['primary']??$family['sourceColor']??'')));
                if(!preg_match('/^#[0-9A-F]{6}$/',$primary)) throw new \RuntimeException('Sol returned no valid reference primary color.');
                $family['sourceColor']=$primary; $family['primary']=$primary; $family['buttonPrimary']=$primary;
                Log::debug('[AiFlex] reference theme derived',['model'=>$this->models->sol(),'primary'=>$primary]);
                return ['primary'=>$primary,'family'=>$family,'source'=>'sol_reference_theme_v1'];
            }catch(\Throwable $e){$lastError=$e; Log::warning('[AiFlex] reference theme derivation failed',['error'=>$e->getMessage()]);}
        }
        throw new \RuntimeException('Reference theme derivation failed after Sol retries.',0,$lastError);
    }

    /** Analyze a full-page screenshot once and return an atomic, validated page plan. */
    public function generatePageFromReference($file, string $request, array $theme = [], array $catalog = [], string $referenceMode = 'layout_only'): array
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') throw new \RuntimeException('OpenAI API key is not configured.');
        if(!$file || !method_exists($file,'getRealPath')) throw new \InvalidArgumentException('A valid reference image is required.');
        $bytes=@file_get_contents($file->getRealPath());
        if($bytes===false || $bytes==='') throw new \RuntimeException('Reference image could not be read.');
        $mime=(string)($file->getMimeType() ?: 'image/png');
        $dataUri='data:'.$mime.';base64,'.base64_encode($bytes);
        $referenceMode=$referenceMode==='layout_and_theme'?'layout_and_theme':'layout_only';
        $catalogRows=[];
        foreach(array_slice($catalog,0,320) as $spark){
            if(!is_array($spark)||empty($spark['key'])) continue;
            $catalogRows[]=['key'=>(string)$spark['key'],'name'=>(string)($spark['name']??''),'category'=>(string)($spark['category']??''),'description'=>(string)($spark['description']??'')];
        }
        $system=<<<'PROMPT'
You are Sol, Cosmic CMS's full-page visual architect. Analyze the supplied FULL PAGE website screenshot and return JSON only:
{"page_plan":{"sections":[...]}}
Divide the screenshot top-to-bottom into meaningful website sections. Each section must have semantic_type, instruction, implementation (registered|ai_flex), and optional spark_key/block.
Prefer a registered Spark ONLY when one supplied in registered_catalog is a strong semantic/composition match. spark_key must exactly equal a supplied key. Otherwise use ai_flex.
For ai_flex include a complete structured luna_custom_section block using the same safe schema: type,semantic_type,category,layout,alignment,media_position,density,accent_shape,section_mood,eyebrow,heading,heading_accent_text,text,primary_label,primary_url,secondary_label,secondary_url,image_url,items,theme,visual_style,ai_flex. Never emit HTML/CSS/JS/Tailwind.
If reference_mode=layout_only preserve the active Cosmic theme. If layout_and_theme, infer screenshot visual styling into supported visual_style values. Do not invent remote image URLs.
Keep the section order exactly as visible. Do not create duplicate filler sections. Aim for 3-12 sections. ai_flex metadata: source=sol, reference_mode, theme_policy and intent.
PROMPT;
        $payload=['request'=>$request,'reference_mode'=>$referenceMode,'active_theme'=>$theme,'registered_catalog'=>$catalogRows];
        $messages=[['role'=>'system','content'=>$system],['role'=>'user','content'=>[
            ['type'=>'text','text'=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
            ['type'=>'image_url','image_url'=>['url'=>$dataUri,'detail'=>'high']],
        ]]];
        $endpoint=rtrim((string)(config('openai.base_uri') ?: 'https://api.openai.com/v1'),'/').'/chat/completions';
        $attempts=[['model'=>$this->models->sol(),'json_mode'=>true],['model'=>$this->models->sol(),'json_mode'=>false]];
        $validKeys=[]; foreach($catalogRows as $r) $validKeys[$r['key']]=true;
        $lastError=null;
        foreach($attempts as $attempt){
            try{
                $body=['model'=>$attempt['model'],'messages'=>$messages]; if($attempt['json_mode']) $body['response_format']=['type'=>'json_object'];
                $response=Http::withToken($apiKey)->connectTimeout(30)->timeout(240)->post($endpoint,$body)->throw()->json();
                $decoded=json_decode((string)data_get($response,'choices.0.message.content',''),true);
                $rawSections=(array)data_get($decoded,'page_plan.sections',[]);
                if(count($rawSections)<1 || count($rawSections)>12) throw new \RuntimeException('Sol returned an invalid whole-page section count.');
                $sections=[];
                foreach($rawSections as $i=>$raw){
                    if(!is_array($raw)) continue;
                    $semantic=Str::lower(trim((string)($raw['semantic_type']??'content'))) ?: 'content';
                    $instruction=trim((string)($raw['instruction']??('Recreate the '.$semantic.' section from the reference.')));
                    $sparkKey=trim((string)($raw['spark_key']??''));
                    $implementation=(string)($raw['implementation']??'ai_flex');
                    if($implementation==='registered' && $sparkKey!=='' && isset($validKeys[$sparkKey])){
                        $sections[]=['semantic_type'=>$semantic,'implementation'=>'registered','spark_key'=>$sparkKey,'instruction'=>$instruction];
                        continue;
                    }
                    $rawBlock=is_array($raw['block']??null)?$raw['block']:[];
                    $rawBlock['semantic_type']=$semantic; $rawBlock['category']=$semantic;
                    $block=$this->validate($rawBlock,[]);
                    $block=$this->applyCompositionPolish($block,$instruction,[]);
                    $block['ai_flex']['reference_mode']=$referenceMode;
                    $block['ai_flex']['theme_policy']=$referenceMode==='layout_and_theme'?'derive_from_reference':'inherit';
                    $block['ai_flex']['model']=(string)$attempt['model'];
                    $block['ai_flex']['department']='sol_reference_page';
                    $sections[]=['semantic_type'=>$semantic,'implementation'=>'ai_flex','instruction'=>$instruction,'block'=>$block];
                }
                if(!$sections) throw new \RuntimeException('Sol returned no usable whole-page sections.');
                Log::debug('[AiFlex] whole-page reference planned',['model'=>$attempt['model'],'reference_mode'=>$referenceMode,'sections'=>count($sections),'registered'=>count(array_filter($sections,fn($x)=>$x['implementation']==='registered'))]);
                return ['composer'=>'sol_reference_page_v1','reference_mode'=>$referenceMode,'sections'=>$sections];
            }catch(\Throwable $e){$lastError=$e; Log::warning('[AiFlex] whole-page reference planning failed',['model'=>$attempt['model'],'error'=>$e->getMessage()]);}
        }
        throw new \RuntimeException('Whole-page reference planning failed after Sol retries.',0,$lastError);
    }

    private function applyCompositionPolish(array $block, string $request, array $source = []): array
    {
        $haystack = Str::lower($request.' '.($block['semantic_type'] ?? '').' '.($block['category'] ?? '').' '.($block['ai_flex']['intent'] ?? '').' '.($source['semantic_type'] ?? '').' '.($source['category'] ?? '').' '.($source['type'] ?? ''));
        if (! Str::contains($haystack, ['hero', 'banner'])) return $block;
        $block['semantic_type'] = 'hero';
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
        $allowed = ['type','custom_spark_key','custom_spark_saved','semantic_type','source_type','category','layout','alignment','media_position','density','accent_shape','section_mood','eyebrow','heading','heading_accent_text','text','primary_label','primary_url','secondary_label','secondary_url','image_url','items','theme','visual_style','review','form','ai_flex'];
        $block = array_intersect_key($block, array_flip($allowed));
        $block['type'] = 'luna_custom_section';
        $block['custom_spark_key'] = 'ai-flex-'.Str::lower(Str::random(12));
        $block['custom_spark_saved'] = false;
        $block['theme'] = 'auto';
        $sourceType = Str::lower(trim((string)($source['type'] ?? '')));
        $sourceSemantic = Str::lower(trim((string)($source['semantic_type'] ?? $source['category'] ?? '')));
        if ($sourceSemantic === '') {
            if (Str::contains($sourceType, ['hero','banner'])) $sourceSemantic = 'hero';
            elseif (Str::contains($sourceType, ['service','feature'])) $sourceSemantic = 'services';
            elseif (Str::contains($sourceType, ['testimonial','review'])) $sourceSemantic = 'testimonials';
            elseif (Str::contains($sourceType, ['faq','accordion'])) $sourceSemantic = 'faq';
            elseif (Str::contains($sourceType, ['contact','form'])) $sourceSemantic = 'contact';
            elseif (Str::contains($sourceType, ['pricing','price'])) $sourceSemantic = 'pricing';
            elseif (Str::contains($sourceType, ['cta','call_to_action'])) $sourceSemantic = 'cta';
        }
        $requestedSemantic = Str::lower(trim((string)($block['semantic_type'] ?? $block['category'] ?? '')));
        $semantic = $sourceSemantic !== '' ? $sourceSemantic : ($requestedSemantic !== '' ? $requestedSemantic : 'content');
        $semantic = preg_replace('/[^a-z0-9_-]+/', '_', $semantic) ?: 'content';
        $block['semantic_type'] = $semantic;
        $block['source_type'] = $sourceType !== '' ? $sourceType : (string)($block['source_type'] ?? '');
        // category remains as a compatibility alias for existing renderer logic.
        $block['category'] = $semantic;
        $block['layout'] = in_array($block['layout'] ?? '', ['editorial','split','feature-grid','card-grid','media-led','stacked','centered'], true) ? $block['layout'] : 'editorial';
        $block['alignment'] = in_array($block['alignment'] ?? '', ['left','center','right'], true) ? $block['alignment'] : 'left';
        $block['media_position'] = in_array($block['media_position'] ?? '', ['left','right','background','top','none'], true) ? $block['media_position'] : 'none';
        $block['items'] = collect(is_array($block['items'] ?? null) ? $block['items'] : [])->take(12)->map(fn($item) => is_array($item) ? array_intersect_key($item,array_flip(['title','text','icon','label','url','image_url'])) : [])->values()->all();
        $block['ai_flex'] = array_merge(['version'=>1,'source'=>'sol','reference_mode'=>'composition_only','theme_policy'=>'inherit'], is_array($block['ai_flex'] ?? null) ? array_intersect_key($block['ai_flex'],array_flip(['version','source','reference_mode','theme_policy','intent','model','department','composition_profile'])) : []);
        foreach (['heading','eyebrow','text','primary_label','primary_url','secondary_label','secondary_url','image_url','semantic_type','source_type','category','density','accent_shape','section_mood'] as $key) {
            if (isset($block[$key]) && !is_scalar($block[$key])) unset($block[$key]);
        }
        if (trim((string)($block['heading'] ?? '')) === '' && !empty($source['heading'])) $block['heading'] = $source['heading'];
        if (empty($block['text']) && !empty($source['text'])) $block['text'] = $source['text'];
        return $block;
    }
}
