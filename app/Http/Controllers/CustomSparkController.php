<?php

namespace App\Http\Controllers;

use App\Helpers\CmsHtmlCompiler;
use App\Support\PageStyleRegistry;
use App\Models\CustomSpark;
use App\Models\Website;
use App\Models\TrialGeneration;
use App\Jobs\BuildVisualFirstCustomPageJob;
use App\Services\CreditService;
use App\Services\SparkCatalog;
use App\Services\PlanEntitlementService;
use App\Services\AiPageGenerationService;
use App\Services\LunaTemplatePlannerService;
use App\Services\LunaCategoryPageService;
use App\Services\LunaCreditPricingService;
use App\Services\LunaNaturalReplyService;
use App\Services\LunaPexelsVideoService;
use App\Services\TrialCreditService;
use App\Services\SmartImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
        LunaPexelsVideoService $lunaVideos
    ) {
        abort_if($trial->claimed_at, 410, 'This trial has already been claimed.');

        $validated=$request->validate([
            'prompt'=>['required','string','max:6000'],
            'blocks'=>['required','string','max:350000'],
            'header'=>['nullable','string','max:80000'],
            'footer'=>['nullable','string','max:120000'],
            'theme'=>['nullable','string','max:12000'],
            'typography'=>['nullable','string','max:12000'],
            'background_style'=>['nullable','string','max:12000'],
            'section_layout'=>['nullable','string','max:12000'],
            'site_memory'=>['nullable','string','max:24000'],
            'target_scope'=>['nullable','in:page,section,header,footer'],
            'target_index'=>['nullable','integer','min:0','max:100'],
            'element_context'=>['nullable','string','max:6000'],
            'target_resolved_theme'=>['nullable','string','max:40'],
            'confirmed'=>['nullable','boolean'],
        ]);
        $blocks=json_decode($validated['blocks'],true);
        $header=json_decode((string)($validated['header']??'{}'),true);
        $footer=json_decode((string)($validated['footer']??'{}'),true);
        if(!is_array($blocks)) throw ValidationException::withMessages(['blocks'=>'The page could not be prepared for Luna.']);
        $siteMemory=json_decode((string)($validated['site_memory']??'{}'),true);
        if(!is_array($siteMemory))$siteMemory=[];

        $scope=(string)($validated['target_scope']??'page');
        $targetIndex=$scope==='section'?(int)($validated['target_index']??-1):-1;
        $prompt=trim((string)$validated['prompt']);
        $lower=Str::lower($prompt);
        if($scope==='header'){
            $shellAction=$this->lunaHeaderScopeAction($prompt,is_array($header)?$header:[],$siteMemory);
            if(is_array($shellAction)){
                return response()->json([
                    'reply'=>$shellAction['reply'],
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
        if($guardrailReply=$this->lunaUnsupportedLowLevelDesignRequest($prompt)){
            return response()->json([
                'reply'=>$guardrailReply,
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
        $backgroundRemove=Str::contains($lower,['remove background image','remove the background image','remove background photo','no background image','clear background image']);
        $backgroundDarker=Str::contains($lower,['make background darker','darken the background','darker overlay','stronger overlay']);
        $backgroundLighter=Str::contains($lower,['make background lighter','lighten the background','lighter overlay','softer overlay']);
        $backgroundAdd=!$backgroundRemove && (
            (Str::contains($lower,'background image') && Str::contains($lower,['add','use','set','change','give','put','apply']))
            || (Str::contains($lower,'background photo') && Str::contains($lower,['add','use','set','change','give','put','apply']))
        );
        $universalBackgroundIntent=$backgroundAdd||$backgroundRemove||$backgroundDarker||$backgroundLighter;
        $designGlobalIntent=Str::contains(Str::lower((string)$validated['prompt']),[
            'all sections','every section','all headings','every heading','whole page','entire page','everywhere','sitewide','site-wide'
        ]);
        $artDirectionIntent=$this->lunaArtDirectionIntent((string)$validated['prompt']);
        if($artDirectionIntent)$designGlobalIntent=true;
        $siteMemory=$this->lunaUpdatedSiteMemory((string)$validated['prompt'],$siteMemory,is_array($theme??null)?$theme:[]);


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
            $sections=$lunaPages->plan($prompt);
            $generated=$lunaPages->generate($prompt,$sections);
            try{$remote=$pageGeneration->applyStartPageRemoteImages($prompt,$generated);if(is_array($remote['blocks']??null))$generated=$remote['blocks'];}catch(\Throwable $e){report($e);}
            $cost=40; $trialCredits->ensureCanSpend($trial,$cost,'Building this page');
            $balance=$trialCredits->consume($trial,$cost,'luna_build_page',['scope'=>'trial']);
            $reply=$natural->compose($prompt,[
                'authenticated'=>false,
                'trial'=>true,
                'scope'=>'page',
            ],[
                'action'=>'build page',
                'action_completed'=>true,
                'generated_sections'=>count($generated),
                'credits_used'=>$cost,
            ]);
            return response()->json(['reply'=>$reply,'blocks'=>array_values($generated),'header'=>$header?:[],'footer'=>$footer?:[],'credit_cost'=>$cost,'credit_balance'=>$balance]);
        }

        $usable=collect(SparkCatalog::all())->map(fn($s)=>[
            'key'=>$s['key'],
            'name'=>$s['name'],
            'category'=>$s['category'],
            'description'=>$s['description'],
            'aliases'=>$s['aliases']??[],
            'media'=>$s['media']??'mixed',
            'layout'=>$s['layout']??[],
            'style'=>$s['style']??[],
            'intent'=>$s['intent']??[],
            'industry_fit'=>$s['industry_fit']??[],
            'position_fit'=>$s['position_fit']??[],
            'capabilities'=>$s['capabilities']??[],
        ])->values()->all();
        $summary=collect($blocks)->values()->map(fn($b,$i)=>['index'=>$i,'type'=>$b['type']??'','heading'=>$b['heading']??$b['title']??''])->all();
        $selected=$scope==='section'&&isset($blocks[$targetIndex])?$blocks[$targetIndex]:[];
        $elementContext=json_decode((string)($validated['element_context']??'{}'),true);
        if(!is_array($elementContext))$elementContext=[];
        if($namedTargetOverrodeSelection)$elementContext=[];
        if($elementContext && $selected){
            $needles=array_values(array_filter([
                trim((string)($elementContext['currentValue']??'')),
                trim((string)($elementContext['url']??'')),
            ]));
            $paths=[];
            $walk=function($value,$path='') use (&$walk,&$paths,$needles){
                if(!is_array($value))return;
                foreach($value as $key=>$item){
                    $next=$path===''?(string)$key:$path.'.'.$key;
                    if(is_scalar($item)){
                        foreach($needles as $needle){
                            if($needle!=='' && trim((string)$item)===$needle){$paths[]=$next;break;}
                        }
                    } elseif(is_array($item)){$walk($item,$next);}
                }
            };
            $walk($selected);
            $elementContext['matched_paths']=array_values(array_unique($paths));
        }

        $system='You are Luna, the invisible website editor. Return JSON only: {"reply":"short reply","operations":[{"action":"edit|replace|insert_before|insert_after|delete|move|theme","index":0,"to_index":0,"spark_key":"registered key when needed","theme_key":"","changes":{},"instruction":""}],"header_changes":{},"footer_changes":{},"page_style":null}. Use ONLY Spark keys from the supplied catalog. Rank candidates by requested aliases/media/capabilities FIRST, selected-section semantic intent/category SECOND, then layout/style/industry/position fit. Never default to Hero merely because Hero also supports the requested media; preserve the selected section role unless the user explicitly asks to change it. For simple edits use edit and only existing schema keys. REPEATER/LIST CRUD IS NON-STRUCTURAL: requests to add, remove, update, rename, expand, reduce, or reorder services/cards/items/testimonials/FAQs/team/pricing/features/logos/gallery/process/list entries MUST use edit on the existing selected section and preserve its current Spark/layout. Update the existing array/repeater key from SELECTED using the full resulting array; do not replace the section unless the user explicitly asks for a different layout/design/type. STRUCTURAL COMMANDS ARE REAL ACTIONS: "change/turn this banner or section into a slider/video/testimonials/etc" MUST use replace on the selected index with the closest matching registered Spark; never simulate a structural change with copy edits. "move this section to the top/first" MUST use move with to_index=0. "move to bottom/last" MUST use move with to_index equal to the last page index. "move up/down" must use move. "add above/below" must use insert_before/insert_after. Section scope may replace, move, delete, or edit the selected section and may insert immediately above/below it. page_style may be balanced|clean|premium only when explicitly requested. header_changes and footer_changes may change shell state when explicitly requested. In page scope, resolve natural section names (hero, banner, services, testimonials, pricing, FAQ, contact, CTA, gallery, process, team, about) from PAGE headings/types. When ELEMENT TARGET is non-empty, treat it as the exact clicked element. Interpret relative design language naturally: a little/slightly means a modest change; more/bigger/roomier means increase from current state; less/smaller/tighter means decrease. References such as "like the hero above", "same as Services", "match the section below", or "similar to the previous section" mean use that existing section as the visual reference while preserving the target section content/role. Never claim a change is complete unless an operation actually changes website state; the server verifies before/after state. MULTI-STEP REQUESTS: when the user asks for several compatible changes in one message, plan all of them in order rather than completing only the first. Use SITE DESIGN MEMORY as a consistency guide, not as permission to override an explicit current request. PAGE ART DIRECTION: requests such as make this page more premium/polished/modern may make coordinated restrained changes across multiple sections while preserving content and semantic section roles. For ordinary content/style requests, edit only matching fields indicated by matched_paths/currentValue/url and preserve the rest of the section. Explicit section transformation/reorder requests override element-only targeting. Never claim a structural change unless you emitted the corresponding operation. Never mention Sparks/templates/schemas to the user. Do not invent image URLs. THEME INTELLIGENCE: choose a theme only when the user explicitly asks for a theme/color-family change or when a first-build planner specifically requests one. Never choose midnight as a generic/default theme; use midnight only when the user explicitly asks for midnight/night styling. For vague style directions, preserve the current site theme and redesign within that family. REQUEST INTELLIGENCE: distinguish content edits from structural redesigns. Text/image/link/name/label changes edit the existing Spark. Add/remove/reorder list or card items edits the repeater. Requests for another layout, redesign, slider, video hero, split, grid, mosaic, testimonial style, or different section type are structural and may replace with the closest registered Spark. Global typography/spacing/background requests are handled by the design-token router; section-specific requests should remain local. Header overlay/logo/nav requests belong to the global header, not the body Spark. If a request contains multiple compatible actions, complete all applicable actions in order. ';
        $apiKey=(string)config('openai.api_key'); abort_if($apiKey==='',503,'Luna is temporarily unavailable.');
        $response=Http::withToken($apiKey)->connectTimeout(30)->timeout(150)->post(rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions',[
            'model'=>env('OPENAI_MODEL','gpt-5-mini'),'response_format'=>['type'=>'json_object'],
            'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>"SCOPE: {$scope}\nTARGET: {$targetIndex}\nREQUEST: {$prompt}\nPAGE: ".json_encode($summary)."\nSELECTED: ".json_encode($selected)."\nELEMENT TARGET: ".json_encode($elementContext)."\nSITE DESIGN MEMORY: ".json_encode($siteMemory)."\nCATALOG: ".json_encode($usable)]],
        ])->throw()->json();
        $plan=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
        $ops=array_values(array_slice(is_array($plan['operations']??null)?$plan['operations']:[],0,12));

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

        // Generic redesign fallback: "redesign this section", "make this section better",
        // "make it more premium/modern/polished", etc. must produce a real structural
        // alternative even when the planner returns no effective mutation.
        if($scope==='section' && isset($blocks[$targetIndex])){
            $genericRedesign=Str::contains($lower,[
                'redesign this section','redesign the section','redesign this','another layout','different layout',
                'new layout','make this section better','make this better','improve this section',
                'make this section more premium','make this more premium','make this section modern',
                'make this more modern','make this section polished','make this more polished',
                'refresh this section','rework this section','restyle this section'
            ]);
            $hasStructural=collect($ops)->contains(fn($op)=>is_array($op)
                && in_array(($op['action']??''),['replace','delete','move','insert_before','insert_after'],true)
                && (int)($op['index']??-1)===$targetIndex);

            if($genericRedesign && !$hasStructural){
                $current=(array)$blocks[$targetIndex];
                $currentKey=(string)($current['type']??'');
                $currentMeta=SparkCatalog::find($currentKey)??[];
                $category=Str::lower((string)($currentMeta['category']??''));
                $intent=collect($currentMeta['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $industry=collect($currentMeta['industry_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $position=collect($currentMeta['position_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();

                $ranked=collect($usable)->filter(fn($spark)=>is_array($spark) && !empty($spark['key']) && ($spark['key']??'')!==$currentKey)
                    ->map(function($spark) use($category,$intent,$industry,$position,$lower){
                        $score=0;
                        $sparkCategory=Str::lower((string)($spark['category']??''));
                        if($category!=='' && $sparkCategory===$category) $score+=180;
                        elseif($category!=='' && in_array($sparkCategory,['hero','header'],true)) $score-=140;
                        $sparkIntent=collect($spark['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $sparkIndustry=collect($spark['industry_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $sparkPosition=collect($spark['position_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $score+=count(array_intersect($intent,$sparkIntent))*32;
                        $score+=count(array_intersect($industry,$sparkIndustry))*14;
                        $score+=count(array_intersect($position,$sparkPosition))*9;
                        $style=collect($spark['style']??[])->map(fn($v)=>Str::lower((string)$v))->implode(' ');
                        if(Str::contains($lower,'premium') && Str::contains($style,['premium','editorial','cinematic','luxury'])) $score+=35;
                        if(Str::contains($lower,'modern') && Str::contains($style,['modern','clean','editorial','minimal'])) $score+=28;
                        if(Str::contains($lower,'polished') && Str::contains($style,['premium','clean','editorial'])) $score+=24;
                        // Stable tie-break keeps repeated prompts predictable without biasing one Spark forever.
                        $score+=(abs(crc32(($spark['key']??'').'|'.$lower))%17);
                        $spark['_luna_redesign_score']=$score;
                        return $spark;
                    })->sortByDesc('_luna_redesign_score')->values();

                $candidate=$ranked->first(fn($spark)=>($spark['_luna_redesign_score']??0)>0);
                if(is_array($candidate) && !empty($candidate['key'])){
                    $ops[]=[
                        'action'=>'replace',
                        'index'=>$targetIndex,
                        'spark_key'=>$candidate['key'],
                        'instruction'=>'Redesign the selected section into a clearly different, higher-quality layout while preserving its semantic purpose, useful copy, CTA intent, and current site theme. Do not return the same layout.',
                    ];
                }
            }
        }

        $pricing=$lunaPricing->estimate($prompt,$ops,$scope);
        $cost=(int)($pricing['credits']??0);
        if(($pricing['requires_confirmation']??false) && !($validated['confirmed']??false)){
            $planningCost=2;
            $trialCredits->ensureCanSpend($trial,$planningCost,'Luna planning');
            $balance=$trialCredits->consume($trial,$planningCost,'luna_plan_confirmation',['scope'=>$scope]);
            $reply=$natural->compose($prompt,[
                'authenticated'=>false,
                'trial'=>true,
                'scope'=>$scope,
            ],[
                'action_completed'=>false,
                'confirmation_required'=>true,
                'estimated_execution_cost'=>$cost,
                'planning_credits_used'=>$planningCost,
            ]);
            return response()->json([
                'reply'=>$reply,
                'mode'=>'confirm',
                'confirmation_cost'=>$cost,
                'credit_cost'=>$planningCost,
                'credit_balance'=>$balance,
            ]);
        }
        if($cost>0)$trialCredits->ensureCanSpend($trial,$cost,'This Luna change');

        $keys=collect($usable)->pluck('key')->flip(); $next=array_values($blocks); $beforeFingerprint=hash('sha256',json_encode($next,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:''); $applied=[];
        foreach($ops as $op){
            if(!is_array($op))continue;$action=(string)($op['action']??'');$i=(int)($op['index']??-1);
            if($scope==='section'&&!in_array($action,['insert_before','insert_after'],true)&&$i!==$targetIndex)continue;
            if($action==='edit'&&isset($next[$i])&&is_array($op['changes']??null)){
                $changes=$op['changes'];unset($changes['type'],$changes['_renderKey']);$changes=array_intersect_key($changes,$next[$i]);$next[$i]=array_merge($next[$i],$changes);$applied[]=['action'=>'edit','index'=>$i];continue;
            }
            if(in_array($action,['replace','insert_before','insert_after'],true)){
                $key=(string)($op['spark_key']??'');if(!$keys->has($key))continue;
                $gen=$lunaPages->generate($prompt."\n".(string)($op['instruction']??''),[$key]);$block=$gen[0]??null;if(!is_array($block))continue;
                try{$remote=$pageGeneration->applyStartPageRemoteImages($prompt,[$block]);if(is_array($remote['blocks'][0]??null))$block=$remote['blocks'][0];}catch(\Throwable $e){report($e);}
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

        $trialThemeKey=collect($applied)->first(fn($item)=>($item['action']??'')==='theme')['theme_key']??null;
        $trialPageStyle=in_array(Str::lower((string)($plan['page_style']??'')),['balanced','clean','premium'],true)
            ? Str::lower((string)$plan['page_style']) : null;
        $shellChanged=!empty($plan['header_changes']??[])||!empty($plan['footer_changes']??[])||$trialPageStyle!==null||$trialThemeKey!==null;
        $afterFingerprint=hash('sha256',json_encode(array_values($next),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
        $blocksActuallyChanged=!hash_equals($beforeFingerprint,$afterFingerprint);
        if(!$blocksActuallyChanged){
            $applied=array_values(array_filter($applied,fn($item)=>in_array(($item['action']??''),['theme'],true)));
        }
        $verifiedSomething=$blocksActuallyChanged||$shellChanged;
        $chargedCost=$verifiedSomething ? $cost : 0;
        $balance=$chargedCost>0
            ? $trialCredits->consume($trial,$chargedCost,'luna_change',['scope'=>$scope,'operations'=>$applied])
            : $trialCredits->balance($trial);
        $reply=($universalBackgroundIntent && !$backgroundApplied)
            ? ('I could not apply the background image change yet. '.($backgroundError?:'The image provider did not return a usable background.'))
            : ($verifiedSomething
                ? (trim((string)($plan['reply']??''))?:'The requested change was applied.')
                : 'I could not verify a real change to the current website, so I left it as-is.');
        return response()->json([
            'reply'=>$reply,
            'blocks'=>$next,
            'header'=>array_merge(is_array($header)?$header:[],is_array($plan['header_changes']??null)?$plan['header_changes']:[]),
            'footer'=>array_merge(is_array($footer)?$footer:[],is_array($plan['footer_changes']??null)?$plan['footer_changes']:[]),
            'theme_key'=>$trialThemeKey,
            'page_style'=>$trialPageStyle,
            'credit_cost'=>$chargedCost,
            'credit_balance'=>$balance,
            'site_memory'=>$siteMemory,
            'applied_operations'=>$applied
        ]);
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
        LunaPexelsVideoService $lunaVideos
    ) {
        $this->authorize('update', $website);

        $validated=$request->validate([
            'prompt'=>['required','string','max:6000'],
            'blocks'=>['required','string','max:350000'],
            'header'=>['nullable','string','max:80000'],
            'footer'=>['nullable','string','max:120000'],
            'theme'=>['nullable','string','max:12000'],
            'site_memory'=>['nullable','string','max:24000'],
            'target_scope'=>['nullable','in:page,section,header,footer'],
            'target_index'=>['nullable','integer','min:0','max:100'],
            'element_context'=>['nullable','string','max:6000'],
            'target_resolved_theme'=>['nullable','string','max:40'],
            'confirmed'=>['nullable','boolean'],
        ]);

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
        if(!is_array($blocks)) {
            throw ValidationException::withMessages(['blocks'=>'The current page could not be prepared for Luna.']);
        }
        $siteMemory=json_decode((string)($validated['site_memory']??'{}'),true);
        if(!is_array($siteMemory))$siteMemory=[];

        $scope=(string)($validated['target_scope']??'page');
        $targetIndex=$scope==='section' ? (int)($validated['target_index']??-1) : -1;
        if($scope==='section' && !isset($blocks[$targetIndex])) {
            throw ValidationException::withMessages(['target_index'=>'That section is no longer available.']);
        }

        $normalizedPrompt=Str::lower(trim((string)$validated['prompt']));
        $user=$request->user();

        // Typography commands are deterministic and cost 0 credits because they
        // update existing design tokens without making an AI/API request.
        $typographyAction=$this->lunaTypographyAction(
            (string)$validated['prompt'],
            $scope,
            $targetIndex,
            $blocks,
            $typography
        );
        if(is_array($typographyAction)){
            return response()->json([
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
                'site_memory'=>$siteMemory,
                'applied_operations'=>$typographyAction['applied_operations'],
            ]);
        }

        $backgroundAction=$this->lunaBackgroundStyleAction(
            (string)$validated['prompt'],
            $scope,
            $targetIndex,
            $blocks,
            $backgroundStyle
        );
        if(is_array($backgroundAction)){
            return response()->json([
                'reply'=>$backgroundAction['reply'],
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
            (string)$validated['prompt'],
            $scope,
            $targetIndex,
            $blocks,
            $sectionLayout
        );
        if(is_array($sectionLayoutAction)){
            return response()->json([
                'reply'=>$sectionLayoutAction['reply'],
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

        if($scope==='header'){
            $shellAction=$this->lunaHeaderScopeAction((string)$validated['prompt'],is_array($header)?$header:[],$siteMemory);
            if(is_array($shellAction)){
                return response()->json([
                    'reply'=>$shellAction['reply'],
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

        if($scope==='header'){
            $pageNavAction=$this->lunaPageNavigationAction((string)$validated['prompt'],$website,is_array($header)?$header:[]);
            if(is_array($pageNavAction)){
                return response()->json([
                    'reply'=>$pageNavAction['reply'],
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
        if($guardrailReply=$this->lunaUnsupportedLowLevelDesignRequest((string)$validated['prompt'])){
            return response()->json([
                'reply'=>$guardrailReply,
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
        $backgroundRemove=Str::contains($normalizedPrompt,['remove background image','remove the background image','remove background photo','no background image','clear background image']);
        $backgroundDarker=Str::contains($normalizedPrompt,['make background darker','darken the background','darker overlay','stronger overlay']);
        $backgroundLighter=Str::contains($normalizedPrompt,['make background lighter','lighten the background','lighter overlay','softer overlay']);
        $backgroundAdd=!$backgroundRemove && (
            (Str::contains($normalizedPrompt,'background image') && Str::contains($normalizedPrompt,['add','use','set','change','give','put','apply']))
            || (Str::contains($normalizedPrompt,'background photo') && Str::contains($normalizedPrompt,['add','use','set','change','give','put','apply']))
        );
        $universalBackgroundIntent=$backgroundAdd||$backgroundRemove||$backgroundDarker||$backgroundLighter;
        $designGlobalIntent=Str::contains(Str::lower((string)$validated['prompt']),[
            'all sections','every section','all headings','every heading','whole page','entire page','everywhere','sitewide','site-wide'
        ]);
        $artDirectionIntent=$this->lunaArtDirectionIntent((string)$validated['prompt']);
        if($artDirectionIntent)$designGlobalIntent=true;
        $siteMemory=$this->lunaUpdatedSiteMemory((string)$validated['prompt'],$siteMemory,is_array($theme??null)?$theme:[]);


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

        // Empty page: Luna silently uses the existing hidden template/Spark planner,
        // then returns a complete starter page. No Add Spark/Template UI is required.
        if($scope==='page' && count($blocks)===0){
            try {
                $sections=$lunaPages->plan((string)$validated['prompt']);
                $generated=$lunaPages->generate((string)$validated['prompt'],$sections);
                if(is_array($generated) && count($generated)>0){
                    $creditCost=40;
                    if($user && !$credits->canAfford($user,$creditCost)){
                        return response()->json(['message'=>"Building this page needs {$creditCost} credits.",'credit_cost'=>$creditCost,'requires_credits'=>true],422);
                    }
                    try {
                        $remote=$pageGeneration->applyStartPageRemoteImages((string)$validated['prompt'],$generated);
                        if(is_array($remote['blocks']??null) && count($remote['blocks'])>0){
                            $generated=$remote['blocks'];
                        }
                    } catch(\Throwable $e) {
                        report($e);
                    }

                    if($user) $credits->consume($user,$creditCost,'Luna page build',$website,'luna-build-'.Str::uuid(),['category'=>'ai']);
                    $reply=$natural->compose((string)$validated['prompt'],[
                        'authenticated'=>true,
                        'scope'=>'page',
                        'website'=>$website->name,
                    ],[
                        'action'=>'build page',
                        'action_completed'=>true,
                        'generated_sections'=>count($generated),
                        'credits_used'=>$creditCost,
                    ]);
                    return response()->json([
                        'reply'=>$reply,
                        'blocks'=>array_values($generated),
                        'header'=>is_array($header)?$header:[],
                        'footer'=>is_array($footer)?$footer:[],
                        'theme_key'=>null,
                        'credit_cost'=>$creditCost,
                        'credit_balance'=>$user ? $credits->balance($user) : null,
                        'applied_operations'=>[['action'=>'build_page','count'=>count($generated)]],
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
        $usable=collect(SparkCatalog::all())
            ->map(fn(array $spark)=>[
                'key'=>$spark['key'],
                'name'=>$spark['name'],
                'category'=>$spark['category'],
                'description'=>$spark['description'],
                'aliases'=>$spark['aliases']??[],
                'media'=>$spark['media']??'mixed',
                'layout'=>$spark['layout']??[],
                'style'=>$spark['style']??[],
                'intent'=>$spark['intent']??[],
                'industry_fit'=>$spark['industry_fit']??[],
                'position_fit'=>$spark['position_fit']??[],
                'capabilities'=>$spark['capabilities']??[],
            ])
            ->values()
            ->all();

        $catalogJson=json_encode($usable,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $elementContext=json_decode((string)($validated['element_context']??'{}'),true);
        if(!is_array($elementContext))$elementContext=[];
        if($namedTargetOverrodeSelection)$elementContext=[];
        if($elementContext && $scope==='section' && isset($blocks[$targetIndex])){
            $needles=array_values(array_filter([
                trim((string)($elementContext['currentValue']??'')),
                trim((string)($elementContext['url']??'')),
            ]));
            $paths=[];
            $walk=function($value,$path='') use (&$walk,&$paths,$needles){
                if(!is_array($value))return;
                foreach($value as $key=>$item){
                    $next=$path===''?(string)$key:$path.'.'.$key;
                    if(is_scalar($item)){
                        foreach($needles as $needle){
                            if($needle!=='' && trim((string)$item)===$needle){$paths[]=$next;break;}
                        }
                    } elseif(is_array($item)){$walk($item,$next);}
                }
            };
            $walk($blocks[$targetIndex]);
            $elementContext['matched_paths']=array_values(array_unique($paths));
        }
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
 "reply":"short natural confirmation",
 "operations":[
   {"action":"edit","index":0,"changes":{}},
   {"action":"replace","index":0,"spark_key":"exact_registered_key","instruction":"content/style migration instruction"},
   {"action":"insert_before|insert_after","index":0,"spark_key":"exact_registered_key","instruction":"what this new section should contain"},
   {"action":"delete","index":0},
   {"action":"move","index":0,"to_index":2},
   {"action":"theme","theme_key":"midnight|emerald|coffee|rose|ocean|indigo|amber|charcoal|violet|teal|ruby|forest|obsidian|navy|espresso|terracotta|asphalt"}
 ],
 "header_changes":{},
 "footer_changes":{},
 "page_style":"balanced|clean|premium|null"
}

RULES:
- Use ONLY spark_key values present in USABLE SPARK CATALOG.
- CROSS-PAGE REFERENCES: when the user says match Home/About/Services/etc, use SIBLING PAGE REFERENCES as design context. Preserve current-page content unless explicitly asked to replace it. Edit the current page only; never claim another page changed.
- SPARK SELECTION RANKING: requested media/capability/aliases are the strongest signal; current selected section intent/category is second; layout/style/industry/position fit are third. Do NOT prefer Hero merely because a candidate is a Hero.
- A request for "slider" means consider EVERY catalog item whose media=slider, aliases mention slider/carousel/slideshow, or capabilities include supports-slider. Then choose the one whose intent best matches the selected section. Example: testimonials -> testimonial carousel; portfolio/work -> gallery/project slider; opening banner -> hero slider.
- Preserve semantic role when transforming media unless the user explicitly asks to change the role. A mid-page services/work/testimonial section should not become a Hero just because Hero has the requested media.
- Use position_fit=top/opening-section-safe as a bonus only when the target is actually the first/opening section.
- Never reveal implementation details such as Spark IDs, templates, schemas or hidden selection.
- For a simple copy/color/image/repeater adjustment, prefer edit. In section scope, use keys exactly from SELECTED BLOCK FULL JSON; do not invent schema keys.
- REPEATER/LIST CRUD MUST PRESERVE LAYOUT: add/remove/update/rename/expand/reduce/reorder services, cards, items, testimonials, FAQs, team members, pricing entries, features, logos, gallery items, steps/process entries, or similar collections by editing the existing array key in SELECTED BLOCK FULL JSON. Return the complete resulting array under that same key. Never use replace for these requests unless the user explicitly asks to change the layout/design/section type.
- STRUCTURAL COMMANDS MUST EMIT STRUCTURAL OPERATIONS. For "change/turn this banner/section into a video", "use a slider", "make this testimonials", etc., use replace on the exact target index with the closest registered Spark. Never answer a structural request with only edit/copy changes.
- For "add X below/above", use insert_after/insert_before relative to the target.
- For "move this section to the top/first", emit move with to_index=0. For "move to the bottom/last", emit move with to_index equal to the last page index. For "move up/down", emit move to the adjacent index.
- In SELECTED SECTION scope, "this", "it", "the slider", "this banner", and similar references mean the selected index unless the user explicitly names another section.
- When ELEMENT TARGET is non-empty, it identifies the exact clicked heading/text/label/button/image. For ordinary edits, change only the matching field(s) indicated by matched_paths/currentValue/url and preserve unrelated fields. For button targets, update label and URL together when the user supplies both. Explicit structural section requests override element-only targeting.
- Preserve useful current content when replacing; instruction should explicitly say what to migrate.
- Do not replace a section when its current Spark can safely satisfy the request.
- Section scope: operate on the selected index only, except an explicit add-before/add-after request.
- Page scope may target sections by natural language. Resolve names like hero, services, testimonials, pricing, FAQ, contact, CTA, gallery, process, team, about from CURRENT PAGE SUMMARY headings/types and use the matching index.
- Requests like "change the hero to video", "add testimonials below services", "move FAQ above contact", or "make pricing darker" should work without the user manually selecting a section.
- Page scope: you may coordinate multiple operations and theme changes, max 8 operations.
- page_style may be balanced, clean, or premium only when explicitly requested. Use it for requests like "make the page premium/clean/balanced".
- Header/footer changes only if explicitly requested or essential to a page-wide theme request.
- Header language mapping: "float header", "overlay header", "header over hero/banner", "transparent header" means header_changes.overlay_header_on_banner=true. "put header above/outside the banner", "solid header", or "disable overlay" means false.
- DESIGN SAFETY CONTRACT: Cosmic owns typography scales, responsive breakpoints, low-level spacing values, raw CSS/Tailwind/HTML/JS, positioning, z-index, transforms, and other implementation mechanics. Never emit arbitrary technical styling values.
- Accept normal design intent (make it more prominent, more breathing room, darker/lighter, rounded, cleaner, premium, change layout) only through existing safe schema fields, registered layouts, theme/page_style, or bounded existing design controls. If the exact request cannot be represented safely, emit no styling operation and explain briefly that Luna can apply a design-system-safe equivalent instead.
- CHANGE ONLY WHAT THE USER REQUESTED. Preserve all unrelated content, typography, spacing, colors, layout, and media.
- Do not invent raw HTML/CSS/JS/PHP/SQL or image URLs. Cosmic resolves requested photography through its image provider after your plan.
- Never change ecommerce/dynamic data bindings unless explicitly requested.
- Never delete content unless the user asks.
- Never claim success in reply unless the JSON contains the operation/state change that performs the request.
PROMPT;

        $apiKey=(string)config('openai.api_key');
        abort_if($apiKey==='',503,'OpenAI is not configured.');

        $response=Http::withToken($apiKey)->timeout(150)->post(
            rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions',
            [
                'model'=>env('OPENAI_MODEL','gpt-5-mini'),
                'response_format'=>['type'=>'json_object'],
                'messages'=>[
                    ['role'=>'system','content'=>$system],
                    ['role'=>'user','content'=>"SCOPE: {$scope}".($scope==='section'?"\nSELECTED INDEX: {$targetIndex}":"")."\n\nUSER REQUEST:\n".$validated['prompt']."\n\nCURRENT PAGE SUMMARY:\n".json_encode($blockSummary,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nSELECTED BLOCK FULL JSON:\n".$targetContext."\n\nELEMENT TARGET:\n".json_encode($elementContext,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nCURRENT THEME:\n".json_encode(is_array($theme)?$theme:[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nSITE DESIGN MEMORY:\n".json_encode($siteMemory,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nSIBLING PAGE REFERENCES:\n".json_encode($siblingPages,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nUSABLE SPARK CATALOG:\n{$catalogJson}"],
                ],
            ]
        )->throw()->json();

        $plan=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
        if(!is_array($plan)) {
            throw ValidationException::withMessages(['prompt'=>'Luna returned an invalid design plan.']);
        }

        $operations=array_values(array_slice(is_array($plan['operations']??null)?$plan['operations']:[],0,8));

        $requestLower=Str::lower((string)$validated['prompt']);
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
                if($desiredKind==='video' && Str::contains($lower,['video background','background video'])){
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


        // Generic redesign fallback: "redesign this section", "make this section better",
        // "make it more premium/modern/polished", etc. must produce a real structural
        // alternative even when the planner returns no effective mutation.
        if($scope==='section' && isset($blocks[$targetIndex])){
            $genericRedesign=Str::contains($requestLower,[
                'redesign this section','redesign the section','redesign this','another layout','different layout',
                'new layout','make this section better','make this better','improve this section',
                'make this section more premium','make this more premium','make this section modern',
                'make this more modern','make this section polished','make this more polished',
                'refresh this section','rework this section','restyle this section'
            ]);
            $hasStructural=collect($operations)->contains(fn($op)=>is_array($op)
                && in_array(($op['action']??''),['replace','delete','move','insert_before','insert_after'],true)
                && (int)($op['index']??-1)===$targetIndex);

            if($genericRedesign && !$hasStructural){
                $current=(array)$blocks[$targetIndex];
                $currentKey=(string)($current['type']??'');
                $currentMeta=SparkCatalog::find($currentKey)??[];
                $category=Str::lower((string)($currentMeta['category']??''));
                $intent=collect($currentMeta['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $industry=collect($currentMeta['industry_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                $position=collect($currentMeta['position_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();

                $ranked=collect($usable)->filter(fn($spark)=>is_array($spark) && !empty($spark['key']) && ($spark['key']??'')!==$currentKey)
                    ->map(function($spark) use($category,$intent,$industry,$position,$requestLower){
                        $score=0;
                        $sparkCategory=Str::lower((string)($spark['category']??''));
                        if($category!=='' && $sparkCategory===$category) $score+=180;
                        elseif($category!=='' && in_array($sparkCategory,['hero','header'],true)) $score-=140;
                        $sparkIntent=collect($spark['intent']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $sparkIndustry=collect($spark['industry_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $sparkPosition=collect($spark['position_fit']??[])->map(fn($v)=>Str::lower((string)$v))->all();
                        $score+=count(array_intersect($intent,$sparkIntent))*32;
                        $score+=count(array_intersect($industry,$sparkIndustry))*14;
                        $score+=count(array_intersect($position,$sparkPosition))*9;
                        $style=collect($spark['style']??[])->map(fn($v)=>Str::lower((string)$v))->implode(' ');
                        if(Str::contains($requestLower,'premium') && Str::contains($style,['premium','editorial','cinematic','luxury'])) $score+=35;
                        if(Str::contains($requestLower,'modern') && Str::contains($style,['modern','clean','editorial','minimal'])) $score+=28;
                        if(Str::contains($requestLower,'polished') && Str::contains($style,['premium','clean','editorial'])) $score+=24;
                        // Stable tie-break keeps repeated prompts predictable without biasing one Spark forever.
                        $score+=(abs(crc32(($spark['key']??'').'|'.$requestLower))%17);
                        $spark['_luna_redesign_score']=$score;
                        return $spark;
                    })->sortByDesc('_luna_redesign_score')->values();

                $candidate=$ranked->first(fn($spark)=>($spark['_luna_redesign_score']??0)>0);
                if(is_array($candidate) && !empty($candidate['key'])){
                    $operations[]=[
                        'action'=>'replace',
                        'index'=>$targetIndex,
                        'spark_key'=>$candidate['key'],
                        'instruction'=>'Redesign the selected section into a clearly different, higher-quality layout while preserving its semantic purpose, useful copy, CTA intent, and current site theme. Do not return the same layout.',
                    ];
                }
            }
        }

        $pricing=$lunaPricing->estimate((string)$validated['prompt'],$operations,$scope);
        $creditCost=(int)($pricing['credits']??0);
        if(($pricing['requires_confirmation']??false) && !($validated['confirmed']??false)){
            $planningCost=2;
            if($user){
                abort_unless($credits->canAfford($user,$planningCost),422,"Luna planning needs {$planningCost} credits.");
                $credits->consume($user,$planningCost,'Luna planning',$website,'luna-plan-'.Str::uuid(),['category'=>'ai','scope'=>$scope]);
            }
            $reply=$natural->compose((string)$validated['prompt'],[
                'authenticated'=>true,
                'scope'=>$scope,
                'website'=>$website->name,
            ],[
                'action_completed'=>false,
                'confirmation_required'=>true,
                'estimated_execution_cost'=>$creditCost,
                'planning_credits_used'=>$planningCost,
            ]);
            return response()->json([
                'reply'=>$reply,
                'mode'=>'confirm',
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
        $beforeFingerprint=hash('sha256',json_encode($nextBlocks,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
        $applied=[];

        foreach($operations as $operation){
            if(!is_array($operation)) continue;
            $action=(string)($operation['action']??'');
            $index=(int)($operation['index']??-1);

            if($scope==='section' && !in_array($action,['insert_before','insert_after'],true) && $index!==$targetIndex) {
                continue;
            }

            if($action==='edit' && isset($nextBlocks[$index]) && is_array($operation['changes']??null)){
                $current=$nextBlocks[$index];
                $changes=$operation['changes'];
                unset($changes['type'],$changes['_renderKey']);
                $changes=array_intersect_key($changes,$current);
                $nextBlocks[$index]=array_merge($current,$changes);
                $applied[]=['action'=>'edit','index'=>$index];
                continue;
            }

            if(in_array($action,['replace','insert_before','insert_after'],true)){
                $sparkKey=trim((string)($operation['spark_key']??''));
                if($sparkKey==='' || !$catalogKeys->has($sparkKey)) continue;

                $reference=$nextBlocks[$index]??null;
                $migration=is_array($reference)
                    ? json_encode(array_intersect_key($reference,array_flip(['heading','title','eyebrow','text','subheading','primary_label','secondary_label','items','cards','slides','images','image_url','video_url','theme'])),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
                    : '{}';
                $instruction=trim((string)($operation['instruction']??''));
                $generationPrompt=trim($validated['prompt'])."\n\nLUNA ORCHESTRATION:\nBuild exactly one {$sparkKey} Spark."
                    .($instruction!==''?"\nInstruction: {$instruction}":'')
                    ."\nPreserve/adapt useful content from this source block when relevant:\n{$migration}";

                try {
                    $generated=$lunaPages->generate($generationPrompt,[$sparkKey]);
                    $newBlock=is_array($generated[0]??null)?$generated[0]:null;
                } catch(\Throwable $e) {
                    report($e);
                    $newBlock=null;
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
                    $nextBlocks[$index]=$newBlock;
                    $applied[]=['action'=>'replace','index'=>$index,'type'=>$sparkKey];
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
        $designTargets=$designGlobalIntent ? array_keys($nextBlocks) : (($scope==='section'&&isset($nextBlocks[$targetIndex]))?[$targetIndex]:[]);
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
        $imageRequest=!$universalBackgroundIntent && Str::contains(Str::lower((string)$validated['prompt']),[
            'image','images','photo','photos','photography','unsplash','picture','pictures',
            'replace the image','change the image','change image','new image'
        ]);
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

        $allowedThemes=['midnight','emerald','coffee','rose','ocean','indigo','amber','charcoal','violet','teal','ruby','forest','obsidian','navy','espresso','terracotta','asphalt'];
        $themeKey=null;
        foreach($operations as $operation){
            if(($operation['action']??'')==='theme' && in_array(($operation['theme_key']??''),$allowedThemes,true)){
                $themeKey=(string)$operation['theme_key'];
                break;
            }
        }

        $headerChanges=is_array($plan['header_changes']??null)?$plan['header_changes']:[];
        $footerChanges=is_array($plan['footer_changes']??null)?$plan['footer_changes']:[];
        $safeHeader=is_array($header)?array_merge($header,$headerChanges):$headerChanges;
        $safeFooter=is_array($footer)?array_merge($footer,$footerChanges):$footerChanges;
        $pageStyle=in_array(Str::lower((string)($plan['page_style']??'')),['balanced','clean','premium'],true)
            ? Str::lower((string)$plan['page_style']) : null;

        $shellChanged=!empty($headerChanges)||!empty($footerChanges)||$themeKey!==null||$pageStyle!==null;
        $afterFingerprint=hash('sha256',json_encode(array_values($nextBlocks),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
        $blocksActuallyChanged=!hash_equals($beforeFingerprint,$afterFingerprint);
        if(!$blocksActuallyChanged){
            $applied=array_values(array_filter($applied,fn($item)=>in_array(($item['action']??''),['theme'],true)));
        }
        $verifiedSomething=$blocksActuallyChanged||$shellChanged;
        $chargedCreditCost=$verifiedSomething ? $creditCost : 0;
        if($chargedCreditCost>0 && $user){
            $credits->consume($user,$chargedCreditCost,'Luna website change',$website,'luna-page-chat-'.Str::uuid(),[
                'category'=>'ai','scope'=>$scope,'operations'=>$applied,
            ]);
        }
        $reply=trim((string)($plan['reply']??''));
        if($universalBackgroundIntent && !$backgroundApplied){
            $reply='I could not apply the background image change yet. '.($backgroundError?:'The image provider did not return a usable background.');
        } elseif($imageRequest && $imageRefreshSucceeded===false){
            $reply='I could not replace the requested images yet. '.($imageRefreshError?:'No new matching images were returned.');
        } elseif(!$verifiedSomething){
            $reply='I could not verify a real change to the current website, so I left it as-is.';
        } elseif($reply===''){
            $reply='The requested change was applied.';
        }

        return response()->json([
            'reply'=>$reply,
            'credit_cost'=>$chargedCreditCost,
            'credit_balance'=>$user ? $credits->balance($user) : null,
            'blocks'=>$nextBlocks,
            'header'=>$safeHeader,
            'footer'=>$safeFooter,
            'theme_key'=>$themeKey,
            'page_style'=>$pageStyle,
            'site_memory'=>$siteMemory,
            'applied_operations'=>$applied,
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
     * Deterministic typography intent router.
     * Global wording updates website theme typography; selected-section wording
     * writes only luna_typography_overrides on that block.
     */
    private function lunaTypographyAction(
        string $prompt,
        string $scope,
        int $targetIndex,
        array $blocks,
        array $typography
    ): ?array {
        $q=Str::lower(trim($prompt));
        if($q==='')return null;

        $mentionsType=Str::contains($q,[
            'typography','font size','font-size','line height','line-height','font weight','font-weight',
            'letter spacing','letter-spacing','h1','h2','h3','h4','h5','h6','heading 1','heading 2','heading 3','heading 4','heading 5','heading 6',
            'body text','paragraph text','paragraphs','card title','card titles','card heading','card headings','stat title','stat titles','card body','card text','eyebrow','labels','badge','badges','meta','button text'
        ]);
        $changeVerb=Str::contains($q,[
            'increase','decrease','bigger','larger','smaller','reduce','make','set','change','update','use','adjust'
        ]);
        if(!$mentionsType || !$changeVerb)return null;

        $selectedType=$scope==='section' && isset($blocks[$targetIndex])
            ? Str::lower((string)($blocks[$targetIndex]['type']??''))
            : '';
        $role=match(true){
            Str::contains($q,['all headings','every heading','all heading sizes','every heading size'])=>'all_headings',
            preg_match('/\b(?:h1|heading\s*1)\b/i',$prompt)===1=>'h1',
            preg_match('/\b(?:h2|heading\s*2)\b/i',$prompt)===1=>'h2',
            preg_match('/\b(?:h3|heading\s*3)\b/i',$prompt)===1=>'h3',
            preg_match('/\b(?:h4|heading\s*4)\b/i',$prompt)===1=>'h4',
            preg_match('/\b(?:h5|heading\s*5)\b/i',$prompt)===1=>'h5',
            preg_match('/\b(?:h6|heading\s*6)\b/i',$prompt)===1=>'h6',
            Str::contains($q,['stat title','stat titles'])=>'stat-title',
            Str::contains($q,['card title','card titles','card heading','card headings'])=>'card-title',
            Str::contains($q,['card body','card text'])=>'card-body',
            Str::contains($q,['body text','paragraph text','paragraphs'])=>'body',
            Str::contains($q,['badge','badges'])=>'badge',
            Str::contains($q,['meta'])=>'meta',
            Str::contains($q,['eyebrow','labels'])=>'eyebrow',
            Str::contains($q,['button text','buttons'])=>'button',
            Str::contains($q,['heading','headline','title']) && Str::startsWith($selectedType,'hero')=>'h1',
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
        if($scope!=='section' && !Str::contains($q,['this section','only this section','just this section']))$global=true;

        $defaults=[
            'h1'=>['size'=>'5.75rem','line'=>'.96','weight'=>'700','tracking'=>'-.05em'],
            'h2'=>['size'=>'4rem','line'=>'1','weight'=>'700','tracking'=>'-.045em'],
            'h3'=>['size'=>'1.75rem','line'=>'1.08','weight'=>'700','tracking'=>'-.025em'],
            'h4'=>['size'=>'1.35rem','line'=>'1.15','weight'=>'700','tracking'=>'-.015em'],
            'h5'=>['size'=>'1.125rem','line'=>'1.25','weight'=>'700','tracking'=>'-.01em'],
            'h6'=>['size'=>'1rem','line'=>'1.3','weight'=>'700','tracking'=>'0em'],
            'card-title'=>['size'=>'1.65rem','line'=>'1.12','weight'=>'700','tracking'=>'-.025em'],
            'stat-title'=>['size'=>'1.35rem','line'=>'1.18','weight'=>'700','tracking'=>'-.015em'],
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
            $explicitAllSize=null;
            if($property==='size' && preg_match('/(\d+(?:\.\d+)?)\s*(rem|px)/i',$prompt,$explicitMatch)){
                $explicitAllSize=$explicitMatch[1].Str::lower($explicitMatch[2]);
            }
            foreach($roles as $headingRole){
                $headingKey=$headingRole.'_'.$property;
                $currentHeading=(string)($nextTypography[$headingKey]??$defaults[$headingRole][$property]);
                if($property==='size'){
                    if($explicitAllSize!==null){
                        $nextTypography[$headingKey]=$explicitAllSize;
                        continue;
                    }
                    $basePx=['h1'=>92,'h2'=>64,'h3'=>28,'h4'=>21.6,'h5'=>18,'h6'=>16][$headingRole];
                    if(preg_match('/^(-?\d+(?:\.\d+)?)px$/',$currentHeading,$m))$basePx=(float)$m[1];
                    elseif(preg_match('/^(-?\d+(?:\.\d+)?)rem$/',$currentHeading,$m))$basePx=(float)$m[1]*16;
                    $factor=Str::contains($q,['increase','bigger','larger','more']) ? 1.12 : (Str::contains($q,['decrease','smaller','reduce','less']) ? .90 : 1.0);
                    $nextTypography[$headingKey]=rtrim(rtrim(number_format(max(10,min(144,$basePx*$factor))/16,3,'.',''),'0'),'.').'rem';
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
                'applied_operations'=>[['action'=>'typography_global','role'=>'all_headings','property'=>$property]],
            ];
        }

        $key=$role.'_'.$property;
        $current=(string)($typography[$key]??$defaults[$role][$property]);

        $explicit=null;
        if($property==='size' && preg_match('/(\d+(?:\.\d+)?)\s*(rem|px)/i',$prompt,$m)){
            $explicit=$m[1].Str::lower($m[2]);
        } elseif($property==='line' && preg_match('/(?:line[- ]height(?:\s*(?:to|at|=))?\s*)(\d+(?:\.\d+)?)/i',$prompt,$m)){
            $explicit=(string)max(.8,min(2.4,(float)$m[1]));
        } elseif($property==='weight' && preg_match('/\b([1-9]00)\b/',$prompt,$m)){
            $explicit=$m[1];
        } elseif($property==='tracking' && preg_match('/(-?\d+(?:\.\d+)?)\s*(em|px)/i',$prompt,$m)){
            $explicit=$m[1].Str::lower($m[2]);
        }

        $increase=Str::contains($q,['increase','bigger','larger','more','heavier']);
        $decrease=Str::contains($q,['decrease','smaller','reduce','less','lighter']);

        $value=$explicit;
        if($value===null){
            if($property==='size'){
                $basePx=[
                    'h1'=>92,'h2'=>64,'h3'=>28,'h4'=>21.6,'h5'=>18,'h6'=>16,'card-title'=>26.4,'stat-title'=>21.6,'card-body'=>15.6,'body'=>16,'eyebrow'=>12,'badge'=>12,'meta'=>12,'button'=>14.4,
                ][$role];
                if(preg_match('/^(-?\d+(?:\.\d+)?)px$/',$current,$m))$basePx=(float)$m[1];
                elseif(preg_match('/^(-?\d+(?:\.\d+)?)rem$/',$current,$m))$basePx=(float)$m[1]*16;
                $factor=$increase ? 1.12 : ($decrease ? .90 : 1.0);
                $value=rtrim(rtrim(number_format(max(10,min(144,$basePx*$factor))/16,3,'.',''),'0'),'.').'rem';
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
                'applied_operations'=>[[
                    'action'=>'typography_global',
                    'role'=>$role,
                    'property'=>$property,
                    'value'=>$value,
                ]],
            ];
        }

        if($scope!=='section' || !isset($blocks[$targetIndex]))return null;
        $nextBlocks=array_values($blocks);
        $nextBlock=is_array($nextBlocks[$targetIndex])?$nextBlocks[$targetIndex]:[];
        $local=is_array($nextBlock['luna_typography_overrides']??null)?$nextBlock['luna_typography_overrides']:[];
        $local[$key]=$value;
        $nextBlock['luna_typography_overrides']=$local;
        $nextBlocks[$targetIndex]=$nextBlock;

        return [
            'reply'=>"Updated only this section's {$role} {$property}.",
            'blocks'=>$nextBlocks,
            'typography_settings'=>$typography,
            'applied_operations'=>[[
                'action'=>'typography_local',
                'index'=>$targetIndex,
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
