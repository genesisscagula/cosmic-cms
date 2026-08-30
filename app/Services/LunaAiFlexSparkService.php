<?php

namespace App\Services;

use App\Support\AiFlexComponentRegistry;
use App\Support\AiFlexComposerGuide;
use App\Support\AiFlexLayoutRecipeRegistry;
use App\Support\AiFlexStructureContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LunaAiFlexSparkService
{
    public function __construct(
        private readonly LunaModelDepartmentService $models,
        private readonly SmartImageService $images,
        private readonly IndustryResolver $industryResolver,
    ) {}

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
Allowed block keys: type,custom_spark_key,custom_spark_saved,semantic_type,source_type,category,layout,alignment,media_position,density,accent_shape,section_mood,eyebrow,heading,heading_accent_text,text,primary_label,primary_url,secondary_label,secondary_url,image_url,image_query,items,elements,theme,visual_style,review,form,ai_flex.
Required: type="luna_custom_section", custom_spark_saved=false, theme="auto". semantic_type is the logical section role (hero, services, testimonials, faq, contact, pricing, cta, content, etc.). When replacing a source section, preserve/inherit its logical role.
AI FLEX UNIVERSAL ELEMENTS (v5): Prefer an `elements` tree whenever the requested/reference composition cannot be faithfully represented by the legacy heading/text/items fields. CANONICAL STRUCTURE: `elements[]` is the Rows repeater. Every root element MUST be type=row. Every row.children[] is the Columns repeater and MUST contain only type=column nodes. Every column.children[] is the Extras repeater and may contain safe content/container elements. Do not place primitives directly at root or directly under a row. Allowed element types: group,row,column,grid,stack,card,background_image,background_video,overlay,slider,slide,button_group,media_group,heading,text,button,image,video,icon,badge,list,divider,stat,spacer,form. Nest containers safely (max depth 8). A form element is structured only: type=form, optional title/note/button_label/success_message/columns/button_alignment, and fields[]. Allowed field controls: text,email,tel,number,date,time,textarea,select,checkbox,radio,hidden. Each field may use name,label,placeholder,type,required,width,options,min,max,step,value,autocomplete. Never emit form HTML, scripts, endpoints, event handlers, raw CSS or Tailwind. Each non-form element may use only structured fields: type,key,text,label,url,src,alt,image_query,icon,value,items,children and a structured style object. Video/background_video elements may additionally use poster,controls,autoplay,muted,loop,plays_inline. slider is a composable interactive container and MUST contain only slide children; slide may contain arbitrary safe AI Flex children. button_group is an action container and MUST contain only button children; use it for paired or grouped CTAs instead of placing unrelated buttons loosely. media_group is a media composition container and MUST contain only image/video children; use style.columns/gap and responsive column settings to create galleries, paired media, or editorial media clusters. slider may use autoplay,interval,loop,show_arrows,show_dots,show_counter,transition. Prefer 3-5 slides and transition=fade unless the request calls for another behavior. background_image and background_video are composable containers: their children render above the media. overlay is a composable container whose style.background and style.opacity create a non-inheriting visual overlay behind its children. For full-bleed media compositions, place background_image/background_video inside a full-width column and nest overlay/content within it. Never emit className, HTML, CSS, JSX, JavaScript or Tailwind. Allowed style keys: gap,columns,width,max_width,min_height,padding,padding_x,padding_y,radius,background,color,border_color,border_width,shadow,align,justify,text_align,font_size,font_weight,line_height,aspect_ratio,object_fit,object_position,opacity,position,top,right,bottom,left,z_index,overflow,order,grow,basis,self_align,tablet_width,mobile_width,tablet_columns,mobile_columns,tablet_gap,mobile_gap,tablet_padding,mobile_padding,tablet_order,mobile_order,tablet_position,mobile_position,tablet_min_height,mobile_min_height. Use responsive geometry intentionally: desktop may use asymmetric columns, controlled absolute/floating cards, overlap and off-axis media; tablet/mobile must collapse safely without horizontal overflow. Use semantic colors primary,surface,surface_alt,white,on_primary,on_surface,on_dark,accent or explicit HEX.
COSMIC SPACING BASELINE: unless the user explicitly requests tighter or wider spacing, use a 1280px content max-width, section padding 80px vertical / 48px horizontal on desktop, 60px vertical on tablet, and 44px vertical / 22px horizontal on mobile. Use 32px between primary columns, 24px on tablet, and 20px on mobile. Use 24px between repeated cards and 16-20px between related copy elements. Do not place headings, copy, buttons, or media flush against section edges.
Allowed layout: editorial,split,feature-grid,card-grid,media-led,stacked,centered.
Allowed alignment: left,center,right. Allowed media_position: left,right,background,top,none.
items is an array of content cards with only title,text,icon,label,url,image_url.
ai_flex is metadata only: {"version":1,"source":"sol","reference_mode":"composition_only","theme_policy":"inherit","intent":"short description","spark_name":"2-5 word reusable section name"}.
Always set ai_flex.spark_name to a concise, distinctive reusable library name based on the generated composition (for example "Midnight Services Grid" or "Editorial Testimonial Split"). Do not ask the user to name it.
visual_style may use only renderer-supported scalar design values; prefer theme inheritance and restrained values. Do not invent remote image URLs. Preserve useful source content when replacing a section.
PROMPT;
        $system .= "\nAI FLEX COMPONENT REGISTRY (authoritative): ".AiFlexComponentRegistry::promptInventory().". Use only renderer-ready registered component types. Reserved primitives are not valid output until activated.";
        $system .= "\nAI FLEX LAYOUT LEGO REGISTRY (geometry only; never Spark lookup): ".AiFlexLayoutRecipeRegistry::promptInventory().".";
        $composerPlan = AiFlexComposerGuide::forRequest($request);
        $system .= "\n".AiFlexComposerGuide::prompt($composerPlan);
        $payload = [
            'request' => $request,
            'active_theme' => $theme,
            'page_context' => $pageContext,
            'source_section' => $source,
            'composer_plan' => $composerPlan,
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
        $fastComposer = (bool) ($pageContext['fast_composer'] ?? false);
        $attempts = [
            ['model'=>$this->models->sol(), 'json_mode'=>true,  'department'=>'sol'],
        ];
        // Start Blank's fast lane must stay fast: if the strict Sol attempt fails,
        // generateStaged() immediately falls back to the safer two-pass pipeline.
        // Other AI Flex entry points retain compatibility retries.
        if (! $fastComposer) {
            $attempts[] = ['model'=>$this->models->sol(), 'json_mode'=>false, 'department'=>'sol_retry'];
            if ($this->models->terra() !== $this->models->sol()) {
                $attempts[] = ['model'=>$this->models->terra(), 'json_mode'=>true, 'department'=>'terra_fallback'];
            }
        }

        $lastError = null;
        foreach ($attempts as $attempt) {
            try {
                $body = ['model'=>$attempt['model'], 'messages'=>$messages];
                if ($attempt['json_mode']) $body['response_format'] = ['type'=>'json_object'];

                $connectTimeout = $fastComposer ? 15 : 30;
                $requestTimeout = $fastComposer
                    ? max(30, min(120, (int) config('openai.ai_flex_fast_timeout', 75)))
                    : 150;
                $response = Http::withToken($apiKey)->connectTimeout($connectTimeout)->timeout($requestTimeout)
                    ->post($endpoint, $body)->throw()->json();
                $content = (string)data_get($response,'choices.0.message.content','');
                $decoded = json_decode($content, true);
                $block = is_array($decoded['block'] ?? null) ? $decoded['block'] : [];
                if ($block === []) throw new \RuntimeException('AI Flex model returned no valid block object.');

                $validated = $this->validate($block, $source);
                $validated = $this->applyCompositionPolish($validated, $request, $source);
                $validated = $this->applyLayoutRecipeGeometry($validated, $composerPlan);
                $validated = $this->applyComposerIntentContracts($validated, $composerPlan);
                $validated = $this->stampComposerMetadata($validated, $composerPlan);
                $this->assertComposerPlanSatisfied($validated, $composerPlan);
                $this->assertComposerQuantitiesSatisfied($validated, $composerPlan);
                $this->assertLayoutRecipeSatisfied($validated, $composerPlan);
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

    /**
     * Start Blank is fast-path first. The Composer Guide + component registry now
     * own enough structure constraints that a successful request should require
     * only one model generation. The older structure -> content pipeline remains
     * as a safety fallback when the fast result fails validation/composer checks.
     */
    public function generateStaged(string $request, array $theme = [], array $pageContext = []): array
    {
        try {
            $completed = $this->generate(
                "FAST COMPOSER PASS. Build the complete responsive section in one pass using only trusted AI Flex components. Honor explicit layout geometry, slider/media controls, CTA grouping, responsive behavior, and useful prompt-specific content. Do not split structure and content into separate drafts.\n\nUSER REQUEST:\n{$request}",
                $theme,
                array_merge($pageContext, [
                    'generation_stage' => 'fast_composer',
                    'fast_composer' => true,
                ]),
                [],
            );

            $completed = $this->enforceExplicitSplitRequest($completed, $request);
            $semantic = Str::lower(trim((string) ($completed['semantic_type'] ?? $completed['category'] ?? 'content'))) ?: 'content';
            $completed = $this->hydrateReferenceMedia($completed, $request, $semantic, 0);
            $completed['ai_flex'] = array_merge(is_array($completed['ai_flex'] ?? null) ? $completed['ai_flex'] : [], [
                'pipeline' => 'fast_composer_v1',
                'structure_locked' => false,
                'stages' => ['intent', 'compose'],
                'fallback_used' => false,
            ]);

            return $this->validate($completed);
        } catch (\Throwable $fastError) {
            Log::warning('[AiFlex] fast composer fell back to staged generation', [
                'error' => $fastError->getMessage(),
            ]);
        }

        $structure = $this->generate(
            "STRUCTURE FALLBACK PASS ONLY. Build the exact responsive section composition requested below. Prioritize explicit row/column count, widths, order, media side, alignment, and requested controls. Use short neutral placeholders for content.\n\nUSER REQUEST:\n{$request}",
            $theme,
            array_merge($pageContext, ['generation_stage' => 'structure_fallback', 'fast_composer_fallback' => true]),
            [],
        );

        $contentCandidate = $this->generate(
            "CONTENT FALLBACK PASS. Fill the supplied validated section structure with useful, prompt-specific copy and media intent. Preserve its element types, nesting, column geometry, responsive order, layout, and media position exactly. Do not flatten, remove, reorder, or add structural nodes.\n\nUSER REQUEST:\n{$request}",
            $theme,
            array_merge($pageContext, ['generation_stage' => 'content_fallback', 'structure_locked' => true, 'fast_composer_fallback' => true]),
            $structure,
        );

        $completed = $this->mergeStagedContent($structure, $contentCandidate);
        $completed = $this->enforceExplicitSplitRequest($completed, $request);
        $semantic = Str::lower(trim((string) ($completed['semantic_type'] ?? $completed['category'] ?? 'content'))) ?: 'content';
        $completed = $this->hydrateReferenceMedia($completed, $request, $semantic, 0);
        $completed['ai_flex'] = array_merge(is_array($completed['ai_flex'] ?? null) ? $completed['ai_flex'] : [], [
            'pipeline' => 'intent_structure_content_fallback',
            'structure_locked' => true,
            'stages' => ['intent', 'structure', 'content'],
            'fallback_used' => true,
        ]);

        return $this->validate($completed, $structure);
    }

    /**
     * Explicit geometry in the user's prompt is a hard requirement, not a hint.
     * Models occasionally flatten a requested image/copy split during the content
     * pass; normalize that narrow case into the canonical Rows → Columns tree.
     */
    private function enforceExplicitSplitRequest(array $block, string $request): array
    {
        $twoColumns = (bool) preg_match('/\b(?:two|2)\s+(?:responsive\s+)?columns?\b/i', $request);
        $hasImage = (bool) preg_match('/\b(?:image|photo|picture|visual)\b/i', $request);
        if (! $twoColumns || ! $hasImage) return $block;

        $imageRight = (bool) preg_match('/\b(?:image|photo|picture|visual)\s+(?:on\s+the\s+)?right\b/i', $request);
        $imageLeft = (bool) preg_match('/\b(?:image|photo|picture|visual)\s+(?:on\s+the\s+)?left\b/i', $request) || ! $imageRight;
        $heading = trim((string) ($block['heading'] ?? 'A thoughtful approach, built around you'));
        $text = trim((string) ($block['text'] ?? 'Clear strategy, careful execution, and a collaborative path to the right result.'));
        $label = trim((string) ($block['primary_label'] ?? 'Learn more'));
        $url = trim((string) ($block['primary_url'] ?? '#')) ?: '#';

        $imageColumn = [
            'type' => 'column',
            'style' => ['width' => 50, 'tablet_width' => 50, 'mobile_width' => 100, 'mobile_order' => $imageLeft ? 1 : 2],
            'children' => [[
                'type' => 'image',
                'src' => trim((string) ($block['image_url'] ?? '')),
                'alt' => $heading,
                'style' => ['width' => 100, 'min_height' => 440, 'mobile_min_height' => 280, 'aspect_ratio' => 1.25, 'object_fit' => 'cover', 'radius' => 24],
            ]],
        ];
        $contentColumn = [
            'type' => 'column',
            'style' => ['width' => 50, 'tablet_width' => 50, 'mobile_width' => 100, 'mobile_order' => $imageLeft ? 2 : 1],
            'children' => array_values(array_filter([
                trim((string) ($block['eyebrow'] ?? '')) !== '' ? ['type' => 'badge', 'text' => trim((string) $block['eyebrow'])] : null,
                ['type' => 'heading', 'text' => $heading, 'style' => ['font_size' => 56, 'font_weight' => 700, 'line_height' => 1]],
                ['type' => 'text', 'text' => $text, 'style' => ['font_size' => 18, 'line_height' => 1.6]],
                ['type' => 'button', 'label' => $label, 'url' => $url],
            ])),
        ];

        $block['layout'] = 'split';
        $block['alignment'] = 'left';
        $block['media_position'] = $imageLeft ? 'left' : 'right';
        $block['image_url'] = trim((string) ($block['image_url'] ?? ''));
        $block['elements'] = [[
            'type' => 'row',
            'style' => ['gap' => 48, 'tablet_gap' => 32, 'mobile_gap' => 24, 'align' => 'center'],
            'children' => $imageLeft ? [$imageColumn, $contentColumn] : [$contentColumn, $imageColumn],
        ]];
        $block['ai_flex'] = array_merge(is_array($block['ai_flex'] ?? null) ? $block['ai_flex'] : [], [
            'intent' => 'verified two-column image and content split',
        ]);

        return $block;
    }

    private function mergeStagedContent(array $structure, array $content): array
    {
        $contentKeys = [
            'eyebrow','heading','heading_accent_text','text','primary_label','primary_url',
            'secondary_label','secondary_url','image_url','image_query','items','review','form',
        ];
        foreach ($contentKeys as $key) {
            if (array_key_exists($key, $content)) $structure[$key] = $content[$key];
        }

        if (is_array($structure['elements'] ?? null) && is_array($content['elements'] ?? null)) {
            $structure['elements'] = $this->mergeElementContent($structure['elements'], $content['elements']);
        }

        return $structure;
    }

    private function mergeElementContent(array $structureNodes, array $contentNodes): array
    {
        $editable = ['text','label','url','src','alt','image_query','poster','icon','value','items','title','note','button_label','success_message','fields'];
        foreach ($structureNodes as $index => $node) {
            if (! is_array($node)) continue;
            $candidate = is_array($contentNodes[$index] ?? null) ? $contentNodes[$index] : [];
            foreach ($editable as $key) {
                if (array_key_exists($key, $candidate)) $node[$key] = $candidate[$key];
            }
            if (is_array($node['children'] ?? null)) {
                $node['children'] = $this->mergeElementContent(
                    $node['children'],
                    is_array($candidate['children'] ?? null) ? $candidate['children'] : [],
                );
            }
            $structureNodes[$index] = $node;
        }
        return $structureNodes;
    }

    /** Generate prompt-aware content for the global Website Shell Mega Footer. */
    public function generateMegaFooter(string $request, array $siteContext = [], array $menu = [], array $theme = []): array
    {
        $apiKey = (string) config('openai.api_key');
        if ($apiKey === '') throw new \RuntimeException('OpenAI API key is not configured.');

        $system = <<<'PROMPT'
You are Sol, Cosmic CMS's AI Flex footer composer. Return JSON only as {"footer":{...}}.
Create one concise global Mega Footer that matches the supplied business prompt, industry, brand tone, and actual website navigation.
Never output HTML, CSS, JSX, scripts, Tailwind classes, or executable code.
The footer object may use only: tagline,primary_label,primary_url,columns.
columns must contain 1-4 objects shaped as {"title":"...","items":[{"label":"...","url":"..."}]}.
Use only the exact labels and URLs supplied in available_navigation. Do not invent pages, routes, email addresses, phone numbers, street addresses, social profiles, legal claims, awards, guarantees, or business facts.
Group the available pages into useful prompt-aware menus and include every supplied page exactly once. Keep headings, tagline, and CTA short. Prefer the supplied Contact page for the primary CTA when present.
PROMPT;
        $system .= "\nAI FLEX COMPONENT REGISTRY (authoritative): ".AiFlexComponentRegistry::promptInventory().". Use only renderer-ready registered component types. Reserved primitives are not valid output until activated.";
        $payload = [
            'request' => $request,
            'site_context' => $siteContext,
            'available_navigation' => array_values($menu),
            'active_theme' => $theme,
        ];
        $endpoint = rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/chat/completions';
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
        ];
        $attempts = [
            ['model' => $this->models->sol(), 'json_mode' => true, 'department' => 'sol_footer'],
            ['model' => $this->models->sol(), 'json_mode' => false, 'department' => 'sol_footer_retry'],
        ];
        if ($this->models->terra() !== $this->models->sol()) {
            $attempts[] = ['model' => $this->models->terra(), 'json_mode' => true, 'department' => 'terra_footer_fallback'];
        }

        $lastError = null;
        foreach ($attempts as $attempt) {
            try {
                $body = ['model' => $attempt['model'], 'messages' => $messages];
                if ($attempt['json_mode']) $body['response_format'] = ['type' => 'json_object'];
                $response = Http::withToken($apiKey)->connectTimeout(30)->timeout(150)
                    ->post($endpoint, $body)->throw()->json();
                $decoded = json_decode((string) data_get($response, 'choices.0.message.content', ''), true);
                $footer = is_array($decoded['footer'] ?? null) ? $decoded['footer'] : [];
                if ($footer === [] || ! is_array($footer['columns'] ?? null)) {
                    throw new \RuntimeException('AI Flex returned no valid Mega Footer object.');
                }
                $footer['ai_flex'] = [
                    'version' => 1,
                    'source' => 'sol',
                    'intent' => 'prompt-aware global mega footer',
                    'model' => (string) $attempt['model'],
                    'department' => (string) $attempt['department'],
                ];
                Log::debug('[AiFlex] global Mega Footer generated', [
                    'model' => $attempt['model'],
                    'department' => $attempt['department'],
                    'column_count' => count($footer['columns']),
                ]);
                return $footer;
            } catch (\Throwable $exception) {
                $lastError = $exception;
                Log::warning('[AiFlex] global Mega Footer generation attempt failed', [
                    'model' => $attempt['model'],
                    'department' => $attempt['department'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException('AI Flex Mega Footer generation failed after all model attempts.', 0, $lastError);
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
Allowed block keys: type,custom_spark_key,custom_spark_saved,semantic_type,source_type,category,layout,alignment,media_position,density,accent_shape,section_mood,eyebrow,heading,heading_accent_text,text,primary_label,primary_url,secondary_label,secondary_url,image_url,image_query,items,elements,theme,visual_style,review,form,ai_flex.
Allowed layout: editorial,split,feature-grid,card-grid,media-led,stacked,centered. media_position: left,right,background,top,none. alignment: left,center,right.
For custom screenshot UI, use elements with the Universal Elements v5 responsive layout contract. Root elements are rows; row.children are columns; column.children are extras. Preserve asymmetric geometry, deliberate overlap, floating cards and non-equal columns when visible. Use tablet/mobile style keys so those layouts collapse safely. Responsive geometry style keys include position,top,right,bottom,left,z_index,overflow,order,grow,basis,self_align,tablet_width,mobile_width,tablet_columns,mobile_columns,tablet_gap,mobile_gap,tablet_padding,mobile_padding,tablet_order,mobile_order,tablet_position,mobile_position,tablet_min_height,mobile_min_height. Absolute/floating desktop elements should normally become relative on tablet/mobile. Allowed element types: group,row,column,grid,stack,card,background_image,background_video,overlay,slider,slide,button_group,media_group,heading,text,button,image,video,icon,badge,list,divider,stat,spacer,form. A form element may contain title,note,button_label,success_message,columns,button_alignment,fields. Fields may be text,email,tel,number,date,time,textarea,select,checkbox,radio,hidden with name,label,placeholder,required,width,options,min,max,step,value,autocomplete. Never emit form HTML, endpoints, scripts, event handlers or raw CSS.
Never invent a remote image URL. image_url must remain empty unless a source section already has a usable image URL.
ai_flex metadata must identify source=sol, reference_mode, theme_policy and a short intent.
PROMPT;
        $composerPlan = AiFlexComposerGuide::forRequest($request);
        $system .= "\nAI FLEX COMPONENT REGISTRY (authoritative): ".AiFlexComponentRegistry::promptInventory().". Use only renderer-ready registered component types.";
        $system .= "\nAI FLEX LAYOUT LEGO REGISTRY (geometry only; never Spark lookup): ".AiFlexLayoutRecipeRegistry::promptInventory().".";
        $system .= "\n".AiFlexComposerGuide::prompt($composerPlan);
        $payload = [
            'request'=>$request,
            'reference_mode'=>$referenceMode,
            'theme_policy'=>$themePolicy,
            'reference_aspect_ratio'=>$ratio,
            'active_theme'=>$theme,
            'page_context'=>$pageContext,
            'source_section'=>$source,
            'composer_plan'=>$composerPlan,
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
                $block=$this->applyLayoutRecipeGeometry($block,$composerPlan);
                $block=$this->applyComposerIntentContracts($block,$composerPlan);
                $block=$this->stampComposerMetadata($block,$composerPlan);
                $this->assertComposerPlanSatisfied($block, $composerPlan);
                $this->assertComposerQuantitiesSatisfied($block, $composerPlan);
                $this->assertLayoutRecipeSatisfied($block, $composerPlan);
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
        // Reference-page mode is AI Flex only. Registered/template catalogs are
        // intentionally ignored so Sol can reproduce the screenshot composition
        // directly instead of translating it into the nearest premade Spark.
        $catalogRows=[];
        $system=<<<'PROMPT'
You are Sol, Cosmic CMS's premium full-page art director. Analyze the supplied FULL PAGE website screenshot and return JSON only:
{"page_plan":{"sections":[...]}}
Divide the reference top-to-bottom into its meaningful section roles and content intent. Every section MUST have semantic_type, instruction, implementation="ai_flex", and a complete block.

REFERENCE DNA POLICY:
- The screenshot is inspiration and brand DNA, NOT a pixel-perfect geometry contract, even if the user's wording says exactly/same/copy.
- Extract its restrained semantic palette, dark/light rhythm, overlay/gradient language, typography character, imagery direction, card treatment, density, and content hierarchy.
- Create Cosmic's OWN premium responsive interpretation. Preserve recognizable section roles and business meaning, but improve composition where needed.
- Do not copy literal screenshot coordinates, screenshot aspect ratios, tiny font measurements, extreme empty space, or accidental visual defects.
- Never promise exact parity. The result is a premium brand-faithful interpretation designed for the Cosmic renderer.

STANDARD COSMIC SPACING CONTRACT:
- Desktop content max width 1280-1440px; section horizontal padding 48-72px; vertical padding normally 80-120px.
- Compact bands may use 48-72px vertical padding; hero may use 96-144px. Avoid empty vertical gaps over 160px unless content clearly requires it.
- Cards normally use 20-32px padding, 16-28px radius, consistent 20-32px gaps, and contrast-safe surfaces.
- Mobile padding 20-28px; collapse grids/rows intentionally; no horizontal overflow; no absolute positioning that remains absolute on mobile.
- Use premium editorial variety across the page: media-led hero, image/content split, refined product/service grid, contrast process/proof band, project/gallery composition, and decisive CTA when those roles fit the reference.
- Avoid generic SaaS dashboards, repetitive equal card grids, blank decorative cards, and large unused whitespace.

REFERENCE MODE IS AI FLEX ONLY. Never choose, request, imitate by key, or fall back to a registered Spark/template.
For ai_flex include a complete structured luna_custom_section block using: type,semantic_type,category,layout,alignment,media_position,density,accent_shape,section_mood,eyebrow,heading,heading_accent_text,text,primary_label,primary_url,secondary_label,secondary_url,image_url,image_query,items,elements,theme,visual_style,ai_flex. For custom compositions, prefer elements using the AI Flex Universal Elements v5 Rows -> Columns -> Extras responsive layout contract. Forms may be placed anywhere in the elements tree using type=form and safe fields[]. Never emit HTML/CSS/JS/Tailwind.
For every visually important photo, keep image_url/src empty and provide a specific image_query describing subject, framing, industry, lighting, and composition. Item images and image elements may also use image_query. Do not create image placeholders when an icon or text treatment is more appropriate.
If reference_mode=layout_only preserve the active Cosmic theme. If layout_and_theme, translate the screenshot brand DNA into supported visual_style values and the supplied semantic theme.
Keep the meaningful section order. Do not create duplicate filler sections. Aim for 5-9 purposeful sections. ai_flex metadata: source=sol, reference_mode, theme_policy, intent, composition_profile="premium_reference_dna_v1".
PROMPT;
        $payload=[
            'request'=>$request,
            'reference_mode'=>$referenceMode,
            'active_theme'=>$theme,
            'registered_catalog'=>$catalogRows,
            'design_strategy'=>'premium_reference_dna_interpretation',
            'page_style'=>'premium',
        ];
        $messages=[['role'=>'system','content'=>$system],['role'=>'user','content'=>[
            ['type'=>'text','text'=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
            ['type'=>'image_url','image_url'=>['url'=>$dataUri,'detail'=>'high']],
        ]]];
        $endpoint=rtrim((string)(config('openai.base_uri') ?: 'https://api.openai.com/v1'),'/').'/chat/completions';
        $attempts=[['model'=>$this->models->sol(),'json_mode'=>true],['model'=>$this->models->sol(),'json_mode'=>false]];
        $validKeys=[]; // intentionally empty: registered Sparks are forbidden in reference-page mode
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
                    // Batch 4 AI-Flex-only guard: even if a model emits "registered",
                    // never allow a premade Spark into screenshot/reference execution.
                    $implementation='ai_flex';
                    $sparkKey='';
                    $rawBlock=is_array($raw['block']??null)?$raw['block']:[];
                    $rawBlock['semantic_type']=$semantic; $rawBlock['category']=$semantic;
                    $rawBlock=$this->hydrateReferenceMedia($rawBlock,$request,$semantic,$i);
                    $block=$this->validate($rawBlock,[]);
                    $block=$this->applyCompositionPolish($block,$instruction,[]);
                    $block['ai_flex']['reference_mode']=$referenceMode;
                    $block['ai_flex']['theme_policy']=$referenceMode==='layout_and_theme'?'derive_from_reference':'inherit';
                    $block['ai_flex']['model']=(string)$attempt['model'];
                    $block['ai_flex']['department']='sol_reference_page';
                    $block['ai_flex']['composition_profile']='premium_reference_dna_v1';
                    $sections[]=['semantic_type'=>$semantic,'implementation'=>'ai_flex','instruction'=>$instruction,'block'=>$block];
                }
                if(!$sections) throw new \RuntimeException('Sol returned no usable whole-page sections.');
                Log::debug('[AiFlex] whole-page reference planned',['model'=>$attempt['model'],'reference_mode'=>$referenceMode,'sections'=>count($sections),'registered'=>0,'ai_flex_only'=>true]);
                return ['composer'=>'sol_reference_page_ai_flex_dna_v2','reference_mode'=>$referenceMode,'ai_flex_only'=>true,'page_style'=>'premium','sections'=>$sections];
            }catch(\Throwable $e){$lastError=$e; Log::warning('[AiFlex] whole-page reference planning failed',['model'=>$attempt['model'],'error'=>$e->getMessage()]);}
        }
        throw new \RuntimeException('Whole-page reference planning failed after Sol retries.',0,$lastError);
    }

    /**
     * Graceful reference fallback: when the high-detail screenshot vision call fails,
     * build a fresh premium AI Flex page from the user's intent and any brand DNA
     * already extracted from the reference. This deliberately sends NO image bytes,
     * avoiding a second failure caused by a large/scaled reference payload.
     */
    public function generatePremiumReferenceFallback(string $request, array $theme = []): array
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') throw new \RuntimeException('OpenAI API key is not configured.');
        $system=<<<'PROMPT'
You are Sol, Cosmic CMS's premium website art director. The detailed screenshot vision pass was unavailable, but its brand/theme DNA may already be present in active_theme. Return JSON only as {"page_plan":{"sections":[...]}}.
Build a fresh, cohesive premium website page using Cosmic standards. Do NOT attempt pixel-perfect screenshot reconstruction. Treat the user's request and supplied theme as authoritative brand direction.
Create 5-8 purposeful sections. Every section MUST use implementation="ai_flex" and include a complete structured luna_custom_section block. Use varied premium compositions: a strong media-led hero, useful content/image split, refined service/product presentation, proof/process/project section where appropriate, and a decisive CTA. Forms are allowed using the safe form element schema.
Use the AI Flex Universal Elements responsive contract. Never emit HTML/CSS/JS/Tailwind. Never choose or name registered Sparks/templates. Keep desktop content width 1280-1440px, deliberate 80-120px section rhythm, mobile-safe stacking, strong contrast, restrained radius/shadows, and no horizontal overflow. Avoid generic SaaS dashboards unless the business itself is software.
For important photos leave image_url/src empty and provide a specific image_query; Cosmic resolves imagery safely. ai_flex metadata must include source=sol, reference_mode="brand_dna_fallback", theme_policy="derive_from_reference", intent, composition_profile="premium_reference_fallback_v1".
PROMPT;
        $payload=['request'=>$request,'active_theme'=>$theme,'design_strategy'=>'premium_brand_dna_fallback','page_style'=>'premium'];
        $endpoint=rtrim((string)(config('openai.base_uri') ?: 'https://api.openai.com/v1'),'/').'/chat/completions';
        $messages=[['role'=>'system','content'=>$system],['role'=>'user','content'=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]];
        $lastError=null;
        foreach([['model'=>$this->models->sol(),'json_mode'=>true],['model'=>$this->models->sol(),'json_mode'=>false]] as $attempt){
            try{
                $body=['model'=>$attempt['model'],'messages'=>$messages];
                if($attempt['json_mode']) $body['response_format']=['type'=>'json_object'];
                $response=Http::withToken($apiKey)->connectTimeout(20)->timeout(180)->post($endpoint,$body)->throw()->json();
                $decoded=json_decode((string)data_get($response,'choices.0.message.content',''),true);
                $rawSections=(array)data_get($decoded,'page_plan.sections',[]);
                if(count($rawSections)<1 || count($rawSections)>10) throw new \RuntimeException('Sol returned an invalid fallback section count.');
                $sections=[];
                foreach($rawSections as $i=>$raw){
                    if(!is_array($raw)) continue;
                    $semantic=Str::lower(trim((string)($raw['semantic_type']??'content'))) ?: 'content';
                    $instruction=trim((string)($raw['instruction']??('Build a premium '.$semantic.' section.')));
                    $rawBlock=is_array($raw['block']??null)?$raw['block']:[];
                    $rawBlock['semantic_type']=$semantic; $rawBlock['category']=$semantic;
                    $rawBlock=$this->hydrateReferenceMedia($rawBlock,$request,$semantic,$i);
                    $block=$this->validate($rawBlock,[]);
                    $block=$this->applyCompositionPolish($block,$instruction,[]);
                    $block['ai_flex']['reference_mode']='brand_dna_fallback';
                    $block['ai_flex']['theme_policy']='derive_from_reference';
                    $block['ai_flex']['model']=(string)$attempt['model'];
                    $block['ai_flex']['department']='sol_reference_fallback';
                    $block['ai_flex']['composition_profile']='premium_reference_fallback_v1';
                    $sections[]=['semantic_type'=>$semantic,'implementation'=>'ai_flex','instruction'=>$instruction,'block'=>$block];
                }
                if(!$sections) throw new \RuntimeException('Sol returned no usable fallback sections.');
                Log::warning('[AiFlex] reference vision fallback succeeded',['model'=>$attempt['model'],'sections'=>count($sections)]);
                return ['composer'=>'sol_reference_brand_dna_fallback_v1','reference_mode'=>'brand_dna_fallback','ai_flex_only'=>true,'page_style'=>'premium','fallback'=>true,'sections'=>$sections];
            }catch(\Throwable $e){$lastError=$e; Log::warning('[AiFlex] reference fallback attempt failed',['error'=>$e->getMessage()]);}
        }
        throw new \RuntimeException('Premium reference fallback failed after Sol retries.',0,$lastError);
    }

    /** Resolve model-authored visual directions through Cosmic's safe image provider. */
    private function hydrateReferenceMedia(array $block, string $request, string $semantic, int $sectionIndex): array
    {
        $industry = $this->industryResolver->resolve($request, 'default');
        $role = $semantic === 'hero'
            ? 'hero'
            : (in_array($semantic, ['portfolio','projects','gallery'], true) ? 'gallery' : 'general');

        $rootQuery = trim((string) ($block['image_query'] ?? ''));
        if ($rootQuery !== '' && trim((string) ($block['image_url'] ?? '')) === '') {
            $block['image_url'] = $this->images->find($rootQuery, $industry, $role);
        }
        unset($block['image_query']);

        if (is_array($block['items'] ?? null)) {
            foreach ($block['items'] as $itemIndex => &$item) {
                if (! is_array($item)) continue;
                $query = trim((string) ($item['image_query'] ?? ''));
                if ($query !== '' && trim((string) ($item['image_url'] ?? '')) === '') {
                    $item['image_url'] = $this->images->find(
                        $query.' distinct view '.($itemIndex + 1),
                        $industry,
                        'gallery'
                    );
                }
                unset($item['image_query']);
            }
            unset($item);
        }

        if (is_array($block['elements'] ?? null)) {
            $imageOrdinal = 0;
            $walk = function (array $nodes) use (&$walk, &$imageOrdinal, $industry, $role, $sectionIndex): array {
                foreach ($nodes as &$node) {
                    if (! is_array($node)) continue;
                    if (in_array(($node['type'] ?? ''), ['image','background_image'], true)) {
                        $query = trim((string) ($node['image_query'] ?? ''));
                        if ($query !== '' && trim((string) ($node['src'] ?? '')) === '') {
                            $imageOrdinal++;
                            $node['src'] = $this->images->find(
                                $query.' section '.($sectionIndex + 1).' composition '.$imageOrdinal,
                                $industry,
                                $role
                            );
                        }
                        unset($node['image_query']);
                    }
                    if (is_array($node['children'] ?? null)) $node['children'] = $walk($node['children']);
                }
                unset($node);
                return $nodes;
            };
            $block['elements'] = $walk($block['elements']);
        }

        return $block;
    }

    /** @param array<string,mixed> $plan */
    private function applyComposerIntentContracts(array $block, array $plan): array
    {
        $requestedSlides = (int) ($plan['requested_slide_count'] ?? 0);
        $controls = is_array($plan['slider_controls'] ?? null) ? $plan['slider_controls'] : [];
        if ($requestedSlides <= 0 && $controls === []) return $block;

        $walk = function (array $nodes) use (&$walk, $controls): array {
            foreach ($nodes as &$node) {
                if (! is_array($node)) continue;
                if (($node['type'] ?? null) === 'slider') {
                    foreach (['show_arrows','show_dots','show_counter'] as $key) {
                        // Explicit true requests are deterministic engine settings. False
                        // means "not explicitly requested", so Luna may keep a sensible default.
                        if (($controls[$key] ?? false) === true) $node[$key] = true;
                    }
                }
                if (is_array($node['children'] ?? null)) $node['children'] = $walk($node['children']);
            }
            unset($node);
            return $nodes;
        };
        if (is_array($block['elements'] ?? null)) $block['elements'] = $walk($block['elements']);
        return $block;
    }

    /** @param array<string,mixed> $plan */
    private function assertComposerQuantitiesSatisfied(array $block, array $plan): void
    {
        $requestedSlides = (int) ($plan['requested_slide_count'] ?? 0);
        if ($requestedSlides <= 0) return;
        $found = null;
        $walk = function (array $nodes) use (&$walk, &$found): void {
            foreach ($nodes as $node) {
                if (! is_array($node) || $found !== null) continue;
                if (($node['type'] ?? null) === 'slider') {
                    $found = count(array_filter((array) ($node['children'] ?? []), static fn ($child): bool => is_array($child) && ($child['type'] ?? null) === 'slide'));
                    return;
                }
                if (is_array($node['children'] ?? null)) $walk($node['children']);
            }
        };
        $walk(is_array($block['elements'] ?? null) ? $block['elements'] : []);
        if ($found !== $requestedSlides) {
            throw new \RuntimeException('AI Flex composer returned '.(int) ($found ?? 0).' slides; requested '.$requestedSlides.'.');
        }
    }

    /** @param array<string,mixed> $plan */
    private function assertComposerPlanSatisfied(array $block, array $plan): void
    {
        $required = array_values(array_filter((array) ($plan['required_components'] ?? []), 'is_string'));
        if ($required === []) return;

        $present = [];
        $walk = function (array $nodes) use (&$walk, &$present): void {
            foreach ($nodes as $node) {
                if (! is_array($node)) continue;
                $type = Str::lower(trim((string) ($node['type'] ?? '')));
                if ($type !== '') $present[$type] = true;
                if (is_array($node['children'] ?? null)) $walk($node['children']);
            }
        };
        $walk(is_array($block['elements'] ?? null) ? $block['elements'] : []);

        $missing = array_values(array_filter($required, static fn (string $type): bool => ! isset($present[$type])));
        if ($missing !== []) {
            throw new \RuntimeException('AI Flex composer omitted required trusted primitives: '.implode(', ', $missing));
        }
    }

    /** @param array<string,mixed> $plan */
    private function stampComposerMetadata(array $block, array $plan): array
    {
        $meta = is_array($block['ai_flex'] ?? null) ? $block['ai_flex'] : [];
        if (trim((string) ($meta['composition_profile'] ?? '')) === '') {
            $meta['composition_profile'] = (string) ($plan['profile'] ?? AiFlexComposerGuide::PROFILE);
        }
        $meta['composer_version'] = (int) ($plan['version'] ?? AiFlexComposerGuide::VERSION);
        $meta['composer_recipe'] = substr((string) ($plan['recipe'] ?? ''), 0, 180);
        $layoutRecipe = is_array($plan['layout_recipe'] ?? null) ? $plan['layout_recipe'] : null;
        $requestedSlides = (int) ($plan['requested_slide_count'] ?? 0);
        if ($requestedSlides > 0) $meta['requested_slide_count'] = $requestedSlides;
        if (is_array($plan['slider_controls'] ?? null) && array_filter($plan['slider_controls'])) {
            $meta['requested_slider_controls'] = array_keys(array_filter($plan['slider_controls']));
        }
        if ($layoutRecipe) {
            $meta['layout_recipe'] = substr((string) ($layoutRecipe['key'] ?? ''), 0, 80);
            $meta['layout_recipe_version'] = AiFlexLayoutRecipeRegistry::VERSION;
            $meta['layout_recipe_contract'] = AiFlexLayoutRecipeRegistry::CONTRACT;
        }
        $block['ai_flex'] = $meta;
        return $block;
    }

    /** @param array<string,mixed> $plan */
    private function applyLayoutRecipeGeometry(array $block, array $plan): array
    {
        $recipe = is_array($plan['layout_recipe'] ?? null) ? $plan['layout_recipe'] : null;
        if (! $recipe || ! is_array($block['elements'] ?? null)) return $block;

        $key = (string) ($recipe['key'] ?? '');
        $kind = (string) ($recipe['kind'] ?? '');
        $elements = $block['elements'];

        if ($kind === 'row') {
            $wanted = array_values((array) ($recipe['desktop_widths'] ?? []));
            $targetRow = null;
            foreach ($elements as $i => $root) {
                if (($root['type'] ?? null) === 'row' && is_array($root['children'] ?? null) && count($root['children']) >= max(1, count($wanted))) {
                    $targetRow = $i; break;
                }
            }
            if ($targetRow !== null) {
                $row = $elements[$targetRow];
                $row['style'] = is_array($row['style'] ?? null) ? $row['style'] : [];
                $row['style']['gap'] = (int) ($row['style']['gap'] ?? 32);
                $row['style']['tablet_gap'] = (int) ($row['style']['tablet_gap'] ?? 24);
                $row['style']['mobile_gap'] = (int) ($row['style']['mobile_gap'] ?? 20);
                $row['style']['align'] = (string) ($row['style']['align'] ?? 'stretch');

                if ($wanted !== []) {
                    // Width percentages plus flex gap must fit inside 100%. Preserve the
                    // requested ratio while reserving a small gap budget for desktop.
                    $n = count($wanted);
                    $usable = $n === 2 ? 96.0 : ($n === 3 ? 94.0 : ($n >= 4 ? 92.0 : 100.0));
                    $sum = max(0.001, array_sum(array_map('floatval', $wanted)));
                    foreach ($wanted as $idx => $ratio) {
                        if (! isset($row['children'][$idx]) || ! is_array($row['children'][$idx])) continue;
                        $col = $row['children'][$idx];
                        $col['style'] = is_array($col['style'] ?? null) ? $col['style'] : [];
                        $col['style']['width'] = round($usable * ((float) $ratio / $sum), 3);
                        $col['style']['tablet_width'] = 100;
                        $col['style']['mobile_width'] = 100;
                        $row['children'][$idx] = $col;
                    }
                }

                if (in_array($key, ['single_centered','content_narrow'], true) && isset($row['children'][0])) {
                    $row['style']['justify'] = 'center';
                    $col = $row['children'][0];
                    $col['style'] = is_array($col['style'] ?? null) ? $col['style'] : [];
                    $col['style']['width'] = 100;
                    $col['style']['max_width'] = $key === 'content_narrow' ? 760 : 960;
                    $col['style']['align'] = (string) ($col['style']['align'] ?? 'center');
                    $row['children'][0] = $col;
                }

                $elements[$targetRow] = $row;
            }
        }

        if ($kind === 'grid') {
            $applyGrid = function (array $nodes) use (&$applyGrid, $recipe): array {
                foreach ($nodes as &$node) {
                    if (! is_array($node)) continue;
                    if (($node['type'] ?? null) === 'grid') {
                        $node['style'] = is_array($node['style'] ?? null) ? $node['style'] : [];
                        $node['style']['columns'] = (int) ($recipe['desktop_columns'] ?? 2);
                        $node['style']['tablet_columns'] = (int) ($recipe['tablet_columns'] ?? 2);
                        $node['style']['mobile_columns'] = (int) ($recipe['mobile_columns'] ?? 1);
                        $node['style']['gap'] = (int) ($node['style']['gap'] ?? 24);
                        return $nodes;
                    }
                    if (is_array($node['children'] ?? null)) $node['children'] = $applyGrid($node['children']);
                }
                unset($node);
                return $nodes;
            };
            $elements = $applyGrid($elements);
        }

        // Featured bento recipes use the row split above and encourage the dense side
        // to stay a deterministic 2-column grid when Luna supplied one.
        if (in_array($key, ['bento_featured_left','bento_featured_right'], true)) {
            $applyFirstGrid = function (array $nodes) use (&$applyFirstGrid): array {
                foreach ($nodes as &$node) {
                    if (! is_array($node)) continue;
                    if (($node['type'] ?? null) === 'grid') {
                        $node['style'] = is_array($node['style'] ?? null) ? $node['style'] : [];
                        $node['style']['columns'] = 2;
                        $node['style']['tablet_columns'] = 2;
                        $node['style']['mobile_columns'] = 1;
                        $node['style']['gap'] = (int) ($node['style']['gap'] ?? 20);
                        return $nodes;
                    }
                    if (is_array($node['children'] ?? null)) $node['children'] = $applyFirstGrid($node['children']);
                }
                unset($node);
                return $nodes;
            };
            $elements = $applyFirstGrid($elements);
        }

        $block['elements'] = $elements;
        return $block;
    }

    /** @param array<string,mixed> $plan */
    private function assertLayoutRecipeSatisfied(array $block, array $plan): void
    {
        $recipe = is_array($plan['layout_recipe'] ?? null) ? $plan['layout_recipe'] : null;
        if (! $recipe) return;
        $kind = (string) ($recipe['kind'] ?? '');
        $wanted = count((array) ($recipe['desktop_widths'] ?? []));
        $hasRequiredShape = false;
        $walk = function (array $nodes) use (&$walk, &$hasRequiredShape, $kind, $wanted): void {
            foreach ($nodes as $node) {
                if (! is_array($node) || $hasRequiredShape) continue;
                if ($kind === 'grid' && ($node['type'] ?? null) === 'grid') $hasRequiredShape = true;
                if ($kind === 'row' && ($node['type'] ?? null) === 'row' && count((array) ($node['children'] ?? [])) >= max(1, $wanted)) $hasRequiredShape = true;
                if (is_array($node['children'] ?? null)) $walk($node['children']);
            }
        };
        $walk(is_array($block['elements'] ?? null) ? $block['elements'] : []);
        if (! $hasRequiredShape) {
            throw new \RuntimeException('AI Flex composer omitted required layout Lego geometry: '.(string) ($recipe['key'] ?? 'unknown'));
        }
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

    private function sanitizeElements(array $elements, int $depth = 0, int &$budget = 0): array
    {
        if ($depth > 8 || $budget >= 80) return [];
        $allowedTypes = AiFlexComponentRegistry::rendererReadyTypes();
        $containerTypes = AiFlexComponentRegistry::containerTypes();
        $allowedStyle = AiFlexComponentRegistry::styleKeys();
        $out = [];
        foreach (array_slice($elements, 0, 40) as $raw) {
            if (!is_array($raw) || $budget >= 80) continue;
            $type = Str::lower(trim((string)($raw['type'] ?? '')));
            if (!in_array($type, $allowedTypes, true)) continue;
            $budget++;
            $node = ['type'=>$type];
            foreach (['_cosmic_id','key','text','label','url','src','poster','alt','image_query','icon','value'] as $key) {
                if (isset($raw[$key]) && is_scalar($raw[$key])) $node[$key] = mb_substr((string)$raw[$key], 0, $key === 'text' ? 4000 : 500);
            }
            if (in_array($type, ['video','background_video'], true)) {
                $node['controls'] = $type === 'background_video' ? false : (array_key_exists('controls', $raw) ? (bool) $raw['controls'] : true);
                $node['autoplay'] = $type === 'background_video' ? (array_key_exists('autoplay', $raw) ? (bool) $raw['autoplay'] : true) : !empty($raw['autoplay']);
                $node['muted'] = array_key_exists('muted', $raw) ? (bool) $raw['muted'] : true;
                // Browsers generally block unmuted autoplay. Background video is decorative/media-layer
                // content, so an autoplaying background must always be muted for Builder/export parity.
                if ($type === 'background_video' && $node['autoplay']) $node['muted'] = true;
                $node['loop'] = $type === 'background_video' ? (array_key_exists('loop', $raw) ? (bool) $raw['loop'] : true) : !empty($raw['loop']);
                $node['plays_inline'] = array_key_exists('plays_inline', $raw) ? (bool) $raw['plays_inline'] : true;
            }
            if ($type === 'slider') {
                $node['autoplay'] = array_key_exists('autoplay', $raw) ? (bool) $raw['autoplay'] : true;
                $node['interval'] = max(2000, min(15000, (int) ($raw['interval'] ?? 5000)));
                $node['loop'] = array_key_exists('loop', $raw) ? (bool) $raw['loop'] : true;
                $node['show_arrows'] = array_key_exists('show_arrows', $raw) ? (bool) $raw['show_arrows'] : true;
                $node['show_dots'] = array_key_exists('show_dots', $raw) ? (bool) $raw['show_dots'] : true;
                $node['show_counter'] = array_key_exists('show_counter', $raw) ? (bool) $raw['show_counter'] : false;
                $node['transition'] = in_array((string) ($raw['transition'] ?? 'fade'), ['fade','slide'], true) ? (string) $raw['transition'] : 'fade';
            }
            if (isset($raw['items']) && is_array($raw['items'])) {
                $node['items'] = collect($raw['items'])->take(20)->map(function ($item) {
                    if (is_scalar($item)) return mb_substr((string)$item,0,500);
                    if (!is_array($item)) return null;
                    return array_intersect_key($item, array_flip(['text','label','value','icon']));
                })->filter(fn($v)=>$v!==null)->values()->all();
            }
            if ($type === 'form') {
                foreach (['title','note','button_label','success_message'] as $key) {
                    if (isset($raw[$key]) && is_scalar($raw[$key])) $node[$key] = mb_substr((string)$raw[$key], 0, $key === 'success_message' ? 500 : 300);
                }
                $node['columns'] = ((int)($raw['columns'] ?? 2)) === 1 ? 1 : 2;
                $node['button_alignment'] = in_array((string)($raw['button_alignment'] ?? 'left'), ['left','center','right'], true) ? (string)$raw['button_alignment'] : 'left';
                $node['fields'] = collect(is_array($raw['fields'] ?? null) ? $raw['fields'] : [])->take(12)->map(function ($field, $index) {
                    if (!is_array($field)) return null;
                    $type = Str::lower(trim((string)($field['type'] ?? 'text')));
                    if (!in_array($type, ['text','email','tel','number','date','time','textarea','select','checkbox','radio','hidden'], true)) $type = 'text';
                    $name = Str::lower(trim((string)($field['name'] ?? ('field_'.($index+1)))));
                    $name = trim((string)preg_replace('/_+/', '_', preg_replace('/[^a-z0-9_]+/', '_', $name)), '_');
                    if ($name === '' || !preg_match('/^[a-z]/', $name)) $name = 'field_'.($index+1);
                    $clean = ['name'=>mb_substr($name,0,64),'type'=>$type,'required'=>!empty($field['required']),'width'=>(($field['width'] ?? '')==='full'?'full':'half')];
                    foreach (['label','placeholder','value','autocomplete'] as $key) if (isset($field[$key]) && is_scalar($field[$key])) $clean[$key]=mb_substr((string)$field[$key],0,300);
                    foreach (['min','max','step'] as $key) if (isset($field[$key]) && is_numeric($field[$key])) $clean[$key]=(float)$field[$key];
                    if (isset($field['options']) && is_array($field['options'])) $clean['options']=collect($field['options'])->take(20)->map(function($option){
                        if (is_scalar($option)) return mb_substr((string)$option,0,200);
                        if (!is_array($option)) return null;
                        $label=is_scalar($option['label']??null)?mb_substr((string)$option['label'],0,200):'';
                        $value=is_scalar($option['value']??null)?mb_substr((string)$option['value'],0,200):$label;
                        return ['label'=>$label,'value'=>$value];
                    })->filter(fn($v)=>$v!==null)->values()->all();
                    return $clean;
                })->filter()->values()->all();
            }
            $style = is_array($raw['style'] ?? null) ? array_intersect_key($raw['style'], array_flip($allowedStyle)) : [];
            $cleanStyle = [];
            foreach ($style as $key=>$value) {
                if (!is_scalar($value)) continue;
                if (in_array($key,['background','color','border_color'],true)) {
                    $v=Str::lower(trim((string)$value));
                    if (in_array($v,['primary','surface','surface_alt','white','on_primary','on_surface','on_dark','accent','transparent'],true) || preg_match('/^#[0-9a-f]{6}([0-9a-f]{2})?$/i',$v)) $cleanStyle[$key]=$v;
                    continue;
                }
                if ($key==='shadow') { if (in_array((string)$value,['none','sm','md','lg','xl'],true)) $cleanStyle[$key]=(string)$value; continue; }
                if ($key==='align') { if (in_array((string)$value,['start','center','end','stretch'],true)) $cleanStyle[$key]=(string)$value; continue; }
                if ($key==='justify') { if (in_array((string)$value,['start','center','end','between','around'],true)) $cleanStyle[$key]=(string)$value; continue; }
                if ($key==='text_align') { if (in_array((string)$value,['left','center','right'],true)) $cleanStyle[$key]=(string)$value; continue; }
                if (in_array($key,['position','tablet_position','mobile_position'],true)) { if (in_array((string)$value,['static','relative','absolute'],true)) $cleanStyle[$key]=(string)$value; continue; }
                if ($key==='overflow') { if (in_array((string)$value,['visible','hidden','clip'],true)) $cleanStyle[$key]=(string)$value; continue; }
                if ($key==='self_align') { if (in_array((string)$value,['auto','start','center','end','stretch'],true)) $cleanStyle[$key]=(string)$value; continue; }
                if ($key==='object_fit') { if (in_array((string)$value,['cover','contain'],true)) $cleanStyle[$key]=(string)$value; continue; }
                if ($key==='object_position') { $cleanStyle[$key]=mb_substr((string)$value,0,60); continue; }
                $num=is_numeric($value)?(float)$value:null;
                if ($num===null) continue;
                $ranges=['gap'=>[0,160],'columns'=>[1,12],'width'=>[5,100],'max_width'=>[120,2200],'min_height'=>[0,1400],'padding'=>[0,200],'padding_x'=>[0,200],'padding_y'=>[0,200],'radius'=>[0,999],'border_width'=>[0,8],'font_size'=>[8,180],'font_weight'=>[100,900],'line_height'=>[0.7,2.5],'aspect_ratio'=>[0.2,5],'opacity'=>[0,1],'top'=>[-400,1200],'right'=>[-400,1200],'bottom'=>[-400,1200],'left'=>[-400,1200],'z_index'=>[-5,80],'order'=>[-20,20],'grow'=>[0,5],'basis'=>[5,100],'tablet_width'=>[5,100],'mobile_width'=>[5,100],'tablet_columns'=>[1,8],'mobile_columns'=>[1,4],'tablet_gap'=>[0,120],'mobile_gap'=>[0,80],'tablet_padding'=>[0,160],'mobile_padding'=>[0,120],'tablet_order'=>[-20,20],'mobile_order'=>[-20,20],'tablet_min_height'=>[0,1200],'mobile_min_height'=>[0,900]];
                if(isset($ranges[$key])) $cleanStyle[$key]=max($ranges[$key][0],min($ranges[$key][1],$num));
            }
            if ($cleanStyle) $node['style']=$cleanStyle;
            if (in_array($type,$containerTypes,true)) {
                $rawChildren = is_array($raw['children'] ?? null) ? $raw['children'] : [];
                $allowedChildren = AiFlexComponentRegistry::allowedChildren($type);
                if ($allowedChildren !== [] && ! in_array('*', $allowedChildren, true)) {
                    $rawChildren = array_values(array_filter($rawChildren, static function ($child) use ($allowedChildren): bool {
                        return is_array($child) && in_array(Str::lower(trim((string) ($child['type'] ?? ''))), $allowedChildren, true);
                    }));
                }
                $node['children']=$this->sanitizeElements($rawChildren, $depth+1, $budget);
                if ($type === 'slider') $node['children'] = array_slice($node['children'], 0, 8);
                if (in_array($type, ['button_group','media_group'], true)) $node['children'] = array_slice($node['children'], 0, 12);
            }
            $out[]=$node;
        }
        return $out;
    }

    public function validate(array $block, array $source = []): array
    {
        $allowed = ['type','custom_spark_key','custom_spark_saved','semantic_type','source_type','category','layout','alignment','media_position','density','accent_shape','section_mood','eyebrow','heading','heading_accent_text','text','primary_label','primary_url','secondary_label','secondary_url','image_url','image_query','items','elements','theme','visual_style','review','form','runtime','style_overrides','ai_flex'];
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
        $visual = is_array($block['visual_style'] ?? null) ? $block['visual_style'] : [];
        $block['visual_style'] = array_merge([
            'content_max_width' => 1280,
            'section_padding_x' => 48,
            'section_padding_y' => 80,
            'section_padding_y_tablet' => 60,
            'section_padding_y_mobile' => 44,
            'content_gap' => 32,
            'content_gap_tablet' => 24,
            'content_gap_mobile' => 20,
            'item_gap' => 24,
        ], $visual);
        $elementBudget = 0;
        $block['elements'] = $this->sanitizeElements(is_array($block['elements'] ?? null) ? $block['elements'] : [], 0, $elementBudget);
        foreach ($block['elements'] as &$row) {
            if (! is_array($row) || ($row['type'] ?? '') !== 'row') continue;
            $row['style'] = array_merge([
                'gap' => 32,
                'tablet_gap' => 24,
                'mobile_gap' => 20,
                'align' => 'center',
            ], is_array($row['style'] ?? null) ? $row['style'] : []);
        }
        unset($row);
        if ($block['elements'] !== []) $block['elements'] = AiFlexStructureContract::canonicalizeElements($block['elements']);
        $block['ai_flex'] = array_merge([
            'version'=>1,
            'source'=>'sol',
            'reference_mode'=>'composition_only',
            'theme_policy'=>'inherit',
            'component_contract'=>AiFlexComponentRegistry::CONTRACT,
            'component_version'=>AiFlexComponentRegistry::VERSION,
        ], is_array($block['ai_flex'] ?? null) ? array_intersect_key($block['ai_flex'],array_flip(['version','source','reference_mode','theme_policy','intent','spark_name','model','department','composition_profile','structure_contract','structure_version','structure_storage','component_contract','component_version','pipeline','structure_locked','stages','composer_version','composer_recipe','layout_recipe','layout_recipe_version','layout_recipe_contract','requested_slide_count','requested_slider_controls'])) : []);
        if ($block['elements'] !== []) {
            $block['ai_flex']['structure_contract'] = AiFlexStructureContract::CONTRACT;
            $block['ai_flex']['structure_version'] = AiFlexStructureContract::VERSION;
            $block['ai_flex']['structure_storage'] = AiFlexStructureContract::STORAGE_KEY;
        }
        foreach (['heading','eyebrow','text','primary_label','primary_url','secondary_label','secondary_url','image_url','image_query','semantic_type','source_type','category','density','accent_shape','section_mood'] as $key) {
            if (isset($block[$key]) && !is_scalar($block[$key])) unset($block[$key]);
        }
        if (trim((string)($block['heading'] ?? '')) === '' && !empty($source['heading'])) $block['heading'] = $source['heading'];
        if (empty($block['text']) && !empty($source['text'])) $block['text'] = $source['text'];
        return $block;
    }
}
