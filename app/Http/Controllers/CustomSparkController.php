<?php

namespace App\Http\Controllers;

use App\Helpers\CmsHtmlCompiler;
use App\Support\PageStyleRegistry;
use App\Models\CustomSpark;
use App\Models\Website;
use App\Jobs\BuildVisualFirstCustomPageJob;
use App\Services\CreditService;
use App\Services\SparkCatalog;
use App\Services\PlanEntitlementService;
use App\Services\AiPageGenerationService;
use App\Services\LunaTemplatePlannerService;
use App\Services\LunaCategoryPageService;
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

        $response=Http::withToken($apiKey)->timeout(150)->post(
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


    public function pageChat(
        Request $request,
        Website $website,
        LunaCategoryPageService $lunaPages,
        PlanEntitlementService $entitlements
    ) {
        $this->authorize('update', $website);

        $validated=$request->validate([
            'prompt'=>['required','string','max:6000'],
            'blocks'=>['required','string','max:350000'],
            'header'=>['nullable','string','max:80000'],
            'footer'=>['nullable','string','max:120000'],
            'theme'=>['nullable','string','max:12000'],
            'target_scope'=>['nullable','in:page,section'],
            'target_index'=>['nullable','integer','min:0','max:100'],
        ]);

        $blocks=json_decode($validated['blocks'],true);
        $header=json_decode((string)($validated['header']??'{}'),true);
        $footer=json_decode((string)($validated['footer']??'{}'),true);
        $theme=json_decode((string)($validated['theme']??'{}'),true);
        if(!is_array($blocks)) {
            throw ValidationException::withMessages(['blocks'=>'The current page could not be prepared for Luna.']);
        }

        $scope=(string)($validated['target_scope']??'page');
        $targetIndex=$scope==='section' ? (int)($validated['target_index']??-1) : -1;
        if($scope==='section' && !isset($blocks[$targetIndex])) {
            throw ValidationException::withMessages(['target_index'=>'That section is no longer available.']);
        }

        // Luna sees the whole hidden Spark vocabulary, but may only auto-use free
        // Sparks or Sparks already installed by this account. Paid locked Sparks
        // remain invisible to orchestration so chat never bypasses entitlements.
        $user=$request->user();
        $currentTypes=collect($blocks)->pluck('type')->filter()->all();
        $usable=collect(SparkCatalog::all())
            ->filter(function(array $spark) use ($user,$currentTypes,$entitlements){
                if(in_array($spark['key'],$currentTypes,true)) return true;
                if(!$user) return (int)($spark['credits']??0)===0;
                return (bool)($entitlements->sparkAccess($user,(string)($spark['access_level']??'free'))['allowed']??false);
            })
            ->map(fn(array $spark)=>[
                'key'=>$spark['key'],
                'name'=>$spark['name'],
                'category'=>$spark['category'],
                'description'=>$spark['description'],
            ])
            ->values()
            ->all();

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
 "footer_changes":{}
}

RULES:
- Use ONLY spark_key values present in USABLE SPARK CATALOG.
- Never reveal implementation details such as Spark IDs, templates, schemas or hidden selection.
- For a simple copy/color/image/repeater adjustment, prefer edit. In section scope, use keys exactly from SELECTED BLOCK FULL JSON; do not invent schema keys.
- For a structural request ("make this a video", "use a slider", "make this testimonials"), replace with the closest registered Spark.
- For "add X below/above", use insert_after/insert_before relative to the target.
- Preserve useful current content when replacing; instruction should explicitly say what to migrate.
- Do not replace a section when its current Spark can safely satisfy the request.
- Section scope: operate on the selected index only, except an explicit add-before/add-after request.
- Page scope: you may coordinate multiple operations and theme changes, max 8 operations.
- Header/footer changes only if explicitly requested or essential to a page-wide theme request.
- Do not invent raw HTML/CSS/JS/PHP/SQL.
- Never change ecommerce/dynamic data bindings unless explicitly requested.
- Never delete content unless the user asks.
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
                    ['role'=>'user','content'=>"SCOPE: {$scope}".($scope==='section'?"\nSELECTED INDEX: {$targetIndex}":"")."\n\nUSER REQUEST:\n".$validated['prompt']."\n\nCURRENT PAGE SUMMARY:\n".json_encode($blockSummary,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nSELECTED BLOCK FULL JSON:\n".$targetContext."\n\nCURRENT THEME:\n".json_encode(is_array($theme)?$theme:[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nUSABLE SPARK CATALOG:\n{$catalogJson}"],
                ],
            ]
        )->throw()->json();

        $plan=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
        if(!is_array($plan)) {
            throw ValidationException::withMessages(['prompt'=>'Luna returned an invalid design plan.']);
        }

        $operations=array_values(array_slice(is_array($plan['operations']??null)?$plan['operations']:[],0,8));
        $catalogKeys=collect($usable)->pluck('key')->flip();
        $nextBlocks=array_values($blocks);
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
                $newBlock['type']=$sparkKey;
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

        return response()->json([
            'reply'=>trim((string)($plan['reply']??'Done.'))?:'Done.',
            'blocks'=>$nextBlocks,
            'header'=>$safeHeader,
            'footer'=>$safeFooter,
            'theme_key'=>$themeKey,
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

}
