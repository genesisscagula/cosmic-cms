<?php

namespace App\Http\Controllers;

use App\Helpers\CmsHtmlCompiler;
use App\AI\Schemas\SchemaManager;
use App\Support\PageStyleRegistry;
use App\Models\CustomSpark;
use App\Models\Page;
use App\Models\Website;
use App\Models\TrialGeneration;
use App\Jobs\BuildVisualFirstCustomPageJob;
use App\Services\CreditService;
use App\Services\ColorContrastGuard;
use App\Services\SparkCatalog;
use App\Services\SparkEditCapabilityRegistry;
use App\Services\PlanEntitlementService;
use App\Services\AiPageGenerationService;
use App\Services\LunaTemplatePlannerService;
use App\Services\LunaCategoryPageService;
use App\Services\LunaCreditPricingService;
use App\Services\LunaNaturalReplyService;
use App\Services\LunaKnowledgeRouter;
use App\Services\LunaFeasibilityGate;
use App\Services\LunaDesignContinuityService;
use App\Services\LunaResponsiveIntelligenceService;
use App\Services\LunaVisualQaService;
use App\Services\LunaScopeIntelligenceService;
use App\Services\LunaSmartSparkEditingService;
use App\Services\LunaRenderParityService;
use App\Services\LunaExecutionVerificationService;
use App\Services\LunaSiteDesignDnaService;
use App\Services\LunaDesignCriticService;
use App\Services\LunaSelfCorrectionService;
use App\Services\LunaIntentGateway;
use App\Services\LunaInspectService;
use App\Services\LunaPendingActionService;
use App\Services\LunaPexelsVideoService;
use App\Services\LunaSiteAdminActionService;
use App\Services\TrialCreditService;
use App\Services\SmartImageService;
use App\Services\ThemeColorResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomSparkController extends Controller
{
    public function catalog(Request $request, Website $website)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 404);
        return response()->json(['sparks' => $website->customSparks()->latest()->get()]);
    }

    public function generate(Request $request, Website $website, CreditService $credits)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 422, 'Screenshot Sparks are only available for Custom Websites.');
        $validated = $request->validate([
            'screenshot' => ['required','image','mimes:png,jpg,jpeg,webp','max:8192'],
            'name' => ['nullable','string','max:120'],
            'instructions' => ['nullable','string','max:4000'],
            'background_image' => ['nullable','image','mimes:png,jpg,jpeg,webp','max:12288'],
        ]);
$file = $validated['screenshot'];
        $instructions = trim((string) ($validated['instructions'] ?? ''));
        $referencePath = $file->store('custom-sites/'.$website->id.'/references', 'public');
        $referenceUrl = Storage::disk('public')->url($referencePath);
        $dataUri = 'data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath()));

        $referenceAspectRatio = null;
        $referenceDimensions = @getimagesize($file->getRealPath());
        if (is_array($referenceDimensions) && ($referenceDimensions[0] ?? 0) > 0 && ($referenceDimensions[1] ?? 0) > 0) {
            $referenceAspectRatio = round(((float) $referenceDimensions[0]) / ((float) $referenceDimensions[1]), 5);
        }

        $backgroundUrl = '';
        $backgroundDataUri = '';
        if ($request->hasFile('background_image')) {
            $backgroundFile = $request->file('background_image');
            $backgroundPath = $backgroundFile->store('custom-sites/'.$website->id.'/assets', 'public');
            $backgroundUrl = Storage::disk('public')->url($backgroundPath);
            $backgroundDataUri = 'data:'.$backgroundFile->getMimeType().';base64,'.base64_encode(file_get_contents($backgroundFile->getRealPath()));
        }

        $system = <<<'PROMPT'
You are Cosmic AI Visual Rebuilder. Convert ONE supplied website-section screenshot into ONE editable Cosmic CMS Custom Spark.
Return JSON only, never markdown.

PRIMARY RULE: the screenshot is the single source of truth. Do NOT apply a website theme, global design system, brand preset, or your own preferred style. Reconstruct what is visibly present as closely as this schema allows.

VISUAL ANALYSIS BEFORE OUTPUT:
- infer section aspect ratio, content max-width, left/right proportions, alignment and exact visual hierarchy
- NEVER copy the screenshot's literal pixel width/height into section sizing. Preserve its PROPORTIONS. visual_style.reference_aspect_ratio is width / height and is the primary desktop sizing target; section_min_height is only a fallback for responsive layouts
- identify whether the main photo is a background, left/right media, or absent
- estimate visible colors, font sizes, weights, line-height, radii, padding, gaps, overlay opacity and content width
- preserve compact compositions; never turn a horizontal hero into a tall stacked generic section
- preserve forms as forms, reviews/badges as compact UI, and buttons in their observed location
- do not invent extra cards, decorative gradients, dark themes, or content not visible in the screenshot

SCREEN REGION INTELLIGENCE:
- The reference may include a GLOBAL HEADER above/over the section. Detect it; do NOT bake navigation/logo into the Custom Spark.
- If a header is visible, return top-level shell.header_detected=true and a shell.header object. The header is GLOBAL; do NOT bake it into the Custom Spark.
- shell.header supports: {overlay,background_color,text_color,nav_color,height,padding_x,content_max_width,logo_tone,logo_height,nav_size,nav_gap,phone_enabled,phone_text,phone_color,cta_label,cta_url,cta_background,cta_color,cta_radius,menu:[{"label":"","url":"#"}]}.
- overlay=true when the header visually sits on top of the hero/background rather than occupying a separate solid strip.
- If a footer is visible, return shell.footer_detected=true and shell.footer: {background_color,text_color,muted_color,logo_tone,tagline,cta_label,cta_url,columns:[{"title":"","items":[{"label":"","url":"#"}]}],copyright}.
- Header/footer styling should reflect the screenshot, but NEVER reconstruct or replace the site's actual logo asset. Only choose logo_tone/size.
- If no header/footer is visible, return header_detected/footer_detected=false. The body Spark must remain shell-free.

ICON INTELLIGENCE:
- For compact benefits/trust/service items, preserve visible icons. Each item may include icon using one semantic key from:
shield-check, settings, truck, headset, check-circle, star, building, home, ruler, layers, sparkles, phone, mail, map-pin, clock, users.
- Never invent an image URL for a simple line icon. Prefer these local semantic SVG icons so Builder and live export match.
- Preserve horizontal trust strips as horizontal trust strips; do not turn them into generic cards merely because items exist.
- items may include {title,text,label,value,image_url,icon}.

Allowed layout: editorial, split, showcase, bento, mosaic, rail, trust_strip.
Allowed media_position: none,left,right,top,background.
Allowed alignment: left,center,right. density: airy,balanced,compact.

Return keys:
name, category, layout, alignment, media_position, density, accent_shape,
eyebrow, heading, heading_accent_text, text, primary_label, primary_url, secondary_label, secondary_url,
image_url, items, review, form, runtime, visual_style, shell.

image_url: If the screenshot contains photography that cannot be recovered as an independent asset, return an empty string. Never invent an external image URL.
items: max 8 objects {title,text,label,value,image_url}.
review: optional {stars,text}.
form: optional functional lead form:
{title,form_type:"lead",fields,button_label,note,success_message,columns,button_alignment,note_position}.
fields: max 8 objects {name,label,type,placeholder,required,width,options}.
Allowed field type: text,email,tel,textarea,select,checkbox,hidden.
width: full|half. options is only for select/checkbox and must be an array of short strings.
Use stable lowercase snake_case field names. If the screenshot shows only Name + Phone, return exactly those fields; do NOT invent Email or Message.
required must reflect visible/obvious intent. button_label and success_message must be usable on a real submission form.

runtime: optional Cosmic Interaction Runtime. Use when the screenshot or USER DESIGN INSTRUCTIONS needs browser-side behavior.
Schema:
{
 "version":1,
 "state":[{"key","default"}],
 "inputs":[{"key","label","control","default","min","max","step","format","currency","options","width"}],
 "computed":[{"key","label","formula","format","currency","prefix","suffix","display"}],
 "conditions":[{"target","source","operator","value","effect"}],
 "actions":[{"type","label","target","value","include"}],
 "views":[{"key","label","title","text","show_when"}],
 "steps":[{"key","label","title","text"}],
 "modals":[{"key","title","text","button_label"}],
 "collections":[{"key","items","searchable","filterable","sortable"}],
 "outputs":[{"key","source","label","display","format","currency","prefix","suffix"}]
}
Allowed controls: text,email,tel,number,range,select,radio,checkbox,toggle,date,time,search.
Allowed formats: text,number,currency,percent,integer. For currency include ISO currency such as AUD, USD, PHP.
Allowed condition operators: eq,neq,gt,gte,lt,lte,contains,truthy,falsy. Effects: show,hide,enable,disable.
Allowed actions: set_value,toggle,increment,decrement,open_modal,close_modal,next_step,previous_step,go_to_step,select_tab,select_item,filter,search,sort,reset,calculate,scroll_to,submit_form.
Allowed output display: text,badge,result,hero_result,progress,counter,summary.
Formula language: field/state/computed keys, numeric literals, + - * / %, parentheses, comparisons, &&, || and min(),max(),round(),floor(),ceil(),abs(),pow(),if().
Never return raw JavaScript, HTML, CSS, fetch/network code, browser APIs, storage/cookies, eval, arbitrary URLs, PHP, SQL, filesystem or server code.
Use max 16 state values, 16 inputs, 16 computed values, 16 conditions, 12 actions, 10 views, 10 steps, 8 modals, 4 collections, 12 outputs. Keys lowercase snake_case.
Compose primitives instead of inventing a new widget type. Examples:
- tabs = state + select_tab actions + views
- modal/lightbox = open_modal/close_modal + modals
- wizard/quiz = steps + next_step/previous_step
- calculator/configurator = inputs + computed + outputs
- conditional form = inputs + conditions
- searchable/filterable gallery = collections + search/filter/sort actions
- lead calculator = runtime + form; submit_form.include attaches selected/calculated values.
visual_style must contain numeric/color estimates from the screenshot where visible:
{reference_aspect_ratio,section_min_height,content_width,content_max_width,heading_size,heading_line_height,body_size,body_line_height,eyebrow_size,section_padding_x,section_padding_y,content_gap,card_padding,card_radius,button_radius,background_color,heading_color,body_color,accent_color,card_background,copy_width_percent,background_position,background_size,overlay_color,overlay_opacity,overlay_gradient_enabled,overlay_gradient_angle,overlay_gradient_from,overlay_gradient_to,overlay_gradient_from_stop,overlay_gradient_to_stop,review_background,review_text_color,review_star_color,review_text_size,review_radius,review_shadow}.
BACKGROUND FIDELITY:
- When a supplied clean background asset exists, use it exactly and infer object/background_position such as "72% center", "right center", or "center center" from the reference.
- If the reference uses a directional color wash over photography, set overlay_gradient_enabled=true. Match its direction, stops and colors; do not flatten a visible gradient into one opaque color.
- overlay_opacity controls the whole overlay layer. Prefer a gradient ending in transparent (#RRGGBB00 is allowed for overlay_gradient_to).
- Preserve the visible subject: never cover a person/product with the copy when the screenshot keeps them clear.
TYPOGRAPHY/FIDELITY:
- Preserve deliberate heading line breaks, compact badges/reviews, exact form field widths/columns, CTA alignment, and whether the note is inline or below.
- Preserve accent-colored words when visible by returning heading_accent_text with the exact substring and visual_style.heading_accent_color.
- For a 4-item icon/benefit row, use layout=trust_strip and four items with semantic icon keys; match separators and compact spacing.
- Estimate heading_line_height/body_line_height as unitless numbers.
Use null only when genuinely impossible to infer. Use #RRGGBB colors, except overlay_gradient_to may use #RRGGBBAA. Use # for links.
PROMPT;
        $apiKey = (string) config('openai.api_key');
        abort_if($apiKey === '', 503, 'OpenAI is not configured.');
        $response = Http::withToken($apiKey)->timeout(90)->post(rtrim((string)(config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/chat/completions', [
            'model' => env('OPENAI_VISION_MODEL', env('OPENAI_MODEL', 'gpt-5-mini')),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role'=>'system','content'=>$system],
                ['role'=>'user','content'=>array_values(array_filter([
                    ['type'=>'text','text'=>"Rebuild this screenshot faithfully as one editable Custom Spark. The screenshot overrides all defaults. Preserve the REFERENCE ASPECT RATIO rather than its literal pixel dimensions. ".($referenceAspectRatio ? "The uploaded reference ratio is {$referenceAspectRatio}:1 (width:height). Return this as visual_style.reference_aspect_ratio." : "")." Return visual_style estimates as well as content structure.\n\nUSER DESIGN INSTRUCTIONS:\n".($instructions !== '' ? $instructions : 'No extra instructions supplied.') . ($backgroundUrl !== '' ? "\n\nA clean source/background asset is supplied as IMAGE 2. If it corresponds to the screenshot's main media, set media_position appropriately and use this exact image_url: {$backgroundUrl}" : '')],
                    ['type'=>'text','text'=>'IMAGE 1 — REFERENCE SCREENSHOT'],
                    ['type'=>'image_url','image_url'=>['url'=>$dataUri,'detail'=>'high']],
                    $backgroundDataUri !== '' ? ['type'=>'text','text'=>'IMAGE 2 — CLEAN SOURCE / BACKGROUND ASSET'] : null,
                    $backgroundDataUri !== '' ? ['type'=>'image_url','image_url'=>['url'=>$backgroundDataUri,'detail'=>'high']] : null,
                ]))],
            ],
        ])->throw()->json();
        $raw = data_get($response, 'choices.0.message.content', '{}');
        $generated = json_decode($raw, true);
        if (!is_array($generated)) throw ValidationException::withMessages(['screenshot'=>'Cosmic AI returned an invalid section. Try another screenshot.']);

        $key = 'custom_'.Str::lower(Str::random(10));
        $name = trim((string)($validated['name'] ?? $generated['name'] ?? 'Screenshot Spark')) ?: 'Screenshot Spark';
        $block = array_merge([
            'type'=>'luna_custom_section','custom_spark_key'=>$key,'custom_spark_saved'=>false,'category'=>'content','layout'=>'editorial','alignment'=>'left','media_position'=>'none','density'=>'balanced','accent_shape'=>'none','section_mood'=>'auto','eyebrow'=>'','heading'=>$name,'heading_accent_text'=>'','text'=>'','primary_label'=>'','primary_url'=>'#','secondary_label'=>'','secondary_url'=>'#','image_url'=>'','items'=>[],'theme'=>'auto',
            'style_overrides'=>[], 'visual_style'=>[], 'review'=>null, 'form'=>null, 'runtime'=>null,
        ], array_intersect_key($generated, array_flip(['category','layout','alignment','media_position','density','accent_shape','eyebrow','heading','heading_accent_text','text','primary_label','primary_url','secondary_label','secondary_url','image_url','items','review','form','runtime','visual_style'])));
        if ($backgroundUrl !== '' && ($block['media_position'] ?? 'none') !== 'none') {
            $block['image_url'] = $backgroundUrl;
        }

        if ($referenceAspectRatio !== null) {
            $block['visual_style'] = is_array($block['visual_style'] ?? null) ? $block['visual_style'] : [];
            $block['visual_style']['reference_aspect_ratio'] = $referenceAspectRatio;
            $block['visual_style']['sizing_mode'] = 'reference_ratio';
        }

        if (is_array($block['runtime'] ?? null)) {
            $runtime = $block['runtime'];
            $runtime['inputs'] = array_values(array_filter(array_slice(is_array($runtime['inputs'] ?? null) ? $runtime['inputs'] : [], 0, 12), function ($input) {
                return is_array($input) && preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string)($input['key'] ?? ''));
            }));
            $known = collect($runtime['inputs'])->pluck('key')->filter()->values()->all();
            $allowedFunctions = ['min','max','round','floor','ceil','abs','pow','if'];
            $safeComputed = [];
            foreach (array_slice(is_array($runtime['computed'] ?? null) ? $runtime['computed'] : [], 0, 12) as $computed) {
                if (!is_array($computed)) continue;
                $keyName = (string)($computed['key'] ?? '');
                $formula = trim((string)($computed['formula'] ?? ''));
                if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $keyName) || $formula === '' || strlen($formula) > 500) continue;
                if (!preg_match('/^[0-9A-Za-z_+\-*\/%().,<>=!&|\s]+$/', $formula)) continue;
                preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $formula, $matches);
                $unknown = array_diff(array_unique($matches[0] ?? []), array_merge($known, $allowedFunctions));
                if ($unknown) continue;
                $safeComputed[] = $computed;
                $known[] = $keyName;
            }
            $runtime['computed'] = $safeComputed;
            $safeKey = fn($value) => preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string)$value);
            $runtime['version'] = 1;
            $runtime['state'] = array_values(array_filter(array_slice(is_array($runtime['state'] ?? null) ? $runtime['state'] : [], 0, 16), fn($item) => is_array($item) && $safeKey($item['key'] ?? '')));
            $allowedEffects = ['show','hide','enable','disable'];
            $allowedOperators = ['eq','neq','gt','gte','lt','lte','contains','truthy','falsy'];
            $runtime['conditions'] = array_values(array_filter(array_slice(is_array($runtime['conditions'] ?? null) ? $runtime['conditions'] : [], 0, 16), fn($item) => is_array($item) && $safeKey($item['target'] ?? '') && $safeKey($item['source'] ?? '') && in_array(($item['operator'] ?? 'eq'), $allowedOperators, true) && in_array(($item['effect'] ?? 'show'), $allowedEffects, true)));
            $allowedActions = ['set_value','toggle','increment','decrement','open_modal','close_modal','next_step','previous_step','go_to_step','select_tab','select_item','filter','search','sort','reset','calculate','scroll_to','submit_form'];
            $runtime['actions'] = array_values(array_filter(array_slice(is_array($runtime['actions'] ?? null) ? $runtime['actions'] : [], 0, 12), fn($item) => is_array($item) && in_array(($item['type'] ?? ''), $allowedActions, true)));
            foreach (['views'=>10,'steps'=>10,'modals'=>8,'outputs'=>12] as $group=>$limit) {
                $runtime[$group] = array_values(array_filter(array_slice(is_array($runtime[$group] ?? null) ? $runtime[$group] : [], 0, $limit), fn($item) => is_array($item) && $safeKey($item['key'] ?? '')));
            }
            $runtime['collections'] = array_values(array_filter(array_slice(is_array($runtime['collections'] ?? null) ? $runtime['collections'] : [], 0, 4), fn($item) => is_array($item) && $safeKey($item['key'] ?? '') && is_array($item['items'] ?? null)));
            $block['runtime'] = $runtime;
        }

        $schema = [
            'groups'=>[
                'content'=>['eyebrow','heading','text','primary_label','primary_url','secondary_label','secondary_url','image_url','items','form','runtime'],
                'typography'=>['heading_size','heading_line_height','body_size','body_line_height','card_title_size'],
                'layout'=>['layout','alignment','media_position','grid_gap','background_position','background_size'],
                'spacing'=>['section_padding','card_padding'],
                'effects'=>['overlay_gradient_enabled','overlay_gradient_angle','overlay_gradient_from','overlay_gradient_to','overlay_gradient_from_stop','overlay_gradient_to_stop'], 
            ],
            'inherits_global'=>false,
        ];
        $conversation = [];
        if ($instructions !== '') {
            $conversation[] = ['role'=>'user','text'=>$instructions,'at'=>now()->toIso8601String()];
        }
        $conversation[] = ['role'=>'assistant','text'=>'I rebuilt the reference as an editable draft Custom Spark. Generate + QA is free; save it for 50 credits only when you are happy with it. I will keep future edits scoped to this Spark.','at'=>now()->toIso8601String()];
        $spark = $website->customSparks()->create(['key'=>$key,'name'=>$name,'schema'=>$schema,'block'=>$block,'metadata'=>[
            'source'=>'screenshot','credits'=>0,'saved'=>false,'reference_url'=>$referenceUrl,'background_asset_url'=>$backgroundUrl ?: null,'conversation'=>$conversation,
        ]]);
        $rawShell = is_array($generated['shell'] ?? null) ? $generated['shell'] : [];
        $shell = [
            'header_detected' => (bool)($rawShell['header_detected'] ?? false),
            'footer_detected' => (bool)($rawShell['footer_detected'] ?? false),
        ];
        if ($shell['header_detected'] && is_array($rawShell['header'] ?? null)) {
            $header = array_intersect_key($rawShell['header'], array_flip([
                'overlay','background_color','text_color','nav_color','height','padding_x','content_max_width',
                'logo_tone','logo_height','nav_size','nav_gap','phone_enabled','phone_text','phone_color',
                'cta_label','cta_url','cta_background','cta_color','cta_radius','menu'
            ]));
            if (isset($header['menu']) && is_array($header['menu'])) {
                $header['menu'] = array_values(array_slice(array_filter($header['menu'], fn($item)=>is_array($item) && trim((string)($item['label']??''))!==''),0,8));
            }
            $shell['header'] = $header;
        }
        if ($shell['footer_detected'] && is_array($rawShell['footer'] ?? null)) {
            $footer = array_intersect_key($rawShell['footer'], array_flip([
                'background_color','text_color','muted_color','logo_tone','tagline','cta_label','cta_url','columns','copyright'
            ]));
            if (isset($footer['columns']) && is_array($footer['columns'])) {
                $footer['columns'] = array_values(array_slice($footer['columns'],0,4));
            }
            $shell['footer'] = $footer;
        }
        return response()->json(['spark'=>$spark,'block'=>$block,'shell'=>$shell,'credits'=>$credits->balance($request->user())], 201);
    }

    private function promptDesignedBlock(array $generated, string $key, string $name): array
    {
        $block = array_merge([
            'type'=>'luna_custom_section','custom_spark_key'=>$key,'custom_spark_saved'=>false,
            'category'=>'content','layout'=>'editorial','alignment'=>'left','media_position'=>'none',
            'density'=>'balanced','accent_shape'=>'none','section_mood'=>'auto','eyebrow'=>'',
            'heading'=>$name,'text'=>'','primary_label'=>'','primary_url'=>'#','secondary_label'=>'',
            'secondary_url'=>'#','image_url'=>'','items'=>[],'theme'=>'auto','style_overrides'=>[],
            'visual_style'=>[],'review'=>null,'form'=>null,'runtime'=>null,
        ], array_intersect_key($generated, array_flip([
            'category','layout','alignment','media_position','density','accent_shape','eyebrow','heading','heading_accent_text','text',
            'primary_label','primary_url','secondary_label','secondary_url','image_url','items','review','form','runtime','visual_style'
        ])));

        if (is_array($block['runtime'] ?? null)) {
            $runtime = $block['runtime'];
            $safeKey = fn($value) => preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string)$value);
            $runtime['version'] = 1;
            foreach (['state'=>16,'inputs'=>16,'computed'=>16,'conditions'=>16,'actions'=>12,'views'=>10,'steps'=>10,'modals'=>8,'outputs'=>12] as $group=>$limit) {
                $runtime[$group] = array_slice(is_array($runtime[$group] ?? null) ? $runtime[$group] : [], 0, $limit);
            }
            $runtime['collections'] = array_slice(is_array($runtime['collections'] ?? null) ? $runtime['collections'] : [], 0, 4);
            $runtime['state'] = array_values(array_filter($runtime['state'], fn($item)=>is_array($item)&&$safeKey($item['key']??'')));
            $runtime['inputs'] = array_values(array_filter($runtime['inputs'], fn($item)=>is_array($item)&&$safeKey($item['key']??'')));
            $allowedOperators=['eq','neq','gt','gte','lt','lte','contains','truthy','falsy'];
            $allowedEffects=['show','hide','enable','disable'];
            $runtime['conditions']=array_values(array_filter($runtime['conditions'],fn($item)=>is_array($item)&&$safeKey($item['target']??'')&&$safeKey($item['source']??'')&&in_array(($item['operator']??'eq'),$allowedOperators,true)&&in_array(($item['effect']??'show'),$allowedEffects,true)));
            $allowedActions=['set_value','toggle','increment','decrement','open_modal','close_modal','next_step','previous_step','go_to_step','select_tab','select_item','filter','search','sort','reset','calculate','scroll_to','submit_form'];
            $runtime['actions']=array_values(array_filter($runtime['actions'],fn($item)=>is_array($item)&&in_array(($item['type']??''),$allowedActions,true)));
            foreach(['views','steps','modals','outputs'] as $group){$runtime[$group]=array_values(array_filter($runtime[$group],fn($item)=>is_array($item)&&$safeKey($item['key']??'')));}
            $runtime['collections']=array_values(array_filter($runtime['collections'],fn($item)=>is_array($item)&&$safeKey($item['key']??'')&&is_array($item['items']??null)));
            $known = collect($runtime['inputs'])->pluck('key')->merge(collect($runtime['state'])->pluck('key'))->filter()->values()->all();
            $fns = ['min','max','round','floor','ceil','abs','pow','if'];
            $safeComputed=[];
            foreach ($runtime['computed'] as $item) {
                if (!is_array($item) || !$safeKey($item['key']??'')) continue;
                $formula=trim((string)($item['formula']??''));
                if ($formula==='' || strlen($formula)>500 || !preg_match('/^[0-9A-Za-z_+\-*\/%().,<>=!&|\s]+$/',$formula)) continue;
                preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/',$formula,$matches);
                if (array_diff(array_unique($matches[0]??[]),array_merge($known,$fns))) continue;
                $safeComputed[]=$item; $known[]=$item['key'];
            }
            $runtime['computed']=$safeComputed;
            $block['runtime']=$runtime;
        }
        return $block;
    }

    private function designerContract(): string
    {
        return <<<'PROMPT'
Return JSON only. Design an editable Cosmic CMS Custom Spark from the user's brief.
Act as a senior digital art director, conversion designer, and frontend interaction architect.
There may be NO screenshot. In that case you must make strong, intentional design decisions instead of returning generic filler.

Allowed section keys:
name,category,layout,alignment,media_position,density,accent_shape,eyebrow,heading,text,
primary_label,primary_url,secondary_label,secondary_url,image_url,items,review,form,runtime,visual_style.

Allowed layout: editorial,split,showcase,bento,mosaic,rail.
Allowed media_position: none,left,right,top,background. alignment: left,center,right.
items max 8 {title,text,label,value,image_url}. Never invent external image URLs; use image_url="" unless a supplied asset URL is explicitly provided.
review optional {stars,text}.
form optional {title,form_type:"lead",fields,button_label,note,success_message,columns,button_alignment,note_position}.
form fields max 8 {name,label,type,placeholder,required,width,options}; allowed type text,email,tel,textarea,select,checkbox,hidden.

runtime is the Cosmic Interaction Runtime:
version,state,inputs,computed,conditions,actions,views,steps,modals,collections,outputs.
Use it only when interaction improves the requested section. Compose tabs, modal, wizard, calculator, configurator, conditional UI, search/filter/sort from these primitives.
Never return raw HTML/CSS/JavaScript, fetch/network code, browser APIs, PHP, SQL or server code.
Formulas may use keys, numeric literals, + - * / %, parentheses, comparisons, &&, ||, min,max,round,floor,ceil,abs,pow,if.

visual_style should intentionally define section_min_height,content_max_width,heading_size,heading_line_height,body_size,body_line_height,
section_padding_x,section_padding_y,content_gap,card_padding,card_radius,button_radius,background_color,heading_color,body_color,
accent_color,card_background,copy_width_percent and optional overlay/background fields.

DESIGN RULES:
- avoid repetitive card grids unless the brief calls for them
- create clear hierarchy, strong composition, useful whitespace, and one coherent art direction
- keep section content realistic and concise
- if the brief asks for functionality, return a functional runtime/form schema, not descriptive prose about it
- preserve editability: important text belongs in schema fields, not embedded code
PROMPT;
    }

    // Legacy single-section endpoint retained for existing drafts/API compatibility.
    // The Custom Website UI no longer calls this path; Full Page Build uses planWebsite().
    public function design(
        Request $request,
        Website $website,
        CreditService $credits,
        AiPageGenerationService $pageGenerator
    ) {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 422, 'Cosmic AI Designer is only available for Custom Websites.');
        $validated=$request->validate([
            'instructions'=>['required','string','min:5','max:6000'],
            'name'=>['nullable','string','max:120'],
            'background_image'=>['nullable','image','mimes:png,jpg,jpeg,webp','max:12288'],
        ]);

        $instructions=trim($validated['instructions']);
        $category=$this->inferSparkCategory($instructions);
        $selection=$pageGenerator->selectSection($category,$instructions);
        $sparkType=(string)($selection['section']??'');
        if ($sparkType==='') {
            throw ValidationException::withMessages(['instructions'=>'Cosmic AI could not find a suitable Spark for this request.']);
        }

        // Important: prompt-only Custom Build does NOT invent a blank layout.
        // It starts from a registered Cosmic Spark, then the normal content/image
        // pipeline customises that Spark. SmartImageService remains the source of
        // imagery, so configured Unsplash stays the primary provider.
        $generated=$pageGenerator->generateBlocksDetailed(
            $instructions."\n\nCUSTOM BUILD DIRECTIVE: Adapt the selected existing Cosmic Spark to this brief. Keep the Spark's authored composition and interaction pattern. Use relevant premium photography through the normal smart-image/Unsplash pipeline. Avoid generic filler and repetitive card-grid substitutions.",
            [$sparkType],
            $selection['image_folder']??null
        );
        $block=$generated['blocks'][0]??null;
        if (!is_array($block)) {
            throw ValidationException::withMessages(['instructions'=>'Cosmic AI selected a Spark but could not generate its content.']);
        }

        $assetUrl='';
        if ($request->hasFile('background_image')) {
            $file=$request->file('background_image');
            $path=$file->store('custom-sites/'.$website->id.'/assets','public');
            $assetUrl=Storage::disk('public')->url($path);
            foreach (['image_url','background_image','background_image_url'] as $key) {
                if (array_key_exists($key,$block)) { $block[$key]=$assetUrl; break; }
            }
        }

        return response()->json([
            'block'=>$block,
            'spark'=>['id'=>null,'key'=>$sparkType,'name'=>$validated['name']??$sparkType],
            'selected_spark'=>$sparkType,
            'image_provider'=>'smart-image/unsplash',
            'credits'=>$credits->balance($request->user()),
            'mode'=>'spark-guided-designer',
        ],201);
    }

    private function inferSparkCategory(string $prompt): string
    {
        $p=Str::lower($prompt);
        $map=[
            'hero'=>['hero','banner','above the fold','masthead'],
            'services'=>['service','capabilities','what we do','products'],
            'portfolio'=>['portfolio','project','gallery','case study','work'],
            'process'=>['process','steps','how it works','timeline'],
            'testimonials'=>['testimonial','review','customer stories','social proof'],
            'pricing'=>['pricing','price','plans','packages','calculator','estimate','quote estimator'],
            'contact'=>['contact','enquiry','inquiry','form','appointment','book'],
            'faq'=>['faq','frequently asked','questions'],
            'team'=>['team','people','staff','leadership'],
            'stats'=>['stats','numbers','metrics','achievements'],
            'about'=>['about','story','mission','values','why choose'],
            'cta'=>['cta','call to action','get started','final quote'],
        ];
        foreach($map as $category=>$needles){
            foreach($needles as $needle){ if(str_contains($p,$needle)) return $category; }
        }
        return 'feature';
    }

    private function fullPageReconstructionContract(): string
    {
        return <<<'PROMPT'
Return JSON only.

You are Cosmic AI's visual reconstruction architect. IMAGE 1 is a COMPLETE landing-page visual source. It may be:
A) a user-supplied full-page screenshot, or
B) an internal visual mockup generated from the user's brief.

Do not choose an existing Cosmic template or registered Spark. Do not mention templates.
Analyze the visual first, segment it into a GLOBAL HEADER, 4–10 BODY SECTIONS, and optional GLOBAL FOOTER, then reconstruct each body region as an independent editable Custom Spark schema.

Return:
{
  "direction":{"name":"","summary":"","palette":["#RRGGBB"],"notes":""},
  "shell":{
    "header_detected":true|false,
    "header":{...},
    "footer_detected":true|false,
    "footer":{...}
  },
  "sections":[ ...Custom Spark schemas in exact top-to-bottom order... ]
}

GLOBAL HEADER:
Do not place logo/navigation inside a body section.
header supports:
{
 "overlay":true|false,
 "background_color":"#RRGGBB or transparent",
 "text_color":"#RRGGBB",
 "nav_color":"#RRGGBB",
 "height":78,
 "padding_x":56,
 "content_max_width":1528,
 "logo_tone":"light|dark",
 "logo_height":40,
 "nav_size":14,
 "nav_gap":30,
 "phone_enabled":true|false,
 "phone_text":"",
 "phone_color":"#RRGGBB",
 "cta_label":"",
 "cta_url":"#",
 "cta_background":"#RRGGBB",
 "cta_color":"#RRGGBB",
 "cta_radius":8,
 "menu":[{"label":"","url":"#"}]
}
Never invent/replace the actual logo asset. Cosmic keeps the site's real logo.

GLOBAL FOOTER:
{
 "background_color":"#RRGGBB",
 "text_color":"#RRGGBB",
 "muted_color":"#RRGGBB",
 "logo_tone":"light|dark",
 "tagline":"",
 "cta_label":"",
 "cta_url":"#",
 "columns":[{"title":"","items":[{"label":"","url":"#"}]}],
 "copyright":""
}

BODY SECTION schema keys:
name,category,layout,alignment,media_position,density,accent_shape,
eyebrow,heading,heading_accent_text,text,primary_label,primary_url,secondary_label,secondary_url,
image_url,image_query,items,review,form,runtime,visual_style.

Allowed layout: editorial,split,showcase,bento,mosaic,rail,trust_strip.
Allowed media_position: none,left,right,top,background.
Allowed alignment: left,center,right.
Allowed density: airy,balanced,compact.

image_url must be empty. For a section needing photography, return a concise image_query describing the subject/composition visible in the visual. Cosmic resolves it through its real image provider after this call.
items max 8 objects: {title,text,label,value,image_url,image_query,icon}.
Allowed semantic icons:
shield-check,settings,truck,headset,check-circle,star,building,home,ruler,layers,sparkles,phone,mail,map-pin,clock,users.

review optional {stars,text}.
form optional:
{title,form_type:"lead",fields,button_label,note,success_message,columns,button_alignment,note_position}.
fields max 8: {name,label,type,placeholder,required,width,options}.
Allowed field type: text,email,tel,textarea,select,checkbox,hidden.

runtime optional Cosmic Interaction Runtime:
{
 "version":1,
 "state":[],
 "inputs":[],
 "computed":[],
 "conditions":[],
 "actions":[],
 "views":[],
 "steps":[],
 "modals":[],
 "collections":[],
 "outputs":[]
}
Use runtime only when the visual/brief clearly benefits from browser-side interaction. Never return raw HTML/CSS/JavaScript, network/browser APIs, PHP, SQL, storage, cookies or server code.

visual_style may include:
reference_aspect_ratio,section_min_height,content_width,content_max_width,heading_size,heading_line_height,
body_size,body_line_height,eyebrow_size,section_padding_x,section_padding_y,content_gap,card_padding,card_radius,
button_radius,background_color,heading_color,heading_accent_color,body_color,accent_color,card_background,
copy_width_percent,background_position,background_size,overlay_color,overlay_opacity,overlay_gradient_enabled,
overlay_gradient_angle,overlay_gradient_from,overlay_gradient_to,overlay_gradient_from_stop,overlay_gradient_to_stop,
review_background,review_text_color,review_star_color,review_text_size,review_radius,review_shadow.

SEGMENTATION/FIDELITY RULES:
- Preserve the visual source's section boundaries, vertical rhythm and layout variety.
- Never collapse the page into repetitive generic cards.
- Preserve large image-led/editorial/full-bleed regions as such.
- Each body section must be independently editable/reorderable.
- Do not bake a header/footer into a body Spark.
- Preserve accent words, icon strips, forms, large project imagery, asymmetric layouts and visual hierarchy.
- Use realistic concise content based on the USER BRIEF, while preserving the visual composition.
- The visual source is the design source of truth; the user brief supplies business meaning.
PROMPT;
    }

    private function createHiddenFullPageMockup(Website $website, string $brief): array
    {
        $apiKey=(string)config('openai.api_key');
        abort_if($apiKey==='',503,'Cosmic AI is not configured.');

        $business=trim((string)($website->name ?: 'the business'));
        $industry=trim((string)($website->industry ?: 'business'));
        $prompt=implode("\n",[
            "Create ONE tall full-page desktop website design mockup for {$business}.",
            "Business/industry context: {$industry}.",
            "USER BRIEF: {$brief}",
            "Canvas: portrait full landing-page composition, approximately 1024x1536, showing the complete page from header through footer.",
            "Act as a senior digital art director. Establish one coherent visual system, but vary section composition throughout the page.",
            "Show a premium header, strong hero, multiple distinct body sections, and footer. Use realistic commercial photography, sophisticated spacing, editorial hierarchy, and clear calls to action.",
            "Avoid repetitive card grids. Mix full-bleed imagery, split compositions, editorial sections, gallery/project layouts, trust/proof, and purposeful whitespace when appropriate.",
            "This image is an INTERNAL DESIGN BLUEPRINT for a later vision reconstruction pass. Prioritize layout, proportion, color, imagery, hierarchy, and section boundaries over perfectly legible tiny text.",
            "Do not render browser chrome, device frames, watermarks, annotations, arrows, measurement labels, or design-tool UI.",
        ]);

        try {
            $response=Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int)config('openai.request_timeout',180))
                ->post(rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/images/generations',[
                    'model'=>env('OPENAI_IMAGE_MODEL','gpt-image-1'),
                    'prompt'=>$prompt,
                    'size'=>'1024x1536',
                    'quality'=>env('OPENAI_CUSTOM_MOCKUP_QUALITY','low'),
                    'n'=>1,
                ]);
        } catch (Throwable $exception) {
            report($exception);
            abort(502,'Cosmic AI could not create the visual design blueprint.');
        }

        if ($response->failed()) {
            report(new \RuntimeException('Custom Build mockup generation failed: '.$response->body()));
            abort(502,'Cosmic AI could not create the visual design blueprint.');
        }
        $encoded=data_get($response->json(),'data.0.b64_json');
        $bytes=is_string($encoded)?base64_decode($encoded,true):false;
        if ($bytes===false || strlen($bytes)<100) {
            abort(502,'Cosmic AI returned an invalid visual design blueprint.');
        }

        $filename='hidden-page-mockup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.png';
        $path="custom-sites/{$website->id}/mockups/{$filename}";
        abort_unless(Storage::disk('public')->put($path,$bytes),502,'The visual design blueprint could not be stored.');

        return [
            'bytes'=>$bytes,
            'path'=>$path,
            'url'=>Storage::disk('public')->url($path),
            'mime'=>'image/png',
            'source'=>'generated_mockup',
        ];
    }

    private function normalizeFullPageShell(array $rawShell): array
    {
        $shell=[
            'header_detected'=>(bool)($rawShell['header_detected']??false),
            'footer_detected'=>(bool)($rawShell['footer_detected']??false),
        ];
        if ($shell['header_detected'] && is_array($rawShell['header']??null)) {
            $header=array_intersect_key($rawShell['header'],array_flip([
                'overlay','background_color','text_color','nav_color','height','padding_x','content_max_width',
                'logo_tone','logo_height','nav_size','nav_gap','phone_enabled','phone_text','phone_color',
                'cta_label','cta_url','cta_background','cta_color','cta_radius','menu'
            ]));
            $header['menu']=array_values(array_slice(array_filter(
                is_array($header['menu']??null)?$header['menu']:[],
                fn($item)=>is_array($item)&&trim((string)($item['label']??''))!==''
            ),0,8));
            $shell['header']=$header;
        }
        if ($shell['footer_detected'] && is_array($rawShell['footer']??null)) {
            $footer=array_intersect_key($rawShell['footer'],array_flip([
                'background_color','text_color','muted_color','logo_tone','tagline','cta_label','cta_url','columns','copyright'
            ]));
            $footer['columns']=array_values(array_slice(is_array($footer['columns']??null)?$footer['columns']:[],0,4));
            $shell['footer']=$footer;
        }
        return $shell;
    }


    public function startFullPageBuild(Request $request, Website $website)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 422, 'Cosmic AI Full Page Build is only available for Custom Websites.');

        $validated=$request->validate([
            'instructions'=>['nullable','string','max:8000','required_without:screenshot'],
            'screenshot'=>['nullable','image','mimes:png,jpg,jpeg,webp','max:16384'],
        ]);

        $brief=trim((string)($validated['instructions']??''));
        if ($brief==='' && $request->hasFile('screenshot')) {
            $brief='Faithfully reconstruct this full-page website reference as an editable responsive landing page.';
        }

        $screenshotPath=null;
        $screenshotMime=null;
        if ($request->hasFile('screenshot')) {
            $file=$request->file('screenshot');
            $screenshotPath=$file->store('custom-sites/'.$website->id.'/full-page-references','public');
            $screenshotMime=$file->getMimeType()?:'image/png';
        }

        $buildId=(string)Str::uuid();
        Cache::put('cosmic:custom-build:'.$buildId,[
            'build_id'=>$buildId,
            'website_id'=>$website->id,
            'user_id'=>$request->user()?->id,
            'status'=>'queued',
            'progress'=>1,
            'stage'=>'Queued for Cosmic AI…',
            'created_at'=>now()->toIso8601String(),
            'updated_at'=>now()->toIso8601String(),
        ],now()->addMinutes(120));

        BuildVisualFirstCustomPageJob::dispatch(
            $buildId,
            (int)$website->id,
            $brief,
            $screenshotPath,
            $screenshotMime
        );

        return response()->json([
            'build_id'=>$buildId,
            'status'=>'queued',
            'progress'=>1,
            'stage'=>'Queued for Cosmic AI…',
        ],202);
    }

    public function fullPageBuildStatus(Request $request, Website $website, string $buildId)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 404);

        $state=Cache::get('cosmic:custom-build:'.$buildId);
        abort_unless(is_array($state) && (int)($state['website_id']??0)===(int)$website->id,404,'This build session could not be found.');

        if (($state['status']??'')==='completed') {
            Cache::forget('cosmic:custom-build:'.$buildId);
        }

        return response()->json($state);
    }

    public function planWebsite(
        Request $request,
        Website $website,
        CreditService $credits,
        SmartImageService $images
    ) {
        $this->authorize('update',$website);
        abort_unless($website->isCustom(),422,'Cosmic AI Full Page Build is only available for Custom Websites.');

        $validated=$request->validate([
            'instructions'=>['nullable','string','max:8000','required_without:screenshot'],
            'screenshot'=>['nullable','image','mimes:png,jpg,jpeg,webp','max:16384'],
        ]);
        $brief=trim((string)($validated['instructions']??''));
        if ($brief==='' && $request->hasFile('screenshot')) {
            $brief='Faithfully reconstruct this full-page website reference as an editable responsive landing page.';
        }

        if ($request->hasFile('screenshot')) {
            $file=$request->file('screenshot');
            $bytes=file_get_contents($file->getRealPath());
            $path=$file->store('custom-sites/'.$website->id.'/full-page-references','public');
            $visual=[
                'bytes'=>$bytes,
                'path'=>$path,
                'url'=>Storage::disk('public')->url($path),
                'mime'=>$file->getMimeType()?:'image/png',
                'source'=>'uploaded_screenshot',
            ];
        } else {
            $visual=$this->createHiddenFullPageMockup($website,$brief);
        }

        $apiKey=(string)config('openai.api_key');
        abort_if($apiKey==='',503,'Cosmic AI is not configured.');
        $dataUri='data:'.$visual['mime'].';base64,'.base64_encode($visual['bytes']);

        $response=Http::withToken($apiKey)->connectTimeout(30)->timeout(150)->post(
            rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions',
            [
                'model'=>env('OPENAI_VISION_MODEL',env('OPENAI_MODEL','gpt-5-mini')),
                'response_format'=>['type'=>'json_object'],
                'messages'=>[
                    ['role'=>'system','content'=>$this->fullPageReconstructionContract()],
                    ['role'=>'user','content'=>[
                        ['type'=>'text','text'=>"USER BRIEF:\n{$brief}\n\nAnalyze IMAGE 1 as the complete visual source. Segment it and return the entire editable landing page reconstruction."],
                        ['type'=>'image_url','image_url'=>['url'=>$dataUri,'detail'=>'high']],
                    ]],
                ],
            ]
        )->throw()->json();

        $generated=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
        $sections=array_values(array_slice(is_array($generated['sections']??null)?$generated['sections']:[],0,10));
        if (count($sections)<2) {
            throw ValidationException::withMessages(['instructions'=>'Cosmic AI could not identify enough page sections from the visual source.']);
        }

        $industry=app(\App\Services\IndustryResolver::class)->resolve($website->industry ?: $brief,'default');
        $blocks=[];$sparkIds=[];
        foreach ($sections as $index=>$section) {
            if (!is_array($section)) continue;

            $query=trim((string)($section['image_query']??''));
            if ($query!=='' && trim((string)($section['image_url']??''))==='') {
                $role=$index===0?'hero':(str_contains(strtolower((string)($section['category']??'')),'project')?'gallery':'general');
                $section['image_url']=$images->find($query,$industry,$role);
                if (($section['media_position']??'none')==='none') {
                    $section['media_position']=$index===0?'background':'right';
                }
            }
            unset($section['image_query']);

            if (is_array($section['items']??null)) {
                foreach ($section['items'] as $itemIndex=>&$item) {
                    if (!is_array($item)) continue;
                    $itemQuery=trim((string)($item['image_query']??''));
                    if ($itemQuery!=='' && trim((string)($item['image_url']??''))==='') {
                        $item['image_url']=$images->find($itemQuery,$industry,'gallery');
                    }
                    unset($item['image_query']);
                }
                unset($item);
            }

            $key='custom_'.Str::lower(Str::random(10));
            $name=trim((string)($section['name']??('Section '.($index+1))))?:('Section '.($index+1));
            $block=$this->promptDesignedBlock($section,$key,$name);

            $spark=$website->customSparks()->create([
                'key'=>$key,
                'name'=>$name,
                'schema'=>['groups'=>[
                    'content'=>['heading','text','items','form','runtime'],
                    'design'=>['visual_style'],
                ],'inherits_global'=>false],
                'block'=>$block,
                'metadata'=>[
                    'source'=>'visual-first-full-page',
                    'visual_source'=>$visual['source'],
                    'visual_source_path'=>$visual['path'],
                    'credits'=>0,
                    'saved'=>false,
                    'page_brief'=>$brief,
                    'section_index'=>$index,
                    'conversation'=>[
                        ['role'=>'user','text'=>$brief,'at'=>now()->toIso8601String()],
                        ['role'=>'assistant','text'=>'I reconstructed this section from the full-page visual design source.','at'=>now()->toIso8601String()],
                    ],
                ],
            ]);
            $blocks[]=$block;
            $sparkIds[]=$spark->id;
        }

        if (count($blocks)<2) {
            throw ValidationException::withMessages(['instructions'=>'Cosmic AI could not build enough editable sections from the visual source.']);
        }

        return response()->json([
            'blocks'=>$blocks,
            'spark_ids'=>$sparkIds,
            'shell'=>$this->normalizeFullPageShell(is_array($generated['shell']??null)?$generated['shell']:[]),
            'site_direction'=>is_array($generated['direction']??null)?$generated['direction']:null,
            'visual_source'=>$visual['source'],
            'credits'=>$credits->balance($request->user()),
            'mode'=>'visual-first-full-page',
        ],201);
    }

    public function qa(Request $request, Website $website, CustomSpark $customSpark)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 422, 'Visual QA is only available for Custom Websites.');
        abort_unless((int) $customSpark->website_id === (int) $website->id, 404);

        $validated = $request->validate([
            'reference' => ['required','image','mimes:png,jpg,jpeg,webp','max:8192'],
            'rendered' => ['required','image','mimes:png,jpg,jpeg,webp','max:8192'],
            'block' => ['required','string','max:80000'],
        ]);

        $currentBlock = json_decode($validated['block'], true);
        if (!is_array($currentBlock)) {
            throw ValidationException::withMessages(['block' => 'The generated Spark could not be prepared for visual QA.']);
        }

        $toDataUri = static function ($file): string {
            return 'data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath()));
        };
        $referenceUri = $toDataUri($validated['reference']);
        $renderedUri = $toDataUri($validated['rendered']);

        $system = <<<'PROMPT'
You are Cosmic AI Visual QA, the second pass of a screenshot-to-component compiler.
Return JSON only, never markdown.

You receive:
IMAGE 1 = ORIGINAL REFERENCE screenshot.
IMAGE 2 = CURRENT RENDER of the generated Custom Spark only.
You also receive CURRENT_BLOCK JSON.

GOAL: make CURRENT_BLOCK visually closer to IMAGE 1 without redesigning it and without regenerating correct parts unnecessarily.

STRICT QA METHOD:
1. Compare section PROPORTIONS/aspect ratio and content vertical position. Do not copy IMAGE 1 literal pixel height. Preserve CURRENT_BLOCK.visual_style.reference_aspect_ratio when present and tune composition inside that ratio.
2. Compare content max width, copy width, left/right proportions and alignment.
3. Compare heading size/line breaks, body size/line height, eyebrow, review badge, button and form geometry.
4. Compare background, cards, colors, radii, padding and gaps.
5. Compare background image focal position and whether the reference uses a directional gradient/wash. Fix background_position and gradient fields before changing unrelated content.
6. Detect missing media/background photography explicitly.
6a. Compare semantic icons, item-row orientation/separators, and accent-colored heading words. If the reference has a compact horizontal icon strip, preserve layout=trust_strip and icon keys.
6b. Ignore any global header in IMAGE 1 when correcting the section; it is handled separately by shell detection.
7. Preserve exact visible wording already captured unless it is clearly wrong.
8. Preserve any correct structure. Apply the smallest set of fixes needed.
9. Prioritize critical mismatches in this order: missing/wrong media, section geometry, background position/gradient, typography scale/line breaks, form geometry, spacing, minor colors.
10. Never invent an external image URL. If the reference contains photography but CURRENT_BLOCK has no recoverable image asset, keep image_url empty and report missing_source_asset in issues.
11. Do not add website header/footer/navigation. QA only the supplied section.

Return:
{
  "score": integer 0-100,
  "breakdown": {"layout":0-100,"typography":0-100,"spacing":0-100,"color":0-100,"media":0-100,"components":0-100},
  "issues": [{"type":"layout|typography|spacing|color|media|form|review|content","severity":"critical|major|minor","message":"..."}],
  "block": FULL corrected block object
}

The corrected block must keep type=luna_custom_section and custom_spark_key unchanged.
Use the existing supported fields only. Tune visual_style numerically when possible instead of replacing content structure.
PROMPT;

        $apiKey = (string) config('openai.api_key');
        abort_if($apiKey === '', 503, 'OpenAI is not configured.');
        $response = Http::withToken($apiKey)->timeout(120)->post(rtrim((string)(config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/chat/completions', [
            'model' => env('OPENAI_VISION_MODEL', env('OPENAI_MODEL', 'gpt-5-mini')),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role'=>'system','content'=>$system],
                ['role'=>'user','content'=>[
                    ['type'=>'text','text'=>"CURRENT_BLOCK JSON:\n".json_encode($currentBlock, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\nCompare IMAGE 1 reference to IMAGE 2 render, then return the full corrected block."],
                    ['type'=>'text','text'=>'IMAGE 1 — ORIGINAL REFERENCE'],
                    ['type'=>'image_url','image_url'=>['url'=>$referenceUri,'detail'=>'high']],
                    ['type'=>'text','text'=>'IMAGE 2 — CURRENT RENDER'],
                    ['type'=>'image_url','image_url'=>['url'=>$renderedUri,'detail'=>'high']],
                ]],
            ],
        ])->throw()->json();

        $raw = data_get($response, 'choices.0.message.content', '{}');
        $qa = json_decode($raw, true);
        if (!is_array($qa) || !is_array($qa['block'] ?? null)) {
            throw ValidationException::withMessages(['rendered' => 'Cosmic AI could not complete visual QA. The first-pass Spark was kept.']);
        }

        $fixed = $qa['block'];
        $allowed = ['type','custom_spark_key','custom_spark_saved','category','layout','alignment','media_position','density','accent_shape','section_mood','eyebrow','heading','heading_accent_text','text','primary_label','primary_url','secondary_label','secondary_url','image_url','items','theme','style_overrides','visual_style','review','form'];
        $fixed = array_intersect_key($fixed, array_flip($allowed));
        $fixed['type'] = 'luna_custom_section';
        $fixed['custom_spark_key'] = $customSpark->key;
        $fixed['style_overrides'] = [];
        $fixed = array_merge($currentBlock, $fixed);

        $metadata = $this->pushRevision($customSpark, $currentBlock, 'Before Cosmic AI visual QA');
        $metadata['qa'] = [
            'score' => max(0, min(100, (int) ($qa['score'] ?? 0))),
            'issues' => array_slice(is_array($qa['issues'] ?? null) ? $qa['issues'] : [], 0, 12),
            'breakdown' => is_array($qa['breakdown'] ?? null) ? array_intersect_key($qa['breakdown'], array_flip(['layout','typography','spacing','color','media','components'])) : [],
            'passes' => (int) data_get($metadata, 'qa.passes', 0) + 1,
        ];
        $conversation = is_array($metadata['conversation'] ?? null) ? $metadata['conversation'] : [];
        $score = $metadata['qa']['score'];
        $conversation[] = ['role'=>'assistant','text'=>'Visual QA complete'.($score ? ' — '.$score.'% match.' : '.').' I applied one focused correction pass without changing other Sparks.','at'=>now()->toIso8601String()];
        $metadata['conversation'] = array_slice($conversation, -40);
        $customSpark->forceFill(['block' => $fixed, 'metadata' => $metadata])->save();

        return response()->json([
            'spark' => $customSpark->fresh(),
            'block' => $fixed,
            'qa' => $metadata['qa'],
        ]);
    }



    private function pushRevision(CustomSpark $spark, array $block, string $reason): array
    {
        $metadata = is_array($spark->metadata) ? $spark->metadata : [];
        $revisions = is_array($metadata['revisions'] ?? null) ? $metadata['revisions'] : [];
        $revisions[] = [
            'id' => (string) Str::uuid(),
            'reason' => $reason,
            'at' => now()->toIso8601String(),
            'block' => $block,
        ];
        $metadata['revisions'] = array_slice($revisions, -20);
        return $metadata;
    }




    private function syncSavedSparkToPages(Website $website, CustomSpark $spark): void
    {
        $savedBlock = is_array($spark->block) ? $spark->block : [];
        $savedBlock['custom_spark_key'] = $spark->key;
        $savedBlock['custom_spark_saved'] = true;

        $primary = (string) data_get($website->theme_settings, 'primary', 'midnight');
        $publishedPrimary = (string) data_get($website->published_theme_settings ?: $website->theme_settings, 'primary', $primary);

        $website->pages()->get()->each(function ($page) use ($savedBlock, $spark, $primary, $publishedPrimary, $website) {
            $syncBlocks = function ($blocks) use ($savedBlock, $spark) {
                if (!is_array($blocks)) return [$blocks, false];
                $changed = false;
                $next = array_map(function ($candidate) use ($savedBlock, $spark, &$changed) {
                    if (!is_array($candidate) || ($candidate['custom_spark_key'] ?? null) !== $spark->key) return $candidate;
                    $changed = true;
                    return array_merge($candidate, $savedBlock, ['custom_spark_saved'=>true]);
                }, $blocks);
                return [$next, $changed];
            };

            [$draftBlocks, $draftChanged] = $syncBlocks($page->blocks ?? []);
            [$publishedBlocks, $publishedChanged] = $syncBlocks($page->published_blocks ?? []);

            $updates = [];
            if ($draftChanged) $updates['blocks'] = $draftBlocks;
            if ($publishedChanged) {
                $updates['published_blocks'] = $publishedBlocks;
                if ($page->status === 'published') {
                    $style = PageStyleRegistry::normalize($page->published_page_style ?: $website->published_page_style ?: $website->page_style);
                    $updates['published_html'] = CmsHtmlCompiler::compile($publishedBlocks, $publishedPrimary, ['page_style'=>$style]);
                }
            }

            if ($updates) $page->forceFill($updates)->save();
        });
    }


    public function savedLibrary(Request $request, Website $website)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 422, 'Custom Spark Library is only available for Custom Websites.');

        $sparks = $website->customSparks()->latest('updated_at')->get()->filter(function ($spark) {
            $metadata = is_array($spark->metadata) ? $spark->metadata : [];
            return (bool) ($metadata['saved'] ?? false);
        })->values()->map(function ($spark) {
            return [
                'key' => $spark->key,
                'name' => $spark->name,
                'block' => $spark->block,
                'updated_at' => optional($spark->updated_at)->toIso8601String(),
            ];
        });

        return response()->json(['sparks' => $sparks]);
    }

    public function duplicateSaved(Request $request, Website $website, string $key)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 422, 'Custom Spark Library is only available for Custom Websites.');
        $source = $website->customSparks()->where('key', $key)->firstOrFail();
        $metadata = is_array($source->metadata) ? $source->metadata : [];
        abort_unless((bool) ($metadata['saved'] ?? false), 422, 'Only saved Custom Sparks can be duplicated.');

        $newKey = 'custom_'.Str::lower(Str::random(10));
        $block = is_array($source->block) ? $source->block : [];
        $block['custom_spark_key'] = $newKey;
        $block['custom_spark_saved'] = true;

        $copy = $website->customSparks()->create([
            'key' => $newKey,
            'name' => trim((string) $source->name).' Copy',
            'reference_path' => $source->reference_path,
            'block' => $block,
            'metadata' => [
                'source' => 'saved-library',
                'saved' => true,
                'saved_at' => now()->toIso8601String(),
                'conversation' => [['role'=>'assistant','text'=>'Duplicated from your saved Custom Spark Library.','at'=>now()->toIso8601String()]],
                'revisions' => [],
            ],
        ]);

        return response()->json(['spark'=>$copy,'block'=>$block]);
    }


    public function saveSpark(Request $request, Website $website, string $key, CreditService $credits)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 422, 'Save This Spark is only available for Custom Websites.');
        $spark = $website->customSparks()->where('key', $key)->firstOrFail();
        $metadata = is_array($spark->metadata) ? $spark->metadata : [];

        if ((bool) ($metadata['saved'] ?? false)) {
            $this->syncSavedSparkToPages($website, $spark);
            return response()->json([
                'spark' => $spark->fresh(),
                'credits' => $credits->balance($request->user()),
                'already_saved' => true,
                'synced_to_pages' => true,
            ]);
        }

        if (!$credits->canAfford($request->user(), 50)) {
            throw ValidationException::withMessages(['credits' => 'You need 50 credits to save this Custom Spark.']);
        }

        $credits->consume(
            $request->user(),
            50,
            'Save Custom Spark',
            $website,
            'custom-spark-save:'.$spark->id,
            ['category'=>'sparks','custom_spark_id'=>$spark->id]
        );

        $metadata = $this->pushRevision($spark, is_array($spark->block) ? $spark->block : [], 'Before saving Custom Spark');
        $metadata['saved'] = true;
        $metadata['saved_at'] = now()->toIso8601String();
        $metadata['save_credits'] = 50;
        $conversation = is_array($metadata['conversation'] ?? null) ? $metadata['conversation'] : [];
        $conversation[] = ['role'=>'assistant','text'=>'This Spark is saved to the website and ready for normal publishing/export.','at'=>now()->toIso8601String()];
        $metadata['conversation'] = array_slice($conversation, -40);
        $savedBlock = is_array($spark->block) ? $spark->block : [];
        $savedBlock['custom_spark_saved'] = true;
        $spark->block = $savedBlock;
        $spark->metadata = $metadata;
        $spark->save();
        $this->syncSavedSparkToPages($website, $spark);

        return response()->json([
            'spark' => $spark->fresh(),
            'credits' => $credits->balance($request->user()),
            'saved' => true,
        ]);
    }


    public function undo(Request $request, Website $website, string $key)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 422, 'Undo is only available for Custom Websites.');
        $spark = $website->customSparks()->where('key', $key)->firstOrFail();
        $metadata = is_array($spark->metadata) ? $spark->metadata : [];
        $revisions = is_array($metadata['revisions'] ?? null) ? $metadata['revisions'] : [];
        if (!$revisions) {
            throw ValidationException::withMessages(['revision' => 'There is no earlier Cosmic AI revision to restore.']);
        }

        $revision = array_pop($revisions);
        $restored = is_array($revision['block'] ?? null) ? $revision['block'] : null;
        if (!$restored) {
            throw ValidationException::withMessages(['revision' => 'That revision could not be restored.']);
        }

        $metadata['revisions'] = $revisions;
        $restored['custom_spark_saved'] = (bool) ($metadata['saved'] ?? false);
        $conversation = is_array($metadata['conversation'] ?? null) ? $metadata['conversation'] : [];
        $conversation[] = ['role'=>'assistant','text'=>'Restored the previous Spark version.','at'=>now()->toIso8601String()];
        $metadata['conversation'] = array_slice($conversation, -40);

        $spark->block = $restored;
        $spark->metadata = $metadata;
        $spark->save();

        return response()->json([
            'block' => $restored,
            'spark' => $spark->fresh(),
            'messages' => $metadata['conversation'],
            'revisions_remaining' => count($revisions),
        ]);
    }


    public function chatHistory(Request $request, Website $website, string $key)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 404);
        $spark = $website->customSparks()->where('key', $key)->firstOrFail();
        $metadata = is_array($spark->metadata) ? $spark->metadata : [];
        return response()->json([
            'spark' => $spark,
            'messages' => array_values(is_array($metadata['conversation'] ?? null) ? $metadata['conversation'] : []),
            'qa' => $metadata['qa'] ?? null,
            'reference_url' => $metadata['reference_url'] ?? null,
            'revisions' => collect(array_reverse(is_array($metadata['revisions'] ?? null) ? $metadata['revisions'] : []))->take(20)->map(fn($revision) => [
                'id' => $revision['id'] ?? null,
                'reason' => $revision['reason'] ?? 'Revision',
                'at' => $revision['at'] ?? null,
            ])->values(),
        ]);
    }


    private function lunaUnsupportedLowLevelDesignRequest(string $prompt): ?string
    {
        $p=Str::lower(trim($prompt));
        if($p==='') return null;

        // Cosmic owns implementation-level styling. Luna accepts normal design intent,
        // but deliberately rejects raw CSS/Tailwind/DOM instructions that can break
        // Builder -> Preview -> static export parity.
        $rawCodeIntent=(bool) preg_match('/(?:^|\\s)(?:css|html|javascript|js|php|tailwind)(?:\\s|$)|(?:font-size|line-height|letter-spacing|z-index|position\\s*:\\s*|display\\s*:\\s*|grid-template|transform\\s*:\\s*|!important|className|class=|style=)/i',$prompt);
        $exactCssValue=(bool) preg_match('/\\b\\d+(?:\\.\\d+)?\\s*(?:px|rem|em|vw|vh|svh|dvh|%)\\b/i',$prompt);
        $technicalLayout=Str::contains($p,['absolute positioning','position absolute','fixed positioning','negative margin','negative padding','custom breakpoint','media query','raw css','custom css','tailwind class','inject css','inject html','custom javascript','custom js']);

        if(!$rawCodeIntent && !$exactCssValue && !$technicalLayout) return null;

        // URLs are content, not styling instructions; pasted media links remain supported.
        if(preg_match('#https?://#i',$prompt) && Str::contains($p,['video','image','photo','link','url'])) return null;

        return "I can’t apply that exact low-level styling safely. I can make the requested visual change using Cosmic’s responsive design system so the Builder and live export stay consistent.";
    }

    /**
     * Ground vague Luna redesign/media requests in the website's existing business context.
     * This prevents a generic request such as "make this section better" from drifting
     * into an unrelated industry when a replacement Spark needs fresh photography.
     */
    private function lunaGroundedMediaPrompt(string $prompt, array $blocks, array $siteMemory=[], array $selected=[]): string
    {
        $prompt=trim($prompt);
        $resolver=app(\App\Services\IndustryResolver::class);

        // Explicit industry language in the current request always wins.
        $explicit=$resolver->resolve($prompt,'default');
        $contextParts=[];
        foreach(['industry','business_type','business','site_industry','website_industry'] as $key){
            if(!empty($siteMemory[$key]) && is_scalar($siteMemory[$key])) $contextParts[]=(string)$siteMemory[$key];
        }
        if($selected) $contextParts[]=json_encode($selected,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'';
        foreach(array_slice($blocks,0,12) as $block){
            if(!is_array($block)) continue;
            $contextParts[]=implode(' ',array_filter([
                (string)($block['type']??''),(string)($block['eyebrow']??''),(string)($block['heading']??''),
                (string)($block['title']??''),(string)($block['text']??''),(string)($block['description']??'')
            ]));
        }
        $context=trim(implode("\n",$contextParts));
        $industry=$explicit!=='default'?$explicit:$resolver->resolve($context,'default');
        if($industry==='default') return $prompt;

        $label=$resolver->displayName($industry,Str::headline($industry));
        $query=$resolver->searchQuery($industry);
        return $prompt
            ."\n\nWEBSITE INDUSTRY CONTEXT: {$label}."
            ."\nMEDIA SEMANTIC LOCK: Any new/replaced photography must clearly belong to {$label} and the selected section's existing purpose/content."
            ." Do not introduce people, locations, equipment, products, or activities from an unrelated industry."
            ." Preserve existing relevant media unless replacement improves the request."
            ."\nPreferred stock-photo subject vocabulary: {$query}.";
    }

    private function lunaUpdatedSiteMemory(string $prompt, array $memory, array $theme=[]): array
    {
        $p=Str::lower($prompt);
        $memory=is_array($memory)?$memory:[];
        $styleWords=['premium','luxury','minimal','clean','bold','editorial','corporate','playful','modern','elegant','warm','dark','bright'];
        foreach($styleWords as $style){
            if(preg_match('/\b'.preg_quote($style,'/').'\b/i',$prompt))$memory['design_direction']=$style;
        }
        if(Str::contains($p,['more breathing room','spacious','more spacing']))$memory['spacing']='spacious';
        if(Str::contains($p,['compact','tighter spacing','less spacing']))$memory['spacing']='compact';
        if(Str::contains($p,['rounded cards','more rounded']))$memory['corners']='rounded';
        if(Str::contains($p,['sharp corners','less rounded','square corners']))$memory['corners']='sharp';
        if(Str::contains($p,['large headings','bigger headings','dramatic headings']))$memory['heading_scale']='large';
        if(Str::contains($p,['smaller headings','subtle headings']))$memory['heading_scale']='restrained';
        if(!empty($theme['primary']))$memory['theme_primary']=$theme['primary'];
        $memory['last_direction_at']=now()->toIso8601String();
        return array_slice($memory,0,20,true);
    }

    private function lunaArtDirectionIntent(string $prompt): bool
    {
        $p=Str::lower($prompt);
        return Str::contains($p,[
            'make this page feel','make the page feel','make this page more','make the page more',
            'polish this page','improve this page','art direct','more premium','more luxury',
            'more polished','more professional','more editorial','more modern'
        ]);
    }

    private function lunaReferenceSectionIndex(string $prompt, array $blocks, int $targetIndex): ?int
    {
        $p=Str::lower($prompt);
        if(Str::contains($p,['section above','one above','previous section'])) return $targetIndex>0?$targetIndex-1:null;
        if(Str::contains($p,['section below','one below','next section'])) return $targetIndex>=0&&$targetIndex<count($blocks)-1?$targetIndex+1:null;

        $aliases=[
            'hero'=>['hero','banner','masthead'],'services'=>['services','service'],
            'testimonials'=>['testimonials','testimonial','reviews'],'pricing'=>['pricing','plans'],
            'faq'=>['faq'],'contact'=>['contact'],'cta'=>['cta','call to action'],
            'gallery'=>['gallery','portfolio','projects'],'process'=>['process','timeline'],
            'team'=>['team'],'about'=>['about','story'],
        ];
        if(!Str::contains($p,['like ','same as','match ','similar to','copy the','use the']))return null;
        foreach($aliases as $name=>$words){
            if(!collect($words)->contains(fn($word)=>preg_match('/\b'.preg_quote($word,'/').'\b/i',$prompt)))continue;
            foreach($blocks as $index=>$block){
                if($index===$targetIndex)continue;
                $hay=Str::lower((string)($block['type']??'').' '.(string)($block['heading']??$block['title']??''));
                if(Str::contains($hay,$words))return (int)$index;
            }
        }
        return null;
    }

    private function lunaRelativeDesignIntent(string $prompt, array $current=[], array $reference=[]): array
    {
        $changes=$this->lunaDesignIntent($prompt,$current);
        $p=Str::lower($prompt);

        // Selected-section vague design intent: apply a restrained, deterministic
        // visual polish instead of falling through to "no verified change".
        $vagueSectionDesign=Str::contains($p,[
            'this section feel more premium',
            'make this section more premium',
            'make this section feel premium',
            'make this section more modern',
            'make this section feel more modern',
            'make this section visually interesting',
            'make it more visually interesting',
            'polish this section',
            'improve this section design',
            'improve the design of this section',
            'make this section better',
        ]);
        if($vagueSectionDesign && empty($changes)){
            $currentGap=(float)($current['content_gap']??24);
            $currentRadius=(float)($current['card_radius']??16);
            $currentImageRadius=(float)($current['image_radius']??12);
            $currentPadding=(float)($current['section_padding_y']??80);

            $changes=[
                'content_gap'=>min(40,max(28,$currentGap+4)),
                'card_radius'=>min(28,max(18,$currentRadius+4)),
                'image_radius'=>min(24,max(14,$currentImageRadius+2)),
                'section_padding_y'=>min(112,max(88,$currentPadding+8)),
            ];

            if(Str::contains($p,['not too busy','without making it too busy','keep it clean','subtle'])){
                $changes['content_gap']=min($changes['content_gap'],32);
                $changes['card_radius']=min($changes['card_radius'],22);
                $changes['image_radius']=min($changes['image_radius'],18);
                $changes['section_padding_y']=min($changes['section_padding_y'],96);
            }
        }

        // Page-level art direction must preserve the established typography/spacing
        // contract. Luna may change content, media, registered layout, theme, and
        // repeaters, but it no longer invents arbitrary font/spacing overrides merely
        // because the user asks for a more premium/modern/polished feel.
        if($this->lunaArtDirectionIntent($prompt) && empty($changes)){
            $changes=[];
        }
        $relative=Str::contains($p,['a little','slightly','bit ','more ','less ','bigger','smaller','larger','tighter','roomier','dramatic']);
        if(!$relative)return $changes;

        $referenceDesign=is_array($reference['luna_design_overrides']??null)?$reference['luna_design_overrides']:[];
        if(Str::contains($p,['same as','match ','like the','similar to'])&&!empty($referenceDesign)){
            foreach(['heading_size','body_size','heading_line_height','body_line_height','letter_spacing','section_padding_y','section_padding_x','content_gap','card_radius','image_radius','content_max_width','section_min_height','text_align'] as $key){
                if(array_key_exists($key,$referenceDesign))$changes[$key]=$referenceDesign[$key];
            }
        }
        return $this->clampLunaDesignOverrides($changes);
    }

    /**
     * Map natural-language global design requests onto the centralized semantic
     * component tokens. This is deterministic after Luna has interpreted scope:
     * one token change can safely affect all registered Sparks.
     */
    private function lunaComponentDesignIntent(string $prompt, array $current=[]): array
    {
        $p=Str::lower($prompt);
        $changes=[];

        $radius=null;
        if(Str::contains($p,['square','sharp corners','no rounding','no rounded'])) $radius=0;
        elseif(Str::contains($p,['less rounded','reduce rounding','smaller radius'])) $radius=10;
        elseif(Str::contains($p,['more rounded','softer corners','rounded cards'])) $radius=24;

        if($radius!==null){
            if(Str::contains($p,['button'])) $changes['button_radius']=$radius===24?9999:$radius;
            elseif(Str::contains($p,['image','photo'])) $changes['image_radius']=$radius;
            elseif(Str::contains($p,['input','form'])) $changes['input_radius']=$radius;
            elseif(Str::contains($p,['card'])) $changes['card_radius']=$radius;
            else{
                $changes['card_radius']=$radius;
                $changes['image_radius']=$radius;
                $changes['input_radius']=$radius;
                $changes['button_radius']=$radius===24?9999:$radius;
            }
        }

        if(Str::contains($p,['pill button','pill-shaped button','pill shaped button'])){
            $changes['button_radius']=9999;
        }

        return array_merge($current,$changes);
    }

    private function lunaGlobalComponentTokenAction(
        string $prompt,
        array $scopeResolution,
        array $components
    ): ?array {
        if(($scopeResolution['scope']??'')!=='global_token') return null;
        $token=(string)($scopeResolution['global_token']??'');
        if(!in_array($token,['card','button','image'],true)) return null;

        $q=Str::lower(trim($prompt));
        $changes=[];

        $explicitRadius=null;
        if(preg_match('/(\d+(?:\.\d+)?)\s*(px|rem)/i',$prompt,$m)){
            $explicitRadius=$m[1].Str::lower($m[2]);
        }

        $less=Str::contains($q,['less rounded','reduce rounding','smaller radius','sharper','more square']);
        $more=Str::contains($q,['more rounded','softer corners','rounder','pill']);
        $square=Str::contains($q,['square','no rounding','sharp corners','no rounded']);

        $key=match($token){
            'card'=>'card_radius',
            'button'=>'button_radius',
            'image'=>'image_radius',
        };
        $current=(string)($components[$key]??match($token){
            'button'=>'9999px',
            'card'=>'20px',
            'image'=>'16px',
        });

        if($explicitRadius!==null){
            $value=$explicitRadius;
        }elseif($square){
            $value='0px';
        }elseif($token==='button' && $more){
            $value='9999px';
        }else{
            $base=match($token){'button'=>18,'card'=>20,'image'=>16};
            if(preg_match('/^(\d+(?:\.\d+)?)px$/i',$current,$m))$base=(float)$m[1];
            elseif(preg_match('/^(\d+(?:\.\d+)?)rem$/i',$current,$m))$base=(float)$m[1]*16;
            if($less)$base=max(0,$base*.65);
            elseif($more)$base=min($token==='button'?9999:48,$base*1.35);
            else return null;
            $value=$base>=9999?'9999px':rtrim(rtrim(number_format($base,2,'.',''),'0'),'.').'px';
        }

        $next=$components;
        $next[$key]=$value;

        return [
            'reply'=>"Updated the global {$token} radius token.",
            'components'=>$next,
            'applied_operations'=>[[
                'action'=>'component_token_global',
                'target'=>$token,
                'property'=>'radius',
                'value'=>$value,
            ]],
        ];
    }


    /**
     * Extract an explicitly supplied brand hex. Palette derivation remains
     * centralized/deterministic so a manual brand picker can reuse it at 0 AI credits.
     */
    private function lunaExplicitBrandColor(string $prompt): ?string
    {
        $lower=Str::lower($prompt);
        $hasThemeIntent=Str::contains($lower,[
            'brand color','brand colour','primary color','primary colour',
            'website theme','site theme','theme into','theme to','color family','colour family',
            'brand theme','make the website','make this website','use this brand','this brand'
        ]);
        if(!$hasThemeIntent) return null;
        if(preg_match('/#([0-9a-f]{6}|[0-9a-f]{3})\b/i',$prompt,$m)){
            $hex='#'.Str::upper($m[1]);
            if(strlen($hex)===4) $hex='#'.$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3];
            return $hex;
        }
        return null;
    }

    /**
     * Resolve short theme follow-ups against the last concrete color choice.
     * Luna suggestions remain conversational, but once the user supplies a HEX
     * after an apply/change request, the request becomes directly executable.
     */
    private function lunaResolveThemeFollowUp(string $prompt,array &$siteMemory): string
    {
        $prompt=trim($prompt);
        $lower=Str::lower($prompt);
        $contextState=is_array($siteMemory['context_state']??null)?$siteMemory['context_state']:[];
        $prior=is_array($contextState['last_verified_action']??null)?$contextState['last_verified_action']:(is_array($siteMemory['routing_context']??null)?$siteMemory['routing_context']:[]);
        $themeContext=is_array($siteMemory['theme_context']??null)?$siteMemory['theme_context']:[];
        $hex=null;
        if(preg_match('/#([0-9a-f]{6}|[0-9a-f]{3})\b/i',$prompt,$match)){
            $hex=$this->lunaNormalizeHex('#'.$match[1]);
        }

        $directThemeLanguage=Str::contains($lower,[
            'theme','brand color','brand colour','primary color','primary colour',
            'palette','color scheme','colour scheme','green theme','website color','website colour',
        ]);
        $referenceLanguage=Str::contains($lower,['this one','use this','that one','apply it','yes please']);
        $priorAction=($prior['intent']??null)==='action';

        if($hex && ($directThemeLanguage || $referenceLanguage || $priorAction)){
            $siteMemory['theme_context']=[
                'primary'=>$hex,
                'scope'=>'site',
                'status'=>'resolved',
            ];

            return $prompt."\n\nRESOLVED THEME ACTION: Apply {$hex} as the exact primary anchor of a premium website theme across the whole page and site design tokens. Execute now without asking another scope or color question.";
        }

        $rememberedHex=$this->lunaNormalizeHex((string)($themeContext['primary']??''));
        $scopeFollowUp=Str::contains($lower,[
            'apply to the whole page','apply it to the whole page','whole page','entire page',
            'apply everywhere','sitewide','site-wide','whole site','entire site',
        ]);
        if($rememberedHex && $scopeFollowUp && ($priorAction || Str::contains($lower,['apply','use','set']))){
            return $prompt."\n\nRESOLVED THEME ACTION: Use the previously selected {$rememberedHex} as the exact primary anchor and apply its premium semantic theme across the whole page and site design tokens. Execute now without clarification.";
        }

        return $prompt;
    }

    /**
     * Produce a coherent semantic family instead of blindly replacing every
     * old primary color with the supplied brand color.
     */
    private function lunaNormalizeHex(?string $value): ?string
    {
        $value=trim((string)$value);
        if(!preg_match('/^#([0-9a-f]{6}|[0-9a-f]{3})$/i',$value,$m)) return null;
        $hex=Str::upper($m[1]);
        if(strlen($hex)===3) $hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        return '#'.$hex;
    }

    /**
     * Premium deterministic fallback. This is also the repair source when
     * Luna's proposed family is incomplete or fails contrast validation.
     */
    private function lunaBrandColorMode(string $primary): string
    {
        $primary=$this->lunaNormalizeHex($primary)?:'#224248';
        return app(ColorContrastGuard::class)->luminance($primary)>=0.52 ? 'light' : 'dark';
    }

    private function lunaLegacyBrandPalette(array $palette,string $mode): array
    {
        return array_replace($palette,[
            'sourceColor'=>$palette['primary'],
            'primaryHover'=>$palette['primary_hover'],
            'primarySoft'=>$palette['primary_soft'],
            'secondary'=>$palette['secondary'],
            'background'=>$palette['primary'],
            'surface'=>$palette['surface'],
            'surfaceMuted'=>$palette['surface_alt'],
            'text'=>$palette['on_primary'],
            'surfaceText'=>$palette['body'],
            'buttonPrimary'=>$palette['button_primary'],
            'buttonText'=>$palette['button_text'],
            'buttonSecondary'=>$palette['button_secondary'],
            'buttonSecondaryText'=>$palette['button_secondary_text'],
            'onPrimary'=>$palette['on_primary'],
            'onDark'=>$palette['on_dark'],
            'mode'=>$mode,
            'heroTone'=>$mode==='light'?'dark':'primary',
            'headerOverlay'=>true,
        ]);
    }

    /**
     * Only two custom-brand patterns are allowed:
     * - dark anchor: brand primary owns the hero and white/light surfaces support it.
     * - light anchor: a derived dark secondary owns the hero so the exact light
     *   brand color remains visible on CTAs/highlights without washing out contrast.
     */
    private function lunaBrandColorFamily(string $primary): array
    {
        $primary=$this->lunaNormalizeHex($primary)?:'#224248';
        $mode=$this->lunaBrandColorMode($primary);
        return $this->lunaLegacyBrandPalette(
            app(ThemeColorResolver::class)->fromCustomHex($primary),
            $mode,
        );

    }

    private function lunaApplyBrandVisualPattern(array $blocks,array $header,array $family): array
    {
        $mode=(string)($family['mode']??'dark');
        $next=array_values($blocks);
        $heroIndex=null;
        foreach($next as $index=>$block){
            $type=Str::lower((string)($block['type']??''));
            if(Str::startsWith($type,'hero_') || Str::contains($type,['hero','banner'])){
                $heroIndex=$index;
                break;
            }
        }

        $heroChanged=false;
        if($heroIndex!==null && isset($next[$heroIndex]) && is_array($next[$heroIndex])){
            $desired=$mode==='light'?'dark':'primary';
            if(($next[$heroIndex]['theme']??null)!==$desired){
                $next[$heroIndex]['theme']=$desired;
                $heroChanged=true;
            }
        }

        $nextHeader=$header;
        $overlayChanged=false;
        if($heroIndex!==null && !($nextHeader['overlay_header_on_banner']??false)){
            $nextHeader['overlay_header_on_banner']=true;
            $overlayChanged=true;
        }

        // Custom-shell overrides must not pin unreadable colors after a rebrand.
        if($heroIndex!==null && is_array($nextHeader['custom_style']??null)){
            $style=$nextHeader['custom_style'];
            $style['background_color']='transparent';
            $style['text_color']='#FFFFFF';
            $style['nav_color']='#FFFFFF';
            $style['logo_tone']='light';
            $style['cta_background']=$mode==='light' ? (string)($family['primary']??'#FFFFFF') : '#FFFFFF';
            $style['cta_color']=$mode==='light' ? (string)($family['buttonText']??'#0F172A') : '#0F172A';
            $nextHeader['custom_style']=$style;
        }

        return [
            'blocks'=>$next,
            'header'=>$nextHeader,
            'mode'=>$mode,
            'hero_index'=>$heroIndex,
            'hero_changed'=>$heroChanged,
            'overlay_changed'=>$overlayChanged,
        ];
    }

    /**
     * Validate Luna's proposed premium family against a strict schema.
     * The user's supplied brand hex always remains the primary anchor.
     */
    private function lunaValidateBrandColorFamily(string $primary,mixed $proposal): array
    {
        $primary=$this->lunaNormalizeHex($primary)?:'#224248';
        return $this->lunaLegacyBrandPalette(
            app(ThemeColorResolver::class)->fromCustomHex($primary,is_array($proposal)?$proposal:[]),
            $this->lunaBrandColorMode($primary),
        );

    }


    private function lunaDesignQa(array $overrides): array
    {
        $safe=$this->clampLunaDesignOverrides($overrides);
        $notes=[];
        if(($safe['heading_size']??0)>96){
            $safe['heading_size']=96;
            $notes[]='heading size capped at a responsive-safe maximum';
        }
        if(($safe['body_size']??0)>22){
            $safe['body_size']=22;
            $notes[]='body size capped for readable layout';
        }
        if(($safe['section_padding_y']??0)>160){
            $safe['section_padding_y']=160;
            $notes[]='section spacing capped to avoid excessive empty space';
        }
        if(isset($safe['heading_size'],$safe['heading_line_height'])&&$safe['heading_size']>=72&&$safe['heading_line_height']>1.2){
            $safe['heading_line_height']=1.12;
            $notes[]='large-heading line height tightened to reduce wrapping/overflow';
        }
        if(isset($safe['content_max_width'])&&$safe['content_max_width']<640){
            $safe['content_max_width']=640;
            $notes[]='content width raised to the safe minimum';
        }
        return ['overrides'=>$safe,'notes'=>$notes];
    }

    /**
     * Sanitize Luna header mutations so navigation structure remains exportable,
     * mobile-safe, and compatible with the manual Builder editor.
     */
    private function lunaNormalizeHeaderMenu(array $items, int $depth=0): array
    {
        if($depth>2) return [];
        $limit=$depth===0?12:8;
        $clean=[];
        foreach(array_slice($items,0,$limit) as $item){
            if(!is_array($item)) continue;
            $label=trim((string)($item['label']??''));
            $url=trim((string)($item['url']??'#'));
            if($label==='') $label='Menu item';
            $label=mb_substr($label,0,80);
            $url=mb_substr($url===''?'#':$url,0,1000);
            $next=['label'=>$label,'url'=>$url];
            $children=is_array($item['children']??null)?$item['children']:[];
            if($children!==[]){
                $normalized=$this->lunaNormalizeHeaderMenu($children,$depth+1);
                if($normalized!==[]) $next['children']=$normalized;
            }
            $clean[]=$next;
        }
        return $clean;
    }

    private function lunaSafeHeaderChanges(array $current, array $changes): array
    {
        $allowed=[
            'type','theme','logo_text','logo_image_url','logo_height','logo_max_width','logo_filter_key','logo_filter',
            'overlay_header_on_banner','cta_label','cta_url','menu',
            'phone_enabled','phone_text','custom_shell_mode','custom_style'
        ];
        $safe=[];
        foreach($allowed as $key){
            if(!array_key_exists($key,$changes)) continue;
            $value=$changes[$key];
            if($key==='menu'){
                if(is_array($value)) $safe[$key]=$this->lunaNormalizeHeaderMenu($value);
                continue;
            }
            if($key==='overlay_header_on_banner'||$key==='phone_enabled'||$key==='custom_shell_mode'){
                $safe[$key]=(bool)$value;
                continue;
            }
            if(in_array($key,['cta_label','logo_text','phone_text'],true)){
                $safe[$key]=mb_substr(trim((string)$value),0,160);
                continue;
            }
            if(in_array($key,['cta_url','logo_image_url'],true)){
                $safe[$key]=mb_substr(trim((string)$value),0,1000);
                continue;
            }
            $safe[$key]=$value;
        }
        return array_merge($current,$safe);
    }

    private function lunaNormalizeFooterColumns(array $columns): array
    {
        $clean=[];
        foreach(array_slice($columns,0,4) as $column){
            if(!is_array($column)) continue;
            $title=mb_substr(trim((string)($column['title']??'Column')),0,80);
            $items=[];
            foreach(array_slice(is_array($column['items']??null)?$column['items']:[],0,6) as $item){
                if(!is_array($item)) continue;
                $items[]=[
                    'label'=>mb_substr(trim((string)($item['label']??'Menu item')),0,80) ?: 'Menu item',
                    'url'=>mb_substr(trim((string)($item['url']??'#')) ?: '#',0,1000),
                ];
            }
            $clean[]=['title'=>$title ?: 'Column','items'=>$items];
        }
        return $clean;
    }

    private function lunaSafeFooterChanges(array $current, array $changes): array
    {
        $safe=$current;
        foreach(['logo_text','logo_image_url','logo_height','logo_filter_key','logo_filter','copyright','privacy_label','privacy_url','terms_label','terms_url','theme'] as $key){
            if(array_key_exists($key,$changes)) $safe[$key]=$changes[$key];
        }
        if(array_key_exists('mega_enabled',$changes)) $safe['mega_enabled']=(bool)$changes['mega_enabled'];

        if(is_array($changes['contact']??null)){
            $contact=[];
            foreach(['email','phone','address'] as $key){
                if(array_key_exists($key,$changes['contact'])) $contact[$key]=mb_substr(trim((string)$changes['contact'][$key]),0,240);
            }
            $safe['contact']=array_merge(is_array($safe['contact']??null)?$safe['contact']:[],$contact);
        }

        if(is_array($changes['social_links']??null)){
            $links=[];
            foreach(array_slice($changes['social_links'],0,6) as $item){
                if(!is_array($item)) continue;
                $links[]=[
                    'label'=>mb_substr(trim((string)($item['label']??'Social')),0,80) ?: 'Social',
                    'url'=>mb_substr(trim((string)($item['url']??'#')) ?: '#',0,1000),
                ];
            }
            $safe['social_links']=$links;
        }

        if(is_array($changes['mega_footer']??null)){
            $incoming=$changes['mega_footer'];
            $mega=is_array($safe['mega_footer']??null)?$safe['mega_footer']:[];
            foreach(['tagline','primary_label','primary_url','theme','enabled'] as $key){
                if(array_key_exists($key,$incoming)) $mega[$key]=$incoming[$key];
            }
            if(array_key_exists('columns',$incoming) && is_array($incoming['columns'])){
                $mega['columns']=$this->lunaNormalizeFooterColumns($incoming['columns']);
            }
            if(array_key_exists('enabled',$mega)) $mega['enabled']=(bool)$mega['enabled'];
            $safe['mega_footer']=$mega;
        }

        return $safe;
    }

    private function lunaBlockFingerprint(array $block): string
    {
        $copy=$block;
        unset($copy['_renderKey']);
        return hash('sha256',json_encode($copy,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
    }

    private function clampLunaDesignOverrides(array $values): array
    {
        $limits=[
            'heading_size'=>[20,112],
            'body_size'=>[12,26],
            'heading_line_height'=>[0.88,1.6],
            'body_line_height'=>[1.15,2.0],
            'letter_spacing'=>[-2,8],
            'section_padding_y'=>[0,200],
            'section_padding_x'=>[0,120],
            'content_gap'=>[0,96],
            'card_radius'=>[0,64],
            'image_radius'=>[0,64],
            'content_max_width'=>[560,1800],
            'section_min_height'=>[0,1200],
        ];
        $clean=[];
        foreach($limits as $key=>[$min,$max]){
            if(!array_key_exists($key,$values)||!is_numeric($values[$key]))continue;
            $clean[$key]=max($min,min($max,(float)$values[$key]));
        }
        if(isset($values['text_align'])&&in_array($values['text_align'],['left','center','right'],true)){
            $clean['text_align']=$values['text_align'];
        }
        return $clean;
    }

    private function lunaDesignIntent(string $prompt, array $current=[]): array
    {
        $p=Str::lower($prompt);
        $changes=[];
        $number=null;
        if(preg_match('/(-?\d+(?:\.\d+)?)\s*(?:px)?/i',$prompt,$m))$number=(float)$m[1];
        $more=Str::contains($p,['increase','bigger','larger','more ','add more','roomier','spacious','dramatic','huge']);
        $less=Str::contains($p,['decrease','smaller','less ','tighter','compact','reduce']);
        $value=function(string $key,float $fallback,float $step)use($current,$number,$more,$less){
            if($number!==null)return $number;
            $base=is_numeric($current[$key]??null)?(float)$current[$key]:$fallback;
            return $less?$base-$step:$base+$step;
        };

        if(Str::contains($p,['heading','headline','title'])&&Str::contains($p,['size','bigger','larger','smaller','huge','increase','decrease'])){
            $changes['heading_size']=$value('heading_size',52,8);
        }
        if(Str::contains($p,['body text','paragraph','copy','body font'])&&Str::contains($p,['size','bigger','larger','smaller','increase','decrease'])){
            $changes['body_size']=$value('body_size',16,2);
        }
        if(Str::contains($p,['line height','line-height','leading'])){
            if(Str::contains($p,['heading','headline','title']))$changes['heading_line_height']=$value('heading_line_height',1.05,.08);
            else $changes['body_line_height']=$value('body_line_height',1.55,.12);
        }
        if(Str::contains($p,['letter spacing','letter-spacing','tracking'])){
            $changes['letter_spacing']=$value('letter_spacing',0,.5);
        }
        if(Str::contains($p,['padding','spacing'])&&Str::contains($p,['section','top','bottom','vertical','padding'])){
            $changes['section_padding_y']=$value('section_padding_y',72,16);
        }
        if(Str::contains($p,['horizontal padding','left and right padding','side padding'])){
            $changes['section_padding_x']=$value('section_padding_x',24,12);
        }
        if(Str::contains($p,['gap','space between'])){
            $changes['content_gap']=$value('content_gap',24,8);
        }
        if(Str::contains($p,['corner','radius','rounded'])){
            if(Str::contains($p,['image','photo']))$changes['image_radius']=$value('image_radius',16,8);
            else $changes['card_radius']=$value('card_radius',16,8);
        }
        if(Str::contains($p,['content width','container width','max width'])){
            $changes['content_max_width']=$value('content_max_width',1280,120);
        }
        if(Str::contains($p,['section height','min height','taller section','shorter section'])){
            $changes['section_min_height']=$value('section_min_height',560,80);
        }
        if(preg_match('/\b(align|alignment|text)\b.*\b(left|center|right)\b/i',$prompt,$m)){
            $changes['text_align']=Str::lower($m[2]);
        }

        return $this->clampLunaDesignOverrides($changes);
    }

    private function universalBackgroundState(?string $resolvedTheme, array $block, int $index): string
    {
        $resolved=Str::lower(trim((string)$resolvedTheme));
        $declared=Str::lower(trim((string)($block['theme']??'')));
        $value=$resolved!=='' && $resolved!=='auto' ? $resolved : $declared;

        if(Str::contains($value,['primary','dark','midnight','navy','obsidian','charcoal'])) return 'primary';
        if(Str::contains($value,['surface','stone','soft','muted'])) return 'surface';
        if(Str::contains($value,['white','light','clean'])) return 'white';

        $type=Str::lower((string)($block['type']??''));
        return ($index===0 || Str::contains($type,['hero','banner'])) ? 'primary' : 'white';
    }

    private function universalBackgroundOverlay(string $state, string $strength='balanced'): string
    {
        $strength=Str::lower($strength);
        return match($state){
            'primary'=>match($strength){
                'lighter'=>'linear-gradient(135deg, rgba(8,15,28,.58), rgba(8,15,28,.28))',
                'darker'=>'linear-gradient(135deg, rgba(8,15,28,.88), rgba(8,15,28,.62))',
                default=>'linear-gradient(135deg, rgba(8,15,28,.76), rgba(8,15,28,.46))',
            },
            'surface'=>match($strength){
                'lighter'=>'linear-gradient(135deg, rgba(248,250,252,.72), rgba(241,245,249,.42))',
                'darker'=>'linear-gradient(135deg, rgba(241,245,249,.94), rgba(226,232,240,.78))',
                default=>'linear-gradient(135deg, rgba(248,250,252,.88), rgba(241,245,249,.62))',
            },
            default=>match($strength){
                'lighter'=>'linear-gradient(135deg, rgba(255,255,255,.72), rgba(255,255,255,.42))',
                'darker'=>'linear-gradient(135deg, rgba(255,255,255,.96), rgba(248,250,252,.82))',
                default=>'linear-gradient(135deg, rgba(255,255,255,.90), rgba(255,255,255,.66))',
            },
        };
    }

    private function applyUniversalBackgroundIntent(
        AiPageGenerationService $pageGeneration,
        string $prompt,
        array $block,
        string $state,
        bool $remove=false,
        ?string $strength=null
    ): array {
        if($remove){
            unset(
                $block['universal_background_image_url'],
                $block['universal_background_overlay'],
                $block['universal_background_state'],
                $block['universal_background_position']
            );
            $block['universal_background_enabled']=false;
            return ['block'=>$block,'success'=>true,'removed'=>true,'error'=>null];
        }

        $block['universal_background_enabled']=true;
        $block['universal_background_state']=$state;
        $block['universal_background_overlay']=$this->universalBackgroundOverlay($state,$strength?:'balanced');
        $block['universal_background_position']=$block['universal_background_position']??'center center';

        // Overlay-only requests should preserve the existing image.
        if(($strength==='darker'||$strength==='lighter') && trim((string)($block['universal_background_image_url']??''))!==''){
            return ['block'=>$block,'success'=>true,'removed'=>false,'error'=>null];
        }

        // Create one isolated provider slot so adding a section background never
        // replaces cards, thumbnails, avatars or other existing Spark imagery.
        $probe=[
            'type'=>$block['type']??'website_section',
            'universal_background_image_url'=>'',
        ];
        try{
            $remote=$pageGeneration->applyStartPageRemoteImages(
                trim($prompt)."\nUNIVERSAL SECTION BACKGROUND: Find one relevant wide editorial background photograph for this exact section. Do not alter any other imagery.",
                [$probe]
            );
            $url=trim((string)data_get($remote,'blocks.0.universal_background_image_url',''));
            if($url!==''){
                $block['universal_background_image_url']=$url;
                return ['block'=>$block,'success'=>true,'removed'=>false,'error'=>null];
            }
        }catch(\Throwable $e){
            report($e);
        }

        // Never enable a blank background or claim success when the provider failed.
        $block['universal_background_enabled']=false;
        return ['block'=>$block,'success'=>false,'removed'=>false,'error'=>'No matching background image was returned by the image provider.'];
    }

    public function trialPageChat(
        Request $request,
        TrialGeneration $trial,
        LunaCategoryPageService $lunaPages,
        AiPageGenerationService $pageGeneration,
        LunaCreditPricingService $lunaPricing,
        TrialCreditService $trialCredits,
        LunaNaturalReplyService $natural,
        LunaPexelsVideoService $lunaVideos,
        LunaKnowledgeRouter $knowledge,
        LunaPendingActionService $pendingActions,
        LunaFeasibilityGate $feasibilityGate,
        LunaScopeIntelligenceService $scopeIntelligence,
        LunaSmartSparkEditingService $smartSparkEditing,
        SparkEditCapabilityRegistry $sparkEditCapabilities,
        \App\Services\SparkEditCapabilityExecutor $sparkEditCapabilityExecutor,
        \App\Services\LunaTailwindMutationService $tailwindMutations,
        \App\Services\LunaSparkSchemaEditorService $sparkSchemaEditor,
        LunaRenderParityService $renderParity,
        LunaExecutionVerificationService $executionVerification,
        LunaSiteDesignDnaService $siteDna,
        LunaIntentGateway $intentGateway,
        \App\Services\LunaContextStateService $contextState,
        LunaInspectService $inspectService,
        LunaSiteAdminActionService $siteAdminActions
    ) {
        // A whole-page Luna build intentionally runs several bounded provider
        // calls (intent, planning, content, media and the final reply). The
        // default web SAPI limit is only 60 seconds, so it can terminate a
        // healthy build between stages before their own HTTP timeouts apply.
        // Keep this scoped to the throttled builder endpoint.
        set_time_limit(600);

        abort_if($trial->claimed_at, 410, 'This trial has already been claimed.');

        $validated=$request->validate([
            'prompt'=>['required','string','max:6000'],
            'conversation'=>['nullable','string','max:180000'],
            'blocks'=>['required','string','max:350000'],
            'header'=>['nullable','string','max:80000'],
            'footer'=>['nullable','string','max:120000'],
            'theme'=>['nullable','string','max:12000'],
            'typography'=>['nullable','string','max:12000'],
            'background_style'=>['nullable','string','max:12000'],
            'section_layout'=>['nullable','string','max:12000'],
            'components'=>['nullable','string','max:12000'],
            'site_memory'=>['nullable','string','max:24000'],
            'target_scope'=>['nullable','in:page,section,header,footer'],
            'target_index'=>['nullable','integer','min:0','max:100'],
            'element_context'=>['nullable','string','max:30000'],
            'target_item_index'=>['nullable','integer','min:0','max:100'],
            'target_item_mode'=>['nullable','in:repeater_item'],
            'target_collection_key'=>['nullable','string','max:80'],
            'target_resolved_theme'=>['nullable','string','max:40'],
            'confirmed'=>['nullable','boolean'],
            'pending_action_token'=>['nullable','string','max:100'],
            'current_page_id'=>['nullable','integer','min:1'],
        ]);
        $blocks=json_decode($validated['blocks'],true);
        $header=json_decode((string)($validated['header']??'{}'),true);
        $footer=json_decode((string)($validated['footer']??'{}'),true);
        if(!is_array($blocks)) throw ValidationException::withMessages(['blocks'=>'The page could not be prepared for Luna.']);
        $currentPage=null;
        if(!empty($validated['current_page_id'])){
            $currentPage=Page::query()->find((int)$validated['current_page_id']);
            abort_unless(
                $currentPage
                && (
                    (int)$trial->page_id===(int)$currentPage->id
                    || ((int)$trial->website_id>0 && (int)$trial->website_id===(int)$currentPage->website_id)
                ),
                404,
                'This page is not part of the trial website.'
            );
        }
        $siteMemory=json_decode((string)($validated['site_memory']??'{}'),true);
        if(!is_array($siteMemory))$siteMemory=[];
        $elementContext=json_decode((string)($validated['element_context']??'{}'),true);
        if(!is_array($elementContext))$elementContext=[];
        $builderConversation=$this->lunaBuilderConversation((string)($validated['conversation']??''));
        if(!isset($elementContext['itemIndex']) && isset($validated['target_item_index'])){
            $elementContext['itemIndex']=(int)$validated['target_item_index'];
        }
        if(!isset($elementContext['collectionKey']) && !empty($validated['target_collection_key'])){
            $elementContext['collectionKey']=(string)$validated['target_collection_key'];
        }

        $scope=(string)($validated['target_scope']??'page');
        $targetIndex=$scope==='section'?(int)($validated['target_index']??-1):-1;
        $originalUserPrompt=trim((string)$validated['prompt']);
        $scopeResolution=$scopeIntelligence->resolve($originalUserPrompt,$scope,$elementContext);
        $scopeContract=$scopeIntelligence->plannerDirective($scopeResolution);
        $smartEditContract=$smartSparkEditing->contractDirective();
        $pendingActor='trial:'.$trial->id;
        $resumePendingPlan=null;
        $stalePendingPlan=false;
        $orphanAffirmative=false;

        if($validated['confirmed']??false){
            $token=trim((string)($validated['pending_action_token']??''));
            $candidate=$token!=='' ? $pendingActions->consume($pendingActor,$token) : null;
            if(is_array($candidate) && ($candidate['kind']??'')==='trial_destructive_delete'){
                $currentFingerprint=hash('sha256',json_encode(array_values($blocks),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
                if(hash_equals((string)($candidate['blocks_fingerprint']??''),$currentFingerprint)){
                    $resumePendingPlan=$candidate;
                    $validated['prompt']=(string)$candidate['prompt'];
                    $scope=(string)($candidate['scope']??$scope);
                    $targetIndex=$scope==='section'?(int)($candidate['target_index']??$targetIndex):-1;
                }else{
                    $stalePendingPlan=true;
                }
            }else{
                $orphanAffirmative=true;
            }
        }

        $prompt=$this->lunaResolveThemeFollowUp(trim((string)$validated['prompt']),$siteMemory);
        $validated['prompt']=$prompt;
        $scopeResolution=$scopeIntelligence->resolve($prompt,$scope,$elementContext);
        $scopeContract=$scopeIntelligence->plannerDirective($scopeResolution);
        $smartEditContract=$smartSparkEditing->contractDirective();
        if($resumePendingPlan && is_array($resumePendingPlan['canonical_schema']??null)){
            $canonicalIntent=$resumePendingPlan['canonical_schema'];
        }else{
            $routeContext=[
                'surface'=>'trial_builder',
                'ui_scope'=>$scope,
                'target_index'=>$targetIndex,
                'has_blocks'=>count($blocks)>0,
                'page_sparks'=>array_values(array_map(
                    fn($block,$index)=>[
                        'index'=>$index,
                        'type'=>is_array($block)?(string)($block['type']??''):'',
                        'label'=>is_array($block)?Str::limit(trim((string)($block['heading']??$block['title']??$block['eyebrow']??$block['type']??'')),80,''):'',
                    ],
                    $blocks,
                    array_keys($blocks)
                )),
                'current_page_id'=>$currentPage?->id,
                'current_page_title'=>$currentPage?->title,
                'current_page_slug'=>$currentPage?->slug,
                'element_context'=>$elementContext,
                'conversation'=>$builderConversation,
            ];
            $siteMemory=$contextState->attach($siteMemory,$routeContext);
            $routeContext['context_state']=$contextState->routingContext($siteMemory,$routeContext);
            $intentRoute=$intentGateway->route($prompt,$siteMemory,$routeContext);
            $canonicalIntent=($intentRoute['intent']??'chat')==='action'
                ? $intentGateway->classifyAction($prompt,$siteMemory,$routeContext)
                : ['intent'=>'chat'];
        }

        // API 1 owns the chat | action boundary. Legacy structural detection may
        // refine an already-routed ACTION, but it must never promote CHAT into
        // ACTION behind the router's back.
        if(
            !$resumePendingPlan
            && (($canonicalIntent['intent']??'chat')==='action')
            && $sparkEditCapabilities->isStructuralRequest($prompt,is_array($canonicalIntent)?$canonicalIntent:[])
        ){
            $structuralLower=Str::lower($prompt);
            $structuralOperation=Str::contains($structuralLower,['move ','move this','reorder']) ? 'move'
                : (Str::contains($structuralLower,['add ','insert ','create ']) ? 'add'
                : (Str::contains($structuralLower,['remove ','delete ']) ? 'delete'
                : (Str::contains($structuralLower,['redesign','make this section better','improve this section','polish this section','refresh this section','rework this section','restyle this section']) ? 'redesign' : 'replace')));
            $canonicalIntent=array_merge(is_array($canonicalIntent)?$canonicalIntent:[],[
                'intent'=>'action',
                'execution_allowed'=>true,
                'domain'=>'section',
                'operation'=>$structuralOperation,
                'scope'=>'section',
            ]);
        }
        $siteMemory=$intentGateway->memory($siteMemory,$canonicalIntent);
        $siteMemory=$contextState->rememberRouted($siteMemory,$canonicalIntent);
        $coreActionPrompt=$this->lunaCoreActionExecutionPrompt($prompt,$canonicalIntent);
        $trialDna=$siteDna->hydrate($siteMemory,[]);
        $siteDnaContract=$siteDna->plannerDirective($siteMemory,$trialDna,false);
        $knowledgePacket=$knowledge->contextFor($prompt,$scope);
        $canonicalConversation=(($canonicalIntent['intent']??'chat')==='chat');
        $capabilityQuestion=$resumePendingPlan?false:(($canonicalIntent['intent']??'chat')==='chat' && ($canonicalIntent['chat_type']??'general')==='capability');
        $feasibility=$resumePendingPlan
            ? ['feasibility'=>'supported','execution_allowed'=>true,'requires_confirmation'=>false,'informational'=>false]
            : $feasibilityGate->evaluate($prompt,$knowledgePacket,$scope);
        if($canonicalConversation){
            $feasibility['informational']=true;
            $feasibility['execution_allowed']=false;
            $feasibility['requires_confirmation']=false;
        } elseif(($canonicalIntent['intent']??'')==='action'){
            // Direct-action architecture: the canonical router owns chat vs action.
            // Legacy feasibility may describe limits/fallbacks, but it must not turn
            // a concrete build/update action back into a conversational proposal.
            $feasibility['informational']=false;
            $feasibility['execution_allowed']=(bool)($canonicalIntent['execution_allowed']??true);
            $feasibility['requires_confirmation']=(($canonicalIntent['action']??'')==='delete');
        } else {
            $feasibility['execution_allowed']=false;
            $feasibility['requires_confirmation']=false;
        }
        $trialNaturalReplyFromFacts=function(array $facts) use($natural,$prompt,$knowledgePacket){
            return $natural->compose($prompt,[
                'authenticated'=>false,
                'trial'=>true,
                'canonical_knowledge'=>$knowledgePacket,
            ],array_merge([
                'canonical_knowledge'=>$knowledgePacket,
                'rule'=>'Formulate the user-facing reply from verified facts and canonical documentation. Never invent a completed action.',
            ],$facts));
        };

        if(!$resumePendingPlan && $canonicalConversation){
            return response()->json([
                'reply'=>$trialNaturalReplyFromFacts([
                    'canonical_intent'=>$canonicalIntent,
                    'action_completed'=>false,
                    'conversation_mode'=>'chat_docs_only',
                    'rule'=>'This is a chat turn. Answer from canonical documentation and verified context, then stop. Do not create a pending action, do not mutate the trial page, do not ask for Proceed/Continue, and do not expose internal implementation terminology.',
                ]),
                'mode'=>'grounded_info',
                'canonical_intent'=>$canonicalIntent,
                'credit_cost'=>0,
                'credit_balance'=>$trialCredits->balance($trial),
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'site_memory'=>$siteMemory,
                'pending_action'=>false,
                'applied_operations'=>[],
            ]);
        }

        if(!$resumePendingPlan && ($canonicalIntent['action']??'')==='inspect'){
            $inspectFacts=$inspectService->inspect($prompt,[
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme'=>json_decode((string)($validated['theme']??'{}'),true)?:[],
                'typography'=>json_decode((string)($validated['typography']??'{}'),true)?:[],
                'section_layout'=>json_decode((string)($validated['section_layout']??'{}'),true)?:[],
                'components'=>json_decode((string)($validated['components']??'{}'),true)?:[],
                'scope'=>$scope,
                'target_index'=>$targetIndex,
            ]);
            return response()->json([
                'reply'=>$trialNaturalReplyFromFacts([
                    'canonical_intent'=>$canonicalIntent,
                    'action_completed'=>true,
                    'inspection'=>$inspectFacts,
                    'credit_cost'=>0,
                    'rule'=>'Answer only from the verified read-only inspection facts. Do not claim any website mutation. Keep the answer concise and user-facing.',
                ]),
                'mode'=>'inspect',
                'canonical_intent'=>$canonicalIntent,
                'inspection'=>$inspectFacts,
                'credit_cost'=>0,
                'credit_balance'=>$trialCredits->balance($trial),
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'site_memory'=>$siteMemory,
                'pending_action'=>false,
                'applied_operations'=>[],
            ]);
        }

        if(
            !$resumePendingPlan
            && ($canonicalIntent['needs_clarification']??false)
            && !(($canonicalIntent['intent']??'')==='action' && ($canonicalIntent['execution_allowed']??false))
        ){
            return response()->json([
                'reply'=>$trialNaturalReplyFromFacts([
                    'canonical_intent'=>$canonicalIntent,
                    'action_completed'=>false,
                    'missing_information'=>$canonicalIntent['missing']??[],
                    'known_entities'=>$canonicalIntent['entities']??[],
                    'rule'=>'Ask only for genuinely missing information. Preserve known entities and do not mutate the trial page.',
                ]),
                'mode'=>'clarify',
                'canonical_intent'=>$canonicalIntent,
                'credit_cost'=>0,
                'credit_balance'=>$trialCredits->balance($trial),
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'site_memory'=>$siteMemory,
                'pending_action'=>false,
                'applied_operations'=>[],
            ]);
        }

        if($stalePendingPlan || $orphanAffirmative){
            return response()->json([
                'reply'=>$natural->compose($originalUserPrompt,[
                    'authenticated'=>false,
                    'trial'=>true,
                    'canonical_knowledge'=>$knowledgePacket,
                ],[
                    'action_completed'=>false,
                    'pending_action_available'=>false,
                    'constraint'=>$stalePendingPlan
                        ? 'The trial page changed after deletion confirmation was prepared, so the destructive action was discarded for safety.'
                        : 'There is no valid destructive-action confirmation to execute.',
                    'next_step'=>'Ask the user to request the deletion again.',
                ]),
                'mode'=>'reply',
                'credit_cost'=>0,
                'credit_balance'=>$trialCredits->balance($trial),
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'applied_operations'=>[],
            ]);
        }

        $trialAction=(string)($canonicalIntent['action']??'update');
        if(!$resumePendingPlan && $trialAction==='publish'){
            $verification=$executionVerification->verify([['action'=>'publish']],[],false);
            return response()->json([
                'reply'=>$trialNaturalReplyFromFacts(['action'=>'publish trial page','action_completed'=>false,'execution_verification'=>$verification,'constraint'=>'A trial page must be saved to an account before it can be published.','available_next_action'=>'save the trial, then publish from Builder']),
                'mode'=>'reply','canonical_intent'=>$canonicalIntent,'execution_verification'=>$verification,
                'credit_cost'=>0,'credit_balance'=>$trialCredits->balance($trial),'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],'footer'=>is_array($footer)?$footer:[],
                'site_memory'=>$siteMemory,'pending_action'=>false,'applied_operations'=>[],
            ],422);
        }
        if(!$resumePendingPlan && $trialAction==='navigate'){
            $target=Str::lower(trim($this->lunaCanonicalTargetText($canonicalIntent['target']??null).' '.$prompt));
            $navigateUrl=Str::contains($target,['pricing','plans','upgrade'])?route('pricing',['token'=>$trial->token]):null;
            $verification=$executionVerification->verify([['action'=>'navigate']],$navigateUrl?[['action'=>'navigate','target'=>$navigateUrl,'verified'=>true]]:[],$navigateUrl!==null);
            return response()->json([
                'reply'=>$trialNaturalReplyFromFacts(['action'=>'navigate','action_completed'=>$navigateUrl!==null,'destination'=>$navigateUrl?'Pricing':null,'execution_verification'=>$verification,'constraint'=>$navigateUrl?null:'That destination is not available from the trial Builder.']),
                'mode'=>$navigateUrl?'navigate':'reply','navigate_url'=>$navigateUrl,'canonical_intent'=>$canonicalIntent,
                'execution_verification'=>$verification,'execution_phases'=>['Thinking','Checking'],'credit_cost'=>0,
                'credit_balance'=>$trialCredits->balance($trial),'site_memory'=>$siteMemory,'pending_action'=>false,
                'applied_operations'=>$verification['verified_operations']??[],
            ],$navigateUrl?200:422);
        }

        // Trial typography edits use the same deterministic token router as the
        // authenticated Builder. This keeps simple requests fast, free, and
        // verifiable instead of letting the creative planner merely describe a
        // size that was never written to page state.
        $trialTypography=json_decode((string)($validated['typography']??'{}'),true);
        if(!is_array($trialTypography))$trialTypography=[];
        $trialTypographyAction=data_get($canonicalIntent,'routing.menu_scope')==='sparks'
            ? null
            : $this->lunaTypographyAction(
                $prompt,
                $scope,
                $targetIndex,
                $blocks,
                $trialTypography,
                $canonicalIntent
            );
        if(is_array($trialTypographyAction)){
            $trialTypographyAction=$this->lunaVerifyTypographyAction(
                $trialTypographyAction,
                $blocks,
                $trialTypography,
                $canonicalIntent,
                $executionVerification,
                $contextState,
                $siteMemory,
                $routeContext??[]
            );
            return response()->json([
                'reply'=>$trialTypographyAction['reply'],
                'mode'=>'reply',
                'canonical_intent'=>$canonicalIntent,
                'credit_cost'=>0,
                'credit_balance'=>$trialCredits->balance($trial),
                'blocks'=>$trialTypographyAction['blocks'],
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>null,
                'typography_settings'=>$trialTypographyAction['typography_settings'],
                'page_style'=>null,
                'site_memory'=>$trialTypographyAction['site_memory'],
                'pending_action'=>false,
                'applied_operations'=>$trialTypographyAction['applied_operations'],
                'execution_verification'=>$trialTypographyAction['execution_verification'],
            ]);
        }

        $lower=Str::lower($prompt);
        if(data_get($canonicalIntent,'routing.menu_scope')!=='sparks' && $scope==='header'){
            $shellAction=$this->lunaHeaderScopeAction($coreActionPrompt,is_array($header)?$header:[],$siteMemory);
            if(is_array($shellAction)){
                return response()->json([
                    'reply'=>$trialNaturalReplyFromFacts([
                    'action'=>($shellAction['mode']??'reply')==='logo_name_required'?'prepare logo generation':'header change',
                    'action_completed'=>!empty($shellAction['applied_operations']),
                    'verified_operations'=>$shellAction['applied_operations']??[],
                    'needs_user_input'=>($shellAction['mode']??'')==='logo_name_required',
                    'missing_information'=>($shellAction['mode']??'')==='logo_name_required'?'the business/company name to use in the logo':null,
                    'planned_action'=>($shellAction['mode']??'')==='logo_generate'?'generate the logo using the resolved company name':null,
                    'logo_company_name'=>$shellAction['logo_company_name']??null,
                ]),
                    'mode'=>$shellAction['mode']??'reply',
                    'logo_company_name'=>$shellAction['logo_company_name']??null,
                    'blocks'=>$blocks,
                    'header'=>$shellAction['header']??(is_array($header)?$header:[]),
                    'footer'=>is_array($footer)?$footer:[],
                    'theme_key'=>null,
                    'page_style'=>null,
                    'credit_cost'=>0,
                    'credit_balance'=>$trialCredits->balance($trial),
                    'site_memory'=>$shellAction['site_memory']??$siteMemory,
                    'applied_operations'=>$shellAction['applied_operations']??[],
                ]);
            }
        }
        if(data_get($canonicalIntent,'routing.menu_scope')!=='sparks' && ($guardrailReply=$this->lunaUnsupportedLowLevelDesignRequest($prompt))){
            return response()->json([
                'reply'=>$trialNaturalReplyFromFacts([
                    'action_completed'=>false,
                    'capability_status'=>'unsupported exact low-level implementation request',
                    'constraint'=>'Cosmic owns raw CSS/Tailwind/DOM implementation details to protect responsive Builder/live parity.',
                    'fallback'=>'Offer the closest design-system-safe visual equivalent.',
                ]),
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>null,
                'page_style'=>null,
                'credit_cost'=>0,
                'credit_balance'=>$trialCredits->balance($trial),
                'site_memory'=>$siteMemory,
                'applied_operations'=>[],
            ]);
        }
        if(data_get($canonicalIntent,'routing.menu_scope')==='theme' && ($namedTheme=$this->lunaStandaloneNamedThemeKey($prompt))){
            $siteMemory['theme_context']=['primary'=>$namedTheme,'scope'=>'site','status'=>'applied'];
            return response()->json([
                'reply'=>'Applied the '.Str::headline($namedTheme).' theme across the website.',
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>$namedTheme,
                'page_style'=>null,
                'credit_cost'=>0,
                'credit_balance'=>$trialCredits->balance($trial),
                'site_memory'=>$siteMemory,
                'applied_operations'=>[['action'=>'theme','theme_key'=>$namedTheme,'verified'=>true]],
            ]);
        }
        if(data_get($canonicalIntent,'routing.menu_scope')==='theme' && ($automaticTheme=$this->lunaAutomaticThemeKey($prompt,json_decode((string)($validated['theme']??'{}'),true)?:[],(string)($trial->industry??'')))){
            $siteMemory['theme_context']=['primary'=>$automaticTheme,'scope'=>'site','status'=>'applied','source'=>'luna_auto_family'];
            return response()->json([
                'reply'=>'Applied a '.Str::headline($automaticTheme).' color family across the website.',
                'mode'=>'reply',
                'canonical_intent'=>$canonicalIntent,
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>$automaticTheme,
                'page_style'=>null,
                'credit_cost'=>0,
                'credit_balance'=>$trialCredits->balance($trial),
                'site_memory'=>$siteMemory,
                'applied_operations'=>[['action'=>'theme','theme_key'=>$automaticTheme,'verified'=>true,'source'=>'luna_auto_family']],
            ]);
        }
        $backgroundRemove=Str::contains($lower,['remove background image','remove the background image','remove background photo','no background image','clear background image']);
        $backgroundDarker=Str::contains($lower,['make background darker','darken the background','darker overlay','stronger overlay']);
        $backgroundLighter=Str::contains($lower,['make background lighter','lighten the background','lighter overlay','softer overlay']);
        $backgroundAdd=!$backgroundRemove && (
            (Str::contains($lower,'background image') && Str::contains($lower,['add','use','set','change','give','put','apply']))
            || (Str::contains($lower,'background photo') && Str::contains($lower,['add','use','set','change','give','put','apply']))
        );
        $universalBackgroundIntent=$backgroundAdd||$backgroundRemove||$backgroundDarker||$backgroundLighter;

        // Mutation-scope lock: a copy/content rewrite must not accidentally mutate
        // media or design just because the prompt mentions those fields in a
        // preservation clause (e.g. "keep images/layout unchanged").
        $mutationPrompt=Str::lower((string)$validated['prompt']);
        $explicitMediaMutation=Str::contains($mutationPrompt,[
            'replace image','replace the image','change image','change the image','new image',
            'replace photo','change photo','new photo','replace photography','change photography',
            'replace video','change video','new video','use a different image','use different image',
            'use a different photo','use different photo','refresh image','refresh photo',
        ]);
        $contentRewriteIntent=Str::contains($mutationPrompt,[
            'rewrite','reword','rewrite the content','rewrite content','rewrite the copy','rewrite copy',
            'make the copy','shorten the copy','shorter copy','improve the copy','update the content',
            'update content','change the wording','tone of voice','make the text','make this copy',
        ]);
        $contentOnlyIntent=$contentRewriteIntent && !$explicitMediaMutation && !$universalBackgroundIntent
            && !Str::contains($mutationPrompt,[
                'redesign','change the layout','different layout','change layout','replace section',
                'change the design','different design','turn this section','transform this section',
            ]);

        $designGlobalIntent=Str::contains(Str::lower((string)$validated['prompt']),[
            'all sections','every section','all headings','every heading','all h1','all h2','all h3','all h4','all h5','all h6',
            'all buttons','every button','all cards','every card','all images','every image','all forms','every form',
            'whole page','entire page','everywhere','sitewide','site-wide','whole website','entire website','global',
            'brand color','brand colour','primary color','primary colour','color family','colour family',
            'all heading colors','all heading colours','website rounding','site rounding'
        ]);
        $artDirectionIntent=$this->lunaArtDirectionIntent((string)$validated['prompt']);
        // Relative art direction on a selected section stays local.
        if($artDirectionIntent && $scope!=='section')$designGlobalIntent=true;
        $siteMemory=$this->lunaUpdatedSiteMemory((string)$validated['prompt'],$siteMemory,is_array($theme??null)?$theme:[]);
        // Whole-page requests that explicitly name a section (hero/services/etc.)
        // get a deterministic section index before capability/Tailwind planning.
        // This lets Luna use only that Spark's rendered Tailwind inventory.
        if($scope==='page'){
            $namedPageTargetIndex=$this->lunaNamedBlockTargetIndex((string)$validated['prompt'],$blocks);
            if($namedPageTargetIndex!==null)$targetIndex=$namedPageTargetIndex;
        }


        // If the user explicitly names a different section, that name overrides
        // the currently selected section. This keeps "change the hero..." reliable
        // even when the chat was opened from FAQ, Contact, Services, etc.
        $namedTargetOverrodeSelection=false;
        if($scope==='section'){
            $originalSelectedIndex=$targetIndex;
            $namedTargets=[
                'hero'=>['hero','banner','masthead'],
                'services'=>['services','service'],
                'testimonials'=>['testimonial','reviews','review'],
                'pricing'=>['pricing','price','plans'],
                'faq'=>['faq','questions'],
                'contact'=>['contact','enquiry','inquiry'],
                'cta'=>['cta','call to action'],
                'gallery'=>['gallery','portfolio','projects','work'],
                'process'=>['process','steps','timeline'],
                'team'=>['team','people','staff'],
                'about'=>['about','story'],
            ];
            $requestedNamedTarget=null;
            foreach($namedTargets as $name=>$aliases){
                foreach($aliases as $alias){
                    if(preg_match('/\b'.preg_quote($alias,'/').'\b/i',$prompt)){
                        $requestedNamedTarget=$name;
                        break 2;
                    }
                }
            }
            if($requestedNamedTarget!==null){
                foreach(array_values($blocks) as $candidateIndex=>$candidateBlock){
                    $candidateType=Str::lower((string)($candidateBlock['type']??''));
                    $candidateHeading=Str::lower((string)($candidateBlock['heading']??$candidateBlock['title']??$candidateBlock['eyebrow']??''));
                    $candidateMeta=SparkCatalog::find((string)($candidateBlock['type']??''))??[];
                    $candidateCategory=Str::lower((string)($candidateMeta['category']??''));
                    $haystack=$candidateType.' '.$candidateHeading.' '.$candidateCategory;
                    $matches=match($requestedNamedTarget){
                        'hero'=>Str::contains($haystack,['hero','banner','mini heroes']),
                        'services'=>Str::contains($haystack,['services','service']),
                        'testimonials'=>Str::contains($haystack,['testimonial','review']),
                        'pricing'=>Str::contains($haystack,['pricing','price']),
                        'faq'=>Str::contains($haystack,'faq'),
                        'contact'=>Str::contains($haystack,['contact','location']),
                        'cta'=>Str::contains($haystack,'cta'),
                        'gallery'=>Str::contains($haystack,['gallery','portfolio','case studies','projects']),
                        'process'=>Str::contains($haystack,['process','proof','timeline']),
                        'team'=>Str::contains($haystack,'team'),
                        'about'=>Str::contains($haystack,'about'),
                        default=>false,
                    };
                    if($matches){
                        $targetIndex=$candidateIndex;
                        $namedTargetOverrodeSelection=($candidateIndex!==$originalSelectedIndex);
                        break;
                    }
                }
            }
        }

        $overlayOn=Str::contains($lower,['float header','floating header','overlay header','header over hero','header over banner','transparent header']);
        $overlayOff=Str::contains($lower,['disable overlay','turn off overlay','remove overlay','solid header','header above banner','header outside banner']);
        if($overlayOn||$overlayOff){
            $cost=10; $trialCredits->ensureCanSpend($trial,$cost,'This Luna change');
            $header=is_array($header)?$header:[];
            $header['overlay_header_on_banner']=$overlayOn&&!$overlayOff;
            $balance=$trialCredits->consume($trial,$cost,'luna_header',['scope'=>'trial']);
            $reply=$natural->compose($prompt,[
                'authenticated'=>false,
                'trial'=>true,
                'scope'=>$scope,
            ],[
                'action'=>'header overlay change',
                'action_completed'=>true,
                'overlay_enabled'=>$header['overlay_header_on_banner'],
                'credits_used'=>$cost,
            ]);
            return response()->json(['reply'=>$reply,'blocks'=>$blocks,'header'=>$header,'footer'=>$footer?:[],'page_style'=>$header['overlay_header_on_banner']?'balanced':null,'credit_cost'=>$cost,'credit_balance'=>$balance]);
        }

        if($scope==='page' && count($blocks)===0){
            $designPlan=$lunaPages->planDetailed($prompt);
            $sections=is_array($designPlan['sections']??null)?array_values($designPlan['sections']):[];
            if($sections===[]) throw ValidationException::withMessages(['prompt'=>'Luna could not prepare a valid page composition.']);
            $lockedPlan=[
                'theme'=>(string)($designPlan['theme']??''),'template_key'=>(string)($designPlan['template_key']??''),
                'industry'=>(string)($designPlan['industry']??'general'),'design_direction'=>(string)($designPlan['design_direction']??''),
                'media_direction'=>(string)($designPlan['media_direction']??''),'sections'=>$sections,
            ];
            $contentPrompt=$prompt."\n\nLOCKED DESIGN/COMPOSITION JSON:\n".json_encode($lockedPlan,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\nGenerate content only for these selected registered sections. Do not redesign.";
            $generated=$lunaPages->generate($contentPrompt,$sections);
            try{$remote=$pageGeneration->applyStartPageRemoteImages($contentPrompt,$generated);if(is_array($remote['blocks']??null))$generated=$remote['blocks'];}catch(\Throwable $e){report($e);}
            $verification=$executionVerification->verify([['action'=>'build_page']],count($generated)>0?[['action'=>'build_page','count'=>count($generated),'verified'=>true]]:[],count($generated)>0);
            $cost=40; $trialCredits->ensureCanSpend($trial,$cost,'Building this page');
            $balance=($verification['can_claim_complete']??false)?$trialCredits->consume($trial,$cost,'luna_build_page',['scope'=>'trial']):$trialCredits->balance($trial);
            $reply=$trialNaturalReplyFromFacts(['action'=>'build page','action_completed'=>(bool)($verification['can_claim_complete']??false),'generated_sections'=>count($generated),'credits_used'=>($verification['can_claim_complete']??false)?$cost:0,'execution_verification'=>$verification]);
            return response()->json([
                'reply'=>$reply,'blocks'=>array_values($generated),'header'=>$header?:[],'footer'=>$footer?:[],
                'design_plan'=>$lockedPlan,'execution_verification'=>$verification,'canonical_intent'=>$canonicalIntent,
                'execution_phases'=>['Thinking','Planning','Designing','Building','Checking'],
                'credit_cost'=>($verification['can_claim_complete']??false)?$cost:0,'credit_balance'=>$balance,
                'applied_operations'=>$verification['verified_operations']??[],
            ]);
        }

        // Ordinary edits load only the selected Spark's capability contract.
        // The 329-entry catalog is lazy and appears only for structural work.
        $usable=[];
        $summary=collect($blocks)->values()->map(fn($b,$i)=>['index'=>$i,'type'=>$b['type']??'','heading'=>$b['heading']??$b['title']??''])->all();
        $selected=$scope==='section'&&isset($blocks[$targetIndex])?$blocks[$targetIndex]:[];

        // Normalize counted repeaters for Luna. Older saved Bento blocks only contain
        // the original five service fields; expose the extension slots without
        // changing the persisted block until the user actually requests CRUD.
        if(is_array($selected) && in_array(($selected['type']??''),['services_bento_premium','services_editorial_premium','services_showcase_premium','services_minimal_luxury','services_contrast_premium','services_split_premium','services_grid_premium','services_feature_premium'],true)){
            $selected=array_merge([
                'service_count'=>(int)($selected['service_count']??5),
                'service_six_number'=>'06','service_six_title'=>'','service_six_text'=>'',
                'service_seven_number'=>'07','service_seven_title'=>'','service_seven_text'=>'',
            ],$selected);
        }

        $elementContext=json_decode((string)($validated['element_context']??'{}'),true);
        if(!is_array($elementContext))$elementContext=[];
        $builderConversation=$this->lunaBuilderConversation((string)($validated['conversation']??''));
        if(!isset($elementContext['itemIndex']) && isset($validated['target_item_index'])){
            $elementContext['itemIndex']=(int)$validated['target_item_index'];
        }
        if(!isset($elementContext['collectionKey']) && !empty($validated['target_collection_key'])){
            $elementContext['collectionKey']=(string)$validated['target_collection_key'];
        }
        if($namedTargetOverrodeSelection){
            // Clear stale clicked-element targeting when a named page section (for example
            // "hero") overrides the previous selection, but preserve the request-scoped
            // Tailwind inventory collected for that newly resolved Spark. Without this,
            // Whole Page visual edits reach the planner with zero legal rendered slots and
            // verification correctly reports no website-state change.
            $tailwindContext=array_intersect_key($elementContext,[
                'tailwindInventory'=>true,
                'tailwind_inventory'=>true,
                'tailwindPageInventory'=>true,
                'tailwindTargetIndex'=>true,
                'tailwindTargetName'=>true,
                'tailwindSlot'=>true,
                'tailwind_slot'=>true,
            ]);
            $elementContext=$tailwindContext;
        }
        if($elementContext && $selected){
            $elementContext['matched_paths']=$smartSparkEditing->resolveMatchedPaths($selected,$elementContext,$prompt);
        }
        $editCapabilityPayload=$sparkEditCapabilities->payloadForRequest(
            $prompt,
            is_string($selected['type']??null)?$selected['type']:null,
            is_array($selected)?$selected:[],
            $elementContext,
            is_array($canonicalIntent)?$canonicalIntent:[],
        );
        $usable=is_array($editCapabilityPayload['catalog']??null)?$editCapabilityPayload['catalog']:[];
        if($scope==='page' && is_array($elementContext['tailwindPageInventory']??null)){
            $pageInventory=$elementContext['tailwindPageInventory'];
            $resolvedInventory=$pageInventory[$targetIndex]??$pageInventory[(string)$targetIndex]??null;
            if(is_array($resolvedInventory)) $elementContext['tailwindInventory']=$resolvedInventory;
        }
        $tailwindEditContext=$selected ? $tailwindMutations->plannerContext($selected,$elementContext) : [];

        $system='You are Luna, the invisible website editor. Return JSON only: {"operations":[{"action":"edit|replace|insert_before|insert_after|delete|move|theme","index":0,"to_index":0,"spark_key":"registered key when needed","theme_key":"","changes":{},"tailwind_mutations":[{"slot":"rendered slot id","add":["Tailwind utility"],"remove":["conflicting current utility"]}],"instruction":""}],"header_changes":{},"footer_changes":{},"brand_color_family":null,"page_style":null}. Use ONLY Spark keys from the supplied catalog. Rank candidates by requested aliases/media/capabilities FIRST, selected-section semantic intent/category SECOND, then layout/style/industry/position fit. Never default to Hero merely because Hero also supports the requested media; preserve the selected section role unless the user explicitly asks to change it. For simple edits use edit and only existing schema keys. REPEATER/LIST CRUD IS NON-STRUCTURAL:
For the compatible premium Services family (services_bento_premium, services_editorial_premium, services_showcase_premium, services_minimal_luxury, services_contrast_premium, services_split_premium, services_grid_premium, services_feature_premium), treat featured_* as item 1 and service_two_* through service_seven_* as items 2–7. service_count controls visible services. "Add N services" MUST use edit, increase service_count by N up to 7, and fill the newly exposed sequential service_*_title/text/number fields with relevant content. "Remove the least important service" MUST use edit, compact the remaining service fields in order, decrement service_count, and preserve the layout. requests to add, remove, update, rename, expand, reduce, or reorder services/cards/items/testimonials/FAQs/team/pricing/features/logos/gallery/process/list entries MUST use edit on the existing selected section and preserve its current Spark/layout. Update the existing array/repeater key from SELECTED using the full resulting array; do not replace the section unless the user explicitly asks for a different layout/design/type. STRUCTURAL COMMANDS ARE REAL ACTIONS: "change/turn this banner or section into a slider/video/testimonials/etc" MUST use replace on the selected index with the closest matching registered Spark; never simulate a structural change with copy edits. "move this section to the top/first" MUST use move with to_index=0. "move to bottom/last" MUST use move with to_index equal to the last page index. "move up/down" must use move. "add above/below" must use insert_before/insert_after. Section scope may replace, move, delete, or edit the selected section and may insert immediately above/below it. page_style may be balanced|clean|premium only when explicitly requested. header_changes and footer_changes may change shell state when explicitly requested. FOOTER CONTRACT: footer_changes may update logo metadata, copyright, privacy/terms labels+URLs, contact {email,phone,address}, social_links [{label,url}], mega_enabled, and mega_footer {enabled,theme,tagline,primary_label,primary_url,columns:[{title,items:[{label,url}]}]}. Preserve unrelated footer fields. Footer columns max 4, items max 6 each, social links max 6. Footer-only requests must not mutate header or body sections. HEADER NAVIGATION CONTRACT: header_changes.menu is the complete resulting menu array. Each item is {"label":"...","url":"...","children":[...]}; children may nest to 3 levels total. For add/remove/rename/reorder/submenu requests, preserve unrelated items and return the complete updated menu. Header navigation is always plain dropdown navigation; do not create or enable mega menus. Never invent a page URL when the user did not provide one; use an existing matching menu/page target or "#". Header manual-equivalent navigation structure changes are valid shell changes. In page scope, resolve natural section names (hero, banner, services, testimonials, pricing, FAQ, contact, CTA, gallery, process, team, about) from PAGE headings/types. When ELEMENT TARGET is non-empty, treat it as the exact clicked element. Interpret relative design language naturally: a little/slightly means a modest change; more/bigger/roomier means increase from current state; less/smaller/tighter means decrease. References such as "like the hero above", "same as Services", "match the section below", or "similar to the previous section" mean use that existing section as the visual reference while preserving the target section content/role. Never claim a change is complete unless an operation actually changes website state; the server verifies before/after state. MULTI-STEP REQUESTS: when the user asks for several compatible changes in one message, plan all of them in order rather than completing only the first. Use SITE DESIGN MEMORY as a consistency guide, not as permission to override an explicit current request. PAGE ART DIRECTION: requests such as make this page more premium/polished/modern may make coordinated restrained changes across multiple sections while preserving content and semantic section roles. For ordinary content/style requests, edit only matching fields indicated by matched_paths/currentValue/url and preserve the rest of the section. Explicit section transformation/reorder requests override element-only targeting. Never claim a structural change unless you emitted the corresponding operation. Never mention Sparks/templates/schemas to the user. Do not invent image URLs. COLOR FAMILY DESIGN: when the user supplies an explicit HEX and asks to make/change the website theme, brand, colors, or color family around it, keep that exact HEX as `brand_color_family.primary` and design a tasteful premium semantic family around it. Return `brand_color_family` with this schema: {"sourceColor":"#RRGGBB","primary":"#RRGGBB","primaryHover":"#RRGGBB","primarySoft":"#RRGGBB","secondary":"#RRGGBB","accent":"#RRGGBB","background":"#RRGGBB","surface":"#RRGGBB","surfaceMuted":"#RRGGBB","heading":"#RRGGBB","text":"#RRGGBB","muted":"#RRGGBB","border":"#RRGGBB","buttonPrimary":"#RRGGBB","buttonText":"#RRGGBB","buttonSecondary":"#RRGGBB","buttonSecondaryText":"#RRGGBB","success":"#RRGGBB","warning":"#RRGGBB","error":"#RRGGBB","onPrimary":"#RRGGBB","onDark":"#RRGGBB","gradient":{"from":"#RRGGBB","via":"#RRGGBB","to":"#RRGGBB","glow":"#RRGGBB","angle":125}}. Aim for premium restraint: harmonious surfaces, readable body text, meaningful accent separation, and no random rainbow palette. For a DARK supplied HEX, the exact primary may own the hero and headings with contrast-safe light foregrounds. For a LIGHT supplied HEX, preserve the exact HEX as the brand/CTA anchor but use a derived dark secondary for headings and a dark hero so light buttons remain clearly visible. The server enforces one of these two patterns and may repair unsafe palette values.  DOCUMENTATION GROUNDING: You receive a LUNA KNOWLEDGE PACKET containing relevant canonical Cosmic CMS documentation and capability records. Product/capability claims MUST be grounded in that packet. If a capability is unsupported, planned, or limited, say so accurately and offer the documented fallback. If the packet does not establish support, do not invent support. A capability question is informational: return a useful natural reply and NO operations/header/footer/theme/page-style mutation. Server runtime guards remain authoritative. THEME INTELLIGENCE: choose a theme only when the user explicitly asks for a theme/color-family change or when a first-build planner specifically requests one. If the user directly asks to change/switch the theme without naming a family, choose one suitable different color family now and emit a theme operation; do not ask which theme or ask for confirmation. Never choose midnight as a generic/default theme; use midnight only when the user explicitly asks for midnight/night styling. For vague style directions that are not explicit theme-change requests, preserve the current site theme and redesign within that family. REQUEST INTELLIGENCE: distinguish content edits from structural redesigns. Text/image/link/name/label changes edit the existing Spark. Add/remove/reorder list or card items edits the repeater. Requests for another layout, redesign, slider, video hero, split, grid, mosaic, testimonial style, or different section type are structural and may replace with the closest registered Spark. Global typography/spacing/background requests are handled by the design-token router; section-specific requests should remain local. Header overlay/logo/nav requests belong to the global header, not the body Spark. If a request contains multiple compatible actions, complete all applicable actions in order. ';
        $system="INTERNAL TARGET/CHANGE PLANNER. Intermediate output is JSON only. Never return reply, message, response, suggestion, question, confirmation copy, or any user-facing text.\n".$system;
        $system.="\nLAZY SPARK CONTRACT: For mode=selected_spark, edit only the selected Spark through its declared modules and do not emit structural operations. For mode=structural_catalog, structural operations may use only supplied catalog keys.\nTAILWIND MUTATION CONTRACT: For specific visual styling requests, prefer operation.tailwind_mutations using only slot ids in TAILWIND EDIT CONTEXT.rendered_slots. Resolve natural target words through each rendered slot's semantic aliases. Plurals/groups such as buttons/CTAs mean mutate every matching button/cta slot in the resolved section; singular primary/secondary button means only that matching slot. Preserve unrelated classes. Remove conflicting current utilities before adding replacements. Never emit raw CSS or rewrite markup. Content edits remain in changes.";
        if($resumePendingPlan && is_array($resumePendingPlan['plan']??null)){
            $plan=$resumePendingPlan['plan'];
        }else{
            $apiKey=(string)config('openai.api_key'); abort_if($apiKey==='',503,'Luna is temporarily unavailable.');
            $response=Http::withToken($apiKey)->connectTimeout(30)->timeout(150)->post(rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions',[
                'model'=>env('OPENAI_MODEL','gpt-5-mini'),'response_format'=>['type'=>'json_object'],
                'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>"SCOPE: {$scope}\nTARGET: {$targetIndex}\n{$scopeContract}\n{$smartEditContract}\n{$siteDnaContract}\nREQUEST: {$prompt}\nPAGE: ".json_encode($summary)."\nCURRENT HEADER: ".json_encode(is_array($header)?$header:[])."\nCURRENT FOOTER: ".json_encode(is_array($footer)?$footer:[])."\nSELECTED: ".json_encode($selected)."\nELEMENT TARGET: ".json_encode($elementContext)."\nSPARK EDIT CAPABILITY: ".json_encode($editCapabilityPayload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\nTAILWIND EDIT CONTEXT: ".json_encode($tailwindEditContext,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\nSITE DESIGN MEMORY: ".json_encode($siteMemory)."\nLUNA KNOWLEDGE PACKET: ".json_encode($knowledgePacket,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\nCATALOG: ".json_encode($usable)]],
            ])->throw()->json();
            $plan=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
        }
        if(!is_array($plan)) {
            throw ValidationException::withMessages(['prompt'=>'Luna returned an invalid grounded response.']);
        }
        if(
            $capabilityQuestion
            || (
                !($feasibility['execution_allowed']??false)
                && !(($canonicalIntent['intent']??'')==='action' && ($canonicalIntent['execution_allowed']??false))
            )
        ){
            return response()->json([
                'reply'=>$trialNaturalReplyFromFacts([
                    'action_completed'=>false,
                    'conversation_mode'=>'feasibility_first',
                    'feasibility'=>$feasibility,
                    'constraint'=>'No website mutation is allowed from this request in the current turn.',
                    'fallback'=>$feasibility['alternative']??'Explain the documented limit naturally.',
                    'next_step'=>($feasibility['feasibility']??'')==='alternative_available'
                        ? 'Offer the documented alternative and ask whether the user wants that alternative.'
                        : 'Answer naturally from canonical documentation without claiming a change.',
                ]),
                'blocks'=>$blocks,
                'header'=>$header,
                'footer'=>$footer,
                'credit_cost'=>0,
                'credit_balance'=>$trialCredits->balance($trial),
                'site_memory'=>$siteMemory,
                'mode'=>'grounded_info',
                'grounded_capabilities'=>array_values(array_filter(array_map(fn($cap)=>$cap['id']??null,$knowledgePacket['capabilities']??[]))),
                'applied_operations'=>[],
            ]);
        }
        $explicitBrandPrimary=$this->lunaExplicitBrandColor($prompt);
        $ops=array_values(array_slice(is_array($plan['operations']??null)?$plan['operations']:[],0,12));
        if($explicitBrandPrimary){
            $ops=array_values(array_filter($ops,fn($op)=>!(is_array($op)&&($op['action']??'')==='theme')));
        } elseif($requestedThemeKey=$this->lunaRequestedThemeKey($prompt)) {
            $ops=array_values(array_filter($ops,fn($op)=>!(is_array($op)&&($op['action']??'')==='theme')));
            $ops[]=['action'=>'theme','theme_key'=>$requestedThemeKey];
        }

        // Deterministic structural correction: natural-language positioning commands
        // must become real move operations even if the planner under-specifies them.
        if($scope==='section' && isset($blocks[$targetIndex])){
            $moveTo=null;
            if(Str::contains($lower,['top of the page','top of page','first section','move it to the top','move this to the top','move to top'])) $moveTo=0;
            elseif(Str::contains($lower,['bottom of the page','bottom of page','last section','move it to the bottom','move this to the bottom','move to bottom'])) $moveTo=max(0,count($blocks)-1);
            elseif(Str::contains($lower,['move up','one section up','move it up'])) $moveTo=max(0,$targetIndex-1);
            elseif(Str::contains($lower,['move down','one section down','move it down'])) $moveTo=min(max(0,count($blocks)-1),$targetIndex+1);
            if($moveTo!==null){
                $ops=array_values(array_filter($ops,fn($op)=>!(is_array($op)&&($op['action']??'')==='move')));
                $ops[]=['action'=>'move','index'=>$targetIndex,'to_index'=>$moveTo];
            }

            $transformRequested=Str::contains($lower,['change this','turn this','convert this','replace this','make this','change the banner','change banner','change the hero','change hero']);
            $desiredKind=null;
            foreach(['slider','video','testimonial','gallery','pricing','faq','services','contact'] as $kind){
                if(Str::contains($lower,$kind)){$desiredKind=$kind;break;}
            }
            $hasReplace=collect($ops)->contains(fn($op)=>is_array($op)&&($op['action']??'')==='replace'&&(int)($op['index']??-1)===$targetIndex);
            if($transformRequested && $desiredKind && !$hasReplace){
                $currentType=Str::lower((string)($blocks[$targetIndex]['type']??''));
                $currentMeta=SparkCatalog::find((string)($blocks[$targetIndex]['type']??''))??[];
                $currentCategory=Str::lower((string)($currentMeta['category']??''));
                $currentIntent=collect($currentMeta['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $isOpening=$targetIndex===0 || Str::contains($currentType,['hero','banner']);
                $candidate=collect($usable)
                    ->map(function($spark) use($desiredKind,$currentCategory,$currentIntent,$isOpening){
                        $aliases=collect($spark['aliases']??[])->map(fn($v)=>Str::lower((string)$v))->implode(' ');
                        $caps=collect($spark['capabilities']??[])->map(fn($v)=>Str::lower((string)$v))->implode(' ');
                        $haystack=Str::lower(($spark['key']??'').' '.($spark['name']??'').' '.($spark['description']??'').' '.$aliases.' '.($spark['media']??'').' '.$caps);
                        $matches=Str::contains($haystack,$desiredKind)
                            || ($desiredKind==='slider' && (($spark['media']??'')==='slider' || Str::contains($caps,'supports-slider')));
                        if(!$matches) return null;
                        $score=0;
                        if(Str::lower((string)($spark['media']??''))===$desiredKind) $score+=120;
                        if(Str::contains($aliases,$desiredKind)) $score+=100;
                        if(Str::contains($caps,'supports-'.$desiredKind)) $score+=90;
                        if($currentCategory!=='' && Str::lower((string)($spark['category']??''))===$currentCategory) $score+=70;
                        $sparkIntent=collect($spark['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $score+=count(array_intersect($currentIntent,$sparkIntent))*35;
                        $positions=collect($spark['position_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        if($isOpening && (in_array('top',$positions,true)||in_array('opening-section-safe',$spark['capabilities']??[],true))) $score+=35;
                        if(!$isOpening && Str::lower((string)($spark['category']??''))==='hero') $score-=80;
                        if(Str::contains(Str::lower((string)($spark['name']??'')),'premium')) $score+=5;
                        $spark['_luna_score']=$score;
                        return $spark;
                    })->filter()->sortByDesc('_luna_score')->first();
                if($desiredKind==='video' && Str::contains($lower,['video background','background video'])){
                    $backgroundVideoCandidate=collect($usable)->first(fn($spark)=>is_array($spark) && ($spark['key']??'')==='hero_video_background');
                    if(is_array($backgroundVideoCandidate)) $candidate=$backgroundVideoCandidate;
                }
                if(is_array($candidate) && !empty($candidate['key'])){
                    $ops[]=[
                        'action'=>'replace',
                        'index'=>$targetIndex,
                        'spark_key'=>$candidate['key'],
                        'instruction'=>"Transform the selected section into a {$desiredKind} while preserving relevant existing content and brand direction.",
                    ];
                }
            }
        }

        // Generic section redesign must result in a materially different Spark.
        // Do not trust a model-proposed same-Spark replace/edit as a redesign:
        // deterministically pick a compatible registered alternative, exclude the
        // current Spark, prefer a different layout family, and preserve site theme.
        if($scope==='section' && isset($blocks[$targetIndex])){
            $genericRedesign=Str::contains($requestLower,[
                'redesign this section','redesign the section','redesign this','another layout','different layout',
                'new layout','make this section better','make this better','improve this section',
                'make this section more premium','make this more premium','make this section modern',
                'make this more modern','make this section polished','make this more polished',
                'refresh this section','rework this section','restyle this section'
            ]);

            if($genericRedesign){
                $current=(array)$blocks[$targetIndex];
                $currentKey=(string)($current['type']??'');
                $currentMeta=SparkCatalog::find($currentKey)??[];
                $category=Str::lower((string)($currentMeta['category']??''));
                $intent=collect($currentMeta['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $industry=collect($currentMeta['industry_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $position=collect($currentMeta['position_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $currentLayouts=collect($currentMeta['layout']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $schemaKeys=collect(array_keys(SchemaManager::map()))->flip();

                $ranked=collect($usable)
                    ->filter(fn($spark)=>is_array($spark)
                        && !empty($spark['key'])
                        && ($spark['key']??'')!==$currentKey
                        && $schemaKeys->has((string)($spark['key']??'')))
                    ->map(function($spark) use($category,$intent,$industry,$position,$currentLayouts,$requestLower){
                        $score=0;
                        $sparkCategory=Str::lower((string)($spark['category']??''));

                        // Semantic role is the strongest redesign constraint.
                        if($category!=='' && $sparkCategory===$category) $score+=260;
                        elseif($category!=='' && in_array($sparkCategory,['hero','header','footer'],true)) $score-=300;
                        else $score-=80;

                        $sparkIntent=collect($spark['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $sparkIndustry=collect($spark['industry_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $sparkPosition=collect($spark['position_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $sparkLayouts=collect($spark['layout']??[])->map(fn($v)=>Str::lower((string)$v))->all();

                        $score+=count(array_intersect($intent,$sparkIntent))*45;
                        $score+=count(array_intersect($industry,$sparkIndustry))*12;
                        $score+=count(array_intersect($position,$sparkPosition))*8;

                        // A redesign should be visually/materially different.
                        $layoutOverlap=count(array_intersect($currentLayouts,$sparkLayouts));
                        if($currentLayouts!==[] && $sparkLayouts!==[] && $layoutOverlap===0) $score+=120;
                        elseif($layoutOverlap>0) $score-=45;

                        $style=collect($spark['style']??[])->map(fn($v)=>Str::lower((string)$v))->implode(' ');
                        if(Str::contains($requestLower,'premium') && Str::contains($style,['premium','editorial','cinematic','luxury'])) $score+=55;
                        if(Str::contains($requestLower,'modern') && Str::contains($style,['modern','clean','editorial','minimal'])) $score+=38;
                        if(Str::contains($requestLower,'visually interesting') && Str::contains($style,['premium','editorial','bold','modern'])) $score+=30;
                        if(Str::contains($requestLower,'polished') && Str::contains($style,['premium','clean','editorial'])) $score+=28;

                        // Stable tie-break, while category/layout remain dominant.
                        $score+=(abs(crc32(($spark['key']??'').'|'.$requestLower))%19);
                        $spark['_luna_redesign_score']=$score;
                        return $spark;
                    })
                    ->sortByDesc('_luna_redesign_score')
                    ->values();

                // Prefer the same semantic category. Only fall back to another category
                // when the catalog truly has no registered alternative for this role.
                $candidate=null;
                if($category==='services'){
                    // Route explicit design language to the matching premium Services family.
                    // This prevents generic fixed-order fallback from repeatedly choosing
                    // Editorial/Showcase regardless of what the user actually asked for.
                    $semanticServiceKey=null;
                    if(Str::contains($requestLower,['contrast','high-contrast','high contrast','bold','performance-focused','performance focused','dark'])){
                        $semanticServiceKey='services_contrast_premium';
                    } elseif(Str::contains($requestLower,['minimal luxury','quiet luxury','minimal and luxurious','minimal','luxurious','whitespace-heavy','whitespace heavy'])){
                        $semanticServiceKey='services_minimal_luxury';
                    } elseif(Str::contains($requestLower,['editorial','magazine','asymmetric','asymmetrical'])){
                        $semanticServiceKey='services_editorial_premium';
                    } elseif(Str::contains($requestLower,['split layout','split layouts','alternating split','alternating','split service','split services'])){
                        $semanticServiceKey='services_split_premium';
                    } elseif(Str::contains($requestLower,['3-column','3 column','three-column','three column','grid premium','service grid','services grid'])){
                        $semanticServiceKey='services_grid_premium';
                    } elseif(Str::contains($requestLower,['featured service','feature one service','one featured service','prominently featured','supporting service cards','supporting cards'])){
                        $semanticServiceKey='services_feature_premium';
                    } elseif(Str::contains($requestLower,['large imagery','large image','large automotive image','more visual','image-led','image led','showcase'])){
                        $semanticServiceKey='services_showcase_premium';
                    } elseif(Str::contains($requestLower,['bento','varied card sizes','varied cards'])){
                        $semanticServiceKey='services_bento_premium';
                    }

                    if($semanticServiceKey!==null && $semanticServiceKey!==$currentKey){
                        $match=$ranked->first(fn($spark)=>(string)($spark['key']??'')===$semanticServiceKey);
                        if(is_array($match) && ($match['_luna_redesign_score']??0)>0){
                            $candidate=$match;
                        }
                    }

                    // Generic "more premium/better" requests choose the highest-ranked
                    // materially different Services design rather than a hard-coded first item.
                    if($candidate===null){
                        $premiumFamily=[
                            'services_bento_premium',
                            'services_editorial_premium',
                            'services_showcase_premium',
                            'services_minimal_luxury',
                            'services_contrast_premium',
                            'services_split_premium',
                            'services_grid_premium',
                            'services_feature_premium',
                        ];
                        $candidate=$ranked->first(fn($spark)=>
                            in_array((string)($spark['key']??''),$premiumFamily,true)
                            && (string)($spark['key']??'')!==$currentKey
                            && ($spark['_luna_redesign_score']??0)>0
                        );
                    }
                }
                $candidate=$candidate
                    ?? $ranked->first(fn($spark)=>
                        Str::lower((string)($spark['category']??''))===$category
                        && ($spark['_luna_redesign_score']??0)>0
                    )
                    ?? $ranked->first(fn($spark)=>($spark['_luna_redesign_score']??0)>0);

                if(is_array($candidate) && !empty($candidate['key'])){
                    // Override target redesign operations from the model. This prevents
                    // same-Spark replacements or no-op edits from blocking the fallback.
                    $ops=array_values(array_filter($ops,function($op) use($targetIndex){
                        if(!is_array($op)) return true;
                        if((int)($op['index']??-1)!==$targetIndex) return true;
                        return !in_array(($op['action']??''),['edit','replace'],true);
                    }));

                    $ops[]=[
                        'action'=>'replace',
                        'index'=>$targetIndex,
                        'spark_key'=>(string)$candidate['key'],
                        'instruction'=>'This is a verified redesign. Preserve the selected section purpose, useful copy, service/item meaning, CTA intent, and current website theme, but migrate them into this materially different layout. Do not reuse the old Spark/layout.',
                        'redesign_from'=>$currentKey,
                    ];
                }
            }
        }

        if(
            $resumePendingPlan
            && is_array($resumePendingPlan['operations']??null)
            && array_values($resumePendingPlan['operations'])!==[]
        ){
            $ops=array_values($resumePendingPlan['operations']);
        }

        // Nested router Batch 4: Spark schema edits do not depend on the legacy
        // planner producing a micro-edit operation. API 3 already locked the target.
        $nestedSparkTarget=data_get($canonicalIntent,'routing.spark_target');
        if(data_get($canonicalIntent,'routing.menu_scope')==='sparks' && is_array($nestedSparkTarget)){
            $nestedIndex=(int)($nestedSparkTarget['index']??-1);
            if($nestedIndex>=0 && isset($blocks[$nestedIndex])){
                $ops=[['action'=>'edit','index'=>$nestedIndex,'changes'=>[]]];
                $scope='section';
                $targetIndex=$nestedIndex;
            }
        }

        $pricing=$lunaPricing->estimate($prompt,$ops,$scope);
        $cost=(int)($pricing['credits']??0);
        // Batch 2 direct execution: trial build/update actions execute immediately.
        // Only destructive delete keeps a confirmation gate.
        $needsLargeConfirmation=!$resumePendingPlan
            && (($canonicalIntent['intent']??'')==='action')
            && (($canonicalIntent['action']??'')==='delete');

        if($needsLargeConfirmation && !($validated['confirmed']??false)){
            // Destructive safety confirmation is UI/state only — no AI credit charge.
            $planningCost=0;
            $balance=$trialCredits->balance($trial);

            $blocksFingerprint=hash('sha256',json_encode(array_values($blocks),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
            $pendingToken=$pendingActions->put($pendingActor,[
                'kind'=>'trial_destructive_delete',
                'prompt'=>$prompt,
                'scope'=>$scope,
                'target_index'=>$targetIndex,
                'plan'=>$plan,
                'operations'=>$ops,
                'canonical_schema'=>$canonicalIntent,
                'blocks_fingerprint'=>$blocksFingerprint,
                'estimated_execution_cost'=>$cost,
                'trial_id'=>$trial->id,
            ]);

            $reply=$natural->compose($prompt,[
                'authenticated'=>false,
                'trial'=>true,
                'scope'=>$scope,
                'canonical_knowledge'=>$knowledgePacket,
            ],[
                'action_completed'=>false,
                'confirmation_required'=>true,
                'pending_action_stored'=>true,
                'pending_action_token'=>$pendingToken,
                'planned_operations'=>$ops,
                'estimated_execution_cost'=>$cost,
                'planning_credits_used'=>$planningCost,
                'next_step'=>'Ask for an explicit deletion confirmation using the Delete button. This safety confirmation costs 0 credits.',
                'rule'=>'Do not claim the website changed. Ask only whether to delete the selected content. Never say Proceed, Continue, Shall I proceed, or expose implementation details.',
            ]);
            return response()->json([
                'reply'=>$reply,
                'mode'=>'confirm',
                'pending_action'=>true,
                'pending_action_token'=>$pendingToken,
                'canonical_intent'=>$canonicalIntent,
                'confirmation_cost'=>$cost,
                'credit_cost'=>$planningCost,
                'credit_balance'=>$balance,
            'site_memory'=>$siteMemory,
            ]);
        }
        if($cost>0)$trialCredits->ensureCanSpend($trial,$cost,'This Luna change');

        $keys=collect($usable)->pluck('key')->flip(); $next=array_values($blocks); $fullSchemaEdited=false; $beforeFingerprint=hash('sha256',json_encode($next,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:''); $applied=[];
        foreach($ops as $op){
            if(!is_array($op))continue;$action=(string)($op['action']??'');$i=(int)($op['index']??-1);
            if($scope==='section'&&!in_array($action,['insert_before','insert_after'],true)&&$i!==$targetIndex)continue;
            if($action==='edit'&&isset($next[$i])&&(is_array($op['changes']??null)||is_array($op['tailwind_mutations']??null))){
                $changes=is_array($op['changes']??null)?$op['changes']:[];unset($changes['type'],$changes['_renderKey']);

                // API 4 full-schema editor owns Spark edits in the new nested route.
                // Run it once for the exact API-3-selected Spark and bypass the
                // legacy micro-mutation/repeater path when it returns a valid schema.
                $sparkTarget=data_get($canonicalIntent,'routing.spark_target');
                if(!$fullSchemaEdited && data_get($canonicalIntent,'routing.menu_scope')==='sparks'
                    && is_array($sparkTarget) && (int)($sparkTarget['index']??-1)===$i){
                    $schemaResult=$sparkSchemaEditor->edit($prompt,$next[$i],$sparkTarget,$elementContext,$builderConversation);
                    if(($schemaResult['ok']??false)===true){
                        $next[$i]=$schemaResult['block'];
                        $fullSchemaEdited=true;
                        if(($schemaResult['changed']??false)===true){
                            $applied[]=[
                                'action'=>'spark_full_schema_edit',
                                'index'=>$i,
                                'spark_type'=>$sparkTarget['type'],
                                'diff'=>$schemaResult['diff']??[],
                                'before_fingerprint'=>$schemaResult['before_fingerprint']??null,
                                'after_fingerprint'=>$schemaResult['after_fingerprint']??null,
                                'verified'=>true,
                            ];
                        }
                        continue;
                    }
                    Log::warning('Luna full Spark schema edit rejected',[
                        'trial_id'=>$trial->id??null,
                        'spark_type'=>$next[$i]['type']??null,
                        'index'=>$i,
                        'reason'=>$schemaResult['reason']??'unknown',
                        'details'=>array_diff_key($schemaResult,['block'=>true,'editable'=>true,'tailwind'=>true]),
                    ]);
                    // Fail closed for the nested Sparks path. Never fall back to
                    // the old alias/micro-mutation engine after API 4 rejects.
                    $fullSchemaEdited=true;
                    continue;
                }

                $tailwindResult=$tailwindMutations->apply($next[$i],is_array($op['tailwind_mutations']??null)?$op['tailwind_mutations']:[],$elementContext);
                if(($tailwindResult['applied']??[])!==[]){
                    $next[$i]=$tailwindResult['block'];
                    $applied[]=['action'=>'tailwind_edit','index'=>$i,'mutations'=>$tailwindResult['applied'],'verified'=>true];
                }
                if(($tailwindResult['rejected']??[])!==[]){
                    Log::warning('Luna Tailwind mutation rejected', [
                        'website_id'=>$website->id ?? null,
                        'page_id'=>$page->id ?? null,
                        'spark_type'=>$next[$i]['type'] ?? null,
                        'index'=>$i,
                        'rejected'=>$tailwindResult['rejected'],
                        'requested_mutations'=>$op['tailwind_mutations'] ?? [],
                        'rendered_slots'=>array_values(array_filter(array_map(
                            fn($row)=>is_array($row)?($row['slot']??null):null,
                            is_array($elementContext['tailwindInventory']??null)?$elementContext['tailwindInventory']:[]
                        ))),
                    ]);
                }
                if($changes===[]) continue;
                $repeaterResult=$smartSparkEditing->applyRepeaterIntent($prompt,$next[$i],$elementContext);
                if(is_array($repeaterResult) && ($repeaterResult['action']??'')!=='repeater_remove_unresolved'){
                    $next[$i]=$repeaterResult['block'];
                    $applied[]=['action'=>$repeaterResult['action'],'index'=>$i,'collection'=>$repeaterResult['collection']??null,'item_index'=>$repeaterResult['item_index']??null,'verified'=>true];
                    continue;
                }
                $sanitized=$smartSparkEditing->sanitizeEdit($next[$i],$changes,$elementContext);
                $next[$i]=$sanitized['block'];
                if(($sanitized['paths']??[])!==[])$applied[]=['action'=>'edit','index'=>$i,'mode'=>$sanitized['mode']??'block','paths'=>$sanitized['paths'],'verified'=>true];
                continue;
            }
            if(in_array($action,['replace','insert_before','insert_after'],true)){
                $key=(string)($op['spark_key']??'');if(!$keys->has($key))continue;
                $reference=$next[$i]??[];
                $groundedMediaPrompt=$this->lunaGroundedMediaPrompt($prompt,$next,$siteMemory,is_array($reference)?$reference:[]);
                $gen=$lunaPages->generate($groundedMediaPrompt."\n".(string)($op['instruction']??''),[$key]);$block=$gen[0]??null;if(!is_array($block))continue;
                try{$remote=$pageGeneration->applyStartPageRemoteImages($groundedMediaPrompt,[$block]);if(is_array($remote['blocks'][0]??null))$block=$remote['blocks'][0];}catch(\Throwable $e){report($e);}
                $block['type']=$key;
                try{
                    $videoBlocks=$lunaVideos->apply($prompt,[$block]);
                    if(is_array($videoBlocks[0]??null)) $block=$videoBlocks[0];
                }catch(\Throwable $e){report($e);}
                $block['_renderKey']='luna-'.Str::lower(Str::random(10));
                if($action==='replace'&&isset($next[$i]))$next[$i]=$block;elseif($action==='insert_before')array_splice($next,max(0,$i),0,[$block]);else array_splice($next,max(0,$i+1),0,[$block]);
                $applied[]=['action'=>$action,'index'=>$i];continue;
            }
            if($action==='delete'&&isset($next[$i])){array_splice($next,$i,1);$applied[]=['action'=>'delete','index'=>$i];}
            if($action==='move'&&isset($next[$i])){$to=max(0,min(count($next)-1,(int)($op['to_index']??$i)));$moving=$next[$i];array_splice($next,$i,1);array_splice($next,$to,0,[$moving]);$applied[]=['action'=>'move','index'=>$i,'to_index'=>$to];continue;}
            if($action==='theme'){
                $allowedThemes=['midnight','emerald','coffee','rose','ocean','indigo','amber','charcoal','violet','teal','ruby','forest','obsidian','navy','espresso','terracotta','asphalt'];
                $key=(string)($op['theme_key']??'');
                if(in_array($key,$allowedThemes,true)){$applied[]=['action'=>'theme','theme_key'=>$key];}
            }
        }
        $designApplied=false;
        $designChanges=[];
        $designPrompt=(string)$validated['prompt'];
        $designTargets=$designGlobalIntent ? array_keys($next) : (($scope==='section'&&isset($next[$targetIndex]))?[$targetIndex]:[]);
        foreach($designTargets as $designIndex){
            if(!isset($next[$designIndex])||!is_array($next[$designIndex]))continue;
            $currentDesign=is_array($next[$designIndex]['luna_design_overrides']??null)?$next[$designIndex]['luna_design_overrides']:[];
            $currentComponents=is_array($next[$designIndex]['luna_component_overrides']??null)?$next[$designIndex]['luna_component_overrides']:[];
            $componentChanges=$this->lunaComponentDesignIntent($designPrompt,$currentComponents);
            if($componentChanges!==$currentComponents){
                $next[$designIndex]['luna_component_overrides']=$componentChanges;
                $designChanges['components']=$componentChanges;
                $designApplied=true;
            }
            $referenceIndex=$this->lunaReferenceSectionIndex($designPrompt,$next,(int)$designIndex);
            $reference=$referenceIndex!==null&&isset($next[$referenceIndex])?$next[$referenceIndex]:[];
            $changes=$this->lunaRelativeDesignIntent($designPrompt,$currentDesign,$reference);
            if(empty($changes))continue;
            $qa=$this->lunaDesignQa(array_merge($currentDesign,$changes));
            $next[$designIndex]['luna_design_overrides']=$qa['overrides'];
            $designChanges=array_merge($designChanges,$changes);
            $designQaNotes=array_values(array_unique(array_merge($designQaNotes??[],$qa['notes'])));
            $designApplied=true;
        }
        if($designApplied){
            $applied[]=['action'=>'design_overrides','scope'=>$designGlobalIntent?'page':'section','changes'=>$designChanges,'qa_notes'=>$designQaNotes??[],'verified'=>true];
        }

        $backgroundApplied=false;
        $backgroundError=null;
        if($scope==='section' && $universalBackgroundIntent && isset($next[$targetIndex])){
            $resolvedState=$this->universalBackgroundState(
                $namedTargetOverrodeSelection ? '' : (string)($validated['target_resolved_theme']??''),
                $next[$targetIndex],
                $targetIndex
            );
            $strength=$backgroundDarker?'darker':($backgroundLighter?'lighter':null);
            $result=$this->applyUniversalBackgroundIntent(
                $pageGeneration,
                $prompt,
                $next[$targetIndex],
                $resolvedState,
                $backgroundRemove,
                $strength
            );
            $next[$targetIndex]=$result['block'];
            $backgroundApplied=(bool)$result['success'];
            $backgroundError=$result['error']??null;
            if($backgroundApplied){
                $applied[]=[
                    'action'=>$backgroundRemove?'remove_universal_background':'universal_background',
                    'index'=>$targetIndex,
                    'state'=>$resolvedState,
                    'strength'=>$strength?:'balanced',
                ];
            }
        }

        $brandPrimary=$explicitBrandPrimary ?: $this->lunaExplicitBrandColor($designPrompt);
        $brandColorFamily=$brandPrimary
            ? $this->lunaValidateBrandColorFamily($brandPrimary,$plan['brand_color_family']??null)
            : null;
        $brandPattern=null;
        if($brandColorFamily){
            $aiPaletteProposed=is_array($plan['brand_color_family']??null);
            $brandPattern=$this->lunaApplyBrandVisualPattern($next,is_array($header)?$header:[],$brandColorFamily);
            $next=$brandPattern['blocks'];
            $header=$brandPattern['header'];
            $applied[]=[
                'action'=>'brand_color_family',
                'scope'=>'site',
                'primary'=>$brandPrimary,
                'mode'=>$brandPattern['mode'],
                'source'=>$aiPaletteProposed?'luna_designed_validated':'deterministic_fallback',
                'verified'=>true
            ];
            if($brandPattern['hero_changed']) $applied[]=['action'=>'brand_hero_contrast','index'=>$brandPattern['hero_index'],'mode'=>$brandPattern['mode'],'verified'=>true];
            if($brandPattern['overlay_changed']) $applied[]=['action'=>'header_overlay','scope'=>'header','enabled'=>true,'verified'=>true];
        }

        $trialThemeKey=collect($applied)->first(fn($item)=>($item['action']??'')==='theme')['theme_key']??null;
        $trialPageStyle=in_array(Str::lower((string)($plan['page_style']??'')),['balanced','clean','premium'],true)
            ? Str::lower((string)$plan['page_style']) : null;
        $shellChanged=!empty($plan['header_changes']??[])||!empty($plan['footer_changes']??[])||$trialPageStyle!==null||$trialThemeKey!==null||$brandColorFamily!==null;
        $afterFingerprint=hash('sha256',json_encode(array_values($next),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
        $blocksActuallyChanged=!hash_equals($beforeFingerprint,$afterFingerprint);
        if(!$blocksActuallyChanged){
            $applied=array_values(array_filter($applied,fn($item)=>in_array(($item['action']??''),['theme'],true)));
        }
        $verifiedSomething=$blocksActuallyChanged||$shellChanged;
        $executionVerificationResult=$executionVerification->verify($ops,$applied,$verifiedSomething);
        $siteMemory=$contextState->rememberVerified($siteMemory,$canonicalIntent,$executionVerificationResult,$applied,$routeContext??[],['blocks_fingerprint'=>$beforeFingerprint],['blocks_fingerprint'=>$afterFingerprint]);
        $siteMemory=$siteDna->updateAfterExecution(
            $siteMemory,$trialDna,$executionVerificationResult,$scopeResolution,
            ['primary'=>$trialThemeKey,'custom_brand_theme'=>$brandColorFamily],
            ['page_style'=>$trialPageStyle]
        );
        $chargedCost=$verifiedSomething ? $cost : 0;
        $balance=$chargedCost>0
            ? $trialCredits->consume($trial,$chargedCost,'luna_change',['scope'=>$scope,'operations'=>$applied])
            : $trialCredits->balance($trial);
        $reply='';
        if(($executionVerificationResult['status']??'failed')==='partial'){
            $reply=$trialNaturalReplyFromFacts([
                'action_completed'=>false,
                'execution_status'=>'partial',
                'verified_operations'=>$executionVerificationResult['verified_operations']??[],
                'unverified_operations'=>$executionVerificationResult['unverified_operations']??[],
                'visual_qa'=>$postQaResult ? ['score'=>$postQaResult['score']??null,'grade'=>$postQaResult['grade']??null,'pass'=>$postQaResult['pass']??false,'summary'=>$postQaResult['summary']??[]] : null,
                'constraint'=>'Some requested changes were verified, but at least one planned operation did not complete. Do not say Done or imply full completion.',
            ]);
        } elseif(($executionVerificationResult['status']??'failed')==='failed' && $verifiedSomething){
            $reply=$trialNaturalReplyFromFacts([
                'action_completed'=>false,
                'execution_status'=>'failed',
                'verified_operations'=>$executionVerificationResult['verified_operations']??[],
                'unverified_operations'=>$executionVerificationResult['unverified_operations']??[],
                'visual_qa'=>$postQaResult ? ['score'=>$postQaResult['score']??null,'grade'=>$postQaResult['grade']??null,'pass'=>$postQaResult['pass']??false,'summary'=>$postQaResult['summary']??[]] : null,
                'constraint'=>'The requested plan could not be fully verified. Report what failed instead of claiming completion.',
            ]);
        } elseif($universalBackgroundIntent && !$backgroundApplied){
            $reply=$trialNaturalReplyFromFacts([
                'action'=>'background image change',
                'action_completed'=>false,
                'error'=>$backgroundError?:'The image provider did not return a usable background.',
                'verified_operations'=>$applied,
            ]);
        } elseif(!$verifiedSomething){
            $reply=$trialNaturalReplyFromFacts([
                'action_completed'=>false,
                'verified_operations'=>$applied,
                'constraint'=>'The server could not verify a real website-state change.',
            ]);
        } elseif($reply===''){
            $reply=$trialNaturalReplyFromFacts([
                'action_completed'=>true,
                'verified_operations'=>$applied,
                'theme_changed'=>$trialThemeKey!==null || $brandColorFamily!==null,
                'page_style_changed'=>$trialPageStyle!==null,
            ]);
        }
        $verifiedTargetReply=$smartSparkEditing->verifiedTargetReply(
            $elementContext,
            $prompt,
            (string)($executionVerificationResult['status']??'failed')
        );
        if($verifiedTargetReply!==null)$reply=$verifiedTargetReply;
        $trialSafeHeader=$this->lunaSafeHeaderChanges(is_array($header)?$header:[],is_array($plan['header_changes']??null)?$plan['header_changes']:[]);
        if($brandColorFamily){
            $trialSafeHeader=$this->lunaApplyBrandVisualPattern($next,$trialSafeHeader,$brandColorFamily)['header'];
        }
        return response()->json([
            'reply'=>$reply,
            'blocks'=>$next,
            'brand_color_family'=>$brandColorFamily,
            'header'=>$trialSafeHeader,
            'brand_theme_mode'=>$brandPattern['mode']??null,
            'footer'=>$this->lunaSafeFooterChanges(is_array($footer)?$footer:[],is_array($plan['footer_changes']??null)?$plan['footer_changes']:[]),
            'theme_key'=>$trialThemeKey,
            'page_style'=>$trialPageStyle,
            'credit_cost'=>$chargedCost,
            'credit_balance'=>$balance,
            'site_memory'=>$siteMemory,
            'applied_operations'=>$applied,
            'execution_verification'=>$executionVerificationResult,
            'canonical_intent'=>$canonicalIntent,
            'execution_phases'=>['Thinking','Planning','Designing','Building','Checking'],
        ]);
    }

    /**
     * Deterministic selected-section reordering.
     * Handles absolute, adjacent, and named relative moves without an AI/API call.
     */
    private function lunaSectionReorderAction(
        string $prompt,
        string $scope,
        int $targetIndex,
        array $blocks
    ): ?array {
        if($scope!=='section' || !isset($blocks[$targetIndex]) || count($blocks)<2) return null;

        $lower=Str::lower(trim($prompt));
        $moveIntent=Str::contains($lower,['move this section','move this','move it','move section']);
        if(!$moveIntent) return null;

        $count=count($blocks);
        $toIndex=null;
        $relativeTarget=null;
        $relation=null;

        if(Str::contains($lower,['top of the page','top of page','first section','move it to the top','move this to the top','move to top'])){
            $toIndex=0;
        }elseif(Str::contains($lower,['bottom of the page','bottom of page','last section','move it to the bottom','move this to the bottom','move to bottom'])){
            $toIndex=$count-1;
        }elseif(Str::contains($lower,['one section up','move it up','move this section up','move this up'])){
            $toIndex=max(0,$targetIndex-1);
        }elseif(Str::contains($lower,['one section down','move it down','move this section down','move this down'])){
            $toIndex=min($count-1,$targetIndex+1);
        }else{
            if(Str::contains($lower,[' above ',' before '])) $relation='before';
            elseif(Str::contains($lower,[' below ',' after '])) $relation='after';
            if(!$relation) return null;

            $aliases=[
                'hero'=>['hero','banner','masthead'],
                'services'=>['services','service section','our services'],
                'testimonials'=>['testimonials','testimonial','reviews','customer feedback','feedback'],
                'pricing'=>['pricing','price','plans'],
                'faq'=>['faq','faqs','questions'],
                'contact'=>['contact','contact us','enquiry','inquiry'],
                'cta'=>['cta','call to action'],
                'gallery'=>['gallery','portfolio','projects','work'],
                'process'=>['process','steps','timeline'],
                'team'=>['team','our team','people','staff'],
                'about'=>['about','our story','story'],
                'stats'=>['stats','statistics','numbers','at a glance','why choose us','practical difference'],
            ];

            $requestedCategory=null;
            foreach($aliases as $category=>$names){
                foreach($names as $name){
                    if(preg_match('/\b'.preg_quote($name,'/').'\b/i',$lower)){
                        $requestedCategory=$category;
                        break 2;
                    }
                }
            }
            if(!$requestedCategory) return null;

            foreach($blocks as $index=>$block){
                if($index===$targetIndex || !is_array($block)) continue;
                $type=(string)($block['type']??'');
                $meta=SparkCatalog::find($type)??[];
                $category=Str::lower((string)($meta['category']??''));
                $haystack=Str::lower(implode(' ',array_filter([
                    $type,
                    $category,
                    (string)($meta['name']??''),
                    (string)($block['heading']??''),
                    (string)($block['title']??''),
                    (string)($block['eyebrow']??''),
                ])));

                $matches=$category===$requestedCategory;
                if(!$matches){
                    foreach($aliases[$requestedCategory] as $name){
                        if(Str::contains($haystack,Str::lower($name))){
                            $matches=true;
                            break;
                        }
                    }
                }
                if($matches){
                    $relativeTarget=$index;
                    break;
                }
            }
            if($relativeTarget===null) return [
                'reply'=>"I couldn't find the requested {$requestedCategory} section on this page.",
                'blocks'=>$blocks,
                'changed'=>false,
                'applied_operations'=>[],
            ];

            // Existing move executor removes the selected item first, then inserts at
            // to_index. Convert original indexes to that post-removal coordinate space.
            if($relation==='before'){
                $toIndex=$targetIndex<$relativeTarget ? $relativeTarget-1 : $relativeTarget;
            }else{
                $toIndex=$targetIndex<$relativeTarget ? $relativeTarget : $relativeTarget+1;
            }
            $toIndex=max(0,min($count-1,$toIndex));
        }

        if($toIndex===null || $toIndex===$targetIndex){
            return [
                'reply'=>'This section is already in that position.',
                'blocks'=>$blocks,
                'changed'=>false,
                'applied_operations'=>[],
            ];
        }

        $next=array_values($blocks);
        $moving=$next[$targetIndex];
        array_splice($next,$targetIndex,1);
        $insertAt=max(0,min(count($next),$toIndex));
        array_splice($next,$insertAt,0,[$moving]);

        $reply=$relativeTarget!==null
            ? 'Moved this section '.($relation==='before'?'above':'below').' the requested section.'
            : 'Moved this section to the requested position.';

        return [
            'reply'=>$reply,
            'blocks'=>array_values($next),
            'changed'=>true,
            'applied_operations'=>[[
                'action'=>'move',
                'index'=>$targetIndex,
                'to_index'=>$insertAt,
                'relative_to'=>$relativeTarget,
                'relation'=>$relation,
                'verified'=>true,
            ]],
        ];
    }

    /**
     * AI-assisted, deterministic CRUD for the counted Bento Services Spark.
     * AI chooses/generates semantic content; the server owns the structural mutation
     * and verifies count/field changes before any credits are charged.
     */
    private function lunaBentoServicesCrudAction(
        string $prompt,
        int $targetIndex,
        array $blocks
    ): ?array {
        if(!isset($blocks[$targetIndex]) || !is_array($blocks[$targetIndex])) return null;
        $block=$blocks[$targetIndex];
        if(!in_array(($block['type']??''),['services_bento_premium','services_editorial_premium','services_showcase_premium','services_minimal_luxury','services_contrast_premium','services_split_premium','services_grid_premium','services_feature_premium'],true)) return null;

        $lower=Str::lower(trim($prompt));
        $addIntent=Str::contains($lower,['add service','add services','add another service','add more service','more relevant service']);
        $removeIntent=Str::contains($lower,['remove service','remove the least important service','remove least important service','delete service','remove one service']);
        if(!$addIntent && !$removeIntent) return null;

        $count=max(1,min(7,(int)($block['service_count']??5)));
        $words=['one'=>1,'two'=>2,'three'=>3,'four'=>4,'five'=>5,'six'=>6,'seven'=>7];
        $requestedCount=1;
        if(preg_match('/\b([1-7])\b/',$lower,$m)) $requestedCount=(int)$m[1];
        else foreach($words as $word=>$number){
            if(preg_match('/\b'.preg_quote($word,'/').'\b/',$lower)){
                $requestedCount=$number; break;
            }
        }

        $slots=[
            1=>['number'=>'featured_number','title'=>'featured_title','text'=>'featured_text'],
            2=>['number'=>'service_two_number','title'=>'service_two_title','text'=>'service_two_text'],
            3=>['number'=>'service_three_number','title'=>'service_three_title','text'=>'service_three_text'],
            4=>['number'=>'service_four_number','title'=>'service_four_title','text'=>'service_four_text'],
            5=>['number'=>'service_five_number','title'=>'service_five_title','text'=>'service_five_text'],
            6=>['number'=>'service_six_number','title'=>'service_six_title','text'=>'service_six_text'],
            7=>['number'=>'service_seven_number','title'=>'service_seven_title','text'=>'service_seven_text'],
        ];
        $items=[];
        for($i=1;$i<=$count;$i++){
            $slot=$slots[$i];
            $items[]=[
                'title'=>trim((string)($block[$slot['title']]??'')),
                'text'=>trim((string)($block[$slot['text']]??'')),
            ];
        }

        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return [
            'error'=>'Luna is temporarily unavailable.',
            'changed'=>false,
            'ai_used'=>false,
        ];

        if($addIntent){
            $addCount=min($requestedCount,7-$count);
            if($addCount<1) return [
                'reply'=>'This section already has the maximum of 7 services.',
                'blocks'=>$blocks,
                'changed'=>false,
                'ai_used'=>false,
                'operation'=>'services_add',
                'count_before'=>$count,
                'count_after'=>$count,
            ];

            $existingTitles=array_values(array_filter(array_map(fn($item)=>$item['title'],$items)));
            try{
                $response=Http::withToken($apiKey)->connectTimeout(30)->timeout(120)->post(
                    rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions',
                    [
                        'model'=>env('OPENAI_MODEL','gpt-5-mini'),
                        'response_format'=>['type'=>'json_object'],
                        'messages'=>[
                            ['role'=>'system','content'=>'Return JSON only: {"services":[{"title":"concise service title","text":"one concise customer-facing sentence"}]}. Generate distinct, useful services that fit the business/page context. Do not duplicate existing services.'],
                            ['role'=>'user','content'=>"REQUEST: {$prompt}\nEXISTING SERVICES: ".json_encode($existingTitles)."\nSECTION HEADING: ".(string)($block['heading']??'')."\nGenerate exactly {$addCount} new relevant service(s)."],
                        ],
                    ]
                )->throw()->json();
                $payload=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
                $generated=array_values(array_filter(is_array($payload['services']??null)?$payload['services']:[],fn($item)=>is_array($item)&&trim((string)($item['title']??''))!==''));
            }catch(\Throwable $e){
                report($e);
                $generated=[];
            }
            if(count($generated)<$addCount) return [
                'reply'=>'I could not generate enough relevant services to make a verified change.',
                'blocks'=>$blocks,
                'changed'=>false,
                'ai_used'=>true,
                'operation'=>'services_add',
                'count_before'=>$count,
                'count_after'=>$count,
            ];

            $next=$blocks;
            $nextBlock=$block;
            for($j=0;$j<$addCount;$j++){
                $slotIndex=$count+$j+1;
                $slot=$slots[$slotIndex];
                $item=$generated[$j];
                $nextBlock[$slot['number']]=str_pad((string)$slotIndex,2,'0',STR_PAD_LEFT);
                $nextBlock[$slot['title']]=Str::limit(trim((string)$item['title']),80,'');
                $nextBlock[$slot['text']]=Str::limit(trim((string)($item['text']??'')),220,'');
            }
            $nextCount=$count+$addCount;
            $nextBlock['service_count']=$nextCount;
            $nextBlock['_renderKey']='luna-crud-'.Str::uuid();
            $next[$targetIndex]=$nextBlock;

            return [
                'reply'=>"Added {$addCount} relevant service".($addCount===1?'':'s')." to this section.",
                'blocks'=>$next,
                'changed'=>true,
                'ai_used'=>true,
                'operation'=>'services_add',
                'count_before'=>$count,
                'count_after'=>$nextCount,
            ];
        }

        if($removeIntent){
            if($count<=1) return [
                'reply'=>'This section needs at least one service, so I left it unchanged.',
                'blocks'=>$blocks,
                'changed'=>false,
                'ai_used'=>false,
                'operation'=>'services_remove',
                'count_before'=>$count,
                'count_after'=>$count,
            ];

            $removeIndex=$count; // safe deterministic fallback
            try{
                $response=Http::withToken($apiKey)->connectTimeout(30)->timeout(120)->post(
                    rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions',
                    [
                        'model'=>env('OPENAI_MODEL','gpt-5-mini'),
                        'response_format'=>['type'=>'json_object'],
                        'messages'=>[
                            ['role'=>'system','content'=>'Return JSON only: {"remove_index":1}. Choose the least important/redundant service for the business. Use a 1-based index and never remove a clearly core service when a weaker supporting service exists.'],
                            ['role'=>'user','content'=>"REQUEST: {$prompt}\nSERVICES: ".json_encode($items)],
                        ],
                    ]
                )->throw()->json();
                $payload=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
                $candidate=(int)($payload['remove_index']??0);
                if($candidate>=1 && $candidate<=$count) $removeIndex=$candidate;
            }catch(\Throwable $e){
                report($e);
            }

            array_splice($items,$removeIndex-1,1);
            $next=$blocks;
            $nextBlock=$block;
            $newCount=count($items);
            for($i=1;$i<=7;$i++){
                $slot=$slots[$i];
                if($i<=$newCount){
                    $item=$items[$i-1];
                    $nextBlock[$slot['number']]=str_pad((string)$i,2,'0',STR_PAD_LEFT);
                    $nextBlock[$slot['title']]=$item['title'];
                    $nextBlock[$slot['text']]=$item['text'];
                }else{
                    $nextBlock[$slot['number']]=str_pad((string)$i,2,'0',STR_PAD_LEFT);
                    $nextBlock[$slot['title']]='';
                    $nextBlock[$slot['text']]='';
                }
            }
            $nextBlock['service_count']=$newCount;
            $nextBlock['_renderKey']='luna-crud-'.Str::uuid();
            $next[$targetIndex]=$nextBlock;

            return [
                'reply'=>'Removed the least important service and rebalanced the section.',
                'blocks'=>$next,
                'changed'=>true,
                'ai_used'=>true,
                'operation'=>'services_remove',
                'removed_index'=>$removeIndex,
                'count_before'=>$count,
                'count_after'=>$newCount,
            ];
        }

        return null;
    }

    /**
     * Last-resort structural migration for Services redesigns.
     * This keeps Test 2 reliable even if the content-model response fails validation.
     * The user-requested AI redesign still goes through the AI path first; this fallback
     * only preserves/adapts existing service copy into the chosen registered layout.
     */
    private function lunaServiceRedesignFallback(array $source, string $sparkKey): ?array
    {
        $heading=(string)($source['heading']??$source['title']??'Our services');
        $eyebrow=(string)($source['eyebrow']??$source['tagline']??'Services');
        $text=(string)($source['text']??$source['description']??$source['subheading']??'');
        $primaryLabel=(string)($source['primary_label']??$source['button_label']??'Learn more');
        $primaryUrl=(string)($source['primary_url']??$source['button_url']??'#');

        $services=[];
        foreach([
            ['featured_title','featured_text'],
            ['service_two_title','service_two_text'],
            ['service_three_title','service_three_text'],
            ['service_four_title','service_four_text'],
            ['service_five_title','service_five_text'],
        ] as [$titleKey,$textKey]){
            $title=trim((string)($source[$titleKey]??''));
            $body=trim((string)($source[$textKey]??''));
            if($title!==''||$body!=='') $services[]=['title'=>$title?:'Service','text'=>$body];
        }
        foreach(['services','cards','items','features'] as $collectionKey){
            if($services!==[] || !is_array($source[$collectionKey]??null)) continue;
            foreach($source[$collectionKey] as $item){
                if(!is_array($item)) continue;
                $title=trim((string)($item['title']??$item['name']??$item['label']??''));
                $body=trim((string)($item['text']??$item['desc']??$item['description']??$item['summary']??''));
                if($title!==''||$body!=='') $services[]=['title'=>$title?:'Service','text'=>$body];
            }
        }
        if($services===[]){
            $services=[
                ['title'=>'Core service','text'=>$text],
                ['title'=>'Specialist support','text'=>''],
                ['title'=>'Ongoing care','text'=>''],
            ];
        }

        // Repeat only when a target schema needs more visible items; existing service
        // meaning is preserved rather than inventing unrelated services.
        $at=function(int $index) use($services){
            return $services[$index]??$services[$index%count($services)];
        };

        $base=['type'=>$sparkKey,'theme'=>'auto','eyebrow'=>$eyebrow,'heading'=>$heading,'text'=>$text,'primary_label'=>$primaryLabel,'primary_url'=>$primaryUrl];

        $compatiblePremiumServices=['services_bento_premium','services_editorial_premium','services_showcase_premium','services_minimal_luxury','services_contrast_premium','services_split_premium','services_grid_premium','services_feature_premium'];
        if(in_array($sparkKey,$compatiblePremiumServices,true)){
            $out=$base;
            $out['image_url']=(string)($source['image_url']??'');
            $out['featured_image_url']=(string)($source['featured_image_url']??$source['image_url']??'');
            $out['service_two_image_url']=(string)($source['service_two_image_url']??'');
            $out['service_three_image_url']=(string)($source['service_three_image_url']??'');
            $out['service_four_image_url']=(string)($source['service_four_image_url']??'');
            $out['service_five_image_url']=(string)($source['service_five_image_url']??'');
            $out['service_six_image_url']=(string)($source['service_six_image_url']??'');
            $out['service_seven_image_url']=(string)($source['service_seven_image_url']??'');
            $out['featured_meta']=(string)($source['featured_meta']??'');
            $out['proof_value']=(string)($source['proof_value']??'');
            $out['proof_label']=(string)($source['proof_label']??'');
            $words=['featured','two','three','four','five','six','seven'];
            $numberKeys=['featured_number','service_two_number','service_three_number','service_four_number','service_five_number','service_six_number','service_seven_number'];
            $titleKeys=['featured_title','service_two_title','service_three_title','service_four_title','service_five_title','service_six_title','service_seven_title'];
            $textKeys=['featured_text','service_two_text','service_three_text','service_four_text','service_five_text','service_six_text','service_seven_text'];
            for($i=0;$i<7;$i++){
                $item=$at($i);
                $out[$numberKeys[$i]]=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);
                $out[$titleKeys[$i]]=$item['title'];
                $out[$textKeys[$i]]=$item['text'];
            }
            $out['service_count']=max(1,min(7,(int)($source['service_count']??count($services)??5)));
            $out['_luna_redesign_fallback']=true;
            return $out;
        }

        if($sparkKey==='services_horizontal'){
            $out=$base;
            for($i=1;$i<=6;$i++){
                $item=$at($i-1);
                $word=['one','two','three','four','five','six'][$i-1];
                $out["service_{$word}_number"]=str_pad((string)$i,2,'0',STR_PAD_LEFT);
                $out["service_{$word}_title"]=$item['title'];
                $out["service_{$word}_text"]=$item['text'];
            }
            $out['_luna_redesign_fallback']=true;
            return $out;
        }

        if($sparkKey==='services_interactive_tabs'){
            $out=$base;
            foreach(['one','two','three','four'] as $i=>$word){
                $item=$at($i);
                $out["tab_{$word}_label"]=$item['title'];
                $out["tab_{$word}_title"]=$item['title'];
                $out["tab_{$word}_text"]=$item['text'];
            }
            $out['_luna_redesign_fallback']=true;
            return $out;
        }

        if($sparkKey==='services_hover_cards'){
            $out=$base;
            foreach(['one','two','three','four','five','six'] as $i=>$word){
                $item=$at($i);
                $out["card_{$word}_number"]=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);
                $out["card_{$word}_title"]=$item['title'];
                $out["card_{$word}_summary"]=$item['text'];
                $out["card_{$word}_text"]=$item['text'];
                $out["card_{$word}_link"]='Learn more';
            }
            $out['_luna_redesign_fallback']=true;
            return $out;
        }

        return null;
    }

    /**
     * Theme is sticky after a website has an established visual identity.
     * Luna may replace it only when the user clearly asks for a theme/brand/color change.
     */
    private function lunaExplicitThemeChangeIntent(string $prompt): bool
    {
        $q=Str::lower(trim($prompt));
        if($q==='') return false;

        if(Str::contains($q,[
            'change the theme','change theme','switch the theme','switch theme','new theme',
            'replace the theme','different theme','change the color scheme','change color scheme',
            'change the colour scheme','change colour scheme','new color scheme','new colour scheme',
            'change the palette','new palette','rebrand','re-brand','change the brand colors',
            'change brand colors','change the brand colours','change brand colours',
        ])) return true;

        if(
            preg_match('/\b(change|switch|apply|use|set|replace)\b/i',$q)
            && preg_match('/\b(theme|palette|colou?r scheme|brand colou?rs?)\b/i',$q)
        ) return true;

        $themeNames=['midnight','emerald','coffee','rose','dark','ocean','indigo','amber','charcoal','violet','teal','ruby','forest','obsidian','navy','espresso','terracotta','asphalt'];
        foreach($themeNames as $theme){
            if(preg_match('/\b(?:use|switch\s+to|change\s+to|make\s+(?:the\s+)?(?:theme|colors?|colours?|palette)\s+)'.preg_quote($theme,'/').'\b/i',$q)){
                return true;
            }
        }

        return false;
    }

    /** Resolve a requested built-in theme without relying on planner wording. */
    private function lunaRequestedThemeKey(string $prompt): ?string
    {
        if(!$this->lunaExplicitThemeChangeIntent($prompt)) return null;

        $themes=['midnight','emerald','coffee','rose','dark','ocean','indigo','amber','charcoal','violet','teal','ruby','forest','obsidian','navy','espresso','terracotta','asphalt'];
        $matches=[];
        foreach($themes as $theme){
            if(preg_match_all('/\b'.preg_quote($theme,'/').'\b/i',$prompt,$found,PREG_OFFSET_CAPTURE)){
                foreach($found[0] as $hit) $matches[]=['key'=>$theme,'offset'=>(int)$hit[1]];
            }
        }
        if($matches===[]) return null;

        usort($matches,fn($a,$b)=>$b['offset']<=>$a['offset']);
        return $matches[0]['key'];
    }

    private function lunaStandaloneNamedThemeKey(string $prompt): ?string
    {
        $key=$this->lunaRequestedThemeKey($prompt);
        if($key===null) return null;

        $q=Str::lower($prompt);
        if(Str::contains($q,[' and ',' also ',' plus ',' then ',' as well'])) return null;
        if(Str::contains($q,[
            'heading','copy','text','image','photo','logo','font','typography','spacing',
            'layout','section','button','navigation','header','footer','publish','delete',
        ])) return null;

        return $key;
    }

    /**
     * A generic "change the theme" request is executable work. When the user
     * has not named a family or supplied a HEX, choose a different safe family
     * deterministically instead of asking them to choose again.
     */
    private function lunaAutomaticThemeKey(string $prompt,array $theme=[],?string $industry=null): ?string
    {
        if(!$this->lunaExplicitThemeChangeIntent($prompt)) return null;
        if($this->lunaRequestedThemeKey($prompt)!==null) return null;
        if($this->lunaExplicitBrandColor($prompt)!==null) return null;

        $q=Str::lower($prompt);
        // Mixed requests belong to the normal planner; this helper is intentionally
        // only for a standalone site-wide theme/color-family switch.
        if(Str::contains($q,[' and ',' also ',' plus ',' then ',' as well'])) return null;
        if(Str::contains($q,[
            'heading','copy','text','image','photo','logo','font','typography','spacing',
            'layout','section','button','navigation','header','footer','publish','delete',
        ])) return null;

        $industry=Str::lower(trim((string)$industry));
        $families=match(true){
            Str::contains($industry,['finance','law','legal','real estate','technology','software']) => ['navy','indigo','ocean','charcoal'],
            Str::contains($industry,['restaurant','coffee','bakery','hotel','travel']) => ['terracotta','coffee','espresso','amber'],
            Str::contains($industry,['medical','dentist','health','cleaning']) => ['teal','ocean','emerald','navy'],
            Str::contains($industry,['construction','automotive','electric','plumbing']) => ['charcoal','navy','asphalt','amber'],
            Str::contains($industry,['salon','beauty','fashion','boutique']) => ['rose','violet','ruby','terracotta'],
            default => ['ocean','emerald','violet','navy','terracotta','amber','charcoal'],
        };

        $current=Str::lower(trim((string)($theme['primary']??$theme['theme_key']??$theme['key']??'')));
        foreach($families as $candidate){
            if($candidate!==$current) return $candidate;
        }
        return $families[0]??'ocean';
    }

    /** V5 targets are structured objects; retain compatibility with legacy string targets. */
    /**
     * Batch 2 V5 adapter: convert structured canonical operations into a compact
     * deterministic hint for existing core Builder executors. The original user
     * prompt is preserved, but schema values become authoritative enough that
     * executors no longer depend on exact user phrasing alone.
     */
    /**
     * Ephemeral Builder conversation supplied by the current browser tab.
     * Never persisted here; refresh naturally starts a new conversation.
     */
    private function lunaBuilderConversation(string $json): array
    {
        $decoded=json_decode($json,true);
        if(!is_array($decoded)) return [];

        $out=[];
        foreach($decoded as $turn){
            if(!is_array($turn)) continue;
            $role=(string)($turn['role']??'');
            if(!in_array($role,['user','assistant'],true)) continue;
            $content=trim((string)($turn['content']??$turn['text']??''));
            if($content==='') continue;
            $out[]=['role'=>$role,'content'=>Str::limit($content,6000,'')];
            if(count($out)>=120) break;
        }
        return $out;
    }

    private function lunaCoreActionExecutionPrompt(string $prompt, array $canonicalIntent): string
    {
        if (($canonicalIntent['intent'] ?? '') !== 'action') return $prompt;

        $ops = is_array($canonicalIntent['operations'] ?? null) ? $canonicalIntent['operations'] : [];
        if ($ops === []) {
            $ops = [[
                'domain' => $canonicalIntent['domain'] ?? null,
                'operation' => $canonicalIntent['operation'] ?? null,
                'scope' => $canonicalIntent['scope'] ?? null,
                'target' => $canonicalIntent['target'] ?? null,
                'changes' => $canonicalIntent['changes'] ?? [],
            ]];
        }

        $hints = [];
        foreach ($ops as $op) {
            if (!is_array($op)) continue;
            $domain = Str::lower(trim((string)($op['domain'] ?? '')));
            $operation = Str::lower(trim((string)($op['operation'] ?? 'update')));
            $leafOperation = Str::lower(trim((string)($op['leaf_operation'] ?? '')));
            $scope = Str::lower(trim((string)($op['scope'] ?? '')));
            $target = Str::lower(trim($this->lunaCanonicalTargetText($op['target'] ?? null)));
            $changes = is_array($op['changes'] ?? null) ? $op['changes'] : [];

            $parts = array_values(array_filter([$leafOperation, $operation, $target, $domain]));
            if ($scope === 'site' || $scope === 'global_token') $parts[] = 'across the site';
            elseif ($scope === 'section') $parts[] = 'this section';

            foreach ($changes as $key => $value) {
                $keyText = str_replace('_', ' ', (string)$key);
                if (is_scalar($value)) {
                    $parts[] = $keyText.' '.(string)$value;
                    continue;
                }
                if (!is_array($value)) continue;
                $flat = [];
                array_walk_recursive($value, function ($v, $k) use (&$flat) {
                    if (is_scalar($v)) $flat[] = str_replace('_', ' ', (string)$k).' '.(string)$v;
                });
                if ($flat !== []) $parts[] = $keyText.' '.implode(' ', $flat);
            }
            if ($parts !== []) $hints[] = implode(' ', $parts);
        }

        return trim($prompt."\n[V5 action hints: ".implode(' | ', $hints).']');
    }

    private function lunaCanonicalTargetText(mixed $target): string
    {
        if(is_string($target)) return trim($target);
        if(!is_array($target)) return '';
        $parts=[];
        foreach(['key','label','selector','field','level','role','tag','tagName','blockType','block_type','id'] as $key){
            if(isset($target[$key]) && is_scalar($target[$key]) && trim((string)$target[$key])!=='') $parts[]=trim((string)$target[$key]);
        }
        return implode(' ',array_values(array_unique($parts)));
    }

    /** Resolve only known, authorized Builder destinations. */
    private function lunaBuilderNavigationTarget(Website $website,array $canonicalIntent,string $prompt,?int $currentPageId=null): ?array
    {
        $target=Str::lower(trim($this->lunaCanonicalTargetText($canonicalIntent['target']??null)));
        $haystack=trim($target.' '.Str::lower($prompt));
        if(Str::contains($haystack,['dashboard','workspace'])) return ['url'=>route('dashboard'),'label'=>'Dashboard'];
        if(Str::contains($haystack,['media library','media-library','uploads'])) return ['url'=>route('media-library.index',['website'=>$website->id]),'label'=>'Media Library'];
        if(Str::contains($haystack,['inquiries','form submissions','messages'])) return ['url'=>route('websites.inquiries.index',['website'=>$website->id]),'label'=>'Inquiries'];
        $pages=$website->pages()->get(['id','title','slug']);
        $page=null;
        if(Str::contains($haystack,['homepage','home page']) || $target==='home') $page=$pages->first(fn($candidate)=>in_array(Str::lower((string)$candidate->slug),['home','homepage'],true));
        if(!$page && $target!==''){
            $page=$pages->first(function($candidate) use($target){
                $title=Str::lower((string)$candidate->title);$slug=Str::lower((string)$candidate->slug);
                return $target===$title || $target===$slug || Str::contains($target,[$title,$slug]);
            });
        }
        if(!$page){
            $page=$pages->first(function($candidate) use($haystack){
                $title=Str::lower((string)$candidate->title);$slug=Str::lower((string)$candidate->slug);
                return ($title!=='' && Str::contains($haystack,$title)) || ($slug!=='' && Str::contains($haystack,$slug));
            });
        }
        if(!$page && $currentPageId && Str::contains($haystack,['current page','this page','builder'])) $page=$pages->firstWhere('id',$currentPageId);
        if($page) return ['url'=>route('pages.builder',['page'=>$page->id]),'label'=>(string)$page->title];
        if(Str::contains($haystack,['pages','page list','all pages'])) return ['url'=>route('pages.index',['website'=>$website->id]),'label'=>'Pages'];
        return null;
    }

    public function pageChat(
        Request $request,
        Website $website,
        LunaCategoryPageService $lunaPages,
        PlanEntitlementService $entitlements,
        AiPageGenerationService $pageGeneration,
        LunaCreditPricingService $lunaPricing,
        CreditService $credits,
        LunaNaturalReplyService $natural,
        LunaPexelsVideoService $lunaVideos,
        LunaKnowledgeRouter $knowledge,
        LunaPendingActionService $pendingActions,
        LunaFeasibilityGate $feasibilityGate,
        LunaDesignContinuityService $designContinuity,
        LunaResponsiveIntelligenceService $responsive,
        LunaVisualQaService $visualQa,
        LunaScopeIntelligenceService $scopeIntelligence,
        LunaSmartSparkEditingService $smartSparkEditing,
        SparkEditCapabilityRegistry $sparkEditCapabilities,
        \App\Services\SparkEditCapabilityExecutor $sparkEditCapabilityExecutor,
        \App\Services\LunaTailwindMutationService $tailwindMutations,
        \App\Services\LunaSparkSchemaEditorService $sparkSchemaEditor,
        LunaRenderParityService $renderParity,
        LunaExecutionVerificationService $executionVerification,
        LunaSiteDesignDnaService $siteDna,
        LunaDesignCriticService $designCritic,
        LunaSelfCorrectionService $selfCorrection,
        LunaIntentGateway $intentGateway,
        \App\Services\LunaContextStateService $contextState,
        LunaInspectService $inspectService,
        LunaSiteAdminActionService $siteAdminActions,
        \App\Services\LunaContentCommerceActionService $contentCommerceActions
    ) {
        // Whole-page generation is a multi-stage operation and can legitimately
        // exceed PHP's default 60-second request limit. Each remote call still
        // has its own tighter timeout; this only prevents PHP from killing the
        // orchestration while a valid build is in progress.
        set_time_limit(600);

        $this->authorize('update', $website);

        $validated=$request->validate([
            'prompt'=>['required','string','max:6000'],
            'conversation'=>['nullable','string','max:180000'],
            'blocks'=>['required','string','max:350000'],
            'header'=>['nullable','string','max:80000'],
            'footer'=>['nullable','string','max:120000'],
            'theme'=>['nullable','string','max:12000'],
            'typography'=>['nullable','string','max:12000'],
            'background_style'=>['nullable','string','max:12000'],
            'section_layout'=>['nullable','string','max:12000'],
            'components'=>['nullable','string','max:12000'],
            'site_memory'=>['nullable','string','max:24000'],
            'target_scope'=>['nullable','in:page,section,header,footer'],
            'target_index'=>['nullable','integer','min:0','max:100'],
            'element_context'=>['nullable','string','max:30000'],
            'target_item_index'=>['nullable','integer','min:0','max:100'],
            'target_item_mode'=>['nullable','in:repeater_item'],
            'target_collection_key'=>['nullable','string','max:80'],
            'target_resolved_theme'=>['nullable','string','max:40'],
            'confirmed'=>['nullable','boolean'],
            'pending_action_token'=>['nullable','string','max:100'],
            'current_page_id'=>['nullable','integer','min:1'],
        ]);
        $originalUserPrompt=trim((string)$validated['prompt']);
        $user=$request->user();

        $blocks=json_decode($validated['blocks'],true);
        $header=json_decode((string)($validated['header']??'{}'),true);
        $footer=json_decode((string)($validated['footer']??'{}'),true);
        $theme=json_decode((string)($validated['theme']??'{}'),true);
        $typography=json_decode((string)($validated['typography']??'{}'),true);
        if(!is_array($typography))$typography=[];
        $backgroundStyle=json_decode((string)($validated['background_style']??'{}'),true);
        if(!is_array($backgroundStyle))$backgroundStyle=[];
        $sectionLayout=json_decode((string)($validated['section_layout']??'{}'),true);
        if(!is_array($sectionLayout))$sectionLayout=[];
        $components=json_decode((string)($validated['components']??'{}'),true);
        if(!is_array($components))$components=[];
        $elementContext=json_decode((string)($validated['element_context']??'{}'),true);
        if(!is_array($elementContext))$elementContext=[];
        $builderConversation=$this->lunaBuilderConversation((string)($validated['conversation']??''));
        if(!isset($elementContext['itemIndex']) && isset($validated['target_item_index'])){
            $elementContext['itemIndex']=(int)$validated['target_item_index'];
        }
        if(!isset($elementContext['collectionKey']) && !empty($validated['target_collection_key'])){
            $elementContext['collectionKey']=(string)$validated['target_collection_key'];
        }
        if(!is_array($blocks)) {
            throw ValidationException::withMessages(['blocks'=>'The current page could not be prepared for Luna.']);
        }
        $siteMemory=json_decode((string)($validated['site_memory']??'{}'),true);
        if(!is_array($siteMemory))$siteMemory=[];

        $scope=(string)($validated['target_scope']??'page');
        $targetIndex=$scope==='section' ? (int)($validated['target_index']??-1) : -1;
        $scopeResolution=$scopeIntelligence->resolve($originalUserPrompt,$scope,$elementContext);
        $scopeContract=$scopeIntelligence->plannerDirective($scopeResolution);
        // Hotfix: pageChat must initialize the smart-edit planner contract
        // before any conversational or mutation planning branch references it.
        $smartEditContract=$smartSparkEditing->contractDirective();
        $pendingActor='builder:'.($user?->id ?? 'guest').':website:'.$website->id;
        $resumePendingPlan=null;
        $stalePendingPlan=false;
        $orphanAffirmative=false;

        if($validated['confirmed']??false){
            $token=trim((string)($validated['pending_action_token']??''));
            $candidate=$token!=='' ? $pendingActions->consume($pendingActor,$token) : null;
            if(is_array($candidate) && ($candidate['kind']??'')==='builder_destructive_delete'){
                $currentFingerprint=hash('sha256',json_encode(array_values($blocks),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
                if(hash_equals((string)($candidate['blocks_fingerprint']??''),$currentFingerprint)){
                    $resumePendingPlan=$candidate;
                    $validated['prompt']=(string)$candidate['prompt'];
                    $validated['confirmed']=true;
                    $scope=(string)($candidate['scope']??$scope);
                    $targetIndex=$scope==='section' ? (int)($candidate['target_index']??$targetIndex) : -1;
                }else{
                    $stalePendingPlan=true;
                }
            }else{
                $orphanAffirmative=true;
            }
        }

        $prompt=$this->lunaResolveThemeFollowUp(trim((string)$validated['prompt']),$siteMemory);
        $validated['prompt']=$prompt;
        $scopeResolution=$scopeIntelligence->resolve($prompt,$scope,$elementContext);
        $scopeContract=$scopeIntelligence->plannerDirective($scopeResolution);

        // API 1 decides only chat/action. API 2 emits machine-only action JSON.
        if($resumePendingPlan && is_array($resumePendingPlan['canonical_schema']??null)){
            $canonicalIntent=$resumePendingPlan['canonical_schema'];
        }else{
            $routeContext=[
                'surface'=>'builder',
                'ui_scope'=>$scope,
                'target_index'=>$targetIndex,
                'has_blocks'=>count($blocks)>0,
                'page_sparks'=>array_values(array_map(
                    fn($block,$index)=>[
                        'index'=>$index,
                        'type'=>is_array($block)?(string)($block['type']??''):'',
                        'label'=>is_array($block)?Str::limit(trim((string)($block['heading']??$block['title']??$block['eyebrow']??$block['type']??'')),80,''):'',
                    ],
                    $blocks,
                    array_keys($blocks)
                )),
                'website_id'=>$website->id,
                'website_name'=>$website->name,
                'current_page_id'=>$validated['current_page_id']??null,
                'element_context'=>$elementContext,
                'conversation'=>$builderConversation,
            ];
            $siteMemory=$contextState->attach($siteMemory,$routeContext);
            $siteMemory=$contextState->invalidateForNavigation($siteMemory,$routeContext);
            $routeContext['context_state']=$contextState->routingContext($siteMemory,$routeContext);
            $intentRoute=$intentGateway->route($prompt,$siteMemory,$routeContext);
            $canonicalIntent=($intentRoute['intent']??'chat')==='action'
                ? $intentGateway->classifyAction($prompt,$siteMemory,$routeContext)
                : ['intent'=>'chat'];
        }

        // API 1 owns the chat | action boundary. Legacy structural detection may
        // refine an already-routed ACTION, but it must never promote CHAT into
        // ACTION behind the router's back.
        if(
            !$resumePendingPlan
            && (($canonicalIntent['intent']??'chat')==='action')
            && $sparkEditCapabilities->isStructuralRequest($prompt,is_array($canonicalIntent)?$canonicalIntent:[])
        ){
            $structuralLower=Str::lower($prompt);
            $structuralOperation=Str::contains($structuralLower,['move ','move this','reorder']) ? 'move'
                : (Str::contains($structuralLower,['add ','insert ','create ']) ? 'add'
                : (Str::contains($structuralLower,['remove ','delete ']) ? 'delete'
                : (Str::contains($structuralLower,['redesign','make this section better','improve this section','polish this section','refresh this section','rework this section','restyle this section']) ? 'redesign' : 'replace')));
            $canonicalIntent=array_merge(is_array($canonicalIntent)?$canonicalIntent:[],[
                'intent'=>'action',
                'execution_allowed'=>true,
                'domain'=>'section',
                'operation'=>$structuralOperation,
                'scope'=>'section',
            ]);
        }
        $siteMemory=$intentGateway->memory($siteMemory,$canonicalIntent);
        $siteMemory=$contextState->rememberRouted($siteMemory,$canonicalIntent);
        $coreActionPrompt=$this->lunaCoreActionExecutionPrompt($prompt,$canonicalIntent);
        $knowledgePacket=$knowledge->contextFor($prompt,$scope);
        $canonicalConversation=(($canonicalIntent['intent']??'chat')==='chat');
        $capabilityQuestion=!$resumePendingPlan
            && (($canonicalIntent['intent']??'chat')==='chat'
            && (
                ($canonicalIntent['chat_type']??'general')==='capability'
                || (($knowledgePacket['query_type']??'')==='capability_question')
            ));
        $feasibility=$resumePendingPlan
            ? ['feasibility'=>'supported','execution_allowed'=>true,'requires_confirmation'=>false,'informational'=>false]
            : $feasibilityGate->evaluate($prompt,$knowledgePacket,$scope);

        // Canonical intent owns the top-level execution gate.
        if($canonicalConversation){
            $feasibility['informational']=true;
            $feasibility['execution_allowed']=false;
            $feasibility['requires_confirmation']=false;
        } elseif(($canonicalIntent['intent']??'')==='action'){
            // Direct-action architecture: the canonical router owns chat vs action.
            // Legacy feasibility may describe limits/fallbacks, but it must not turn
            // a concrete build/update action back into a conversational proposal.
            $feasibility['informational']=false;
            $feasibility['execution_allowed']=(bool)($canonicalIntent['execution_allowed']??true);
            $feasibility['requires_confirmation']=(($canonicalIntent['action']??'')==='delete');
        } else {
            $feasibility['execution_allowed']=false;
            $feasibility['requires_confirmation']=false;
        }

        if(!$resumePendingPlan && $canonicalConversation){
            $reply=$natural->compose($prompt,[
                'authenticated'=>true,
                'scope'=>$scope,
                'website'=>$website->name,
                'canonical_knowledge'=>$knowledgePacket,
            ],[
                'canonical_intent'=>$canonicalIntent,
                'action_completed'=>false,
                'conversation_mode'=>'chat_docs_only',
                'credit_cost'=>0,
                'rule'=>'This is a chat turn. Answer naturally and concisely from canonical documentation and verified context, then stop. Do not ask the user to Proceed/Continue. Do not imply work has started. Use customer-facing website language only; never expose Sparks, templates, schemas, planner internals, API mechanics, or other implementation details unless explicitly asked about Cosmic internals.',
            ]);
            return response()->json([
                'reply'=>$reply,
                'mode'=>'grounded_info',
                'canonical_intent'=>$canonicalIntent,
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'site_memory'=>$siteMemory,
                'pending_action'=>false,
                'applied_operations'=>[],
            ]);
        }

        if(!$resumePendingPlan && ($canonicalIntent['action']??'')==='inspect'){
            $inspectFacts=$inspectService->inspect($prompt,[
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme'=>is_array($theme)?$theme:[],
                'typography'=>$typography,
                'section_layout'=>$sectionLayout,
                'components'=>$components,
                'scope'=>$scope,
                'target_index'=>$targetIndex,
            ]);
            if(($canonicalIntent['domain']??'')==='qa'){
                $qaFacts=$visualQa->audit(
                    $prompt,
                    array_values(array_map(fn($block)=>is_array($block)?(string)($block['type']??'unknown'):'unknown',$blocks)),
                    $blocks,
                    [
                        'typography'=>$typography,
                        'section_layout'=>$sectionLayout,
                        'components'=>$components,
                    ]
                );
                $inspectFacts['qa']=[
                    'score'=>$qaFacts['score']??null,
                    'grade'=>$qaFacts['grade']??null,
                    'pass'=>$qaFacts['pass']??false,
                    'summary'=>$qaFacts['summary']??[],
                    'findings'=>$qaFacts['findings']??[],
                    'auto_mutated'=>false,
                    'verified'=>true,
                ];
            }
            $reply=$natural->compose($prompt,[
                'authenticated'=>true,
                'scope'=>$scope,
                'website'=>$website->name,
                'canonical_knowledge'=>$knowledgePacket,
            ],[
                'canonical_intent'=>$canonicalIntent,
                'action_completed'=>true,
                'inspection'=>$inspectFacts,
                'credit_cost'=>0,
                'rule'=>'Answer only from the verified read-only inspection facts. Do not claim a mutation. Use customer-facing website language and keep the answer concise.',
            ]);
            return response()->json([
                'reply'=>$reply,
                'mode'=>'inspect',
                'canonical_intent'=>$canonicalIntent,
                'inspection'=>$inspectFacts,
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'site_memory'=>$siteMemory,
                'pending_action'=>false,
                'applied_operations'=>[],
            ]);
        }

        if(
            !$resumePendingPlan
            && ($canonicalIntent['needs_clarification']??false)
            && !(($canonicalIntent['intent']??'')==='action' && ($canonicalIntent['execution_allowed']??false))
        ){
            $reply=$natural->compose($prompt,[
                'authenticated'=>true,
                'scope'=>$scope,
                'website'=>$website->name,
                'canonical_knowledge'=>$knowledgePacket,
            ],[
                'canonical_intent'=>$canonicalIntent,
                'action_completed'=>false,
                'missing_information'=>$canonicalIntent['missing']??[],
                'known_entities'=>$canonicalIntent['entities']??[],
                'rule'=>'Ask only for the information genuinely missing from the canonical intent. Preserve known details from prior turns. Do not ask the user to Proceed yet and do not mutate the website.',
            ]);
            return response()->json([
                'reply'=>$reply,
                'mode'=>'clarify',
                'canonical_intent'=>$canonicalIntent,
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'site_memory'=>$siteMemory,
                'pending_action'=>false,
                'applied_operations'=>[],
            ]);
        }

        if($scope==='section' && !isset($blocks[$targetIndex])) {
            throw ValidationException::withMessages(['target_index'=>'That section is no longer available.']);
        }

        $normalizedPrompt=Str::lower(trim((string)$validated['prompt']));

        // Canonical mutation intent bundle — initialize before any downstream branch.
        $mutationPrompt=$normalizedPrompt;
        $explicitMediaMutation=Str::contains($mutationPrompt,[
            'replace image','replace the image','change image','change the image',
            'replace photo','change photo','replace media','change media',
            'replace video','replace the video','change video','change the video',
            'video background','background video','use this video','use video',
            'use this image','use this photo','use a different image','use different image',
            'use a different photo','use different photo','refresh image','refresh photo',
            'thumbnail','poster'
        ]);
        $universalBackgroundIntent=Str::contains($mutationPrompt,[
            'background image','section background','background photo','background media',
            'change background','replace background','remove background','clear background'
        ]);
        $contentRewriteIntent=Str::contains($mutationPrompt,[
            'rewrite','reword','rewrite the content','rewrite content','rewrite the copy','rewrite copy',
            'rewrite the text','rewrite text','make the copy','shorten the copy','shorter copy',
            'improve the copy','update the content','update content','change the wording',
            'tone of voice','make the text','make this copy','make the content shorter','make the text shorter'
        ]);
        $contentOnlyIntent=$contentRewriteIntent
            && !$explicitMediaMutation
            && !$universalBackgroundIntent
            && !Str::contains($mutationPrompt,[
                'redesign','change the layout','different layout','change layout','replace section',
                'change the design','different design','turn this section','transform this section'
            ]);
        $naturalReplyFromFacts=function(array $facts) use($natural,$validated,$scope,$website,$knowledgePacket){
            return $natural->compose((string)$validated['prompt'],[
                'authenticated'=>true,
                'scope'=>$scope,
                'website'=>$website->name,
                'canonical_knowledge'=>$knowledgePacket,
            ],array_merge([
                'canonical_knowledge'=>$knowledgePacket,
                'rule'=>'Formulate the user-facing reply from verified facts and canonical documentation. Never invent a completed action.',
            ],$facts));
        };


        if($stalePendingPlan || $orphanAffirmative){
            return response()->json([
                'reply'=>$natural->compose($originalUserPrompt,[
                    'authenticated'=>true,
                    'scope'=>$scope,
                    'website'=>$website->name,
                    'canonical_knowledge'=>$knowledgePacket,
                ],[
                    'action_completed'=>false,
                    'pending_action_available'=>false,
                    'constraint'=>$stalePendingPlan
                        ? 'The page changed after deletion confirmation was prepared, so the destructive action was discarded for safety.'
                        : 'There is no valid destructive-action confirmation to execute.',
                    'next_step'=>'Ask the user to request the deletion again.',
                ]),
                'mode'=>'reply',
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'applied_operations'=>[],
            ]);
        }


        $canonicalAction=(string)($canonicalIntent['action']??'update');
        if(!$resumePendingPlan && $canonicalAction==='publish'){
            $pageId=(int)($validated['current_page_id']??0);
            $pageExists=$pageId>0 && $website->pages()->whereKey($pageId)->exists();
            if(!$pageExists){
                $verification=$executionVerification->verify([['action'=>'publish']],[],false);
                return response()->json([
                    'reply'=>$naturalReplyFromFacts(['action'=>'publish page','action_completed'=>false,'execution_verification'=>$verification,'constraint'=>'The current page could not be resolved inside this website, so publishing was not attempted.']),
                    'mode'=>'reply','canonical_intent'=>$canonicalIntent,'execution_verification'=>$verification,
                    'credit_cost'=>0,'credit_balance'=>$user ? $credits->balance($user) : null,
                    'blocks'=>$blocks,'header'=>is_array($header)?$header:[],'footer'=>is_array($footer)?$footer:[],'applied_operations'=>[],
                ],422);
            }
            return response()->json([
                'reply'=>null,'mode'=>'publish','publish_page_id'=>$pageId,'canonical_intent'=>$canonicalIntent,
                'execution_request'=>['action'=>'publish','page_id'=>$pageId],
                'execution_phases'=>['Thinking','Planning','Building','Checking'],'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,'site_memory'=>$siteMemory,'pending_action'=>false,
            ]);
        }
        if(!$resumePendingPlan && $canonicalAction==='navigate'){
            $destination=$this->lunaBuilderNavigationTarget($website,$canonicalIntent,$prompt,isset($validated['current_page_id'])?(int)$validated['current_page_id']:null);
            $verification=$executionVerification->verify([['action'=>'navigate']],$destination?[['action'=>'navigate','target'=>$destination['url'],'verified'=>true]]:[],is_array($destination));
            return response()->json([
                'reply'=>$naturalReplyFromFacts(['action'=>'navigate','action_completed'=>is_array($destination),'destination'=>$destination['label']??null,'execution_verification'=>$verification,'constraint'=>$destination?null:'No authorized destination matched the request.']),
                'mode'=>$destination?'navigate':'reply','navigate_url'=>$destination['url']??null,
                'canonical_intent'=>$canonicalIntent,'execution_verification'=>$verification,
                'execution_phases'=>['Thinking','Checking'],'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,'site_memory'=>$siteMemory,
                'pending_action'=>false,'applied_operations'=>$verification['verified_operations']??[],
            ],$destination?200:422);
        }

        // Batch 5 destructive data actions use the same zero-credit safety
        // confirmation, but bypass the visual Spark planner entirely.
        if(
            !$resumePendingPlan
            && $canonicalAction==='delete'
            && in_array((string)($canonicalIntent['domain']??''),['post','commerce'],true)
            && !($validated['confirmed']??false)
        ){
            $blocksFingerprint=hash('sha256',json_encode(array_values($blocks),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
            $pendingToken=$pendingActions->put($pendingActor,[
                'kind'=>'builder_destructive_delete',
                'prompt'=>(string)$validated['prompt'],
                'scope'=>$scope,
                'target_index'=>$targetIndex,
                'plan'=>[],
                'operations'=>$canonicalIntent['operations']??[],
                'canonical_schema'=>$canonicalIntent,
                'blocks_fingerprint'=>$blocksFingerprint,
                'estimated_execution_cost'=>0,
                'website_id'=>$website->id,
            ]);
            return response()->json([
                'reply'=>$naturalReplyFromFacts([
                    'action_completed'=>false,
                    'confirmation_required'=>true,
                    'pending_action_stored'=>true,
                    'pending_action_token'=>$pendingToken,
                    'planned_operations'=>$canonicalIntent['operations']??[],
                    'estimated_execution_cost'=>0,
                    'next_step'=>'Ask for explicit deletion confirmation using the Delete button.',
                    'rule'=>'Do not claim anything was deleted before confirmation.',
                ]),
                'mode'=>'confirm','pending_action'=>true,'pending_action_token'=>$pendingToken,
                'canonical_intent'=>$canonicalIntent,'confirmation_cost'=>0,'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
            ]);
        }

        // Batch 5: explicit Posts / Updates and Ecommerce data actions execute
        // deterministically at 0 credits. Destructive post/product/category deletes
        // are only executed after the existing Builder delete confirmation resumes.
        if(
            in_array((string)($canonicalIntent['domain']??''),['post','commerce'],true)
            && (
                in_array($canonicalAction,['build','update'],true)
                || ($canonicalAction==='delete' && (($validated['confirmed']??false) || $resumePendingPlan))
            )
        ){
            $contentPage=null;
            if(!empty($validated['current_page_id'])){
                $contentPage=$website->pages()->whereKey((int)$validated['current_page_id'])->first();
            }
            $contentResult=$contentCommerceActions->apply($website,$contentPage,$prompt,$canonicalIntent,$user?->id);
            if(is_array($contentResult) && ($contentResult['handled']??false)){
                $ok=(bool)($contentResult['success']??false);
                $verification=$executionVerification->verify(
                    $contentResult['operations']??[],
                    $contentResult['operations']??[],
                    $ok
                );
                return response()->json([
                    'reply'=>$naturalReplyFromFacts([
                        'action'=>(string)($contentResult['domain']??'content').' action',
                        'action_completed'=>$ok,
                        'execution_verification'=>$verification,
                        'constraint'=>$ok?null:($contentResult['message']??'The requested content action could not be safely resolved.'),
                    ]),
                    'mode'=>'reply','canonical_intent'=>$canonicalIntent,'execution_verification'=>$verification,
                    'credit_cost'=>0,'credit_balance'=>$user ? $credits->balance($user) : null,
                    'blocks'=>$blocks,'header'=>is_array($header)?$header:[],'footer'=>is_array($footer)?$footer:[],
                    'site_memory'=>$siteMemory,'pending_action'=>false,
                    'applied_operations'=>$contentResult['operations']??[],
                    'content_result'=>array_filter([
                        'post_id'=>$contentResult['post_id']??null,
                        'product_id'=>$contentResult['product_id']??null,
                        'category_id'=>$contentResult['category_id']??null,
                    ],fn($v)=>$v!==null),
                ],$ok?200:422);
            }
        }

        // Batch 4: explicit SEO/site-settings/form-recipient actions can execute
        // deterministically without spending AI credits. Structural form edits still
        // continue through the Builder planner because it owns each Spark schema.
        if(!$resumePendingPlan && $canonicalAction==='update' && in_array((string)($canonicalIntent['domain']??''),['seo','settings','form'],true)){
            $adminPage=null;
            if(!empty($validated['current_page_id'])){
                $adminPage=$website->pages()->whereKey((int)$validated['current_page_id'])->first();
            }
            $adminResult=$siteAdminActions->apply($website,$adminPage,$prompt,$canonicalIntent);
            if(is_array($adminResult) && ($adminResult['handled']??false)){
                $ok=(bool)($adminResult['success']??false);
                $verification=$executionVerification->verify(
                    $adminResult['operations']??[],
                    $adminResult['operations']??[],
                    $ok
                );
                return response()->json([
                    'reply'=>$naturalReplyFromFacts([
                        'action'=>(string)($adminResult['domain']??'site settings').' update',
                        'action_completed'=>$ok,
                        'execution_verification'=>$verification,
                        'constraint'=>$ok?null:($adminResult['message']??'The requested setting could not be safely resolved.'),
                    ]),
                    'mode'=>'reply','canonical_intent'=>$canonicalIntent,'execution_verification'=>$verification,
                    'credit_cost'=>0,'credit_balance'=>$user ? $credits->balance($user) : null,
                    'blocks'=>$blocks,'header'=>is_array($header)?$header:[],'footer'=>is_array($footer)?$footer:[],
                    'site_memory'=>$siteMemory,'pending_action'=>false,
                    'applied_operations'=>$adminResult['operations']??[],
                ],$ok?200:422);
            }
        }

        $allowedSiteThemes=['midnight','emerald','coffee','rose','dark','ocean','indigo','amber','charcoal','violet','teal','ruby','forest','obsidian','navy','espresso','terracotta','asphalt'];
        $websiteThemeSettings=(array)($website->theme_settings??[]);
        $currentSiteTheme=Str::lower(trim((string)($websiteThemeSettings['primary']??'')));
        if(!in_array($currentSiteTheme,$allowedSiteThemes,true)) $currentSiteTheme='midnight';
        $explicitThemeChange=$this->lunaExplicitThemeChangeIntent((string)$validated['prompt']);
        $hasExistingSiteContent=(bool)($websiteThemeSettings['luna_theme_locked']??false)
            || $website->pages()->get(['blocks'])->contains(fn($candidate)=>is_array($candidate->blocks??null) && count($candidate->blocks)>0);
        $preserveSiteTheme=$hasExistingSiteContent && !$explicitThemeChange;
        $designDna=$designContinuity->designDna($website,is_array($theme)?$theme:[],$siteMemory);
        $designContinuityConstraint=$designContinuity->plannerConstraint($designDna,$explicitThemeChange);
        $siteDesignDna=$siteDna->hydrate($siteMemory,$designDna);
        $siteDnaContract=$siteDna->plannerDirective($siteMemory,$siteDesignDna,$explicitThemeChange);
        $siteBundle=is_array(data_get($website->settings,'site_bundle')) ? data_get($website->settings,'site_bundle') : [];
        $explicitWholeSiteRedesign=Str::contains(Str::lower((string)$validated['prompt']),[
            'redesign all pages','redesign the whole website','redesign whole website','redesign the entire website',
            'replace the site bundle','change the site bundle','new design for all pages','rebuild all pages'
        ]);
        $bundleTemplateKeys=[];
        if($siteBundle!==[] && !$explicitWholeSiteRedesign){
            $currentBundlePage=collect((array)($siteBundle['pages']??[]))->first(
                fn($candidate)=>(int)($candidate['page_id']??0)===(int)($validated['current_page_id']??0)
            );
            if(is_array($currentBundlePage)){
                $bundleTemplateKeys=array_values(array_unique(array_filter([
                    (string)($currentBundlePage['template_key']??''),
                    ...array_map('strval',(array)($currentBundlePage['candidate_template_keys']??[])),
                ])));
            }
            if($bundleTemplateKeys===[]){
                $bundleTemplateKeys=array_values(array_unique(array_filter(array_map(
                    'strval',(array)($siteBundle['template_candidates']??[])
                ))));
            }
            $siteMemory['site_bundle']=[
                'bundle_key'=>$siteBundle['bundle_key']??null,
                'bundle_name'=>$siteBundle['bundle_name']??null,
                'archetype'=>$siteBundle['archetype']??null,
                'design_contract'=>$siteBundle['design_contract']??[],
                'template_constraint'=>'bundle_first',
            ];
        }

        $globalComponentAction=$this->lunaGlobalComponentTokenAction(
            $coreActionPrompt,
            $scopeResolution,
            $components
        );
        if(is_array($globalComponentAction)){
            return response()->json([
                'reply'=>$naturalReplyFromFacts([
                    'action'=>'global component token change',
                    'action_completed'=>true,
                    'resolved_scope'=>$scopeResolution,
                    'verified_operations'=>$globalComponentAction['applied_operations']??[],
                ]),
                'mode'=>'reply',
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'components'=>$globalComponentAction['components']??$components,
                'site_memory'=>$siteMemory,
                'scope_resolution'=>$scopeResolution,
                'applied_operations'=>$globalComponentAction['applied_operations']??[],
            ]);
        }

        // Selected-Spark component capabilities are deterministic and use the
        // registry's canonical storage/render contract. This runs before the
        // prose planner so a simple radius adjustment cannot be converted into
        // a generic, unverifiable design_overrides operation.
        $capabilityLeaf=Str::lower((string)($canonicalIntent['leaf_operation']??''));
        $localCardRadiusRequest=$scope==='section'
            && isset($blocks[$targetIndex])
            && (
                $capabilityLeaf==='border_radius'
                || (bool)preg_match('/\b(?:cards?|tiles?)\b.*\b(?:rounded|rounder|radius|corners?)\b|\b(?:rounded|rounder|radius|corners?)\b.*\b(?:cards?|tiles?)\b/i',$coreActionPrompt)
            );
        if($localCardRadiusRequest){
            $capabilityDirection=Str::contains(Str::lower($coreActionPrompt),[
                'less rounded','reduce rounding','smaller radius','sharper','more square','square corners','too much',
            ]) ? 'decrease' : 'increase';
            $capabilityResult=$sparkEditCapabilityExecutor->executeRelative(
                array_values($blocks),
                $targetIndex,
                'border_radius',
                $capabilityDirection,
                [
                    'target'=>'cards',
                    'current_value'=>$components['card_radius']??'24px',
                    'element_context'=>$elementContext,
                ],
            );
            if($capabilityResult['applied']??false){
                $mutation=(array)($capabilityResult['mutation']??[]);
                $appliedOperations=(array)($capabilityResult['applied_operations']??[]);
                $verification=(array)($capabilityResult['verification']??[]);
                $siteMemory=$contextState->rememberVerified(
                    $siteMemory,
                    array_replace($canonicalIntent,[
                        'domain'=>'design','operation'=>'update','leaf_operation'=>'border_radius',
                        'scope'=>'section','changes'=>['relative'=>['direction'=>$mutation['direction']??$capabilityDirection]],
                    ]),
                    $verification,
                    $appliedOperations,
                    $routeContext??[],
                    [
                        'blocks_fingerprint'=>hash('sha256',json_encode($capabilityResult['before_blocks']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:''),
                        'value'=>$mutation['before']??null,
                        'reversible_state'=>[
                            'kind'=>'spark_capability','scope'=>'section','index'=>$targetIndex,
                            'property'=>'border_radius','path'=>$mutation['path']??null,'value'=>$mutation['before']??null,
                        ],
                    ],
                    [
                        'blocks_fingerprint'=>hash('sha256',json_encode($capabilityResult['after_blocks']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:''),
                        'value'=>$mutation['after']??null,
                    ],
                );
                $verb=($mutation['direction']??'increase')==='increase'?'more rounded':'less rounded';
                return response()->json([
                    'reply'=>'Made the selected cards '.$verb.' ('.($mutation['before']??'current').' → '.($mutation['after']??'updated').').',
                    'mode'=>'reply',
                    'credit_cost'=>0,
                    'credit_balance'=>$user ? $credits->balance($user) : null,
                    'blocks'=>$capabilityResult['blocks'],
                    'header'=>is_array($header)?$header:[],
                    'footer'=>is_array($footer)?$footer:[],
                    'site_memory'=>$siteMemory,
                    'scope_resolution'=>$scopeResolution,
                    'applied_operations'=>$appliedOperations,
                    'execution_verification'=>$verification,
                ]);
            }
        }

        // Card surface requests use one semantic, persisted component value.
        // Builder and the static compiler derive the background, foreground,
        // heading and border tokens together so Luna cannot darken a card while
        // verifying (or rendering) only one of those four contrast-sensitive parts.
        $explicitCardSurfacePrompt=(bool)preg_match(
            '/\b(?:cards?|tiles?|items?)\b.*\b(?:dark|darker|light|lighter|white|surface)\b|\b(?:dark|darker|light|lighter|white)\b.*\b(?:cards?|tiles?|items?)\b/i',
            $coreActionPrompt,
        );
        $localCardSurfaceRequest=in_array($scope,['section','element'],true)
            && isset($blocks[$targetIndex])
            && ($capabilityLeaf==='card_surface' || $explicitCardSurfacePrompt);
        if($localCardSurfaceRequest){
            $cardSurface=Str::contains(Str::lower($coreActionPrompt),['dark','darker']) ? 'dark' : 'surface';
            $capabilityResult=$sparkEditCapabilityExecutor->executeSet(
                array_values($blocks),
                $targetIndex,
                'card_surface',
                $cardSurface,
                [
                    'target'=>'cards',
                    'current_value'=>data_get($blocks[$targetIndex],'luna_component_overrides.card_surface','surface'),
                    'element_context'=>$elementContext,
                ],
            );
            if($capabilityResult['applied']??false){
                $mutation=(array)($capabilityResult['mutation']??[]);
                $appliedOperations=(array)($capabilityResult['applied_operations']??[]);
                $verification=(array)($capabilityResult['verification']??[]);
                $siteMemory=$contextState->rememberVerified(
                    $siteMemory,
                    array_replace($canonicalIntent,[
                        'domain'=>'design','operation'=>'update','leaf_operation'=>'card_surface',
                        'scope'=>'section','changes'=>['value'=>$mutation['after']??$cardSurface],
                    ]),
                    $verification,
                    $appliedOperations,
                    $routeContext??[],
                    [
                        'blocks_fingerprint'=>hash('sha256',json_encode($capabilityResult['before_blocks']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:''),
                        'value'=>$mutation['before']??null,
                        'reversible_state'=>[
                            'kind'=>'spark_capability','scope'=>'section','index'=>$targetIndex,
                            'property'=>'card_surface','path'=>$mutation['path']??null,'value'=>$mutation['before']??null,
                        ],
                    ],
                    [
                        'blocks_fingerprint'=>hash('sha256',json_encode($capabilityResult['after_blocks']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:''),
                        'value'=>$mutation['after']??null,
                    ],
                );
                $reply=$cardSurface==='dark'
                    ? 'Made the selected cards dark and verified their text contrast is readable.'
                    : 'Returned the selected cards to a light surface and verified their text contrast is readable.';
                return response()->json([
                    'reply'=>$reply,
                    'mode'=>'reply',
                    'credit_cost'=>0,
                    'credit_balance'=>$user ? $credits->balance($user) : null,
                    'blocks'=>$capabilityResult['blocks'],
                    'header'=>is_array($header)?$header:[],
                    'footer'=>is_array($footer)?$footer:[],
                    'site_memory'=>$siteMemory,
                    'scope_resolution'=>$scopeResolution,
                    'applied_operations'=>$appliedOperations,
                    'execution_verification'=>$verification,
                ]);
            }
        }

        // Typography commands are deterministic and cost 0 credits because they
        // update existing design tokens without making an AI/API request.
        $typographyAction=data_get($canonicalIntent,'routing.menu_scope')==='sparks'
            ? null
            : $this->lunaTypographyAction(
                $coreActionPrompt,
                $scope,
                $targetIndex,
                $blocks,
                $typography,
                $canonicalIntent
            );
        if(is_array($typographyAction)){
            $typographyAction=$this->lunaVerifyTypographyAction(
                $typographyAction,
                $blocks,
                $typography,
                $canonicalIntent,
                $executionVerification,
                $contextState,
                $siteMemory,
                $routeContext??[]
            );
            return response()->json([
                // This wording is derived from the verified before/after delta.
                // A prose model must not reinterpret "too much" and describe the
                // opposite direction from the mutation that actually happened.
                'reply'=>$typographyAction['reply'],
                'mode'=>'reply',
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$typographyAction['blocks'],
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>null,
                'typography_settings'=>$typographyAction['typography_settings'],
                'page_style'=>null,
                'site_memory'=>$typographyAction['site_memory'],
                'applied_operations'=>$typographyAction['applied_operations'],
                'execution_verification'=>$typographyAction['execution_verification'],
            ]);
        }

        $backgroundAction=$this->lunaBackgroundStyleAction(
            $coreActionPrompt,
            $scope,
            $targetIndex,
            $blocks,
            $backgroundStyle
        );
        if(is_array($backgroundAction)){
            return response()->json([
                'reply'=>$naturalReplyFromFacts([
                    'action'=>'background/design-token change',
                    'action_completed'=>!empty($backgroundAction['applied_operations']),
                    'verified_operations'=>$backgroundAction['applied_operations']??[],
                    'scope'=>$scope,
                ]),
                'mode'=>'reply',
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$backgroundAction['blocks'],
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>null,
                'background_style'=>$backgroundAction['background_style'],
                'page_style'=>null,
                'site_memory'=>$siteMemory,
                'applied_operations'=>$backgroundAction['applied_operations'],
            ]);
        }

        $sectionLayoutAction=$this->lunaSectionLayoutAction(
            $coreActionPrompt,
            $scope,
            $targetIndex,
            $blocks,
            $sectionLayout
        );
        if(is_array($sectionLayoutAction)){
            return response()->json([
                'reply'=>$naturalReplyFromFacts([
                    'action'=>'section layout/design-token change',
                    'action_completed'=>!empty($sectionLayoutAction['applied_operations']),
                    'verified_operations'=>$sectionLayoutAction['applied_operations']??[],
                    'scope'=>$scope,
                ]),
                'mode'=>'reply',
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$sectionLayoutAction['blocks'],
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>null,
                'section_layout'=>$sectionLayoutAction['section_layout'],
                'page_style'=>null,
                'site_memory'=>$siteMemory,
                'applied_operations'=>$sectionLayoutAction['applied_operations'],
            ]);
        }

        if($scope==='section'){
            $reorderAction=$this->lunaSectionReorderAction(
                (string)$validated['prompt'],
                $scope,
                $targetIndex,
                $blocks
            );
            if(is_array($reorderAction)){
                return response()->json([
                    'reply'=>$naturalReplyFromFacts([
                        'action'=>'section reorder',
                        'action_completed'=>(bool)($reorderAction['changed']??false),
                        'verified_operations'=>$reorderAction['applied_operations']??[],
                        'constraint'=>($reorderAction['changed']??false)?null:'The requested deterministic move did not change page order.',
                    ]),
                    'mode'=>'reply',
                    'credit_cost'=>0,
                    'credit_balance'=>$user ? $credits->balance($user) : null,
                    'blocks'=>$reorderAction['blocks'],
                    'header'=>is_array($header)?$header:[],
                    'footer'=>is_array($footer)?$footer:[],
                    'theme_key'=>null,
                    'page_style'=>null,
                    'site_memory'=>$siteMemory,
                    'applied_operations'=>$reorderAction['applied_operations']??[],
                ]);
            }
        }

        if($scope==='section'){
            $serviceCrud=$this->lunaBentoServicesCrudAction(
                (string)$validated['prompt'],
                $targetIndex,
                $blocks
            );
            if(is_array($serviceCrud)){
                $crudCost=($serviceCrud['changed']??false) && ($serviceCrud['ai_used']??false) ? 15 : 0;
                if($crudCost>0 && $user && !$credits->canAfford($user,$crudCost)){
                    return response()->json([
                        'message'=>"This Luna change needs {$crudCost} credits, but your balance is too low.",
                        'credit_cost'=>$crudCost,
                        'requires_credits'=>true,
                    ],422);
                }
                if($crudCost>0 && $user){
                    $credits->consume($user,$crudCost,'Luna content CRUD',$website,'luna-content-crud-'.Str::uuid(),[
                        'category'=>'ai','scope'=>'section','operation'=>$serviceCrud['operation']??'services_crud',
                        'count_before'=>$serviceCrud['count_before']??null,
                        'count_after'=>$serviceCrud['count_after']??null,
                    ]);
                }
                return response()->json([
                    'reply'=>$naturalReplyFromFacts([
                        'action'=>'services list change',
                        'action_completed'=>(bool)($serviceCrud['changed']??false),
                        'operation'=>$serviceCrud['operation']??null,
                        'count_before'=>$serviceCrud['count_before']??null,
                        'count_after'=>$serviceCrud['count_after']??null,
                        'error'=>$serviceCrud['error']??null,
                        'constraint'=>(!($serviceCrud['changed']??false))?($serviceCrud['reason']??'The server did not verify a service-list mutation.'):null,
                    ]),
                    'mode'=>'reply',
                    'credit_cost'=>$crudCost,
                    'credit_balance'=>$user ? $credits->balance($user) : null,
                    'blocks'=>$serviceCrud['blocks']??$blocks,
                    'header'=>is_array($header)?$header:[],
                    'footer'=>is_array($footer)?$footer:[],
                    'theme_key'=>null,
                    'page_style'=>null,
                    'site_memory'=>$siteMemory,
                    'applied_operations'=>($serviceCrud['changed']??false) ? [[
                        'action'=>'edit',
                        'index'=>$targetIndex,
                        'type'=>'services_crud',
                        'operation'=>$serviceCrud['operation']??null,
                        'count_before'=>$serviceCrud['count_before']??null,
                        'count_after'=>$serviceCrud['count_after']??null,
                    ]] : [],
                ]);
            }
        }

        if(data_get($canonicalIntent,'routing.menu_scope')!=='sparks' && $scope==='header'){
            $shellAction=$this->lunaHeaderScopeAction($coreActionPrompt,is_array($header)?$header:[],$siteMemory);
            if(is_array($shellAction)){
                return response()->json([
                    'reply'=>$naturalReplyFromFacts([
                    'action'=>($shellAction['mode']??'reply')==='logo_name_required'?'prepare logo generation':'header change',
                    'action_completed'=>!empty($shellAction['applied_operations']),
                    'verified_operations'=>$shellAction['applied_operations']??[],
                    'needs_user_input'=>($shellAction['mode']??'')==='logo_name_required',
                    'missing_information'=>($shellAction['mode']??'')==='logo_name_required'?'the business/company name to use in the logo':null,
                    'planned_action'=>($shellAction['mode']??'')==='logo_generate'?'generate the logo using the resolved company name':null,
                    'logo_company_name'=>$shellAction['logo_company_name']??null,
                ]),
                    'mode'=>$shellAction['mode']??'reply',
                    'logo_company_name'=>$shellAction['logo_company_name']??null,
                    'credit_cost'=>0,
                    'credit_balance'=>$user ? $credits->balance($user) : null,
                    'blocks'=>$blocks,
                    'header'=>$shellAction['header']??(is_array($header)?$header:[]),
                    'footer'=>is_array($footer)?$footer:[],
                    'theme_key'=>null,
                    'page_style'=>null,
                    'site_memory'=>$shellAction['site_memory']??$siteMemory,
                    'applied_operations'=>$shellAction['applied_operations']??[],
                ]);
            }
        }

        if(data_get($canonicalIntent,'routing.menu_scope')!=='sparks' && $scope==='header'){
            $pageNavAction=$this->lunaPageNavigationAction((string)$validated['prompt'],$website,is_array($header)?$header:[]);
            if(is_array($pageNavAction)){
                return response()->json([
                    'reply'=>$naturalReplyFromFacts([
                    'action'=>'pages/navigation update',
                    'action_completed'=>!empty($pageNavAction['applied_operations']),
                    'verified_operations'=>$pageNavAction['applied_operations']??[],
                ]),
                    'mode'=>'reply',
                    'credit_cost'=>0,
                    'credit_balance'=>$user ? $credits->balance($user) : null,
                    'blocks'=>$blocks,
                    'header'=>$pageNavAction['header'],
                    'footer'=>is_array($footer)?$footer:[],
                    'theme_key'=>null,
                    'page_style'=>null,
                    'site_memory'=>$siteMemory,
                    'applied_operations'=>$pageNavAction['applied_operations'],
                ]);
            }
        }
        if(data_get($canonicalIntent,'routing.menu_scope')!=='sparks' && ($guardrailReply=$this->lunaUnsupportedLowLevelDesignRequest((string)$validated['prompt']))){
            return response()->json([
                'reply'=>$naturalReplyFromFacts([
                    'action_completed'=>false,
                    'capability_status'=>'unsupported exact low-level implementation request',
                    'constraint'=>'Cosmic owns raw CSS/Tailwind/DOM implementation details to protect responsive Builder/live parity.',
                    'fallback'=>'Offer the closest design-system-safe visual equivalent.',
                ]),
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>null,
                'page_style'=>null,
                'site_memory'=>$siteMemory,
                'applied_operations'=>[],
            ]);
        }
        if(data_get($canonicalIntent,'routing.menu_scope')==='theme' && ($namedTheme=$this->lunaStandaloneNamedThemeKey((string)$validated['prompt']))){
            $siteMemory['theme_context']=['primary'=>$namedTheme,'scope'=>'site','status'=>'applied'];
            return response()->json([
                'reply'=>'Applied the '.Str::headline($namedTheme).' theme across the website.',
                'mode'=>'reply',
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>$namedTheme,
                'page_style'=>null,
                'site_memory'=>$siteMemory,
                'applied_operations'=>[['action'=>'theme','theme_key'=>$namedTheme,'verified'=>true]],
            ]);
        }
        if(data_get($canonicalIntent,'routing.menu_scope')==='theme' && ($automaticTheme=$this->lunaAutomaticThemeKey((string)$validated['prompt'],is_array($theme)?$theme:[],(string)($website->industry??'')))){
            $siteMemory['theme_context']=['primary'=>$automaticTheme,'scope'=>'site','status'=>'applied','source'=>'luna_auto_family'];
            return response()->json([
                'reply'=>'Applied a '.Str::headline($automaticTheme).' color family across the website.',
                'mode'=>'reply',
                'canonical_intent'=>$canonicalIntent,
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$blocks,
                'header'=>is_array($header)?$header:[],
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>$automaticTheme,
                'page_style'=>null,
                'site_memory'=>$siteMemory,
                'applied_operations'=>[['action'=>'theme','theme_key'=>$automaticTheme,'verified'=>true,'source'=>'luna_auto_family']],
            ]);
        }
        $backgroundRemove=Str::contains($normalizedPrompt,['remove background image','remove the background image','remove background photo','no background image','clear background image']);
        $backgroundDarker=Str::contains($normalizedPrompt,['make background darker','darken the background','darker overlay','stronger overlay']);
        $backgroundLighter=Str::contains($normalizedPrompt,['make background lighter','lighten the background','lighter overlay','softer overlay']);
        $backgroundAdd=!$backgroundRemove && (
            (Str::contains($normalizedPrompt,'background image') && Str::contains($normalizedPrompt,['add','use','set','change','give','put','apply']))
            || (Str::contains($normalizedPrompt,'background photo') && Str::contains($normalizedPrompt,['add','use','set','change','give','put','apply']))
        );
$resolvedMutationScope=(string)($scopeResolution['scope']??$scope);
        $designGlobalIntent=in_array($resolvedMutationScope,['page','site','global_token'],true);
        $artDirectionIntent=$this->lunaArtDirectionIntent((string)$validated['prompt']);
        // Relative art direction inherits the resolved scope; explicit local wording never widens.
        if($artDirectionIntent && in_array($resolvedMutationScope,['element','item','section'],true))$designGlobalIntent=false;
        $siteMemory=$this->lunaUpdatedSiteMemory((string)$validated['prompt'],$siteMemory,is_array($theme??null)?$theme:[]);
        // Whole-page requests that explicitly name a section (hero/services/etc.)
        // get a deterministic section index before capability/Tailwind planning.
        // This lets Luna use only that Spark's rendered Tailwind inventory.
        if($scope==='page'){
            $namedPageTargetIndex=$this->lunaNamedBlockTargetIndex((string)$validated['prompt'],$blocks);
            if($namedPageTargetIndex!==null)$targetIndex=$namedPageTargetIndex;
        }


        $promptForTarget=trim((string)$validated['prompt']);

        // If the user explicitly names a different section, that name overrides
        // the currently selected section. This keeps "change the hero..." reliable
        // even when the chat was opened from FAQ, Contact, Services, etc.
        $namedTargetOverrodeSelection=false;
        if($scope==='section'){
            $originalSelectedIndex=$targetIndex;
            $namedTargets=[
                'hero'=>['hero','banner','masthead'],
                'services'=>['services','service'],
                'testimonials'=>['testimonial','reviews','review'],
                'pricing'=>['pricing','price','plans'],
                'faq'=>['faq','questions'],
                'contact'=>['contact','enquiry','inquiry'],
                'cta'=>['cta','call to action'],
                'gallery'=>['gallery','portfolio','projects','work'],
                'process'=>['process','steps','timeline'],
                'team'=>['team','people','staff'],
                'about'=>['about','story'],
            ];
            $requestedNamedTarget=null;
            foreach($namedTargets as $name=>$aliases){
                foreach($aliases as $alias){
                    if(preg_match('/\b'.preg_quote($alias,'/').'\b/i',$promptForTarget)){
                        $requestedNamedTarget=$name;
                        break 2;
                    }
                }
            }
            if($requestedNamedTarget!==null){
                foreach(array_values($blocks) as $candidateIndex=>$candidateBlock){
                    $candidateType=Str::lower((string)($candidateBlock['type']??''));
                    $candidateHeading=Str::lower((string)($candidateBlock['heading']??$candidateBlock['title']??$candidateBlock['eyebrow']??''));
                    $candidateMeta=SparkCatalog::find((string)($candidateBlock['type']??''))??[];
                    $candidateCategory=Str::lower((string)($candidateMeta['category']??''));
                    $haystack=$candidateType.' '.$candidateHeading.' '.$candidateCategory;
                    $matches=match($requestedNamedTarget){
                        'hero'=>Str::contains($haystack,['hero','banner','mini heroes']),
                        'services'=>Str::contains($haystack,['services','service']),
                        'testimonials'=>Str::contains($haystack,['testimonial','review']),
                        'pricing'=>Str::contains($haystack,['pricing','price']),
                        'faq'=>Str::contains($haystack,'faq'),
                        'contact'=>Str::contains($haystack,['contact','location']),
                        'cta'=>Str::contains($haystack,'cta'),
                        'gallery'=>Str::contains($haystack,['gallery','portfolio','case studies','projects']),
                        'process'=>Str::contains($haystack,['process','proof','timeline']),
                        'team'=>Str::contains($haystack,'team'),
                        'about'=>Str::contains($haystack,'about'),
                        default=>false,
                    };
                    if($matches){
                        $targetIndex=$candidateIndex;
                        $namedTargetOverrodeSelection=($candidateIndex!==$originalSelectedIndex);
                        break;
                    }
                }
            }
        }

        // Deterministic shell commands: no model call required, so these are instant and reliable.
        $overlayOn=Str::contains($normalizedPrompt,[
            'float header','floating header','overlay header','header over hero','header over banner',
            'header on banner','transparent header','place header over','move header over'
        ]);
        $overlayOff=Str::contains($normalizedPrompt,[
            'disable overlay','turn off overlay','remove overlay','solid header','header above banner',
            'header outside banner','header above hero','move header above'
        ]);

        if($overlayOn || $overlayOff){
            $creditCost=10;
            if($user && !$credits->canAfford($user,$creditCost)){
                return response()->json(['message'=>"This Luna change needs {$creditCost} credits.",'credit_cost'=>$creditCost,'requires_credits'=>true],422);
            }
            $nextHeader=is_array($header)?$header:[];
            $nextHeader['overlay_header_on_banner']=$overlayOn && !$overlayOff;

            if($user) $credits->consume($user,$creditCost,'Luna header change',$website,'luna-header-'.Str::uuid(),['category'=>'ai']);
            $reply=$natural->compose((string)$validated['prompt'],[
                'authenticated'=>true,
                'scope'=>$scope,
                'website'=>$website->name,
            ],[
                'action'=>'header overlay change',
                'action_completed'=>true,
                'overlay_enabled'=>$nextHeader['overlay_header_on_banner'],
                'credits_used'=>$creditCost,
            ]);
            return response()->json([
                'reply'=>$reply,
                'blocks'=>$blocks,
                'header'=>$nextHeader,
                'footer'=>is_array($footer)?$footer:[],
                'theme_key'=>null,
                'credit_cost'=>$creditCost,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'page_style'=>$nextHeader['overlay_header_on_banner'] ? 'balanced' : null,
                'applied_operations'=>[['action'=>'header_overlay','enabled'=>$nextHeader['overlay_header_on_banner']]],
            ]);
        }

        // Batch 2: normal action/build requests execute directly.
        // No pending plan or Proceed/Continue round-trip is created here.

        // Empty page: Luna silently uses the existing hidden template/Spark planner,
        // then returns a complete starter page. No Add Spark/Template UI is required.
        if($scope==='page' && count($blocks)===0){
            try {
                // API 1 — DESIGN PLANNER ONLY. It sees compact template metadata,
                // chooses the template/Sparks + theme + art/media direction, and does
                // not receive the selected Spark content schemas.
                $plannerPrompt=(string)$validated['prompt'];
                if($preserveSiteTheme){
                    $plannerPrompt.="

EXISTING WEBSITE BRAND CONSTRAINT: Preserve the current website theme '{$currentSiteTheme}'. You may choose a different template/Spark composition, but do not rebrand or choose a different theme unless the user explicitly requested a theme/color/rebrand change.";
                }
                if($designContinuityConstraint!==''){
                    $plannerPrompt.="

".$designContinuityConstraint;
                }
                if($siteDnaContract!==''){
                    $plannerPrompt.="\n\n".$siteDnaContract;
                }
                if($bundleTemplateKeys!==[]){
                    $plannerPrompt.="\n\nCURRENT SITE BUNDLE — AUTHORITATIVE: "
                        .(string)($siteBundle['bundle_name']??$siteBundle['bundle_key']??'Current bundle')
                        .". Choose this inner page only from the server-provided bundle template candidates. "
                        ."Preserve its design contract while allowing page-appropriate composition and content. "
                        ."The bundle is a consistency guardrail, not a content or individual-Spark editing lock.";
                }
                $designPlan=$lunaPages->planDetailed($plannerPrompt,$bundleTemplateKeys!==[]?$bundleTemplateKeys:null);
                if($preserveSiteTheme){
                    $designPlan['theme']=$currentSiteTheme;
                    $designPlan['theme_action']='preserve';
                } else {
                    $designPlan['theme_action']='replace';
                }

                // Batch 11 — deterministic design critic before content generation.
                // It may reorder already selected registered Sparks, but never invent
                // a Spark or silently change the user's established brand.
                $registeredSparkKeys=collect(SparkCatalog::all())->pluck('key')->filter()->values()->all();
                $designCritique=$designCritic->critique($designPlan,$siteDesignDna,$registeredSparkKeys);
                $criticRevisionApplied=false;
                if(($designCritique['decision']??'accept')==='revise'){
                    $recommended=array_values(array_filter(
                        (array)($designCritique['recommended_sections']??[]),
                        fn($key)=>in_array($key,$registeredSparkKeys,true)
                    ));
                    if($recommended!==[] && $recommended!==array_values((array)($designPlan['sections']??[]))){
                        $designPlan['sections']=$recommended;
                        $criticRevisionApplied=true;
                        $designCritique['revision_applied']=true;
                        $designCritique['revision_passes']=1;
                    }
                }
                $sections=is_array($designPlan['sections']??null)?$designPlan['sections']:[];
                if(count($sections)===0) throw new \RuntimeException('Luna did not select any registered sections.');

                // API 2 — CONTENT COMPOSER ONLY. The selected template/theme are now
                // locked. Content generation receives schemas only for those selected
                // Sparks and is not allowed to silently redesign the page.
                $lockedPlan=[
                    'theme'=>(string)($designPlan['theme']??''),
                    'template_key'=>(string)($designPlan['template_key']??''),
                    'template_name'=>(string)($designPlan['template_name']??''),
                    'industry'=>(string)($designPlan['industry']??'general'),
                    'design_direction'=>(string)($designPlan['design_direction']??''),
                    'media_direction'=>(string)($designPlan['media_direction']??''),
                    'page_intent'=>(string)($designPlan['page_intent']??'home'),
                    'composition_industry'=>(string)($designPlan['composition_industry']??($designPlan['industry']??'general')),
                    'composition_roles'=>is_array($designPlan['composition_roles']??null)?$designPlan['composition_roles']:[],
                    'composition_pass'=>(bool)($designPlan['composition_pass']??false),
                    'design_continuity'=>[
                        'established'=>(bool)($designDna['established']??false),
                        'theme_family'=>$designDna['theme_family']??null,
                        'page_style'=>$designDna['page_style']??null,
                        'preserve_tokens'=>$preserveSiteTheme,
                    ],
                    'responsive'=>[
                        'contract'=>$designPlan['responsive_contract']??$responsive->contract($sections),
                        'risks'=>$designPlan['responsive_risks']??[],
                    ],
                    'design_critic'=>[
                        'score'=>$designCritique['score']??null,
                        'grade'=>$designCritique['grade']??null,
                        'decision'=>$designCritique['decision']??'accept',
                        'finding_count'=>count((array)($designCritique['findings']??[])),
                        'revision_applied'=>$criticRevisionApplied,
                    ],
                    'theme_action'=>(string)($designPlan['theme_action']??($preserveSiteTheme?'preserve':'replace')),
                ];
                $contentPrompt=(string)$validated['prompt']
                    ."\n\nLOCKED LUNA DESIGN PLAN (API 1 — authoritative; do not change theme/template):\n"
                    .json_encode($lockedPlan,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
                    ."\nAPI 2 ROLE: Generate content only for the selected registered Sparks. Preserve the locked design and use only industry-relevant media intent.";
                $generated=$lunaPages->generate($contentPrompt,$sections);
                if(is_array($generated) && count($generated)>0){
                    $creditCost=40;
                    if($user && !$credits->canAfford($user,$creditCost)){
                        return response()->json(['message'=>"Building this page needs {$creditCost} credits.",'credit_cost'=>$creditCost,'requires_credits'=>true],422);
                    }
                    try {
                        $remote=$pageGeneration->applyStartPageRemoteImages($contentPrompt,$generated);
                        if(is_array($remote['blocks']??null) && count($remote['blocks'])>0){
                            $generated=$remote['blocks'];
                        }
                    } catch(\Throwable $e) {
                        report($e);
                    }

                    $allowedThemes=['midnight','emerald','coffee','rose','dark','ocean','indigo','amber','charcoal','violet','teal','ruby','forest','obsidian','navy','espresso','terracotta','asphalt'];
                    $plannedTheme=Str::lower(trim((string)($designPlan['theme']??'')));
                    $appliedTheme=in_array($plannedTheme,$allowedThemes,true)?$plannedTheme:null;
                    if($appliedTheme!==null){
                        $settings=(array)($website->theme_settings??[]);
                        $settings['primary']=$appliedTheme;
                        $settings['secondary']=$settings['secondary']??'white';
                        $settings['tertiary']=$settings['tertiary']??'stone';
                        $settings['auto']=true;
                        $settings['luna_theme_locked']=true;
                        $website->forceFill(['theme_settings'=>$settings])->save();
                    }

                    $responsiveTokens=$responsive->recommendedTokens([
                        'section_layout'=>(array)($designDna['section_layout']??[]),
                        'components'=>(array)($designDna['components']??[]),
                    ]);

                    // Preserve centralized design tokens across sibling pages.
                    // New page composition may vary, but site-wide visual DNA remains authoritative.
                    if($preserveSiteTheme){
                        $settings=(array)($website->theme_settings??[]);
                        foreach(['typography','components','section_layout','background_style','custom_brand_theme'] as $tokenGroup){
                            if(array_key_exists($tokenGroup,$designDna) && $designDna[$tokenGroup]!==[] && $designDna[$tokenGroup]!==null){
                                $settings[$tokenGroup]=$designDna[$tokenGroup];
                            }
                        }
                        $website->forceFill(['theme_settings'=>$settings])->save();
                    }

                    $settings=(array)($website->theme_settings??[]);
                    $settings['section_layout']=array_replace(
                        (array)($responsiveTokens['section_layout']??[]),
                        (array)($settings['section_layout']??[])
                    );
                    $settings['components']=array_replace(
                        (array)($responsiveTokens['components']??[]),
                        (array)($settings['components']??[])
                    );
                    $website->forceFill(['theme_settings'=>$settings])->save();

                    $siteMemory=$designContinuity->memory($siteMemory,$designDna,$designPlan);
                    $siteMemory['responsive']=[
                        'risks'=>$designPlan['responsive_risks']??[],
                        'breakpoints'=>($designPlan['responsive_contract']['breakpoints']??[]),
                    ];
                    $visualQaResult=$visualQa->audit(
                        (string)$validated['prompt'],
                        $sections,
                        array_values($generated),
                        (array)($website->theme_settings??[])
                    );
                    $renderParityResult=$renderParity->audit(
                        array_values($generated),
                        (array)($website->theme_settings??[])
                    );
                    $siteMemory['visual_qa']=[
                        'score'=>$visualQaResult['score']??null,
                        'grade'=>$visualQaResult['grade']??null,
                        'finding_count'=>$visualQaResult['summary']['total']??0,
                    ];
                    $siteMemory['design_critic']=[
                        'score'=>$designCritique['score']??null,
                        'grade'=>$designCritique['grade']??null,
                        'decision'=>$designCritique['decision']??null,
                        'finding_count'=>count((array)($designCritique['findings']??[])),
                        'revision_applied'=>$criticRevisionApplied,
                    ];

                    $buildVerification=$executionVerification->verify(
                        [['action'=>'build_page']],
                        count($generated)>0?[['action'=>'build_page','count'=>count($generated),'verified'=>true]]:[],
                        count($generated)>0
                    );
                    if($user && ($buildVerification['can_claim_complete']??false)) $credits->consume($user,$creditCost,'Luna page build',$website,'luna-build-'.Str::uuid(),['category'=>'ai','template_key'=>$designPlan['template_key']??null,'theme'=>$appliedTheme]);
                    $reply=$natural->compose((string)$validated['prompt'],[
                        'authenticated'=>true,
                        'scope'=>'page',
                        'website'=>$website->name,
                    ],[
                        'action'=>'build page',
                        'action_completed'=>(bool)($buildVerification['can_claim_complete']??false),
                        'generated_sections'=>count($generated),
                        'credits_used'=>($buildVerification['can_claim_complete']??false)?$creditCost:0,
                        'execution_verification'=>$buildVerification,
                        'visual_qa'=>[
                            'score'=>$visualQaResult['score']??null,
                            'grade'=>$visualQaResult['grade']??null,
                            'finding_count'=>$visualQaResult['summary']['total']??0,
                            'auto_mutated'=>false,
                        ],
                        'render_parity'=>[
                            'render_contract'=>$renderParityResult['render_contract']??null,
                            'page_supported'=>$renderParityResult['page_supported']??false,
                            'runtime_visual_parity_verified'=>false,
                        ],
                        'rule'=>'The page build completed. Visual QA is diagnostic. Static render-contract coverage is not proof of runtime Builder/Live visual parity; do not claim runtime parity until a rendered comparison verifies it.',
                    ]);
                    return response()->json([
                        'reply'=>$reply,
                        'blocks'=>array_values($generated),
                        'header'=>is_array($header)?$header:[],
                        'footer'=>is_array($footer)?$footer:[],
                        'theme_key'=>$appliedTheme,
                        'design_plan'=>$lockedPlan,
                        'site_memory'=>$siteMemory,
                        'design_continuity'=>$lockedPlan['design_continuity']??[],
                        'section_layout'=>$settings['section_layout']??[],
                        'components'=>$settings['components']??[],
                        'responsive'=>$lockedPlan['responsive']??[],
                        'design_critic'=>$designCritique,
                        'visual_qa'=>$visualQaResult,
                        'render_parity'=>$renderParityResult,
                        'execution_verification'=>$buildVerification,
                        'canonical_intent'=>$canonicalIntent,
                        'execution_phases'=>['Thinking','Planning','Designing','Building','Checking'],
                        'credit_cost'=>($buildVerification['can_claim_complete']??false)?$creditCost:0,
                        'credit_balance'=>$user ? $credits->balance($user) : null,
                        'applied_operations'=>$buildVerification['verified_operations']??[],
                    ]);
                }
            } catch(\Throwable $e) {
                report($e);
            }
        }

        // Luna sees the whole hidden Spark vocabulary, but may only auto-use free
        // Sparks or Sparks already installed by this account. Paid locked Sparks
        // remain invisible to orchestration so chat never bypasses entitlements.
        // AI-only product: Luna may reason over the complete registered design vocabulary.
        // Plans gate product capabilities (standard pages / Posts & Updates / Commerce),
        // not visual Spark or template quality.
        $usable=[];
        if($namedTargetOverrodeSelection){
            // Clear stale clicked-element targeting when a named page section (for example
            // "hero") overrides the previous selection, but preserve the request-scoped
            // Tailwind inventory collected for that newly resolved Spark. Without this,
            // Whole Page visual edits reach the planner with zero legal rendered slots and
            // verification correctly reports no website-state change.
            $tailwindContext=array_intersect_key($elementContext,[
                'tailwindInventory'=>true,
                'tailwind_inventory'=>true,
                'tailwindPageInventory'=>true,
                'tailwindTargetIndex'=>true,
                'tailwindTargetName'=>true,
                'tailwindSlot'=>true,
                'tailwind_slot'=>true,
            ]);
            $elementContext=$tailwindContext;
        }
        if($elementContext && $scope==='section' && isset($blocks[$targetIndex])){
            $elementContext['matched_paths']=$smartSparkEditing->resolveMatchedPaths($blocks[$targetIndex],$elementContext,(string)$validated['prompt']);
        }
        $selectedCapabilityBlock=isset($blocks[$targetIndex])&&is_array($blocks[$targetIndex])?$blocks[$targetIndex]:[];
        $editCapabilityPayload=$sparkEditCapabilities->payloadForRequest(
            (string)$validated['prompt'],
            is_string($selectedCapabilityBlock['type']??null)?$selectedCapabilityBlock['type']:null,
            $selectedCapabilityBlock,
            $elementContext,
            is_array($canonicalIntent)?$canonicalIntent:[],
        );
        $usable=is_array($editCapabilityPayload['catalog']??null)?$editCapabilityPayload['catalog']:[];
        if($scope==='page' && is_array($elementContext['tailwindPageInventory']??null)){
            $pageInventory=$elementContext['tailwindPageInventory'];
            $resolvedInventory=$pageInventory[$targetIndex]??$pageInventory[(string)$targetIndex]??null;
            if(is_array($resolvedInventory)) $elementContext['tailwindInventory']=$resolvedInventory;
        }
        $tailwindEditContext=$selectedCapabilityBlock ? $tailwindMutations->plannerContext($selectedCapabilityBlock,$elementContext) : [];
        $catalogJson=json_encode($usable,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $targetContext=$scope==='section' && isset($blocks[$targetIndex])
            ? json_encode($blocks[$targetIndex],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
            : '{}';
        $blockSummary=collect($blocks)->values()->map(fn($block,$i)=>[
            'index'=>$i,
            'type'=>$block['type']??'',
            'heading'=>$block['heading']??$block['title']??$block['eyebrow']??'',
            'theme'=>$block['theme']??'auto',
        ])->all();
        $siblingPages=$website->pages()
            ->select(['id','title','slug','page_style','blocks'])
            ->orderBy('sort_order')->orderBy('id')->limit(30)->get()
            ->map(function($sibling){
                $blocks=is_array($sibling->blocks)?$sibling->blocks:[];
                return [
                    'id'=>$sibling->id,'title'=>$sibling->title,'slug'=>$sibling->slug,'page_style'=>$sibling->page_style,
                    'sections'=>collect($blocks)->values()->take(20)->map(fn($block,$i)=>[
                        'index'=>$i,
                        'type'=>$block['type']??'',
                        'heading'=>$block['heading']??$block['title']??$block['eyebrow']??'',
                        'theme'=>$block['theme']??'auto',
                        'luna_design_overrides'=>$block['luna_design_overrides']??[],
                    ])->all(),
                ];
            })->all();

        $system=<<<'PROMPT'
You are Luna, the invisible design orchestrator inside Cosmic CMS.
The user never sees the Spark library or Theme picker. Your job is to make natural-language website changes feel magical while ONLY using registered usable Sparks.

Return JSON only:
{
 "operations":[
   {"action":"edit","index":0,"changes":{},"tailwind_mutations":[{"slot":"rendered slot id","add":["Tailwind utility"],"remove":["conflicting current utility"]}]},
   {"action":"replace","index":0,"spark_key":"exact_registered_key","instruction":"content/style migration instruction"},
   {"action":"insert_before|insert_after","index":0,"spark_key":"exact_registered_key","instruction":"what this new section should contain"},
   {"action":"delete","index":0},
   {"action":"move","index":0,"to_index":2},
   {"action":"theme","theme_key":"midnight|emerald|coffee|rose|ocean|indigo|amber|charcoal|violet|teal|ruby|forest|obsidian|navy|espresso|terracotta|asphalt"}
 ],
 "header_changes":{},
 "footer_changes":{},
 "brand_color_family":null,
 "page_style":"balanced|clean|premium|null"
}

RULES:
- This is an internal target/change planner. Never return reply, message, response, suggestion, question, confirmation copy, or any user-facing text.
- Intermediate output is JSON only. The server composes a natural reply after verified execution.
- DOCUMENTATION GROUNDING: LUNA KNOWLEDGE PACKET is the canonical basis for Cosmic CMS capability/product claims. Respect status, limits, cannot_do, fallback, confirmation, and verification fields. If support is not established, do not invent it. Capability questions are informational only: reply from the packet with no website mutation.
- Use ONLY spark_key values present in USABLE SPARK CATALOG.
- HEADER NAVIGATION: when the target is Header Navigation or the user asks to add/remove/rename/reorder menu items, create/remove submenus, use `header_changes` only. Return the COMPLETE resulting `menu` array with items shaped as {label,url,children}. Maximum nesting is 3 levels total. Preserve unrelated menu items. Do not return `mega_menu_enabled`; header navigation does not support mega menus. Do not alter body sections for a navigation-only request.

- FOOTER: when target scope is footer, use `footer_changes` only unless the user explicitly requests a body/header change. Preserve the existing footer and return only intended changed fields. Mega footer columns use {title,items:[{label,url}]}; contact uses {email,phone,address}; social links use [{label,url}]. Manual-equivalent footer structure is global site state.
- CROSS-PAGE REFERENCES: when the user says match Home/About/Services/etc, use SIBLING PAGE REFERENCES as design context. Preserve current-page content unless explicitly asked to replace it. Edit the current page only; never claim another page changed.
- SPARK SELECTION RANKING: requested media/capability/aliases are the strongest signal; current selected section intent/category is second; layout/style/industry/position fit are third. Do NOT prefer Hero merely because a candidate is a Hero.
- A request for "slider" means consider EVERY catalog item whose media=slider, aliases mention slider/carousel/slideshow, or capabilities include supports-slider. Then choose the one whose intent best matches the selected section. Example: testimonials -> testimonial carousel; portfolio/work -> gallery/project slider; opening banner -> hero slider.
- Preserve semantic role when transforming media unless the user explicitly asks to change the role. A mid-page services/work/testimonial section should not become a Hero just because Hero has the requested media.
- Use position_fit=top/opening-section-safe as a bonus only when the target is actually the first/opening section.
- THEME STICKINESS: Existing website theme/brand colors are persistent. Do NOT emit a theme operation for redesigns, new sections, different industries, "make it premium", or other normal design requests. Emit action=theme ONLY when the user explicitly asks to change/switch the theme, color scheme/palette, named theme, or rebrand.
- COLOR FAMILY DESIGN: If the user supplies an explicit HEX and asks to make/change the website theme, brand, palette, colors, or color family around it, do NOT pick a named theme. Return `brand_color_family` using exactly this semantic schema: {sourceColor,primary,primaryHover,primarySoft,secondary,accent,background,surface,surfaceMuted,heading,text,muted,border,buttonPrimary,buttonText,buttonSecondary,buttonSecondaryText,success,warning,error,onPrimary,onDark,gradient:{from,via,to,glow,angle}}. Every color is #RRGGBB. Keep `primary` and `buttonPrimary` equal to the exact user HEX. The server will classify the HEX into exactly one of two visual patterns: DARK anchor = brand-colored primary hero; LIGHT anchor = derived dark-secondary hero with the exact light HEX used as a visible CTA/accent. Design a restrained premium family with readable surfaces and purposeful accent separation. Do not create a random rainbow palette. The server will validate contrast and repair unsafe values.
- Never reveal implementation details such as Spark IDs, templates, schemas or hidden selection.
- For a simple copy/color/image/repeater adjustment, prefer edit. In section scope, use keys exactly from SELECTED BLOCK FULL JSON; do not invent schema keys.
- REPEATER/LIST CRUD MUST PRESERVE LAYOUT: add/remove/update/rename/expand/reduce/reorder services, cards, items, testimonials, FAQs, team members, pricing entries, features, logos, gallery items, steps/process entries, or similar collections by editing the existing array key in SELECTED BLOCK FULL JSON. Return the complete resulting array under that same key. Never use replace for these requests unless the user explicitly asks to change the layout/design/section type.
- SMART SPARK EDIT CONTRACT is authoritative for schema-preserving edits. Unknown keys must not be invented. Element edits must use resolved matched paths. Add/remove item requests must preserve the existing repeater schema and sibling items.
- STRUCTURAL COMMANDS MUST EMIT STRUCTURAL OPERATIONS. For "change/turn this banner/section into a video", "use a slider", "make this testimonials", etc., use replace on the exact target index with the closest registered Spark. Never answer a structural request with only edit/copy changes.
- For "add X below/above", use insert_after/insert_before relative to the target.
- For "move this section to the top/first", emit move with to_index=0. For "move to the bottom/last", emit move with to_index equal to the last page index. For "move up/down", emit move to the adjacent index.
- In SELECTED SECTION scope, "this", "it", "the slider", "this banner", and similar references mean the selected index unless the user explicitly names another section.
- When ELEMENT TARGET is non-empty, it identifies the exact clicked heading/text/label/button/image. For ordinary edits, change only the matching field(s) indicated by matched_paths/currentValue/url and preserve unrelated fields. For button targets, update label and URL together when the user supplies both. Explicit structural section requests override element-only targeting.
- SCOPE CONTRACT is authoritative. `element` means change only the clicked field(s). `item` means change only the selected repeater/card item when its array/index can be resolved. `section` means one section. `page` means the current page. `site` means website-level state only where supported. `global_token` means use centralized design tokens rather than editing every Spark.
- Never broaden `this`, `selected`, `only this`, or `just this` to multiple sections.
- For an item/card target, preserve sibling repeater items unless the user explicitly asks for all/every items.
- Preserve useful current content when replacing; instruction should explicitly say what to migrate.
- Do not replace a section when its current Spark can safely satisfy the request.
- Section scope: operate on the selected index only, except an explicit add-before/add-after request.
- Page scope may target sections by natural language. Resolve names like hero, services, testimonials, pricing, FAQ, contact, CTA, gallery, process, team, about from CURRENT PAGE SUMMARY headings/types and use the matching index.
- Requests like "change the hero to video", "add testimonials below services", "move FAQ above contact", or "make pricing darker" should work without the user manually selecting a section.
- Page scope: you may coordinate multiple operations and theme changes, max 8 operations.
- page_style may be balanced, clean, or premium only when explicitly requested. Use it for requests like "make the page premium/clean/balanced".
- Header/footer changes only if explicitly requested or essential to a page-wide theme request.
- Header language mapping: "float header", "overlay header", "header over hero/banner", "transparent header" means header_changes.overlay_header_on_banner=true. "put header above/outside the banner", "solid header", or "disable overlay" means false.
- DESIGN SAFETY CONTRACT: Cosmic owns raw CSS/HTML/JS and unsafe implementation mechanics. Tailwind utilities may be emitted ONLY inside operation.tailwind_mutations for rendered slot ids supplied in TAILWIND EDIT CONTEXT; never expose them to the user, never rewrite markup, and never emit arbitrary CSS/style payloads.
- Accept normal design intent (make it more prominent, more breathing room, darker/lighter, rounded, cleaner, premium, change layout) only through existing safe schema fields, registered layouts, theme/page_style, or bounded existing design controls. If the exact request cannot be represented safely, emit no styling operation and explain briefly that Luna can apply a design-system-safe equivalent instead.
- RELATIVE DESIGN RULE: when the user says a selected section should feel "more premium", "more modern", "more polished", "better", or "more visually interesting", treat that as an explicit request for a visibly different compatible section design. Never answer that it is already premium/modern. Prefer a different registered compatible layout/Spark while preserving the section purpose and core content; otherwise apply a meaningful bounded design-system-safe visual change. For a selected Services section, choose a DIFFERENT Spark from the premium Services family than the current selected type; never return the same services_* type for a relative redesign.
- CHANGE ONLY WHAT THE USER REQUESTED. Preserve all unrelated content, typography, spacing, colors, layout, and media.
- MUTATION SCOPE LOCK: rewrite/reword/copy/content-only requests may edit textual fields only. Mentions such as "keep images/layout/colors unchanged" are preservation constraints, NOT requests to mutate those fields. Never emit image/media/theme/layout/design changes for a content-only rewrite unless the user explicitly asks to change them.
- Do not invent raw HTML/CSS/JS/PHP/SQL or image URLs. Cosmic resolves requested photography through its image provider after your plan.
- Never change ecommerce/dynamic data bindings unless explicitly requested.
- Never delete content unless the user asks.
- Never claim success in reply unless the JSON contains the operation/state change that performs the request.
PROMPT;
        $system.="\nLAZY SPARK CONTRACT: For mode=selected_spark, edit only the selected Spark through its declared modules and do not emit structural operations. For mode=structural_catalog, structural operations may use only supplied catalog keys.\nTAILWIND MUTATION CONTRACT: For specific visual styling requests, prefer operation.tailwind_mutations using only slot ids in TAILWIND EDIT CONTEXT.rendered_slots. Resolve natural target words through each rendered slot's semantic aliases. Plurals/groups such as buttons/CTAs mean mutate every matching button/cta slot in the resolved section; singular primary/secondary button means only that matching slot. Preserve unrelated classes. Remove conflicting current utilities before adding replacements. Never emit raw CSS or rewrite markup. Content edits remain in changes.";

        if($resumePendingPlan && is_array($resumePendingPlan['plan']??null)){
            $plan=$resumePendingPlan['plan'];
        }else{
            $apiKey=(string)config('openai.api_key');
            abort_if($apiKey==='',503,'OpenAI is not configured.');

            $response=Http::withToken($apiKey)->timeout(150)->post(
                rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions',
                [
                    'model'=>env('OPENAI_MODEL','gpt-5-mini'),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"SCOPE: {$scope}".($scope==='section'?"\nSELECTED INDEX: {$targetIndex}":"")."\n\n".$scopeContract."\n\n".$smartEditContract."\n\n".$siteDnaContract."\n\nUSER REQUEST:\n".$validated['prompt']."\n\nINTERPRETATION:\n".(($scope==='section' && Str::contains(Str::lower((string)$validated['prompt']),['more premium','more modern','more polished','make this section better','polish this section','more visually interesting'])) ? 'This is a relative redesign request. Produce a visibly different compatible registered section layout/design while preserving purpose and core content. Do not reject it because the current section is already premium or modern.' : 'Follow the request literally within the safe schema.')."\n\nCURRENT PAGE SUMMARY:\n".json_encode($blockSummary,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nCURRENT HEADER:\n".json_encode(is_array($header)?$header:[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nCURRENT FOOTER:\n".json_encode(is_array($footer)?$footer:[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nSELECTED BLOCK FULL JSON:\n".$targetContext."\n\nELEMENT TARGET:\n".json_encode($elementContext,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nSPARK EDIT CAPABILITY:\n".json_encode($editCapabilityPayload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nTAILWIND EDIT CONTEXT:\n".json_encode($tailwindEditContext,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nCURRENT THEME:\n".json_encode(is_array($theme)?$theme:[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nSITE DESIGN MEMORY:\n".json_encode($siteMemory,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nLUNA KNOWLEDGE PACKET:\n".json_encode($knowledgePacket,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nSIBLING PAGE REFERENCES:\n".json_encode($siblingPages,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nUSABLE SPARK CATALOG:\n{$catalogJson}"],
                    ],
                ]
            )->throw()->json();

            $plan=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
        }
        if(!is_array($plan)) {
            throw ValidationException::withMessages(['prompt'=>'Luna returned an invalid design plan.']);
        }
        if(
            $capabilityQuestion
            || (
                !($feasibility['execution_allowed']??false)
                && !(($canonicalIntent['intent']??'')==='action' && ($canonicalIntent['execution_allowed']??false))
            )
        ){
            return response()->json([
                'reply'=>$naturalReplyFromFacts([
                    'action_completed'=>false,
                    'conversation_mode'=>'feasibility_first',
                    'feasibility'=>$feasibility,
                    'constraint'=>'No website mutation is allowed from this request in the current turn.',
                    'fallback'=>$feasibility['alternative']??'Explain the documented limit naturally.',
                    'next_step'=>($feasibility['feasibility']??'')==='alternative_available'
                        ? 'Offer the documented alternative and ask whether the user wants that alternative.'
                        : 'Answer naturally from canonical documentation without claiming a change.',
                ]),
                'credit_cost'=>0,
                'credit_balance'=>$user ? $credits->balance($user) : null,
                'blocks'=>$blocks,
                'header'=>$header,
                'footer'=>$footer,
                'theme_key'=>null,
                'brand_color_family'=>null,
                'page_style'=>null,
                'site_memory'=>$siteMemory,
                'mode'=>'grounded_info',
                'grounded_capabilities'=>array_values(array_filter(array_map(fn($cap)=>$cap['id']??null,$knowledgePacket['capabilities']??[]))),
                'applied_operations'=>[],
            ]);
        }

        $operations=array_values(array_slice(is_array($plan['operations']??null)?$plan['operations']:[],0,8));

        $requestLower=Str::lower((string)$validated['prompt']);
        $explicitBrandPrimary=$this->lunaExplicitBrandColor((string)$validated['prompt']);
        if($explicitBrandPrimary){
            $operations=array_values(array_filter($operations,fn($op)=>!(is_array($op)&&($op['action']??'')==='theme')));
        } elseif($requestedThemeKey=$this->lunaRequestedThemeKey((string)$validated['prompt'])) {
            $operations=array_values(array_filter($operations,fn($op)=>!(is_array($op)&&($op['action']??'')==='theme')));
            $operations[]=['action'=>'theme','theme_key'=>$requestedThemeKey];
        }
        if($scope==='section' && isset($blocks[$targetIndex])){
            $moveTo=null;
            if(Str::contains($requestLower,['top of the page','top of page','first section','move it to the top','move this to the top','move to top'])) $moveTo=0;
            elseif(Str::contains($requestLower,['bottom of the page','bottom of page','last section','move it to the bottom','move this to the bottom','move to bottom'])) $moveTo=max(0,count($blocks)-1);
            elseif(Str::contains($requestLower,['move up','one section up','move it up'])) $moveTo=max(0,$targetIndex-1);
            elseif(Str::contains($requestLower,['move down','one section down','move it down'])) $moveTo=min(max(0,count($blocks)-1),$targetIndex+1);
            if($moveTo!==null){
                $operations=array_values(array_filter($operations,fn($op)=>!(is_array($op)&&($op['action']??'')==='move')));
                $operations[]=['action'=>'move','index'=>$targetIndex,'to_index'=>$moveTo];
            }

            $transformRequested=Str::contains($requestLower,['change this','turn this','convert this','replace this','make this','change the banner','change banner','change the hero','change hero']);
            $desiredKind=null;
            foreach(['slider','video','testimonial','gallery','pricing','faq','services','contact'] as $kind){
                if(Str::contains($requestLower,$kind)){$desiredKind=$kind;break;}
            }
            $hasReplace=collect($operations)->contains(fn($op)=>is_array($op)&&($op['action']??'')==='replace'&&(int)($op['index']??-1)===$targetIndex);
            if($transformRequested && $desiredKind && !$hasReplace){
                $currentType=Str::lower((string)($blocks[$targetIndex]['type']??''));
                $currentMeta=SparkCatalog::find((string)($blocks[$targetIndex]['type']??''))??[];
                $currentCategory=Str::lower((string)($currentMeta['category']??''));
                $currentIntent=collect($currentMeta['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $isOpening=$targetIndex===0 || Str::contains($currentType,['hero','banner']);
                $candidate=collect($usable)
                    ->map(function($spark) use($desiredKind,$currentCategory,$currentIntent,$isOpening){
                        $aliases=collect($spark['aliases']??[])->map(fn($v)=>Str::lower((string)$v))->implode(' ');
                        $caps=collect($spark['capabilities']??[])->map(fn($v)=>Str::lower((string)$v))->implode(' ');
                        $haystack=Str::lower(($spark['key']??'').' '.($spark['name']??'').' '.($spark['description']??'').' '.$aliases.' '.($spark['media']??'').' '.$caps);
                        $matches=Str::contains($haystack,$desiredKind)
                            || ($desiredKind==='slider' && (($spark['media']??'')==='slider' || Str::contains($caps,'supports-slider')));
                        if(!$matches) return null;
                        $score=0;
                        if(Str::lower((string)($spark['media']??''))===$desiredKind) $score+=120;
                        if(Str::contains($aliases,$desiredKind)) $score+=100;
                        if(Str::contains($caps,'supports-'.$desiredKind)) $score+=90;
                        if($currentCategory!=='' && Str::lower((string)($spark['category']??''))===$currentCategory) $score+=70;
                        $sparkIntent=collect($spark['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $score+=count(array_intersect($currentIntent,$sparkIntent))*35;
                        $positions=collect($spark['position_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        if($isOpening && (in_array('top',$positions,true)||in_array('opening-section-safe',$spark['capabilities']??[],true))) $score+=35;
                        if(!$isOpening && Str::lower((string)($spark['category']??''))==='hero') $score-=80;
                        if(Str::contains(Str::lower((string)($spark['name']??'')),'premium')) $score+=5;
                        $spark['_luna_score']=$score;
                        return $spark;
                    })->filter()->sortByDesc('_luna_score')->first();
                if($desiredKind==='video' && Str::contains($normalizedPrompt,['video background','background video'])){
                    $backgroundVideoCandidate=collect($usable)->first(fn($spark)=>is_array($spark) && ($spark['key']??'')==='hero_video_background');
                    if(is_array($backgroundVideoCandidate)) $candidate=$backgroundVideoCandidate;
                }
                if(is_array($candidate) && !empty($candidate['key'])){
                    $operations[]=[
                        'action'=>'replace',
                        'index'=>$targetIndex,
                        'spark_key'=>$candidate['key'],
                        'instruction'=>"Transform the selected section into a {$desiredKind} while preserving relevant existing content and brand direction.",
                    ];
                }
            }
        }


        // Generic section redesign must produce a materially different, generatable Spark.
        if($scope==='section' && isset($blocks[$targetIndex])){
            $genericRedesign=Str::contains($requestLower,[
                'redesign this section','redesign the section','redesign this','another layout','different layout',
                'new layout','make this section better','make this better','improve this section',
                'make this section more premium','make this more premium','make this section modern',
                'make this more modern','make this section polished','make this more polished',
                'refresh this section','rework this section','restyle this section'
            ]);

            if($genericRedesign){
                $current=(array)$blocks[$targetIndex];
                $currentKey=(string)($current['type']??'');
                $currentMeta=SparkCatalog::find($currentKey)??[];
                $category=Str::lower((string)($currentMeta['category']??''));
                $intent=collect($currentMeta['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $industry=collect($currentMeta['industry_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $position=collect($currentMeta['position_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $currentLayouts=collect($currentMeta['layout']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $schemaKeys=collect(array_keys(SchemaManager::map()))->flip();

                $ranked=collect($usable)
                    ->filter(fn($spark)=>is_array($spark)
                        && !empty($spark['key'])
                        && ($spark['key']??'')!==$currentKey
                        && $schemaKeys->has((string)($spark['key']??'')))
                    ->map(function($spark) use($category,$intent,$industry,$position,$currentLayouts,$requestLower){
                        $score=0;
                        $sparkCategory=Str::lower((string)($spark['category']??''));
                        if($category!=='' && $sparkCategory===$category) $score+=260;
                        elseif($category!=='' && in_array($sparkCategory,['hero','header','footer'],true)) $score-=300;
                        else $score-=80;

                        $sparkIntent=collect($spark['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $sparkIndustry=collect($spark['industry_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $sparkPosition=collect($spark['position_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $sparkLayouts=collect($spark['layout']??[])->map(fn($v)=>Str::lower((string)$v))->all();

                        $score+=count(array_intersect($intent,$sparkIntent))*45;
                        $score+=count(array_intersect($industry,$sparkIndustry))*12;
                        $score+=count(array_intersect($position,$sparkPosition))*8;

                        $layoutOverlap=count(array_intersect($currentLayouts,$sparkLayouts));
                        if($currentLayouts!==[] && $sparkLayouts!==[] && $layoutOverlap===0) $score+=120;
                        elseif($layoutOverlap>0) $score-=45;

                        $style=collect($spark['style']??[])->map(fn($v)=>Str::lower((string)$v))->implode(' ');
                        if(Str::contains($requestLower,'premium') && Str::contains($style,['premium','editorial','cinematic','luxury'])) $score+=55;
                        if(Str::contains($requestLower,'modern') && Str::contains($style,['modern','clean','editorial','minimal'])) $score+=38;
                        if(Str::contains($requestLower,'visually interesting') && Str::contains($style,['premium','editorial','bold','modern'])) $score+=30;

                        $score+=(abs(crc32(($spark['key']??'').'|'.$requestLower))%19);
                        $spark['_luna_redesign_score']=$score;
                        return $spark;
                    })->sortByDesc('_luna_redesign_score')->values();

                $candidate=null;
                if($category==='services'){
                    // Route explicit design language to the matching premium Services family.
                    // This prevents generic fixed-order fallback from repeatedly choosing
                    // Editorial/Showcase regardless of what the user actually asked for.
                    $semanticServiceKey=null;
                    if(Str::contains($requestLower,['contrast','high-contrast','high contrast','bold','performance-focused','performance focused','dark'])){
                        $semanticServiceKey='services_contrast_premium';
                    } elseif(Str::contains($requestLower,['minimal luxury','quiet luxury','minimal and luxurious','minimal','luxurious','whitespace-heavy','whitespace heavy'])){
                        $semanticServiceKey='services_minimal_luxury';
                    } elseif(Str::contains($requestLower,['editorial','magazine','asymmetric','asymmetrical'])){
                        $semanticServiceKey='services_editorial_premium';
                    } elseif(Str::contains($requestLower,['split layout','split layouts','alternating split','alternating','split service','split services'])){
                        $semanticServiceKey='services_split_premium';
                    } elseif(Str::contains($requestLower,['3-column','3 column','three-column','three column','grid premium','service grid','services grid'])){
                        $semanticServiceKey='services_grid_premium';
                    } elseif(Str::contains($requestLower,['featured service','feature one service','one featured service','prominently featured','supporting service cards','supporting cards'])){
                        $semanticServiceKey='services_feature_premium';
                    } elseif(Str::contains($requestLower,['large imagery','large image','large automotive image','more visual','image-led','image led','showcase'])){
                        $semanticServiceKey='services_showcase_premium';
                    } elseif(Str::contains($requestLower,['bento','varied card sizes','varied cards'])){
                        $semanticServiceKey='services_bento_premium';
                    }

                    if($semanticServiceKey!==null && $semanticServiceKey!==$currentKey){
                        $match=$ranked->first(fn($spark)=>(string)($spark['key']??'')===$semanticServiceKey);
                        if(is_array($match) && ($match['_luna_redesign_score']??0)>0){
                            $candidate=$match;
                        }
                    }

                    // Generic "more premium/better" requests choose the highest-ranked
                    // materially different Services design rather than a hard-coded first item.
                    if($candidate===null){
                        $premiumFamily=[
                            'services_bento_premium',
                            'services_editorial_premium',
                            'services_showcase_premium',
                            'services_minimal_luxury',
                            'services_contrast_premium',
                            'services_split_premium',
                            'services_grid_premium',
                            'services_feature_premium',
                        ];
                        $candidate=$ranked->first(fn($spark)=>
                            in_array((string)($spark['key']??''),$premiumFamily,true)
                            && (string)($spark['key']??'')!==$currentKey
                            && ($spark['_luna_redesign_score']??0)>0
                        );
                    }
                }
                $candidate=$candidate
                    ?? $ranked->first(fn($spark)=>
                        Str::lower((string)($spark['category']??''))===$category
                        && ($spark['_luna_redesign_score']??0)>0
                    )
                    ?? $ranked->first(fn($spark)=>($spark['_luna_redesign_score']??0)>0);

                if(is_array($candidate) && !empty($candidate['key'])){
                    // Generic redesign owns the target operation. Discard model no-op/
                    // unsupported target edits and force one verified alternative.
                    $operations=array_values(array_filter($operations,function($op) use($targetIndex){
                        if(!is_array($op)) return true;
                        if((int)($op['index']??-1)!==$targetIndex) return true;
                        return !in_array(($op['action']??''),['edit','replace'],true);
                    }));
                    $operations[]=[
                        'action'=>'replace',
                        'index'=>$targetIndex,
                        'spark_key'=>(string)$candidate['key'],
                        'instruction'=>'Preserve the selected section purpose, useful copy, service/item meaning, CTA intent, and current website theme, but migrate them into this materially different layout.',
                        'redesign_from'=>$currentKey,
                    ];
                }
            }
        }

        if(
            $resumePendingPlan
            && is_array($resumePendingPlan['operations']??null)
            && array_values($resumePendingPlan['operations'])!==[]
        ){
            $operations=array_values($resumePendingPlan['operations']);
        }

        // Nested router Batch 4: API 4 owns edits for the exact API-3-selected
        // Spark. Do not require the old mutation planner to invent an edit plan.
        $nestedSparkTarget=data_get($canonicalIntent,'routing.spark_target');
        if(data_get($canonicalIntent,'routing.menu_scope')==='sparks' && is_array($nestedSparkTarget)){
            $nestedIndex=(int)($nestedSparkTarget['index']??-1);
            if($nestedIndex>=0 && isset($blocks[$nestedIndex])){
                $operations=[['action'=>'edit','index'=>$nestedIndex,'changes'=>[]]];
                $scope='section';
                $targetIndex=$nestedIndex;
            }
        }

        // Batch 12 recovery uses the exact final operation plan. It never asks the
        // model to reinterpret the request during recovery.
        $recoveryPlannedOperations=$operations;

        $pricing=$lunaPricing->estimate((string)$validated['prompt'],$operations,$scope);
        $creditCost=(int)($pricing['credits']??0);
        // Batch 2 direct execution: normal build/update/publish/navigate actions
        // never enter the legacy pending-confirmation pipeline. Keep a safety
        // confirmation only for destructive delete operations.
        $needsLargeConfirmation=!$capabilityQuestion
            && (($knowledgePacket['query_type']??'')!=='capability_question')
            && !($feasibility['informational']??false)
            && !$resumePendingPlan
            && (($canonicalIntent['intent']??'')==='action')
            && (($canonicalIntent['action']??'')==='delete');

        if($needsLargeConfirmation && !($validated['confirmed']??false)){
            // Destructive safety confirmation is not an AI/API operation.
            $planningCost=0;

            $blocksFingerprint=hash('sha256',json_encode(array_values($blocks),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
            $pendingToken=$pendingActions->put($pendingActor,[
                'kind'=>'builder_destructive_delete',
                'prompt'=>(string)$validated['prompt'],
                'scope'=>$scope,
                'target_index'=>$targetIndex,
                'plan'=>$plan,
                'operations'=>$operations,
                'canonical_schema'=>$canonicalIntent,
                'blocks_fingerprint'=>$blocksFingerprint,
                'estimated_execution_cost'=>$creditCost,
                'website_id'=>$website->id,
            ]);

            $reply=$natural->compose((string)$validated['prompt'],[
                'authenticated'=>true,
                'scope'=>$scope,
                'website'=>$website->name,
                'canonical_knowledge'=>$knowledgePacket,
            ],[
                'action_completed'=>false,
                'confirmation_required'=>true,
                'pending_action_stored'=>true,
                'pending_action_token'=>$pendingToken,
                'planned_operations'=>$operations,
                'estimated_execution_cost'=>$creditCost,
                'planning_credits_used'=>$planningCost,
                'next_step'=>'Ask for an explicit deletion confirmation using the Delete button. This safety confirmation costs 0 credits.',
                'rule'=>'Do not claim the website changed. Ask only whether to delete the selected content. Never say Proceed, Continue, Shall I proceed, or expose implementation details.',
            ]);
            return response()->json([
                'reply'=>$reply,
                'mode'=>'confirm',
                'pending_action'=>true,
                'pending_action_token'=>$pendingToken,
                'canonical_intent'=>$canonicalIntent,
                'confirmation_cost'=>$creditCost,
                'credit_cost'=>$planningCost,
                'credit_balance'=>$user ? $credits->balance($user) : null,
            ]);
        }
        if($creditCost>0 && $user && !$credits->canAfford($user,$creditCost)){
            return response()->json([
                'message'=>"This Luna change needs {$creditCost} credits, but your balance is too low.",
                'credit_cost'=>$creditCost,
                'requires_credits'=>true,
            ],422);
        }
        $catalogKeys=collect($usable)->pluck('key')->flip();
        $nextBlocks=array_values($blocks);
        $fullSchemaEdited=false;
        $beforeFingerprint=hash('sha256',json_encode($nextBlocks,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
        $applied=[];

        foreach($operations as $operation){
            if(!is_array($operation)) continue;
            $action=(string)($operation['action']??'');
            $index=(int)($operation['index']??-1);

            if($scope==='section' && !in_array($action,['insert_before','insert_after'],true) && $index!==$targetIndex) {
                continue;
            }

            if($action==='edit' && isset($nextBlocks[$index]) && (is_array($operation['changes']??null)||is_array($operation['tailwind_mutations']??null))){
                $current=$nextBlocks[$index];
                $changes=is_array($operation['changes']??null)?$operation['changes']:[];
                unset($changes['type'],$changes['_renderKey']);

                $sparkTarget=data_get($canonicalIntent,'routing.spark_target');
                if(!$fullSchemaEdited && data_get($canonicalIntent,'routing.menu_scope')==='sparks'
                    && is_array($sparkTarget) && (int)($sparkTarget['index']??-1)===$index){
                    $schemaResult=$sparkSchemaEditor->edit($prompt,$current,$sparkTarget,$elementContext,$builderConversation);
                    if(($schemaResult['ok']??false)===true){
                        $current=$schemaResult['block'];
                        $nextBlocks[$index]=$current;
                        $fullSchemaEdited=true;
                        if(($schemaResult['changed']??false)===true){
                            $applied[]=[
                                'action'=>'spark_full_schema_edit',
                                'index'=>$index,
                                'spark_type'=>$sparkTarget['type'],
                                'diff'=>$schemaResult['diff']??[],
                                'before_fingerprint'=>$schemaResult['before_fingerprint']??null,
                                'after_fingerprint'=>$schemaResult['after_fingerprint']??null,
                                'verified'=>true,
                            ];
                        }
                        continue;
                    }
                    Log::warning('Luna full Spark schema edit rejected',[
                        'website_id'=>$website->id,
                        'spark_type'=>$current['type']??null,
                        'index'=>$index,
                        'reason'=>$schemaResult['reason']??'unknown',
                        'details'=>array_diff_key($schemaResult,['block'=>true,'editable'=>true,'tailwind'=>true]),
                    ]);
                    // Nested Spark edits are fail-closed: a rejected full schema
                    // is never reinterpreted by the old mutation engine.
                    $fullSchemaEdited=true;
                    continue;
                }

                $tailwindResult=$tailwindMutations->apply($current,is_array($operation['tailwind_mutations']??null)?$operation['tailwind_mutations']:[],$elementContext);
                if(($tailwindResult['applied']??[])!==[]){
                    $current=$tailwindResult['block'];
                    $nextBlocks[$index]=$current;
                    $applied[]=['action'=>'tailwind_edit','index'=>$index,'mutations'=>$tailwindResult['applied'],'verified'=>true];
                }
                if($changes===[]) continue;
                if($contentOnlyIntent){
                    // Text-only means text-only. Strip media, layout, theme and runtime
                    // fields even if the planner returns them. Nested repeaters are
                    // sanitized recursively so existing card/item imagery survives.
                    $stripNonTextMutation=function($value) use (&$stripNonTextMutation){
                        if(!is_array($value)) return $value;
                        $clean=[];
                        foreach($value as $key=>$item){
                            $name=is_string($key)?Str::lower($key):'';
                            if($name!=='' && preg_match('/(image|photo|picture|avatar|thumbnail|video|media|background|theme|style|layout|spacing|padding|margin|color|colour|font|radius|overlay|position|animation)/i',$name)){
                                continue;
                            }
                            $clean[$key]=is_array($item)?$stripNonTextMutation($item):$item;
                        }
                        return $clean;
                    };
                    $changes=$stripNonTextMutation($changes);
                }
                if(in_array(($current['type']??''),['services_bento_premium','services_editorial_premium','services_showcase_premium','services_minimal_luxury','services_contrast_premium','services_split_premium','services_grid_premium','services_feature_premium'],true)){
                    $bentoCrudKeys=array_flip([
                        'service_count',
                        'featured_number','featured_title','featured_text','featured_meta',
                        'featured_image_url',
                        'service_two_number','service_two_title','service_two_text',
                        'service_two_image_url',
                        'service_three_number','service_three_title','service_three_text',
                        'service_three_image_url',
                        'service_four_number','service_four_title','service_four_text',
                        'service_four_image_url',
                        'service_five_number','service_five_title','service_five_text',
                        'service_five_image_url',
                        'service_six_number','service_six_title','service_six_text',
                        'service_six_image_url',
                        'service_seven_number','service_seven_title','service_seven_text',
                        'service_seven_image_url',
                        'proof_value','proof_label'
                    ]);
                    $changes=array_intersect_key($changes,$current+$bentoCrudKeys);
                    if(isset($changes['service_count'])){
                        $changes['service_count']=max(1,min(7,(int)$changes['service_count']));
                    }
                }else{
                    $changes=array_intersect_key($changes,$current);
                }

                $repeaterResult=$smartSparkEditing->applyRepeaterIntent(
                    (string)$validated['prompt'],
                    $current,
                    $elementContext
                );
                if(is_array($repeaterResult) && ($repeaterResult['action']??'')!=='repeater_remove_unresolved'){
                    $nextBlocks[$index]=$repeaterResult['block'];
                    $applied[]=[
                        'action'=>$repeaterResult['action'],
                        'index'=>$index,
                        'collection'=>$repeaterResult['collection']??null,
                        'item_index'=>$repeaterResult['item_index']??null,
                        'verified'=>true,
                    ];
                    continue;
                }

                $sanitizedEdit=$smartSparkEditing->sanitizeEdit($current,$changes,$elementContext);
                $nextBlocks[$index]=$sanitizedEdit['block'];
                if(($sanitizedEdit['paths']??[])!==[]){
                    $applied[]=[
                        'action'=>'edit',
                        'index'=>$index,
                        'mode'=>$sanitizedEdit['mode']??'block',
                        'paths'=>$sanitizedEdit['paths']??[],
                        'verified'=>true,
                    ];
                }
                continue;
            }

            if(in_array($action,['replace','insert_before','insert_after'],true)){
                $sparkKey=trim((string)($operation['spark_key']??''));
                if($sparkKey==='' || !$catalogKeys->has($sparkKey)) continue;

                $reference=$nextBlocks[$index]??null;
                $migration=is_array($reference)
                    ? json_encode(array_intersect_key($reference,array_flip([
                        'heading','title','eyebrow','text','description','body','subheading',
                        'primary_label','primary_url','secondary_label','secondary_url',
                        'cta_label','cta_url','items','cards','services','features','steps',
                        'slides','images','image_url','video_url','theme'
                    ])),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
                    : '{}';
                $instruction=trim((string)($operation['instruction']??''));
                $groundedMediaPrompt=$this->lunaGroundedMediaPrompt((string)$validated['prompt'],$nextBlocks,$siteMemory,is_array($reference)?$reference:[]);
                $generationPrompt=$groundedMediaPrompt."\n\nLUNA ORCHESTRATION:\nBuild exactly one {$sparkKey} Spark."
                    .($instruction!==''?"\nInstruction: {$instruction}":'')
                    ."\nPreserve/adapt useful content from this source block when relevant:\n{$migration}";

                try {
                    $generated=$lunaPages->generate($generationPrompt,[$sparkKey]);
                    $newBlock=is_array($generated[0]??null)?$generated[0]:null;
                } catch(\Throwable $e) {
                    report($e);
                    $newBlock=null;
                }

                if(!$newBlock && $action==='replace' && is_array($reference)){
                    $referenceMeta=SparkCatalog::find((string)($reference['type']??''))??[];
                    $targetMeta=SparkCatalog::find($sparkKey)??[];
                    if(Str::lower((string)($referenceMeta['category']??''))==='services'
                        && Str::lower((string)($targetMeta['category']??''))==='services'){
                        $newBlock=$this->lunaServiceRedesignFallback($reference,$sparkKey);
                    }
                }
                if(!$newBlock) continue;
                try {
                    $remote=$pageGeneration->applyStartPageRemoteImages($generationPrompt,[$newBlock]);
                    if(is_array($remote['blocks'][0]??null)) {
                        $newBlock=$remote['blocks'][0];
                    }
                } catch(\Throwable $e) {
                    report($e); // preserve generated Spark if Unsplash/remote imagery is temporarily unavailable.
                }
                $newBlock['type']=$sparkKey;
                $usedDeterministicRedesignFallback=isset($newBlock['_luna_redesign_fallback']);
                unset($newBlock['_luna_redesign_fallback']);
                try {
                    $videoBlocks=$lunaVideos->apply($generationPrompt,[$newBlock]);
                    if(is_array($videoBlocks[0]??null)) {
                        $newBlock=$videoBlocks[0];
                    }
                } catch(\Throwable $e) {
                    report($e); // keep the generated Spark and local fallback if Pexels is temporarily unavailable.
                }
                $newBlock['_renderKey']='luna-'.Str::lower(Str::random(10));

                if($action==='replace' && isset($nextBlocks[$index])){
                    $oldType=(string)($nextBlocks[$index]['type']??'');
                    $nextBlocks[$index]=$newBlock;
                    $applied[]=[
                        'action'=>'replace',
                        'index'=>$index,
                        'type'=>$sparkKey,
                        'from_type'=>$oldType,
                        'verified_type_change'=>$oldType!==$sparkKey,
                        'content_fallback'=>$usedDeterministicRedesignFallback,
                    ];
                } elseif($action==='insert_before'){
                    $at=max(0,min(count($nextBlocks),$index));
                    array_splice($nextBlocks,$at,0,[$newBlock]);
                    $applied[]=['action'=>'insert_before','index'=>$at,'type'=>$sparkKey];
                } else {
                    $at=max(0,min(count($nextBlocks),$index+1));
                    array_splice($nextBlocks,$at,0,[$newBlock]);
                    $applied[]=['action'=>'insert_after','index'=>$at,'type'=>$sparkKey];
                }
                continue;
            }

            if($action==='delete' && isset($nextBlocks[$index])){
                array_splice($nextBlocks,$index,1);
                $applied[]=['action'=>'delete','index'=>$index];
                continue;
            }

            if($action==='move' && isset($nextBlocks[$index])){
                $to=max(0,min(count($nextBlocks)-1,(int)($operation['to_index']??$index)));
                $moving=$nextBlocks[$index];
                array_splice($nextBlocks,$index,1);
                array_splice($nextBlocks,$to,0,[$moving]);
                $applied[]=['action'=>'move','index'=>$index,'to_index'=>$to];
            }
        }

        $designApplied=false;
        $designChanges=[];
        $designPrompt=(string)$validated['prompt'];
        $designTargets=$contentOnlyIntent ? [] : ($designGlobalIntent ? array_keys($nextBlocks) : (($scope==='section'&&isset($nextBlocks[$targetIndex]))?[$targetIndex]:[]));
        foreach($designTargets as $designIndex){
            if(!isset($nextBlocks[$designIndex])||!is_array($nextBlocks[$designIndex]))continue;
            $currentDesign=is_array($nextBlocks[$designIndex]['luna_design_overrides']??null)?$nextBlocks[$designIndex]['luna_design_overrides']:[];
            $referenceIndex=$this->lunaReferenceSectionIndex($designPrompt,$nextBlocks,(int)$designIndex);
            $reference=$referenceIndex!==null&&isset($nextBlocks[$referenceIndex])?$nextBlocks[$referenceIndex]:[];
            $changes=$this->lunaRelativeDesignIntent($designPrompt,$currentDesign,$reference);
            if(empty($changes))continue;
            $qa=$this->lunaDesignQa(array_merge($currentDesign,$changes));
            $nextBlocks[$designIndex]['luna_design_overrides']=$qa['overrides'];
            $designChanges=array_merge($designChanges,$changes);
            $designQaNotes=array_values(array_unique(array_merge($designQaNotes??[],$qa['notes'])));
            $designApplied=true;
        }
        if($designApplied){
            $applied[]=['action'=>'design_overrides','scope'=>$designGlobalIntent?'page':'section','changes'=>$designChanges,'qa_notes'=>$designQaNotes??[],'verified'=>true];
        }

        $backgroundApplied=false;
        $backgroundError=null;
        if($scope==='section' && $universalBackgroundIntent && isset($nextBlocks[$targetIndex])){
            $resolvedState=$this->universalBackgroundState(
                $namedTargetOverrodeSelection ? '' : (string)($validated['target_resolved_theme']??''),
                $nextBlocks[$targetIndex],
                $targetIndex
            );
            $strength=$backgroundDarker?'darker':($backgroundLighter?'lighter':null);
            $result=$this->applyUniversalBackgroundIntent(
                $pageGeneration,
                (string)$validated['prompt'],
                $nextBlocks[$targetIndex],
                $resolvedState,
                $backgroundRemove,
                $strength
            );
            $nextBlocks[$targetIndex]=$result['block'];
            $backgroundApplied=(bool)$result['success'];
            $backgroundError=$result['error']??null;
            if($backgroundApplied){
                $applied[]=[
                    'action'=>$backgroundRemove?'remove_universal_background':'universal_background',
                    'index'=>$targetIndex,
                    'state'=>$resolvedState,
                    'strength'=>$strength?:'balanced',
                ];
            }
        }

        // A universal background request owns only its isolated background slot.
        // Do not also refresh every existing image in the selected Spark.
        $imageRequest=!$contentOnlyIntent && !$universalBackgroundIntent && $explicitMediaMutation;
        $imageRefreshSucceeded=null;
        $imageRefreshError=null;
        if($imageRequest){
            $imageSnapshot=function($value) use (&$imageSnapshot){
                if(!is_array($value)) return [];
                $found=[];
                foreach($value as $key=>$item){
                    if(is_string($key) && preg_match('/(image|photo|picture|avatar|thumbnail|background).*?(url)?$/i',$key) && is_string($item) && trim($item)!==''){
                        $found[$key]=$item;
                    } elseif(is_array($item)){
                        foreach($imageSnapshot($item) as $childKey=>$childValue){
                            $found[$key.'.'.$childKey]=$childValue;
                        }
                    }
                }
                return $found;
            };
            try {
                if($scope==='section' && isset($nextBlocks[$targetIndex])){
                    $elementType=Str::lower((string)($elementContext['type']??''));
                    $matchedPaths=array_values(array_filter((array)($elementContext['matched_paths']??[]),fn($path)=>is_string($path)&&trim($path)!==''));
                    if(Str::contains($elementType,'image') && !empty($matchedPaths)){
                        $probe=[
                            'type'=>$nextBlocks[$targetIndex]['type']??'website_section',
                            'image_url'=>'',
                        ];
                        $remote=$pageGeneration->applyStartPageRemoteImages(
                            trim((string)$validated['prompt'])."\nSELECTED IMAGE ONLY: Find one image matching this request for the exact clicked image slot. Do not change any other section imagery.",
                            [$probe]
                        );
                        $url=trim((string)data_get($remote,'blocks.0.image_url',''));
                        if($url!==''){
                            data_set($nextBlocks[$targetIndex],$matchedPaths[0],$url);
                            $imageRefreshSucceeded=true;
                            $applied[]=['action'=>'refresh_selected_image','index'=>$targetIndex,'path'=>$matchedPaths[0],'verified'=>true];
                        }else{
                            $imageRefreshSucceeded=false;
                            $imageRefreshError='The image provider returned no matching image for the selected slot.';
                        }
                    } else {
                    $beforeImages=$imageSnapshot($nextBlocks[$targetIndex]);
                    $remote=$pageGeneration->applyStartPageRemoteImages(
                        trim((string)$validated['prompt'])."\nIMAGE REFRESH DIRECTIVE: Replace the current section photography with imagery that directly matches this request. Do not preserve unrelated industry photos.",
                        [$nextBlocks[$targetIndex]]
                    );
                    if(is_array($remote['blocks'][0]??null)){
                        $candidate=array_merge($nextBlocks[$targetIndex],$remote['blocks'][0]);
                        $candidate['type']=$blocks[$targetIndex]['type']??$candidate['type'];
                        $afterImages=$imageSnapshot($candidate);
                        if($afterImages!==[] && $afterImages!==$beforeImages){
                            $nextBlocks[$targetIndex]=$candidate;
                            $imageRefreshSucceeded=true;
                            $applied[]=['action'=>'refresh_images','index'=>$targetIndex,'verified'=>true];
                        } else {
                            $imageRefreshSucceeded=false;
                            $imageRefreshError='The image provider returned no new matching image URLs for the selected section.';
                        }
                    } else {
                        $imageRefreshSucceeded=false;
                        $imageRefreshError='The image provider returned no usable section images.';
                    }
                    }
                } elseif($scope==='page'){
                    $beforeImages=$imageSnapshot($nextBlocks);
                    $remote=$pageGeneration->applyStartPageRemoteImages(
                        trim((string)$validated['prompt'])."\nIMAGE REFRESH DIRECTIVE: Replace unrelated photography across the page with imagery that directly matches this request.",
                        $nextBlocks
                    );
                    if(is_array($remote['blocks']??null) && count($remote['blocks'])===count($nextBlocks)){
                        $afterImages=$imageSnapshot($remote['blocks']);
                        if($afterImages!==[] && $afterImages!==$beforeImages){
                            $nextBlocks=$remote['blocks'];
                            $imageRefreshSucceeded=true;
                            $applied[]=['action'=>'refresh_images','scope'=>'page','verified'=>true];
                        } else {
                            $imageRefreshSucceeded=false;
                            $imageRefreshError='The image provider returned no new matching image URLs for the page.';
                        }
                    }
                }
            } catch(\Throwable $e) {
                report($e);
                $imageRefreshSucceeded=false;
                $imageRefreshError='The remote image provider could not complete the refresh.';
            }
        }

        $allowedThemes=['midnight','emerald','coffee','rose','dark','ocean','indigo','amber','charcoal','violet','teal','ruby','forest','obsidian','navy','espresso','terracotta','asphalt'];
        $themeKey=null;
        if($explicitThemeChange){
            $themeKey=$this->lunaRequestedThemeKey((string)$validated['prompt']);
            foreach($operations as $operation){
                if($themeKey===null && ($operation['action']??'')==='theme' && in_array(($operation['theme_key']??''),$allowedThemes,true)){
                    $themeKey=(string)$operation['theme_key'];
                    break;
                }
            }
            if($themeKey!==null && !collect($applied)->contains(fn($item)=>($item['action']??'')==='theme')){
                $applied[]=['action'=>'theme','theme_key'=>$themeKey,'verified'=>true];
            }
        } else {
            // Hard server guard: model creativity may redesign layout/content, but
            // cannot silently rebrand an established website.
            $operations=array_values(array_filter($operations,fn($operation)=>(($operation['action']??'')!=='theme')));
        }

        $brandPrimary=$explicitBrandPrimary;
        $brandColorFamily=$brandPrimary
            ? $this->lunaValidateBrandColorFamily($brandPrimary,$plan['brand_color_family']??null)
            : null;
        $brandPattern=null;
        if($brandColorFamily){
            $brandPattern=$this->lunaApplyBrandVisualPattern($nextBlocks,is_array($header)?$header:[],$brandColorFamily);
            $nextBlocks=$brandPattern['blocks'];
            $header=$brandPattern['header'];
            $applied[]=[
                'action'=>'brand_color_family',
                'scope'=>'site',
                'primary'=>$brandPrimary,
                'mode'=>$brandPattern['mode'],
                'source'=>is_array($plan['brand_color_family']??null)?'luna_designed_validated':'deterministic_fallback',
                'verified'=>true,
            ];
            if($brandPattern['hero_changed']) $applied[]=['action'=>'brand_hero_contrast','index'=>$brandPattern['hero_index'],'mode'=>$brandPattern['mode'],'verified'=>true];
            if($brandPattern['overlay_changed']) $applied[]=['action'=>'header_overlay','scope'=>'header','enabled'=>true,'verified'=>true];
        }

        $headerChanges=is_array($plan['header_changes']??null)?$plan['header_changes']:[];
        $footerChanges=is_array($plan['footer_changes']??null)?$plan['footer_changes']:[];
        $safeHeader=$this->lunaSafeHeaderChanges(is_array($header)?$header:[],$headerChanges);
        if($brandColorFamily){
            $safeHeader=$this->lunaApplyBrandVisualPattern($nextBlocks,$safeHeader,$brandColorFamily)['header'];
        }
        $safeFooter=$this->lunaSafeFooterChanges(is_array($footer)?$footer:[],$footerChanges);
        $pageStyle=in_array(Str::lower((string)($plan['page_style']??'')),['balanced','clean','premium'],true)
            ? Str::lower((string)$plan['page_style']) : null;

        $shellChanged=!empty($headerChanges)||!empty($footerChanges)||$themeKey!==null||$pageStyle!==null||$brandColorFamily!==null;
        $afterFingerprint=hash('sha256',json_encode(array_values($nextBlocks),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
        $blocksActuallyChanged=!hash_equals($beforeFingerprint,$afterFingerprint);
        if(!$blocksActuallyChanged){
            $applied=array_values(array_filter($applied,fn($item)=>in_array(($item['action']??''),['theme'],true)));
        }
        $verifiedSomething=$blocksActuallyChanged||$shellChanged;
        $executionVerificationResult=$executionVerification->verify($operations,$applied,$verifiedSomething);

        // Batch 12 — one bounded deterministic recovery pass.
        // Only safe idempotent retries are eligible. Destructive/structural actions
        // remain manual failures instead of being guessed or broadened.
        $recoveryPlan=$selfCorrection->prepare(
            $executionVerificationResult,
            $recoveryPlannedOperations,
            $applied,
            $scopeResolution
        );
        $recoveredApplied=[];
        $beforeRecoveryStatus=$executionVerificationResult['status']??null;

        if($recoveryPlan['attempted']??false){
            foreach((array)($recoveryPlan['retry_operations']??[]) as $retry){
                $retryAction=(string)($retry['action']??'');

                if($retryAction==='edit'){
                    $retryIndex=isset($retry['index'])?(int)$retry['index']:-1;
                    $original=collect($recoveryPlannedOperations)->first(function($op) use($retryIndex){
                        return is_array($op)
                            && ($op['action']??'')==='edit'
                            && (int)($op['index']??-1)===$retryIndex;
                    });
                    if(is_array($original) && isset($nextBlocks[$retryIndex]) && is_array($original['changes']??null)){
                        $current=$nextBlocks[$retryIndex];
                        $changes=$original['changes'];
                        unset($changes['type'],$changes['_renderKey']);
                        $changes=array_intersect_key($changes,$current);
                        $sanitized=$smartSparkEditing->sanitizeEdit($current,$changes,$elementContext);
                        if(($sanitized['paths']??[])!==[]){
                            $beforeRetry=hash('sha256',json_encode($nextBlocks[$retryIndex],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
                            $nextBlocks[$retryIndex]=$sanitized['block'];
                            $afterRetry=hash('sha256',json_encode($nextBlocks[$retryIndex],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
                            if(!hash_equals($beforeRetry,$afterRetry)){
                                $recoveredApplied[]=[
                                    'action'=>'edit',
                                    'index'=>$retryIndex,
                                    'mode'=>$sanitized['mode']??'block',
                                    'paths'=>$sanitized['paths']??[],
                                    'verified'=>true,
                                    'recovery_pass'=>1,
                                ];
                            }
                        }
                    }
                } elseif($retryAction==='theme'){
                    $retryTheme=trim((string)($retry['theme_key']??''));
                    if($explicitThemeChange && in_array($retryTheme,$allowedThemes,true)){
                        $themeKey=$retryTheme;
                        $recoveredApplied[]=[
                            'action'=>'theme',
                            'theme_key'=>$retryTheme,
                            'scope'=>'site',
                            'verified'=>true,
                            'recovery_pass'=>1,
                        ];
                    }
                }
            }

            if($recoveredApplied!==[]){
                $applied=array_values(array_merge($applied,$recoveredApplied));
                $afterFingerprint=hash('sha256',json_encode(array_values($nextBlocks),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
                $blocksActuallyChanged=!hash_equals($beforeFingerprint,$afterFingerprint);
                $verifiedSomething=$blocksActuallyChanged||$shellChanged||$recoveredApplied!==[];
                $executionVerificationResult=$executionVerification->verify($operations,$applied,$verifiedSomething);
            }
        }

        $executionVerificationResult['before_status']=$beforeRecoveryStatus;
        $recoveryResult=$selfCorrection->finalize(
            $recoveryPlan,
            $executionVerificationResult,
            $recoveredApplied
        );

        $postQaResult=null;
        if(($canonicalIntent['domain']??'')==='qa' && ($canonicalIntent['operation']??'')==='audit_and_repair'){
            $postQaResult=$visualQa->audit(
                $prompt,
                array_values(array_map(fn($block)=>is_array($block)?(string)($block['type']??'unknown'):'unknown',$nextBlocks)),
                array_values($nextBlocks),
                [
                    'typography'=>$typography,
                    'section_layout'=>$sectionLayout,
                    'components'=>$components,
                ]
            );
            $blockingFindings=array_values(array_filter((array)($postQaResult['findings']??[]),fn($finding)=>in_array((string)($finding['severity']??''),['critical','high'],true)));
            if($blockingFindings!==[]){
                $executionVerificationResult=$executionVerification->failPostcondition(
                    $executionVerificationResult,
                    'Post-repair QA still found a critical/high-severity issue, so full repair cannot be claimed.',
                    ['blocking_findings'=>$blockingFindings]
                );
            }
        }

        $siteMemory=$contextState->rememberVerified($siteMemory,$canonicalIntent,$executionVerificationResult,$applied,$routeContext??[],[],[]);
        $siteMemory=$siteDna->updateAfterExecution(
            $siteMemory,$siteDesignDna,$executionVerificationResult,$scopeResolution,
            [
                'primary'=>$themeKey ?: ($theme['primary']??null),
                'typography'=>$typography,
                'components'=>$components,
                'section_layout'=>$sectionLayout,
                'background_style'=>$backgroundStyle,
                'custom_brand_theme'=>$brandColorFamily ?: ($theme['custom_brand_theme']??null),
            ],
            [
                'page_style'=>$pageStyle,
                'design_direction'=>$plan['design_direction']??null,
                'media_direction'=>$plan['media_direction']??null,
            ]
        );
        $chargedCreditCost=$verifiedSomething ? $creditCost : 0;
        if($chargedCreditCost>0 && $user){
            $credits->consume($user,$chargedCreditCost,'Luna website change',$website,'luna-page-chat-'.Str::uuid(),[
                'category'=>'ai','scope'=>$scope,'operations'=>$applied,
            ]);
        }
        $reply='';
        if(($executionVerificationResult['status']??'failed')==='partial'){
            $reply=$naturalReplyFromFacts([
                'action_completed'=>false,
                'execution_status'=>'partial',
                'verified_operations'=>$executionVerificationResult['verified_operations']??[],
                'unverified_operations'=>$executionVerificationResult['unverified_operations']??[],
                'constraint'=>'Some requested changes were verified, but at least one planned operation did not complete. Do not say Done or imply full completion.',
            ]);
        } elseif(($executionVerificationResult['status']??'failed')==='failed' && $verifiedSomething){
            $reply=$naturalReplyFromFacts([
                'action_completed'=>false,
                'execution_status'=>'failed',
                'verified_operations'=>$executionVerificationResult['verified_operations']??[],
                'unverified_operations'=>$executionVerificationResult['unverified_operations']??[],
                'constraint'=>'The requested plan could not be fully verified. Report what failed instead of claiming completion.',
            ]);
        } elseif($universalBackgroundIntent && !$backgroundApplied){
            $reply=$naturalReplyFromFacts([
                'action'=>'background image change',
                'action_completed'=>false,
                'error'=>$backgroundError?:'The image provider did not return a usable background.',
                'verified_operations'=>$applied,
            ]);
        } elseif($imageRequest && $imageRefreshSucceeded===false){
            $reply=$naturalReplyFromFacts([
                'action'=>'image replacement',
                'action_completed'=>false,
                'error'=>$imageRefreshError?:'No new matching images were returned.',
                'verified_operations'=>$applied,
            ]);
        } elseif(!$verifiedSomething){
            $reply=$naturalReplyFromFacts([
                'action_completed'=>false,
                'verified_operations'=>$applied,
                'constraint'=>'The server could not verify a real website-state change.',
            ]);
        } elseif($reply===''){
            $reply=$naturalReplyFromFacts([
                'action_completed'=>true,
                'verified_operations'=>$applied,
                'execution_verification'=>$executionVerificationResult,
                'visual_qa'=>$postQaResult ? ['score'=>$postQaResult['score']??null,'grade'=>$postQaResult['grade']??null,'pass'=>$postQaResult['pass']??false,'summary'=>$postQaResult['summary']??[]] : null,
                'theme_changed'=>$themeKey!==null || $brandColorFamily!==null,
                'page_style_changed'=>$pageStyle!==null,
            ]);
        }
        $verifiedTargetReply=$smartSparkEditing->verifiedTargetReply(
            $elementContext,
            (string)$validated['prompt'],
            (string)($executionVerificationResult['status']??'failed')
        );
        if($verifiedTargetReply!==null)$reply=$verifiedTargetReply;

        return response()->json([
            'reply'=>$reply,
            'credit_cost'=>$chargedCreditCost,
            'credit_balance'=>$user ? $credits->balance($user) : null,
            'blocks'=>$nextBlocks,
            'header'=>$safeHeader,
            'footer'=>$safeFooter,
            'theme_key'=>$themeKey,
            'brand_color_family'=>$brandColorFamily,
            'brand_theme_mode'=>$brandPattern['mode']??null,
            'page_style'=>$pageStyle,
            'site_memory'=>$siteMemory,
            'applied_operations'=>$applied,
            'execution_verification'=>$executionVerificationResult,
            'self_correction'=>$recoveryResult,
            'canonical_intent'=>$canonicalIntent,
            'execution_phases'=>['Thinking','Planning','Designing','Building','Checking'],
        ]);
    }


    public function chat(Request $request, Website $website, string $key)
    {
        $this->authorize('update', $website);
        abort_unless($website->isCustom(), 422, 'Cosmic AI Spark chat is only available for Custom Websites.');
        $spark = $website->customSparks()->where('key', $key)->firstOrFail();
        $validated = $request->validate([
            'prompt' => ['required','string','max:5000'],
            'block' => ['required','string','max:80000'],
            'asset' => ['nullable','image','mimes:png,jpg,jpeg,webp','max:12288'],
            'target_scope' => ['nullable','in:section,element'],
            'target_key' => ['nullable','string','max:80'],
        ]);
        $currentBlock = json_decode($validated['block'], true);
        if (!is_array($currentBlock)) {
            throw ValidationException::withMessages(['block' => 'This Spark could not be prepared for Cosmic AI.']);
        }

        $metadata = is_array($spark->metadata) ? $spark->metadata : [];
        $conversation = is_array($metadata['conversation'] ?? null) ? $metadata['conversation'] : [];
        $assetUrl = '';
        $assetDataUri = '';
        if ($request->hasFile('asset')) {
            $asset = $request->file('asset');
            $assetPath = $asset->store('custom-sites/'.$website->id.'/assets', 'public');
            $assetUrl = Storage::disk('public')->url($assetPath);
            $assetDataUri = 'data:'.$asset->getMimeType().';base64,'.base64_encode(file_get_contents($asset->getRealPath()));
        }

        $referenceDataUri = '';
        $referenceUrl = (string) ($metadata['reference_url'] ?? '');
        if ($referenceUrl !== '') {
            $relative = preg_replace('#^/storage/#', '', parse_url($referenceUrl, PHP_URL_PATH) ?: '');
            if ($relative && Storage::disk('public')->exists($relative)) {
                $mime = Storage::disk('public')->mimeType($relative) ?: 'image/png';
                $referenceDataUri = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($relative));
            }
        }

        $history = collect(array_slice($conversation, -10))->map(fn ($message) => strtoupper((string)($message['role'] ?? 'user')).': '.trim((string)($message['text'] ?? '')))->implode("\n");
        $system = <<<'PROMPT'
You are Cosmic AI, a focused visual developer inside Cosmic CMS.
You are editing ONE selected Custom Spark only. Never modify another section, website header, footer, page settings, public Sparks, templates, or themes.
Return JSON only.

The user may ask a question or request a change.
- If it is only a question, set action="reply_only" and return CURRENT_BLOCK unchanged.
- If it requests a visual/content change, set action="update" and return the FULL updated block.
- Preserve correct parts. Make the smallest requested change.
- Treat the stored reference screenshot as design context when supplied.
- If a newly uploaded asset is relevant, use its exact URL provided by the user message. Never invent image URLs.
- Keep type=luna_custom_section and custom_spark_key unchanged.
- Use only fields already present in CURRENT_BLOCK plus the supported fields visual_style, style_overrides, review, form, items, image_url.
- For requests about a background, prefer visual_style.background_position/background_size and overlay_gradient_* fields instead of rewriting the section.
- For typography/spacing requests, modify only the relevant numeric visual_style/style_overrides fields. Do not redesign correct regions.
- Forms are functional schema, not decorative markup. Preserve field name/type/required/options unless the user explicitly asks to change the form. Allowed field types: text,email,tel,textarea,select,checkbox,hidden.
- Runtime interactions are declarative. You may add/edit runtime inputs, formulas, conditions and actions using the same safe runtime schema. Never return raw JavaScript/HTML/CSS or network/browser API code.
- If the user asks to compare/match the reference, inspect the supplied reference and prioritize the largest visible mismatch first.

Return:
{"action":"reply_only|update","reply":"short helpful response","block":FULL_BLOCK}
PROMPT;

        $content = [
            ['type'=>'text','text'=>"CURRENT_BLOCK JSON:\n".json_encode($currentBlock, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nRECENT CHAT:\n".($history ?: 'No previous chat.')."\n\nUSER REQUEST:\n".$validated['prompt'].($assetUrl !== '' ? "\n\nNEW UPLOADED ASSET URL (use exactly if requested): {$assetUrl}" : '')],
        ];
        if ($referenceDataUri !== '') {
            $content[] = ['type'=>'text','text'=>'REFERENCE SCREENSHOT FOR THIS SPARK'];
            $content[] = ['type'=>'image_url','image_url'=>['url'=>$referenceDataUri,'detail'=>'high']];
        }
        if ($assetDataUri !== '') {
            $content[] = ['type'=>'text','text'=>'NEW UPLOADED ASSET'];
            $content[] = ['type'=>'image_url','image_url'=>['url'=>$assetDataUri,'detail'=>'high']];
        }

        $apiKey = (string) config('openai.api_key');
        abort_if($apiKey === '', 503, 'OpenAI is not configured.');
        $response = Http::withToken($apiKey)->timeout(120)->post(rtrim((string)(config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/chat/completions', [
            'model' => env('OPENAI_VISION_MODEL', env('OPENAI_MODEL', 'gpt-5-mini')),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role'=>'system','content'=>$system],
                ['role'=>'user','content'=>$content],
            ],
        ])->throw()->json();
        $result = json_decode((string) data_get($response, 'choices.0.message.content', '{}'), true);
        if (!is_array($result)) {
            throw ValidationException::withMessages(['prompt'=>'Cosmic AI returned an invalid response.']);
        }

        $reply = trim((string) ($result['reply'] ?? 'Done.')) ?: 'Done.';
        $action = ($result['action'] ?? 'update') === 'reply_only' ? 'reply_only' : 'update';
        $fixed = $currentBlock;
        if ($action === 'update' && is_array($result['block'] ?? null)) {
            $allowed = ['type','custom_spark_key','custom_spark_saved','category','layout','alignment','media_position','density','accent_shape','section_mood','eyebrow','heading','heading_accent_text','text','primary_label','primary_url','secondary_label','secondary_url','image_url','items','theme','style_overrides','visual_style','review','form','runtime'];
            $candidate = array_intersect_key($result['block'], array_flip($allowed));
            $fixed = array_merge($currentBlock, $candidate);
            $fixed['type'] = 'luna_custom_section';
            $fixed['custom_spark_key'] = $spark->key;
            if ($assetUrl !== '' && str_contains(strtolower($validated['prompt']), 'background') && empty($fixed['image_url'])) {
                $fixed['image_url'] = $assetUrl;
                $fixed['media_position'] = 'background';
            }
            $metadata = $this->pushRevision($spark, $currentBlock, 'Before Cosmic AI chat edit');
            $spark->block = $fixed;
        }

        $conversation[] = ['role'=>'user','text'=>$validated['prompt'],'at'=>now()->toIso8601String()];
        $conversation[] = ['role'=>'assistant','text'=>$reply,'at'=>now()->toIso8601String()];
        $metadata['conversation'] = array_slice($conversation, -40);
        if ($assetUrl !== '') $metadata['last_asset_url'] = $assetUrl;
        $spark->metadata = $metadata;
        $spark->save();

        return response()->json([
            'action'=>$action,
            'reply'=>$reply,
            'block'=>$fixed,
            'messages'=>$metadata['conversation'],
            'spark'=>$spark->fresh(),
        ]);
    }


    /**
     * Fast deterministic actions for the global header. These run before the
     * creative planner so simple site-shell requests never fail because a body
     * Spark scope validator/model misunderstood the target.
     */
    private function lunaHeaderScopeAction(string $prompt,array $header,array $siteMemory): ?array
    {
        $lower=Str::lower(trim($prompt));

        $asksOverlay=Str::contains($lower,['overlay header','header overlay','overlay the header','header over the banner','header over banner','header over the hero','header over hero']);
        if($asksOverlay){
            $disable=Str::contains($lower,['remove','disable','turn off','stop','no overlay','not overlay']);
            $header['overlay_header_on_banner']=!$disable;
            return [
                'reply'=>$disable
                    ? 'Done — I removed the header overlay so it stays in the normal page flow.'
                    : 'Done — I overlaid the header on the opening banner.',
                'header'=>$header,
                'site_memory'=>$siteMemory,
                'applied_operations'=>[['type'=>'header_overlay','enabled'=>!$disable]],
            ];
        }


        $logoIntent=Str::contains($lower,'logo') && Str::contains($lower,['create','generate','make','new logo','regenerate','replace','update']);
        $pendingLogo=(bool)($siteMemory['pending_logo_request']??false);
        if($logoIntent || $pendingLogo){
            $name=null;
            if(preg_match('/(?:logo\s+(?:for|called|named)|brand\s+(?:called|named))\s+["\']?([^"\',.!?]{2,80})/i',$prompt,$match)){
                $name=trim($match[1]);
            }elseif($pendingLogo && !$logoIntent){
                $candidate=trim($prompt," \t\n\r\0\x0B\"'");
                if(mb_strlen($candidate)>=2 && mb_strlen($candidate)<=80)$name=$candidate;
            }

            if(!$name){
                $siteMemory['pending_logo_request']=true;
                return [
                    'reply'=>'What name should I use for the logo? For example, “Business Name”.',
                    'mode'=>'logo_name_required',
                    'header'=>$header,
                    'site_memory'=>$siteMemory,
                    'applied_operations'=>[],
                ];
            }

            unset($siteMemory['pending_logo_request']);
            return [
                'reply'=>"Got it — I’ll create the logo for {$name} and match it to the current brand theme.",
                'mode'=>'logo_generate',
                'logo_company_name'=>Str::limit($name,80,''),
                'header'=>$header,
                'site_memory'=>$siteMemory,
                'applied_operations'=>[['type'=>'logo_generate','company_name'=>Str::limit($name,80,'')]],
            ];
        }

        return null;
    }

    /**
     * Adds only missing pages and menu links. Existing pages are reused so a
     * natural request like "add Home, About Us, Services, Projects, Contact"
     * is idempotent instead of producing duplicates.
     */
    private function lunaPageNavigationAction(string $prompt,Website $website,array $header): ?array
    {
        $lower=Str::lower($prompt);
        $intent=(Str::contains($lower,['page','pages']) && Str::contains($lower,['nav','navigation','menu']))
            && Str::contains($lower,['add','create','make','include']);
        if(!$intent)return null;

        $catalog=[
            'Home'=>['home','homepage','home page'],
            'About Us'=>['about us','about page','about'],
            'Services'=>['services','service page','services page'],
            'Projects'=>['projects','project page','portfolio','work page'],
            'Contact'=>['contact','contact us','contact page'],
            'Pricing'=>['pricing','pricing page'],
            'FAQ'=>['faq','faqs','faq page'],
            'Gallery'=>['gallery','gallery page'],
            'Testimonials'=>['testimonials','reviews'],
            'Team'=>['team','our team'],
            'Blog'=>['blog','news','updates'],
        ];
        $requested=[];
        foreach($catalog as $title=>$aliases){
            if(Str::contains($lower,$aliases))$requested[]=$title;
        }
        $requested=array_values(array_unique($requested));
        if(!$requested)return null;

        $pages=$website->pages()->orderBy('sort_order')->orderBy('id')->get();
        $normalize=fn($value)=>Str::of((string)$value)->lower()->replaceMatches('/[^a-z0-9]+/','')->value();
        $findExisting=function(string $title) use ($pages,$normalize){
            $needle=$normalize($title);
            foreach($pages as $page){
                if($normalize($page->title)===$needle || $normalize($page->slug)===$needle)return $page;
                if($title==='About Us' && in_array($normalize($page->title),['about','ourstory'],true))return $page;
                if($title==='Home' && in_array($normalize($page->slug),['home',''],true))return $page;
                if($title==='Projects' && in_array($normalize($page->title),['portfolio','work','ourwork'],true))return $page;
            }
            return null;
        };

        $created=[];$reused=[];$resolved=[];
        foreach($requested as $title){
            $page=$findExisting($title);
            if($page){
                $reused[]=$title;
                $resolved[$title]=$page;
                continue;
            }
            $slug=$title==='Home'?'home':(Str::slug($title)?:'page');
            $base=$slug;$suffix=2;
            while($website->pages()->where('slug',$slug)->exists())$slug=$base.'-'.$suffix++;
            $page=$website->pages()->create([
                'title'=>$title,
                'slug'=>$slug,
                'page_type'=>'standard',
                'status'=>'draft',
                'sort_order'=>((int)$website->pages()->max('sort_order'))+1,
                'blocks'=>[],
            ]);
            $pages->push($page);
            $created[]=$title;
            $resolved[$title]=$page;
        }

        $menu=array_values(array_filter(is_array($header['menu']??null)?$header['menu']:[],fn($item)=>is_array($item)));
        $menuLabels=array_map(fn($item)=>$normalize($item['label']??''),$menu);
        $addedMenu=[];
        foreach($requested as $title){
            if(in_array($normalize($title),$menuLabels,true))continue;
            $page=$resolved[$title]??null;
            if(!$page)continue;
            $menu[]=[
                'label'=>$title,
                'url'=>$title==='Home' ? '/' : '/'.ltrim((string)$page->slug,'/'),
            ];
            $menuLabels[]=$normalize($title);
            $addedMenu[]=$title;
        }
        $header['menu']=array_values(array_slice($menu,0,12));

        $parts=[];
        if($created)$parts[]='created '.implode(', ',$created);
        if($reused)$parts[]='reused existing '.implode(', ',$reused);
        if($addedMenu)$parts[]='updated navigation with '.implode(', ',$addedMenu);
        $reply='Done — '.($parts?implode('; ',$parts):'the requested pages and navigation were already in place').'.';

        return [
            'reply'=>$reply,
            'header'=>$header,
            'applied_operations'=>[
                ['type'=>'pages_create_missing','created'=>$created,'reused'=>$reused],
                ['type'=>'navigation_add_missing','added'=>$addedMenu],
            ],
        ];
    }

    /**
     * Resolve a section explicitly named in a natural-language request.
     * This is intentionally independent of the Builder's selected scope: users
     * can say "the hero heading" from Whole Page and still target the hero.
     */
    private function lunaNamedBlockTargetIndex(string $prompt,array $blocks): ?int
    {
        $userPrompt=trim((string)preg_replace('/\n\[V5 action hints:.*$/is','',$prompt));
        $namedTargets=[
            'hero'=>['hero','banner','masthead'],
            'services'=>['services','service'],
            'testimonials'=>['testimonials','testimonial','reviews','review'],
            'pricing'=>['pricing','price','plans'],
            'faq'=>['faq','faqs','questions'],
            'contact'=>['contact','contact us','enquiry','inquiry'],
            'cta'=>['cta','call to action'],
            'gallery'=>['gallery','portfolio','projects','work'],
            'process'=>['process','steps','timeline'],
            'team'=>['team','people','staff'],
            'about'=>['about','story'],
        ];

        $requested=null;
        foreach($namedTargets as $name=>$aliases){
            foreach($aliases as $alias){
                if(preg_match('/\b'.preg_quote($alias,'/').'\b/i',$userPrompt)){
                    $requested=$name;
                    break 2;
                }
            }
        }
        if($requested===null)return null;

        foreach(array_values($blocks) as $index=>$block){
            if(!is_array($block))continue;
            $type=Str::lower((string)($block['type']??''));
            $heading=Str::lower((string)($block['heading']??$block['title']??$block['eyebrow']??''));
            $meta=SparkCatalog::find((string)($block['type']??''))??[];
            $category=Str::lower((string)($meta['category']??''));
            $haystack=$type.' '.$heading.' '.$category;
            $matches=match($requested){
                'hero'=>Str::contains($haystack,['hero','banner','mini heroes']),
                'services'=>Str::contains($haystack,['services','service']),
                'testimonials'=>Str::contains($haystack,['testimonial','review']),
                'pricing'=>Str::contains($haystack,['pricing','price']),
                'faq'=>Str::contains($haystack,'faq'),
                'contact'=>Str::contains($haystack,['contact','location']),
                'cta'=>Str::contains($haystack,'cta'),
                'gallery'=>Str::contains($haystack,['gallery','portfolio','case studies','projects']),
                'process'=>Str::contains($haystack,['process','proof','timeline']),
                'team'=>Str::contains($haystack,'team'),
                'about'=>Str::contains($haystack,'about'),
                default=>false,
            };
            if($matches)return $index;
        }

        return null;
    }

    /** Select the canonical typography row that the deterministic executor owns. */
    private function lunaCanonicalTypographyOperation(array $canonicalIntent): ?array
    {
        $top=[
            'operation_id'=>null,
            'domain'=>$canonicalIntent['domain']??null,
            'leaf_operation'=>$canonicalIntent['leaf_operation']??null,
            'operation'=>$canonicalIntent['operation']??null,
            'scope'=>$canonicalIntent['scope']??null,
            'target'=>$canonicalIntent['target']??null,
            'changes'=>is_array($canonicalIntent['changes']??null)?$canonicalIntent['changes']:[],
            'constraints'=>is_array($canonicalIntent['constraints']??null)?$canonicalIntent['constraints']:[],
        ];
        $operations=array_values(array_filter((array)($canonicalIntent['operations']??[]),'is_array'));
        foreach($operations as $operation){
            if(($operation['domain']??null)!=='typography')continue;
            if(($top['domain']??null)==='typography'){
                $top['operation_id']=$operation['operation_id']??'op_1';
                return $top;
            }
            return $operation;
        }
        return ($top['domain']??null)==='typography'?$top:null;
    }

    /** Turn a verified canonical typography row into deterministic parser input. */
    private function lunaTypographySchemaPrompt(string $prompt,array $operation): string
    {
        $parts=['typography'];
        $leaf=Str::lower(trim((string)($operation['leaf_operation']??'')));
        $parts[]=match($leaf){
            'font_size'=>'font size','line_height'=>'line height','font_weight'=>'font weight',
            'letter_spacing'=>'letter spacing',default=>$leaf,
        };
        $target=$this->lunaCanonicalTargetText($operation['target']??null);
        if($target!=='')$parts[]=$target;
        $changes=is_array($operation['changes']??null)?$operation['changes']:[];
        $relative=is_array($changes['relative_size']??null)?$changes['relative_size']:[];
        $direction=Str::lower(trim((string)($relative['direction']??'')));
        if($direction==='increase')$parts[]='increase larger';
        elseif($direction==='decrease')$parts[]='decrease smaller';
        foreach(['value','font_size','line_height','font_weight','letter_spacing'] as $key){
            if(isset($changes[$key])&&is_scalar($changes[$key]))$parts[]=(string)$changes[$key];
        }
        $canonicalScope=Str::lower(trim((string)($operation['scope']??'')));
        if(in_array($canonicalScope,['site','global_token'],true))$parts[]='across the site';
        elseif($canonicalScope==='section')$parts[]='this section';
        return trim($prompt.' '.implode(' ',array_values(array_filter($parts))));
    }

    /**
     * Verify the actual token/block delta before allowing Luna to claim success,
     * and persist only that verified mutation as conversational follow-up context.
     */
    private function lunaVerifyTypographyAction(
        array $result,
        array $beforeBlocks,
        array $beforeTypography,
        array $canonicalIntent,
        LunaExecutionVerificationService $executionVerification,
        \App\Services\LunaContextStateService $contextState,
        array $siteMemory,
        array $routeContext=[]
    ): array {
        $afterBlocks=is_array($result['blocks']??null)?array_values($result['blocks']):array_values($beforeBlocks);
        $afterTypography=is_array($result['typography_settings']??null)?$result['typography_settings']:$beforeTypography;
        $beforeFingerprint=hash('sha256',json_encode([
            'blocks'=>array_values($beforeBlocks),'typography'=>$beforeTypography,
        ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
        $afterFingerprint=hash('sha256',json_encode([
            'blocks'=>$afterBlocks,'typography'=>$afterTypography,
        ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
        $stateChanged=!hash_equals($beforeFingerprint,$afterFingerprint);

        $legacy=is_array($result['applied_operations'][0]??null)?$result['applied_operations'][0]:[];
        $role=(string)($legacy['role']??'h2');
        $property=(string)($legacy['property']??'size');
        $isLocal=($legacy['action']??'')==='typography_local';
        $index=$isLocal&&isset($legacy['index'])?(int)$legacy['index']:null;
        $blockType=$index!==null&&isset($afterBlocks[$index])&&is_array($afterBlocks[$index])
            ? Str::lower((string)($afterBlocks[$index]['type']??''))
            : '';
        $isHero=$index!==null&&Str::startsWith($blockType,'hero');
        $resolvedTarget=$isLocal?[
            'type'=>'heading',
            'key'=>$isHero?'hero.'.$role:'section.'.$index.'.'.$role,
            'label'=>$isHero?'hero heading':$role.' heading',
            'index'=>$index,
            'level'=>$role,
        ]:[
            'type'=>'heading','key'=>$role,'label'=>$role,'level'=>$role,
        ];

        $plan=$this->lunaCanonicalTypographyOperation($canonicalIntent)??[];
        $planChanges=is_array($plan['changes']??null)?$plan['changes']:[];
        $relativeDirection=Str::lower(trim((string)($result['relative_direction']??'')));
        if($property==='size' && !is_array($planChanges['relative_size']??null) && in_array($relativeDirection,['increase','decrease'],true)){
            $planChanges['relative_size']=['direction'=>$relativeDirection,'amount'=>'slight'];
        }
        $plan=array_replace($plan,[
            'operation_id'=>$plan['operation_id']??'op_1',
            'domain'=>'typography',
            'leaf_operation'=>match($property){
                'line'=>'line_height','weight'=>'font_weight','tracking'=>'letter_spacing',default=>'font_size',
            },
            'operation'=>'update',
            'scope'=>$isLocal?'section':'site',
            'target'=>$resolvedTarget,
            'changes'=>$planChanges,
            'expected_state'=>['value'=>$result['after_value']??($legacy['value']??null)],
        ]);
        $applied=array_replace($legacy,[
            'operation_id'=>$plan['operation_id'],
            'domain'=>'typography',
            'leaf_operation'=>$plan['leaf_operation'],
            'operation'=>'update',
            'scope'=>$plan['scope'],
            'target'=>$resolvedTarget,
            'direction'=>$relativeDirection!==''?$relativeDirection:null,
            'before_value'=>$result['before_value']??null,
            'after_value'=>$result['after_value']??($legacy['value']??null),
            'final_state'=>['value'=>$result['after_value']??($legacy['value']??null)],
            'verified'=>$stateChanged,
        ]);
        $verification=$executionVerification->verify([$plan],[$applied],$stateChanged);
        $verifiedComplete=(bool)($verification['can_claim_complete']??false);
        $beforeContext=[
            'blocks_fingerprint'=>$beforeFingerprint,
            'value'=>$result['before_value']??null,
            'reversible_state'=>[
                'kind'=>'typography','scope'=>$plan['scope'],'index'=>$index,
                'role'=>$role,'property'=>$property,'value'=>$result['before_value']??null,
            ],
        ];
        $afterContext=[
            'blocks_fingerprint'=>$afterFingerprint,
            'value'=>$result['after_value']??($legacy['value']??null),
        ];
        $siteMemory=$contextState->rememberVerified(
            $siteMemory,$canonicalIntent,$verification,[$applied],$routeContext,$beforeContext,$afterContext
        );

        $result['blocks']=$verifiedComplete?$afterBlocks:array_values($beforeBlocks);
        $result['typography_settings']=$verifiedComplete?$afterTypography:$beforeTypography;
        $result['execution_verification']=$verification;
        $result['site_memory']=$siteMemory;
        $result['applied_operations']=$verifiedComplete?[$applied]:[];
        if($verifiedComplete){
            $targetLabel=$isHero
                ? 'hero heading'
                : ($isLocal ? 'selected '.Str::upper($role).' heading' : Str::upper($role).' headings across the site');
            $beforeValue=$result['before_value']??null;
            $afterValue=$result['after_value']??($legacy['value']??null);
            $valueDetail=is_scalar($beforeValue)&&is_scalar($afterValue)
                ? ' ('.trim((string)$beforeValue).' → '.trim((string)$afterValue).')'
                : '';
            $result['reply']=match($relativeDirection){
                'increase'=>'Made the '.$targetLabel.' slightly larger'.$valueDetail.'.',
                'decrease'=>'Made the '.$targetLabel.' slightly smaller'.$valueDetail.'.',
                default=>'Updated the '.$targetLabel.' '.$property.$valueDetail.'.',
            };
        }else{
            $result['reply']='I could not verify an actual typography change, so I did not mark it as complete.';
        }
        return $result;
    }

    /** Scale a typography size without collapsing fluid clamp() tokens to their
     * desktop maximum. That guarantees "smaller" is smaller at the viewport the
     * user is actually viewing, not only relative to an assumed 16px/rem maximum. */
    private function lunaScaledTypographySize(string $current,float $factor,float $fallbackPx): string
    {
        $format=fn(float $value)=>rtrim(rtrim(number_format($value,3,'.',''),'0'),'.');
        if(preg_match('/^clamp\(\s*((?:\d+(?:\.\d+)?|\.\d+))(rem|px)\s*,\s*((?:\d+(?:\.\d+)?|\.\d+))(vw)\s*,\s*((?:\d+(?:\.\d+)?|\.\d+))(rem|px)\s*\)$/i',$current,$m)){
            return 'clamp('.$format((float)$m[1]*$factor).Str::lower($m[2]).','
                .$format((float)$m[3]*$factor).Str::lower($m[4]).','
                .$format((float)$m[5]*$factor).Str::lower($m[6]).')';
        }
        if(preg_match('/^((?:\d+(?:\.\d+)?|\.\d+))(px|rem)$/i',$current,$m)){
            return $format((float)$m[1]*$factor).Str::lower($m[2]);
        }
        return $format(max(10,min(144,$fallbackPx*$factor))/16).'rem';
    }

    /**
     * Deterministic typography intent router.
     * Global wording updates website theme typography; an explicitly selected or
     * named section writes only luna_typography_overrides on that block.
     */
    private function lunaTypographyAction(
        string $prompt,
        string $scope,
        int $targetIndex,
        array $blocks,
        array $typography,
        array $canonicalIntent=[]
    ): ?array {
        $userActionPrompt=trim((string)preg_replace('/\n\[V5 action hints:.*$/is','',$prompt));
        $actionPrompt=$userActionPrompt;
        $canonicalOperation=$this->lunaCanonicalTypographyOperation($canonicalIntent);
        $canonicalRelative=is_array($canonicalOperation['changes']['relative_size']??null)
            ? $canonicalOperation['changes']['relative_size']
            : [];
        $canonicalDirection=Str::lower(trim((string)($canonicalRelative['direction']??'')));
        if(is_array($canonicalOperation)){
            $actionPrompt=$this->lunaTypographySchemaPrompt($actionPrompt,$canonicalOperation);
            $canonicalScope=Str::lower(trim((string)($canonicalOperation['scope']??'')));
            if($canonicalScope==='section')$scope='section';
            elseif(in_array($canonicalScope,['site','global_token'],true))$scope='page';
            $canonicalTarget=is_array($canonicalOperation['target']??null)?$canonicalOperation['target']:[];
            foreach(['index','blockIndex','block_index','section_index'] as $indexKey){
                if(isset($canonicalTarget[$indexKey])&&is_numeric($canonicalTarget[$indexKey])){
                    $candidateIndex=(int)$canonicalTarget[$indexKey];
                    if(isset($blocks[$candidateIndex]))$targetIndex=$candidateIndex;
                    break;
                }
            }
        }
        $q=Str::lower($actionPrompt);
        if($q==='')return null;

        $mentionsType=Str::contains($q,[
            'typography','font size','font-size','line height','line-height','font weight','font-weight',
            'letter spacing','letter-spacing','h1','h2','h3','h4','h5','h6','heading 1','heading 2','heading 3','heading 4','heading 5','heading 6',
            'heading','headings','headline','headlines','title','titles',
            'body text','paragraph text','paragraphs','card title','card titles','card heading','card headings','stat title','stat titles','card body','card text','eyebrow','labels','badge','badges','meta','button text'
        ]);
        $changeVerb=Str::contains($q,[
            'increase','decrease','bigger','larger','smaller','reduce','make','set','change','update','use','adjust'
        ]);
        if(!$mentionsType || !$changeVerb)return null;

        $namedTargetIndex=$this->lunaNamedBlockTargetIndex($actionPrompt,$blocks);
        $resolvedTargetIndex=$namedTargetIndex ?? (($scope==='section' && isset($blocks[$targetIndex]))?$targetIndex:null);
        $selectedType=$resolvedTargetIndex!==null && isset($blocks[$resolvedTargetIndex])
            ? Str::lower((string)($blocks[$resolvedTargetIndex]['type']??''))
            : '';
        $explicitUserRole=null;
        if(preg_match('/\b(?:h([1-6])|heading\s*([1-6]))\b/i',$userActionPrompt,$userRoleMatch)){
            $explicitUserRole='h'.($userRoleMatch[1]!==''?$userRoleMatch[1]:$userRoleMatch[2]);
        }
        $canonicalTarget=is_array($canonicalOperation['target']??null)?$canonicalOperation['target']:[];
        $canonicalRoleText=implode(' ',array_values(array_filter(array_map(
            fn($key)=>isset($canonicalTarget[$key])&&is_scalar($canonicalTarget[$key])?(string)$canonicalTarget[$key]:'',
            ['level','role','tag','tagName','key','label']
        ))));
        $canonicalRole=preg_match('/\bh([1-6])\b/i',$canonicalRoleText,$canonicalRoleMatch)
            ? 'h'.$canonicalRoleMatch[1]
            : null;
        $role=match(true){
            Str::contains($q,['all headings','every heading','all heading sizes','every heading size'])=>'all_headings',
            $explicitUserRole!==null=>$explicitUserRole,
            Str::contains($q,['stat title','stat titles'])=>'stat-title',
            Str::contains($q,['card title','card titles','card heading','card headings'])=>'card-title',
            Str::contains($q,['card body','card text'])=>'card-body',
            Str::contains($q,['body text','paragraph text','paragraphs'])=>'body',
            Str::contains($q,['badge','badges'])=>'badge',
            Str::contains($q,['meta'])=>'meta',
            Str::contains($q,['eyebrow','labels'])=>'eyebrow',
            Str::contains($q,['button text','buttons'])=>'button',
            Str::contains($q,['heading','headline','title']) && Str::startsWith($selectedType,'hero')=>'h1',
            $canonicalRole!==null=>$canonicalRole,
            Str::contains($q,['heading','headline','title'])=>'h2',
            default=>null,
        };
        if($role===null)return null;

        $property=match(true){
            Str::contains($q,['line height','line-height'])=>'line',
            Str::contains($q,['font weight','font-weight','bold','lighter weight'])=>'weight',
            Str::contains($q,['letter spacing','letter-spacing','tracking'])=>'tracking',
            default=>'size',
        };

        $global=Str::contains($q,[
            'all ','every ','sitewide','site-wide','whole site','entire site','whole page','entire page',
            'across the site','across this site','globally'
        ]);
        if($resolvedTargetIndex===null && $scope!=='section' && !Str::contains($q,['this section','only this section','just this section']))$global=true;

        $defaults=[
            'h1'=>['size'=>'clamp(3rem,6vw,5.75rem)','line'=>'.96','weight'=>'700','tracking'=>'-.05em'],
            'h2'=>['size'=>'clamp(2.25rem,4.05vw,4rem)','line'=>'1','weight'=>'700','tracking'=>'-.045em'],
            'h3'=>['size'=>'clamp(1.35rem,2vw,1.75rem)','line'=>'1.08','weight'=>'700','tracking'=>'-.025em'],
            'h4'=>['size'=>'clamp(1.125rem,1.5vw,1.35rem)','line'=>'1.15','weight'=>'700','tracking'=>'-.015em'],
            'h5'=>['size'=>'clamp(1rem,1.2vw,1.125rem)','line'=>'1.25','weight'=>'700','tracking'=>'-.01em'],
            'h6'=>['size'=>'clamp(.875rem,1vw,1rem)','line'=>'1.3','weight'=>'700','tracking'=>'0em'],
            'card-title'=>['size'=>'clamp(1.25rem,1.65vw,1.65rem)','line'=>'1.12','weight'=>'700','tracking'=>'-.025em'],
            'stat-title'=>['size'=>'clamp(1.1rem,1.35vw,1.35rem)','line'=>'1.18','weight'=>'700','tracking'=>'-.015em'],
            'card-body'=>['size'=>'.975rem','line'=>'1.65','weight'=>'400','tracking'=>'0em'],
            'body'=>['size'=>'1rem','line'=>'1.75','weight'=>'400','tracking'=>'0em'],
            'eyebrow'=>['size'=>'.75rem','line'=>'1.35','weight'=>'500','tracking'=>'.28em'],
            'badge'=>['size'=>'.75rem','line'=>'1.25','weight'=>'600','tracking'=>'0em'],
            'meta'=>['size'=>'.75rem','line'=>'1.4','weight'=>'500','tracking'=>'.12em'],
            'button'=>['size'=>'.9rem','line'=>'1.25','weight'=>'700','tracking'=>'0em'],
        ];

        if($role==='all_headings'){
            $roles=['h1','h2','h3','h4','h5','h6'];
            $nextTypography=$typography;
            $beforeHeadingValues=[];
            $explicitAllSize=null;
            if($property==='size' && preg_match('/(\d+(?:\.\d+)?)\s*(rem|px)/i',$actionPrompt,$explicitMatch)){
                $explicitAllSize=$explicitMatch[1].Str::lower($explicitMatch[2]);
            }
            foreach($roles as $headingRole){
                $headingKey=$headingRole.'_'.$property;
                $currentHeading=(string)($nextTypography[$headingKey]??$defaults[$headingRole][$property]);
                $beforeHeadingValues[$headingKey]=$currentHeading;
                if($property==='size'){
                    if($explicitAllSize!==null){
                        $nextTypography[$headingKey]=$explicitAllSize;
                        continue;
                    }
                    $basePx=['h1'=>92,'h2'=>64,'h3'=>28,'h4'=>21.6,'h5'=>18,'h6'=>16][$headingRole];
                    $factor=$canonicalDirection==='increase'
                        ? 1.12
                        : ($canonicalDirection==='decrease'
                            ? .90
                            : (Str::contains($q,['increase','bigger','larger','more']) ? 1.12 : (Str::contains($q,['decrease','smaller','reduce','less']) ? .90 : 1.0)));
                    $nextTypography[$headingKey]=$this->lunaScaledTypographySize($currentHeading,$factor,$basePx);
                } elseif($property==='line'){
                    $base=is_numeric($currentHeading)?(float)$currentHeading:(float)$defaults[$headingRole]['line'];
                    $nextTypography[$headingKey]=(string)round(max(.8,min(2.4,$base+(Str::contains($q,['increase','more']) ? .08 :(Str::contains($q,['decrease','less']) ? -.08 : 0)))),2);
                } elseif($property==='weight'){
                    $base=is_numeric($currentHeading)?(int)$currentHeading:(int)$defaults[$headingRole]['weight'];
                    $nextTypography[$headingKey]=(string)max(100,min(900,$base+(Str::contains($q,['increase','bolder','heavier']) ? 100 :(Str::contains($q,['decrease','lighter']) ? -100 : 0))));
                }
            }
            return [
                'reply'=>"Updated heading {$property} across the site.",
                'blocks'=>$blocks,
                'typography_settings'=>$nextTypography,
                'before_value'=>$beforeHeadingValues,
                'after_value'=>array_intersect_key($nextTypography,$beforeHeadingValues),
                'relative_direction'=>$canonicalDirection!==''
                    ? $canonicalDirection
                    : (Str::contains($q,['increase','bigger','larger','more'])?'increase':(Str::contains($q,['decrease','smaller','reduce','less'])?'decrease':null)),
                'applied_operations'=>[['action'=>'typography_global','role'=>'all_headings','property'=>$property]],
            ];
        }

        $key=$role.'_'.$property;
        $localCurrent=null;
        if(!$global && $resolvedTargetIndex!==null && isset($blocks[$resolvedTargetIndex]) && is_array($blocks[$resolvedTargetIndex])){
            $existingLocal=is_array($blocks[$resolvedTargetIndex]['luna_typography_overrides']??null)
                ? $blocks[$resolvedTargetIndex]['luna_typography_overrides']
                : [];
            if(isset($existingLocal[$key])&&is_scalar($existingLocal[$key])&&trim((string)$existingLocal[$key])!==''){
                $localCurrent=(string)$existingLocal[$key];
            }
        }
        $current=(string)($localCurrent??($typography[$key]??$defaults[$role][$property]));

        $explicit=null;
        if($property==='size' && preg_match('/(\d+(?:\.\d+)?)\s*(rem|px)/i',$actionPrompt,$m)){
            $explicit=$m[1].Str::lower($m[2]);
        } elseif($property==='line' && preg_match('/(?:line[- ]height(?:\s*(?:to|at|=))?\s*)(\d+(?:\.\d+)?)/i',$actionPrompt,$m)){
            $explicit=(string)max(.8,min(2.4,(float)$m[1]));
        } elseif($property==='weight' && preg_match('/\b([1-9]00)\b/',$actionPrompt,$m)){
            $explicit=$m[1];
        } elseif($property==='tracking' && preg_match('/(-?\d+(?:\.\d+)?)\s*(em|px)/i',$actionPrompt,$m)){
            $explicit=$m[1].Str::lower($m[2]);
        }

        $increase=$canonicalDirection==='increase'
            || ($canonicalDirection==='' && Str::contains($q,['increase','bigger','larger','more','heavier']));
        $decrease=$canonicalDirection==='decrease'
            || ($canonicalDirection==='' && Str::contains($q,['decrease','smaller','reduce','less','lighter']));

        $value=$explicit;
        if($value===null){
            if($property==='size'){
                $basePx=[
                    'h1'=>92,'h2'=>64,'h3'=>28,'h4'=>21.6,'h5'=>18,'h6'=>16,'card-title'=>26.4,'stat-title'=>21.6,'card-body'=>15.6,'body'=>16,'eyebrow'=>12,'badge'=>12,'meta'=>12,'button'=>14.4,
                ][$role];
                $factor=$increase ? 1.12 : ($decrease ? .90 : 1.0);
                $value=$this->lunaScaledTypographySize($current,$factor,$basePx);
            } elseif($property==='line'){
                $base=is_numeric($current)?(float)$current:(float)$defaults[$role]['line'];
                $value=(string)round(max(.8,min(2.4,$base + ($increase ? .08 : ($decrease ? -.08 : 0)))),2);
            } elseif($property==='weight'){
                $base=is_numeric($current)?(int)$current:(int)$defaults[$role]['weight'];
                $value=(string)max(100,min(900,$base + ($increase ? 100 : ($decrease ? -100 : 0))));
            } else {
                $value=$current;
            }
        }

        if($global){
            $nextTypography=$typography;
            $nextTypography[$key]=$value;
            return [
                'reply'=>"Updated {$role} {$property} across the site.",
                'blocks'=>$blocks,
                'typography_settings'=>$nextTypography,
                'before_value'=>$current,
                'after_value'=>$value,
                'relative_direction'=>$increase?'increase':($decrease?'decrease':null),
                'applied_operations'=>[[
                    'action'=>'typography_global',
                    'role'=>$role,
                    'property'=>$property,
                    'value'=>$value,
                ]],
            ];
        }

        if($resolvedTargetIndex===null || !isset($blocks[$resolvedTargetIndex]))return null;
        $nextBlocks=array_values($blocks);
        $nextBlock=is_array($nextBlocks[$resolvedTargetIndex])?$nextBlocks[$resolvedTargetIndex]:[];
        $local=is_array($nextBlock['luna_typography_overrides']??null)?$nextBlock['luna_typography_overrides']:[];
        $local[$key]=$value;
        $nextBlock['luna_typography_overrides']=$local;
        $nextBlocks[$resolvedTargetIndex]=$nextBlock;

        return [
            'reply'=>"Updated only this section's {$role} {$property}.",
            'blocks'=>$nextBlocks,
            'typography_settings'=>$typography,
            'before_value'=>$current,
            'after_value'=>$value,
            'relative_direction'=>$increase?'increase':($decrease?'decrease':null),
            'applied_operations'=>[[
                'action'=>'typography_local',
                'index'=>$resolvedTargetIndex,
                'role'=>$role,
                'property'=>$property,
                'value'=>$value,
            ]],
        ];
    }


    private function lunaBackgroundStyleAction(
        string $prompt,
        string $scope,
        int $targetIndex,
        array $blocks,
        array $backgroundStyle
    ): ?array {
        $q=Str::lower(trim($prompt));
        if($q==='')return null;

        $mentions=Str::contains($q,[
            'overlay','gradient','background gradient','image overlay','video overlay',
            'darken background','lighten background','darker background','lighter background'
        ]);
        $change=Str::contains($q,[
            'darker','darken','lighter','lighten','stronger','softer','increase','decrease',
            'set','change','update','make','angle','from #','via #','to #'
        ]);
        if(!$mentions || !$change)return null;

        $global=Str::contains($q,[
            'all ','every ','sitewide','site-wide','whole site','entire site',
            'across the site','across this site','globally'
        ]);
        if($scope!=='section' && !Str::contains($q,['this section','only this section','just this section']))$global=true;

        $nextStyle=$backgroundStyle;
        $local=[];
        if($scope==='section' && isset($blocks[$targetIndex]) && is_array($blocks[$targetIndex])){
            $local=is_array($blocks[$targetIndex]['luna_background_overrides']??null)
                ? $blocks[$targetIndex]['luna_background_overrides']
                : [];
        }

        $target=&$nextStyle;
        if(!$global)$target=&$local;

        $changed=[];
        $adjust=function(string $key,float $fallback,float $delta,float $min=.2,float $max=.99) use (&$target,&$changed){
            $base=is_numeric($target[$key]??null)?(float)$target[$key]:$fallback;
            $target[$key]=round(max($min,min($max,$base+$delta)),2);
            $changed[$key]=$target[$key];
        };

        $darker=Str::contains($q,['darker','darken','stronger overlay','increase overlay']);
        $lighter=Str::contains($q,['lighter','lighten','softer overlay','decrease overlay']);

        if($darker || $lighter){
            $delta=$darker ? .08 : -.08;
            $adjust('overlay_light_strong',.88,$delta,.35,.98);
            $adjust('overlay_light_soft',.62,$delta,.20,.95);
            $adjust('overlay_primary_strong',.88,$delta,.35,.98);
            $adjust('overlay_primary_soft',.82,$delta,.25,.95);
            $adjust('overlay_cinematic_strong',.92,$delta,.45,.99);
            $adjust('overlay_cinematic_soft',.72,$delta,.35,.98);
        }

        if(preg_match('/(?:gradient\s+)?angle(?:\s*(?:to|at|=))?\s*(-?\d+(?:\.\d+)?)\s*(?:deg|degrees?)?/i',$prompt,$m)){
            $angle=max(0,min(360,(float)$m[1]));
            $target['gradient_angle']=$angle;
            $changed['gradient_angle']=$angle;
        }

        $hexes=[];
        if(preg_match_all('/#[0-9A-Fa-f]{6}/',$prompt,$matches))$hexes=$matches[0]??[];
        if(count($hexes)>=2){
            $target['gradient_from']=strtoupper($hexes[0]);
            $target['gradient_to']=strtoupper($hexes[count($hexes)-1]);
            $changed['gradient_from']=$target['gradient_from'];
            $changed['gradient_to']=$target['gradient_to'];
            if(count($hexes)>=3){
                $target['gradient_via']=strtoupper($hexes[1]);
                $changed['gradient_via']=$target['gradient_via'];
            }
        }

        if($changed===[])return null;

        if($global){
            return [
                'reply'=>'Updated the background and overlay styling across the site.',
                'blocks'=>$blocks,
                'background_style'=>$nextStyle,
                'applied_operations'=>[[
                    'action'=>'background_global',
                    'changes'=>$changed,
                ]],
            ];
        }

        if($scope!=='section' || !isset($blocks[$targetIndex]))return null;
        $nextBlocks=array_values($blocks);
        $nextBlocks[$targetIndex]['luna_background_overrides']=$local;

        return [
            'reply'=>'Updated only this section’s background and overlay styling.',
            'blocks'=>$nextBlocks,
            'background_style'=>$backgroundStyle,
            'applied_operations'=>[[
                'action'=>'background_local',
                'index'=>$targetIndex,
                'changes'=>$changed,
            ]],
        ];
    }


    private function lunaSectionLayoutAction(
        string $prompt,
        string $scope,
        int $targetIndex,
        array $blocks,
        array $sectionLayout
    ): ?array {
        $q=Str::lower(trim($prompt));
        if($q==='')return null;

        $mentions=Str::contains($q,[
            'section padding','padding top','padding bottom','vertical padding','horizontal padding',
            'section spacing','spacing between sections','container width','content width','max width',
            'section gap','content gap','minimum height','min height'
        ]);
        $change=Str::contains($q,[
            'increase','decrease','bigger','larger','smaller','reduce','more','less',
            'set','change','update','make','wider','narrower'
        ]);
        if(!$mentions || !$change)return null;

        $global=Str::contains($q,[
            'all sections','every section','sitewide','site-wide','whole site','entire site',
            'across the site','globally','all section'
        ]);
        if($scope!=='section' && !Str::contains($q,['this section','only this section','just this section']))$global=true;

        $next=$sectionLayout;
        $local=[];
        if($scope==='section' && isset($blocks[$targetIndex]) && is_array($blocks[$targetIndex])){
            $local=is_array($blocks[$targetIndex]['luna_section_overrides']??null)
                ? $blocks[$targetIndex]['luna_section_overrides']
                : [];
        }
        $target=&$next;
        if(!$global)$target=&$local;

        $property=match(true){
            Str::contains($q,['padding top'])=>'py_top',
            Str::contains($q,['padding bottom'])=>'py_bottom',
            Str::contains($q,['horizontal padding'])=>'px',
            Str::contains($q,['container width','content width','max width'])=>'container',
            Str::contains($q,['section gap','content gap','spacing between sections'])=>'gap',
            Str::contains($q,['minimum height','min height'])=>'min_height',
            default=>'py',
        };

        $globalKey=match($property){
            'py_top','py_bottom'=>'py',
            default=>$property,
        };

        $defaults=['py'=>100,'px'=>28,'container'=>1408,'gap'=>56,'min_height'=>0];
        $units=['py'=>'px','px'=>'px','container'=>'px','gap'=>'px','min_height'=>'px'];

        $explicit=null;
        if(preg_match('/(\d+(?:\.\d+)?)\s*(px|rem|em|%|vh|svh|vw)/i',$prompt,$m)){
            $explicit=$m[1].Str::lower($m[2]);
        }

        $key=$global ? $globalKey : $property;
        $current=(string)($target[$key]??($defaults[$globalKey].$units[$globalKey]));
        $value=$explicit;

        if($value===null){
            $base=$defaults[$globalKey];
            if(preg_match('/^(\d+(?:\.\d+)?)px$/i',$current,$m))$base=(float)$m[1];
            elseif(preg_match('/^(\d+(?:\.\d+)?)rem$/i',$current,$m))$base=(float)$m[1]*16;

            $increase=Str::contains($q,['increase','bigger','larger','more','wider']);
            $decrease=Str::contains($q,['decrease','smaller','reduce','less','narrower']);
            if($globalKey==='container'){
                $base += $increase ? 128 : ($decrease ? -128 : 0);
                $base=max(560,min(1800,$base));
            }elseif($globalKey==='min_height'){
                $base += $increase ? 80 : ($decrease ? -80 : 0);
                $base=max(0,min(1400,$base));
            }else{
                $base *= $increase ? 1.12 : ($decrease ? .90 : 1.0);
                $base=max(0,min(240,$base));
            }
            $value=rtrim(rtrim(number_format($base,2,'.',''),'0'),'.').'px';
        }

        $target[$key]=$value;

        if($global){
            return [
                'reply'=>"Updated {$globalKey} across all sections.",
                'blocks'=>$blocks,
                'section_layout'=>$next,
                'applied_operations'=>[[
                    'action'=>'section_layout_global',
                    'property'=>$globalKey,
                    'value'=>$value,
                ]],
            ];
        }

        if($scope!=='section' || !isset($blocks[$targetIndex]))return null;
        $nextBlocks=array_values($blocks);
        $nextBlocks[$targetIndex]['luna_section_overrides']=$local;

        return [
            'reply'=>"Updated only this section's {$property}.",
            'blocks'=>$nextBlocks,
            'section_layout'=>$sectionLayout,
            'applied_operations'=>[[
                'action'=>'section_layout_local',
                'index'=>$targetIndex,
                'property'=>$property,
                'value'=>$value,
            ]],
        ];
    }


}
