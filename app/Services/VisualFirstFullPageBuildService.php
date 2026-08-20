<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VisualFirstFullPageBuildService
{
    public function __construct(
        private readonly SmartImageService $images,
        private readonly IndustryResolver $industryResolver,
    ) {}

    public function build(
        Website $website,
        string $brief,
        ?string $screenshotPath = null,
        ?string $screenshotMime = null,
        ?callable $progress = null,
    ): array {
        $progress ??= static fn (int $percent, string $stage) => null;

        if ($screenshotPath && Storage::disk('public')->exists($screenshotPath)) {
            $progress(12, 'Reading your full-page reference…');
            $visual = [
                'bytes' => Storage::disk('public')->get($screenshotPath),
                'path' => $screenshotPath,
                'url' => Storage::disk('public')->url($screenshotPath),
                'mime' => $screenshotMime ?: (Storage::disk('public')->mimeType($screenshotPath) ?: 'image/png'),
                'source' => 'uploaded_screenshot',
            ];
        } else {
            $progress(8, 'Designing the hidden visual direction…');
            $visual = $this->createHiddenMockup($website, $brief);
            $progress(30, 'Visual direction created. Mapping page regions…');
        }

        $apiKey = (string) config('openai.api_key');
        abort_if($apiKey === '', 503, 'Cosmic AI is not configured.');
        $dataUri = 'data:'.$visual['mime'].';base64,'.base64_encode($visual['bytes']);

        // Pass 1 is intentionally light: map section boundaries and global shell only.
        // The previous implementation asked one vision call to both understand a tall page
        // and author every section schema, which flattened detailed layouts into generic cards.
        $progress(max(32, $screenshotPath ? 24 : 32), 'Mapping exact section boundaries…');
        $mapResponse = Http::withToken($apiKey)
            ->timeout(max(180, (int) config('openai.request_timeout', 180)))
            ->post(rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/chat/completions', [
                'model' => env('OPENAI_VISION_MODEL', env('OPENAI_MODEL', 'gpt-5-mini')),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->mapContract()],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => "USER BRIEF:\n{$brief}\n\nMap IMAGE 1. Return normalized y-boundaries for each visible body section plus the global header/footer shell. Do not reconstruct body content yet."],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUri, 'detail' => 'high']],
                    ]],
                ],
            ])->throw()->json();

        $mapped = json_decode((string) data_get($mapResponse, 'choices.0.message.content', '{}'), true);
        $regions = array_values(array_slice(is_array($mapped['regions'] ?? null) ? $mapped['regions'] : [], 0, 10));
        if (count($regions) < 2) {
            throw ValidationException::withMessages(['instructions' => 'Cosmic AI could not identify enough page regions from the visual source.']);
        }

        $progress(42, 'Cropping visual regions for high-fidelity reconstruction…');
        $crops = $this->cropRegions($visual['bytes'], $regions);
        if (count($crops) < 2) {
            throw ValidationException::withMessages(['instructions' => 'Cosmic AI could not prepare enough visual regions for reconstruction.']);
        }

        // Pass 2: each section receives its own crop at useful resolution.
        // This preserves local proportions, item counts, whitespace and composition.
        $sections = [];
        $cropCount = max(1, count($crops));
        foreach ($crops as $index => $crop) {
            $progress(44 + (int) round(($index / $cropCount) * 28), 'Reconstructing visual section '.($index + 1).' of '.$cropCount.'…');
            $cropUri = 'data:image/png;base64,'.base64_encode($crop['bytes']);
            $region = $crop['region'];

            $sectionResponse = Http::withToken($apiKey)
                ->timeout(max(150, (int) config('openai.request_timeout', 150)))
                ->post(rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/chat/completions', [
                    'model' => env('OPENAI_VISION_MODEL', env('OPENAI_MODEL', 'gpt-5-mini')),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->sectionContract()],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => "USER BRIEF:\n{$brief}\n\nSECTION ".($index + 1)." OF {$cropCount}\nREGION HINT: ".json_encode($region, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nReconstruct THIS CROP only. Match its composition and proportions rather than defaulting to generic cards."],
                            ['type' => 'image_url', 'image_url' => ['url' => $cropUri, 'detail' => 'high']],
                        ]],
                    ],
                ])->throw()->json();

            $section = json_decode((string) data_get($sectionResponse, 'choices.0.message.content', '{}'), true);
            if (! is_array($section)) continue;
            $section = is_array($section['section'] ?? null) ? $section['section'] : $section;
            $section['visual_style'] = array_merge(
                is_array($section['visual_style'] ?? null) ? $section['visual_style'] : [],
                [
                    'reference_aspect_ratio' => round($crop['width'] / max(1, $crop['height']), 4),
                    'reference_height_px' => $crop['height'],
                    'reference_width_px' => $crop['width'],
                ]
            );
            $sections[] = $section;
        }

        if (count($sections) < 2) {
            throw ValidationException::withMessages(['instructions' => 'Cosmic AI could not reconstruct enough editable sections from the mapped visual regions.']);
        }

        $generated = [
            'sections' => $sections,
            'shell' => is_array($mapped['shell'] ?? null) ? $mapped['shell'] : [],
            'direction' => is_array($mapped['direction'] ?? null) ? $mapped['direction'] : null,
        ];

        $progress(55, 'Building editable Custom Sparks…');
        $industry = $this->industryResolver->resolve($website->industry ?: $brief, 'default');
        $blocks = [];
        $sparkIds = [];
        $sectionCount = max(1, count($sections));

        foreach ($sections as $index => $section) {
            if (! is_array($section)) continue;

            $query = trim((string) ($section['image_query'] ?? ''));
            if ($query !== '' && trim((string) ($section['image_url'] ?? '')) === '') {
                $role = $index === 0
                    ? 'hero'
                    : (str_contains(strtolower((string) ($section['category'] ?? '')), 'project') ? 'gallery' : 'general');
                $section['image_url'] = $this->images->find($query, $industry, $role);
                if (($section['media_position'] ?? 'none') === 'none') {
                    $section['media_position'] = $index === 0 ? 'background' : 'right';
                }
            }
            unset($section['image_query']);

            if (is_array($section['items'] ?? null)) {
                foreach ($section['items'] as &$item) {
                    if (! is_array($item)) continue;
                    $itemQuery = trim((string) ($item['image_query'] ?? ''));
                    if ($itemQuery !== '' && trim((string) ($item['image_url'] ?? '')) === '') {
                        $item['image_url'] = $this->images->find($itemQuery, $industry, 'gallery');
                    }
                    unset($item['image_query']);
                }
                unset($item);
            }

            $key = 'custom_'.Str::lower(Str::random(10));
            $name = trim((string) ($section['name'] ?? ('Section '.($index + 1)))) ?: ('Section '.($index + 1));
            $block = $this->normalizeBlock($section, $key, $name);

            $spark = $website->customSparks()->create([
                'key' => $key,
                'name' => $name,
                'schema' => [
                    'groups' => [
                        'content' => ['heading', 'text', 'items', 'form', 'runtime'],
                        'design' => ['visual_style'],
                    ],
                    'inherits_global' => false,
                ],
                'block' => $block,
                'metadata' => [
                    'source' => 'visual-first-full-page',
                    'visual_source' => $visual['source'],
                    'visual_source_path' => $visual['path'],
                    'credits' => 0,
                    'saved' => false,
                    'page_brief' => $brief,
                    'section_index' => $index,
                    'conversation' => [
                        ['role' => 'user', 'text' => $brief, 'at' => now()->toIso8601String()],
                        ['role' => 'assistant', 'text' => 'I reconstructed this section from the full-page visual design source.', 'at' => now()->toIso8601String()],
                    ],
                ],
            ]);

            $blocks[] = $block;
            $sparkIds[] = $spark->id;
            $progress(55 + (int) round((($index + 1) / $sectionCount) * 32), 'Building section '.($index + 1).' of '.$sectionCount.'…');
        }

        if (count($blocks) < 2) {
            throw ValidationException::withMessages(['instructions' => 'Cosmic AI could not build enough editable sections from the visual source.']);
        }

        $progress(92, 'Applying global header and footer…');

        return [
            'blocks' => $blocks,
            'spark_ids' => $sparkIds,
            'shell' => $this->normalizeShell(is_array($generated['shell'] ?? null) ? $generated['shell'] : []),
            'site_direction' => is_array($generated['direction'] ?? null) ? $generated['direction'] : null,
            'visual_source' => $visual['source'],
            'mode' => 'visual-first-full-page',
        ];
    }

    private function createHiddenMockup(Website $website, string $brief): array
    {
        $apiKey = (string) config('openai.api_key');
        abort_if($apiKey === '', 503, 'Cosmic AI is not configured.');

        $business = trim((string) ($website->name ?: 'the business'));
        $industry = trim((string) ($website->industry ?: 'business'));
        $prompt = implode("\n", [
            "Create ONE tall full-page desktop website design mockup for {$business}.",
            "Business/industry context: {$industry}.",
            "USER BRIEF: {$brief}",
            'Canvas: portrait full landing-page composition, approximately 1024x1536, showing the complete page from header through footer.',
            'Act as a senior digital art director. Establish one coherent visual system, but vary section composition throughout the page.',
            'Show a premium header, strong hero, multiple distinct body sections, and footer. Use realistic commercial photography, sophisticated spacing, editorial hierarchy, and clear calls to action.',
            'Avoid repetitive card grids. Mix full-bleed imagery, split compositions, editorial sections, gallery/project layouts, trust/proof, and purposeful whitespace when appropriate.',
            'This is an INTERNAL DESIGN BLUEPRINT. Prioritize layout, proportion, color, imagery, hierarchy, and section boundaries over perfectly legible tiny text.',
            'Do not render browser chrome, device frames, watermarks, annotations, arrows, measurement labels, or design-tool UI.',
        ]);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(max(180, (int) config('openai.request_timeout', 180)))
                ->post(rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/images/generations', [
                    'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
                    'prompt' => $prompt,
                    'size' => '1024x1536',
                    'quality' => env('OPENAI_CUSTOM_MOCKUP_QUALITY', 'low'),
                    'n' => 1,
                ]);
        } catch (Throwable $exception) {
            report($exception);
            abort(502, 'Cosmic AI could not create the visual design blueprint.');
        }

        if ($response->failed()) {
            report(new \RuntimeException('Custom Build mockup generation failed: '.$response->body()));
            abort(502, 'Cosmic AI could not create the visual design blueprint.');
        }

        $encoded = data_get($response->json(), 'data.0.b64_json');
        $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
        if ($bytes === false || strlen($bytes) < 100) {
            abort(502, 'Cosmic AI returned an invalid visual design blueprint.');
        }

        $filename = 'hidden-page-mockup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.png';
        $path = "custom-sites/{$website->id}/mockups/{$filename}";
        abort_unless(Storage::disk('public')->put($path, $bytes), 502, 'The visual design blueprint could not be stored.');

        return [
            'bytes' => $bytes,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'mime' => 'image/png',
            'source' => 'generated_mockup',
        ];
    }

    private function normalizeShell(array $raw): array
    {
        $shell = [
            'header_detected' => (bool) ($raw['header_detected'] ?? false),
            'footer_detected' => (bool) ($raw['footer_detected'] ?? false),
        ];

        if ($shell['header_detected'] && is_array($raw['header'] ?? null)) {
            $header = array_intersect_key($raw['header'], array_flip([
                'overlay','background_color','text_color','nav_color','height','padding_x','content_max_width',
                'logo_tone','logo_height','nav_size','nav_gap','phone_enabled','phone_text','phone_color',
                'cta_label','cta_url','cta_background','cta_color','cta_radius','menu'
            ]));
            $header['menu'] = array_values(array_slice(array_filter(
                is_array($header['menu'] ?? null) ? $header['menu'] : [],
                fn ($item) => is_array($item) && trim((string) ($item['label'] ?? '')) !== ''
            ), 0, 8));
            $shell['header'] = $header;
        }

        if ($shell['footer_detected'] && is_array($raw['footer'] ?? null)) {
            $footer = array_intersect_key($raw['footer'], array_flip([
                'background_color','text_color','muted_color','logo_tone','tagline','cta_label','cta_url','columns','copyright'
            ]));
            $footer['columns'] = array_values(array_slice(is_array($footer['columns'] ?? null) ? $footer['columns'] : [], 0, 4));
            $shell['footer'] = $footer;
        }

        return $shell;
    }

    private function normalizeBlock(array $generated, string $key, string $name): array
    {
        $block = array_merge([
            'type' => 'luna_custom_section',
            'custom_spark_key' => $key,
            'custom_spark_saved' => false,
            'category' => 'content',
            'layout' => 'editorial',
            'alignment' => 'left',
            'media_position' => 'none',
            'density' => 'balanced',
            'accent_shape' => 'none',
            'section_mood' => 'auto',
            'eyebrow' => '',
            'heading' => $name,
            'heading_accent_text' => '',
            'text' => '',
            'primary_label' => '',
            'primary_url' => '#',
            'secondary_label' => '',
            'secondary_url' => '#',
            'image_url' => '',
            'items' => [],
            'theme' => 'auto',
            'style_overrides' => [],
            'visual_style' => [],
            'review' => null,
            'form' => null,
            'runtime' => null,
        ], array_intersect_key($generated, array_flip([
            'category','layout','alignment','media_position','density','accent_shape','eyebrow','heading','heading_accent_text','text',
            'primary_label','primary_url','secondary_label','secondary_url','image_url','items','review','form','runtime','visual_style'
        ])));

        if (is_array($block['runtime'] ?? null)) {
            $runtime = $block['runtime'];
            $safeKey = fn ($value) => preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) $value);
            $runtime['version'] = 1;

            foreach (['state'=>16,'inputs'=>16,'computed'=>16,'conditions'=>16,'actions'=>12,'views'=>10,'steps'=>10,'modals'=>8,'outputs'=>12] as $group => $limit) {
                $runtime[$group] = array_slice(is_array($runtime[$group] ?? null) ? $runtime[$group] : [], 0, $limit);
            }
            $runtime['collections'] = array_slice(is_array($runtime['collections'] ?? null) ? $runtime['collections'] : [], 0, 4);
            $runtime['state'] = array_values(array_filter($runtime['state'], fn ($item) => is_array($item) && $safeKey($item['key'] ?? '')));
            $runtime['inputs'] = array_values(array_filter($runtime['inputs'], fn ($item) => is_array($item) && $safeKey($item['key'] ?? '')));

            $allowedOperators = ['eq','neq','gt','gte','lt','lte','contains','truthy','falsy'];
            $allowedEffects = ['show','hide','enable','disable'];
            $runtime['conditions'] = array_values(array_filter($runtime['conditions'], fn ($item) =>
                is_array($item)
                && $safeKey($item['target'] ?? '')
                && $safeKey($item['source'] ?? '')
                && in_array(($item['operator'] ?? 'eq'), $allowedOperators, true)
                && in_array(($item['effect'] ?? 'show'), $allowedEffects, true)
            ));

            $allowedActions = ['set_value','toggle','increment','decrement','open_modal','close_modal','next_step','previous_step','go_to_step','select_tab','select_item','filter','search','sort','reset','calculate','scroll_to','submit_form'];
            $runtime['actions'] = array_values(array_filter($runtime['actions'], fn ($item) =>
                is_array($item) && in_array(($item['type'] ?? ''), $allowedActions, true)
            ));

            foreach (['views','steps','modals','outputs'] as $group) {
                $runtime[$group] = array_values(array_filter($runtime[$group], fn ($item) => is_array($item) && $safeKey($item['key'] ?? '')));
            }
            $runtime['collections'] = array_values(array_filter($runtime['collections'], fn ($item) =>
                is_array($item) && $safeKey($item['key'] ?? '') && is_array($item['items'] ?? null)
            ));

            $known = collect($runtime['inputs'])->pluck('key')->merge(collect($runtime['state'])->pluck('key'))->filter()->values()->all();
            $functions = ['min','max','round','floor','ceil','abs','pow','if'];
            $safeComputed = [];
            foreach ($runtime['computed'] as $item) {
                if (! is_array($item) || ! $safeKey($item['key'] ?? '')) continue;
                $formula = trim((string) ($item['formula'] ?? ''));
                if ($formula === '' || strlen($formula) > 500 || ! preg_match('/^[0-9A-Za-z_+\-*\/%().,<>=!&|\s]+$/', $formula)) continue;
                preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $formula, $matches);
                if (array_diff(array_unique($matches[0] ?? []), array_merge($known, $functions))) continue;
                $safeComputed[] = $item;
                $known[] = $item['key'];
            }
            $runtime['computed'] = $safeComputed;
            $block['runtime'] = $runtime;
        }

        return $block;
    }

    private function cropRegions(string $bytes, array $regions): array
    {
        if (! function_exists('imagecreatefromstring')) {
            // GD is normally enabled in Cosmic CMS. If unavailable, keep a safe full-image fallback.
            return array_map(fn ($region) => ['bytes'=>$bytes,'width'=>1024,'height'=>768,'region'=>$region], $regions);
        }

        $image = @imagecreatefromstring($bytes);
        if (! $image) return [];

        $width = imagesx($image);
        $height = imagesy($image);
        $out = [];

        foreach ($regions as $region) {
            if (! is_array($region)) continue;
            $top = max(0.0, min(1.0, (float) ($region['top'] ?? 0)));
            $bottom = max($top + 0.02, min(1.0, (float) ($region['bottom'] ?? 1)));
            $y = (int) floor($top * $height);
            $h = max(80, min($height - $y, (int) ceil(($bottom - $top) * $height)));

            // A tiny overlap protects section-edge content without mixing neighboring sections.
            $bleed = min(18, (int) floor($h * 0.015));
            $y = max(0, $y - $bleed);
            $h = min($height - $y, $h + ($bleed * 2));

            $crop = imagecrop($image, ['x'=>0,'y'=>$y,'width'=>$width,'height'=>$h]);
            if (! $crop) continue;

            ob_start();
            imagepng($crop, null, 6);
            $cropBytes = ob_get_clean();
            imagedestroy($crop);
            if (! is_string($cropBytes) || strlen($cropBytes) < 100) continue;

            $out[] = ['bytes'=>$cropBytes,'width'=>$width,'height'=>$h,'region'=>$region];
        }

        imagedestroy($image);
        return $out;
    }

    private function mapContract(): string
    {
        return <<<'PROMPT'
Return JSON only. IMAGE 1 is a COMPLETE desktop landing-page visual source.

Your only body task is SECTION MAPPING, not reconstruction.
Return:
{
 "direction":{"name":"","summary":"","palette":["#RRGGBB"],"notes":""},
 "shell":{"header_detected":true,"header":{},"footer_detected":true,"footer":{}},
 "regions":[
   {"name":"Hero","category":"hero","top":0.00,"bottom":0.18,"layout_hint":"full-bleed hero with icon strip"}
 ]
}

top/bottom are normalized 0..1 coordinates measured against the full image height.
Map 4–10 meaningful BODY regions in exact visual order. Do not split a single coherent section just because it has cards/items.
Do not include the global header/footer inside body regions when they are clearly global.

Header supports:
overlay,background_color,text_color,nav_color,height,padding_x,content_max_width,logo_tone,logo_height,nav_size,nav_gap,phone_enabled,phone_text,phone_color,cta_label,cta_url,cta_background,cta_color,cta_radius,menu.
Never replace the actual site logo.
Footer supports:
background_color,text_color,muted_color,logo_tone,tagline,cta_label,cta_url,columns,copyright.

Prioritize accurate vertical boundaries and section identity. Do not invent body content.
PROMPT;
    }

    private function sectionContract(): string
    {
        return <<<'PROMPT'
Return JSON only as {"section":{...}}.

IMAGE 1 is ONE cropped desktop website section. Reconstruct this crop as one editable Cosmic Custom Spark.
The crop is the visual truth. USER BRIEF supplies business meaning.

Supported section keys:
name,category,layout,alignment,media_position,density,accent_shape,eyebrow,heading,heading_accent_text,text,
primary_label,primary_url,secondary_label,secondary_url,image_url,image_query,items,review,form,runtime,visual_style.

Allowed layout:
editorial,split,showcase,bento,mosaic,rail,trust_strip,feature_row,media_grid,process_strip,cta_band.
Allowed media_position: none,left,right,top,background.
image_url must be empty. Use image_query for photography.
items max 8: {title,text,label,value,image_url,image_query,icon}.

visual_style is IMPORTANT. Infer the crop faithfully and use:
background_color,heading_color,body_color,accent_color,card_background,
heading_size,body_size,eyebrow_size,section_padding_x,section_padding_y,
content_gap,card_padding,card_radius,button_radius,section_min_height,
content_max_width,copy_width_percent,heading_line_height,body_line_height,
background_position,background_size,overlay_color,overlay_opacity,
overlay_gradient_enabled,overlay_gradient_angle,overlay_gradient_from,
overlay_gradient_to,overlay_gradient_from_stop,overlay_gradient_to_stop,
item_columns,item_gap,item_image_ratio,item_card_style,item_align,
copy_position_x,copy_position_y,media_width_percent,media_height_px.

Fidelity rules:
- Count visible cards/items and reproduce that count.
- Match whether items are horizontal, columns, rail, gallery, process strip or compact trust row.
- Match section density and whitespace; do NOT add huge empty gaps.
- Match image dominance and approximate image/copy proportions.
- If the crop is a dark/full-bleed image section, use background media and appropriate overlay.
- If it is a five-column product row, return item_columns=5 and five items.
- If it is a compact dark process band, do not turn it into a tall generic card section.
- If it is an asymmetric split, preserve the asymmetry.
- Use semantic icons only when icons are visibly part of the composition.
- Avoid generic white cards unless the crop visibly contains white cards.

Semantic icons:
shield-check,settings,truck,headset,check-circle,star,building,home,ruler,layers,sparkles,phone,mail,map-pin,clock,users.

Forms are declarative lead forms. Runtime is declarative only.
Never return raw HTML/CSS/JavaScript/browser APIs/network code/PHP/SQL.
PROMPT;
    }

}
