<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Models\ContentType;
use App\Models\SavedPageTemplate;
use App\Models\Website;
use App\AI\Clients\OpenAIClient;
use App\Cosmic\Pricing\ActionPricing;
use App\Services\CreditService;
use App\Services\ThemeColorResolver;
use App\Support\PageStyleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentWorkspaceController extends Controller
{
    public static function defaults(): array
    {
        return [
            [
                'name' => 'Blog', 'singular_name' => 'Blog post', 'slug' => 'blog', 'icon' => '✎',
                'description' => 'Articles, guides, announcements, and editorial updates.',
                'schema' => [
                    ['key' => 'author', 'label' => 'Author', 'type' => 'text'],
                    ['key' => 'reading_time', 'label' => 'Reading time', 'type' => 'text', 'placeholder' => '5 min read'],
                ],
            ],
            [
                'name' => 'Events', 'singular_name' => 'Event', 'slug' => 'events', 'icon' => '◫',
                'description' => 'Upcoming events, launches, workshops, and schedules.',
                'schema' => [
                    ['key' => 'start_date', 'label' => 'Start date', 'type' => 'date'],
                    ['key' => 'end_date', 'label' => 'End date', 'type' => 'date'],
                    ['key' => 'time', 'label' => 'Time', 'type' => 'text'],
                    ['key' => 'venue', 'label' => 'Venue', 'type' => 'text'],
                    ['key' => 'address', 'label' => 'Address', 'type' => 'text'],
                    ['key' => 'registration_url', 'label' => 'Registration URL', 'type' => 'url'],
                ],
            ],
            [
                'name' => 'Projects', 'singular_name' => 'Project', 'slug' => 'projects', 'icon' => '◇',
                'description' => 'Portfolio work, case studies, completed projects, and outcomes.',
                'schema' => [
                    ['key' => 'client', 'label' => 'Client', 'type' => 'text'],
                    ['key' => 'services', 'label' => 'Services', 'type' => 'text'],
                    ['key' => 'completion_date', 'label' => 'Completion date', 'type' => 'date'],
                    ['key' => 'project_url', 'label' => 'Project URL', 'type' => 'url'],
                ],
            ],
        ];
    }

    public static function ensureDefaults(Website $website): void
    {
        if ($website->contentTypes()->exists()) return;

        foreach (self::defaults() as $index => $definition) {
            $website->contentTypes()->create([
                ...$definition,
                'is_system' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    public static function payload(Website $website): array
    {
        self::ensureDefaults($website);

        $previews = app(\App\Services\PreviewDeploymentService::class);

        return [
            'ai_pricing' => [
                'generate_content_entry' => ActionPricing::GENERATE_CONTENT_ENTRY,
                'generate_content_fields' => ActionPricing::GENERATE_CONTENT_FIELDS,
                'template_ai_personalize' => ActionPricing::TEMPLATE_AI_PERSONALIZE,
            ],
            'types' => $website->contentTypes()
                ->withCount('entries')
                ->with([
                    'entries' => fn ($query) => $query->latest('updated_at')->limit(100),
                    'singleTemplate',
                    'archiveTemplate',
                    'templates' => fn ($query) => $query->where('status', SavedPageTemplate::STATUS_ACTIVE)->latest('updated_at'),
                ])
                ->get()
                ->map(fn (ContentType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'singular_name' => $type->singular_name,
                    'slug' => $type->slug,
                    'icon' => $type->icon,
                    'description' => $type->description,
                    'schema' => $type->schema ?: [],
                    'preset_key' => $type->preset_key,
                    'schema_source' => $type->schema_source ?: ($type->is_system ? 'preset' : 'custom'),
                    'schema_signature' => self::schemaSignature($type->schema ?: []),
                    'single_template_needs_refresh' => (bool) ($type->singleTemplate && $type->single_template_schema_signature && $type->single_template_schema_signature !== self::schemaSignature($type->schema ?: [])),
                    'archive_template_needs_refresh' => (bool) ($type->archiveTemplate && $type->archive_template_schema_signature && $type->archive_template_schema_signature !== self::schemaSignature($type->schema ?: [])),
                    'template_needs_refresh' => (bool) ((($type->singleTemplate && $type->single_template_schema_signature && $type->single_template_schema_signature !== self::schemaSignature($type->schema ?: []))) || (($type->archiveTemplate && $type->archive_template_schema_signature && $type->archive_template_schema_signature !== self::schemaSignature($type->schema ?: [])))),
                    'is_system' => $type->is_system,
                    'entries_count' => $type->entries_count,
                    'single_template_id' => $type->single_template_id,
                    'archive_template_id' => $type->archive_template_id,
                    'single_template' => self::templatePayload($type->singleTemplate),
                    'archive_template' => self::templatePayload($type->archiveTemplate),
                    'templates' => $type->templates->map(fn (SavedPageTemplate $template) => self::templatePayload($template))->values(),
                    'bindings' => self::bindingCatalog($type),
                    'installed_page' => ($page = $website->pages()->where('slug', $type->slug)->where('page_type', 'standard')->first()) ? [
                        'id' => $page->id,
                        'title' => $page->title,
                        'slug' => $page->slug,
                        'builder_url' => route('pages.builder', $page),
                        'preview_url' => (string) $previews->urlForPage($website, $page),
                    ] : null,
                    'archive_url' => (string) $previews->url($website, $type->slug),
                    'entries' => $type->entries->map(fn (ContentEntry $entry) => array_merge(self::entryPayload($entry), ['url' => (string) $previews->url($website, $type->slug.'/'.$entry->slug)]))->values(),
                ])->values(),
        ];
    }


    public function install(Request $request, Website $website)
    {
        $this->authorize('update', $website);
        $validated = $request->validate([
            'with_demo' => ['nullable', 'boolean'],
            'add_navigation' => ['nullable', 'boolean'],
        ]);
        $result = app(\App\Services\ContentInstallerService::class)->install(
            $website,
            (bool)($validated['with_demo'] ?? false),
            (bool)($validated['add_navigation'] ?? true),
        );
        return response()->json(['result' => $result, 'workspace' => self::payload($website->fresh())]);
    }

    public function installTypePage(Request $request, Website $website, ContentType $contentType)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);
        $validated = $request->validate([
            'add_navigation' => ['nullable', 'boolean'],
        ]);

        $result = app(\App\Services\ContentInstallerService::class)->installTypePage(
            $website,
            $contentType,
            (bool) ($validated['add_navigation'] ?? true),
        );

        return response()->json([
            'result' => $result,
            'workspace' => self::payload($website->fresh()),
        ]);
    }

    public function installTypeDemo(Request $request, Website $website, ContentType $contentType)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);
        $validated = $request->validate([
            'add_navigation' => ['nullable', 'boolean'],
        ]);

        $result = app(\App\Services\ContentInstallerService::class)->installTypeDemo(
            $website,
            $contentType,
            (bool) ($validated['add_navigation'] ?? true),
        );

        return response()->json([
            'result' => $result,
            'workspace' => self::payload($website->fresh()),
        ]);
    }

    public function storeType(Request $request, Website $website)
    {
        $this->authorize('update', $website);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'singular_name' => ['nullable', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:40'],
            'schema' => ['nullable', 'array', 'max:40'],
            'schema.*' => ['array'],
            'preset_key' => ['nullable', 'string', 'max:60'],
        ]);
        $validated['schema'] = $this->normalizeSchemaFields($validated['schema'] ?? [], $website);
        $schemaSignature = self::schemaSignature($validated['schema']);

        $base = Str::slug($validated['slug'] ?: $validated['name']) ?: 'content';
        $slug = $this->uniqueTypeSlug($website, $base);
        $type = $website->contentTypes()->create([
            ...$validated,
            'singular_name' => $validated['singular_name'] ?: Str::singular($validated['name']),
            'slug' => $slug,
            'sort_order' => ((int) $website->contentTypes()->max('sort_order')) + 10,
            'preset_key' => $validated['preset_key'] ?? null,
            'schema_source' => !empty($validated['preset_key']) ? 'preset' : 'custom',
            'schema_signature' => $schemaSignature,
        ]);

        return response()->json(['type' => $type, 'workspace' => self::payload($website->fresh())], 201);
    }

    public function updateType(Request $request, Website $website, ContentType $contentType)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'singular_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:40'],
            'schema' => ['nullable', 'array', 'max:40'],
            'schema.*' => ['array'],
        ]);
        $beforeSignature = self::schemaSignature($contentType->schema ?: []);
        $validated['schema'] = $this->normalizeSchemaFields($validated['schema'] ?? [], $website);
        $afterSignature = self::schemaSignature($validated['schema']);
        $schemaChanged = $beforeSignature !== $afterSignature;

        $tracking = ['schema_signature' => $afterSignature];
        if ($schemaChanged) {
            $tracking['schema_source'] = 'customized';
            if ($contentType->singleTemplate && !$contentType->single_template_schema_signature) {
                $tracking['single_template_schema_signature'] = $beforeSignature;
            }
            if ($contentType->archiveTemplate && !$contentType->archive_template_schema_signature) {
                $tracking['archive_template_schema_signature'] = $beforeSignature;
            }
        }

        $contentType->update(array_merge($validated, $tracking));
        return response()->json(['type' => $contentType->fresh(), 'workspace' => self::payload($website->fresh())]);
    }

    public function destroyType(Website $website, ContentType $contentType)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);
        abort_if($contentType->is_system, 422, 'Default content types can be renamed but not deleted.');
        $contentType->delete();
        return response()->json(['status' => 'deleted']);
    }



    /** Generate a reviewable custom-field schema with Cosmic AI. Nothing is saved until the user saves the content type. */
    public function generateFields(Request $request, Website $website, OpenAIClient $openAI, CreditService $credits)
    {
        $this->authorize('update', $website);
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:100'],
            'singular_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'current_schema' => ['nullable', 'array', 'max:40'],
        ]);

        $cost = ActionPricing::GENERATE_CONTENT_FIELDS;
        $reference = 'ai-content-fields-'.Str::uuid();
        $credits->consume(
            $request->user(),
            $cost,
            'Generate content fields with Cosmic AI',
            $website,
            $reference,
            ['content_type_name' => $validated['name'] ?? null, 'category' => 'ai'],
        );

        try {
            $relatedTypes = $website->contentTypes()->get(['id','name','singular_name','slug'])->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'singular_name' => $type->singular_name,
                'slug' => $type->slug,
            ])->values()->all();

            $system = <<<'PROMPT'
You are Cosmic AI, the schema designer inside Cosmic CMS. Return VALID JSON only with no markdown fences or commentary.

Return this exact shape:
{"name":"Plural name","singular_name":"Singular name","slug":"url-slug","description":"Concise public description","fields":[...]}

Each field may use:
- key: snake_case unique key
- label: concise human label
- type: text, textarea, richtext, image, gallery, select, date, datetime, url, number, boolean, relation, group, repeater
- placeholder: optional short helper text
- options: required for select, 2-12 concise options
- related_type_id: for relation only, and only from the supplied related content types
- multiple: boolean for relation only
- fields: child field array for group/repeater; nested children may not be group/repeater
- max_rows: 1-20 for repeater

Rules:
- Infer and return a polished plural name, singular name, URL-safe slug, and concise description from the user's request.
- If the user already supplied a meaningful content type name, preserve its intent while improving obvious placeholders.
- Design a practical editorial/business schema for the user's requested content type.
- Return 3-15 top-level fields unless the request clearly needs fewer/more; never exceed 40.
- Do not duplicate core entry fields already provided by Cosmic CMS: title, slug, excerpt, content, category, tags, featured image, gallery, SEO title/description, featured flag.
- Prefer useful structured fields over redundant free-text fields.
- Use richtext only when genuinely useful; use image/gallery for media; select for controlled vocabularies.
- Only use relation if a supplied related type is clearly useful.
- Keep keys stable, descriptive, and lowercase snake_case.
- Current schema is context only. Improve or replace it according to the user's prompt; do not blindly append duplicates.
PROMPT;

            $user = implode("\n\n", [
                'Website: '.($website->name ?: 'Website'),
                'Industry: '.($website->industry ?: 'General'),
                'Content type: '.($validated['name'] ?? 'Custom content').' (singular: '.($validated['singular_name'] ?? $validated['name'] ?? 'Item').')',
                'Description: '.($validated['description'] ?? 'Not provided'),
                'Current schema: '.json_encode($validated['current_schema'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'Related content types: '.json_encode($relatedTypes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'User request: '.trim($validated['prompt']),
            ]);

            $raw = trim($openAI->chat($system, $user));
            $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw);
            $generated = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(is_array($generated['fields'] ?? null), 422, 'Cosmic AI returned an invalid field schema. Please try again.');

            $schema = $this->normalizeSchemaFields($generated['fields'], $website);
            abort_if(count($schema) === 0, 422, 'Cosmic AI returned no usable fields. Try a more specific prompt.');

            return response()->json([
                'name' => trim((string) ($generated['name'] ?? $validated['name'] ?? 'Custom content')),
                'singular_name' => trim((string) ($generated['singular_name'] ?? $validated['singular_name'] ?? 'Item')),
                'slug' => Str::slug((string) ($generated['slug'] ?? $generated['name'] ?? $validated['name'] ?? 'content')),
                'description' => Str::limit(trim((string) ($generated['description'] ?? $validated['description'] ?? '')), 500, ''),
                'schema' => $schema,
                'credits_spent' => $cost,
                'first_design_free' => false,
                'credit_balance' => $credits->balance($request->user()),
            ]);
        } catch (\Throwable $exception) {
            try {
                if ($cost > 0) $credits->refund(
                    $request->user(),
                    $cost,
                    'Refund for failed Cosmic AI field generation',
                    $website,
                    $reference.'-refund',
                    ['content_type_name' => $validated['name'] ?? null, 'category' => 'refund'],
                );
            } catch (\Throwable) {
                // Preserve the original generation failure.
            }

            report($exception);
            $message = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $exception->getStatusCode() === 422
                ? $exception->getMessage()
                : 'Cosmic AI could not generate fields. Your credits were refunded.';

            return response()->json(['message' => $message], $message === 'Cosmic AI could not generate fields. Your credits were refunded.' ? 503 : 422);
        }
    }

    public function generateTemplate(Request $request, Website $website, ContentType $contentType, OpenAIClient $openAI, CreditService $credits)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);

        $validated = $request->validate([
            'template_type' => ['required', Rule::in([SavedPageTemplate::TYPE_SINGLE, SavedPageTemplate::TYPE_ARCHIVE])],
            'prompt' => ['nullable', 'string', 'max:2000'],
            'first_design' => ['nullable', 'boolean'],
        ]);

        $mode = $validated['template_type'];
        $isFirstDesign = (bool)($validated['first_design'] ?? false)
            && !$contentType->is_system
            && !$contentType->templates()->where('template_type', $mode)->exists();
        $cost = $isFirstDesign ? 0 : ActionPricing::TEMPLATE_AI_PERSONALIZE;
        $reference = 'ai-content-template-'.Str::uuid();
        if ($cost > 0) {
            $credits->consume(
                $request->user(),
                $cost,
                'Design dynamic content template with Cosmic AI',
                $website,
                $reference,
                ['content_type_id' => $contentType->id, 'template_type' => $mode, 'category' => 'ai'],
            );
        }

        try {
            $bindings = self::bindingCatalog($contentType);
            $bindingLines = collect($bindings)->map(fn ($binding) => '- '.$binding['key'].' ('.$binding['type'].'): '.$binding['label'])->implode("\n");
            $schemaJson = json_encode($contentType->schema ?: [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $themeJson = json_encode($website->theme_settings ?: [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $themeKey = (string) data_get($website->theme_settings, 'primary', 'midnight');
            $resolvedPalette = app(ThemeColorResolver::class)->palette($themeKey);
            $paletteJson = json_encode($resolvedPalette, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $dynamicPageContext = $this->dynamicTemplatePageContext($website, $contentType);
            $requestPrompt = trim((string) ($validated['prompt'] ?? ''));

            $system = <<<'PROMPT'
You are Cosmic AI, the dynamic layout designer inside Cosmic CMS. Return VALID JSON only with no markdown fences.
Required keys: markup, design_summary, used_bindings.

Your job is DESIGN ONLY. Do not generate real article/product/property/event content. Build a premium, responsive Tailwind HTML template that uses only the supplied Cosmic binding tokens for dynamic data.

Hard rules:
- markup must be a single HTML fragment, never a full html/head/body document.
- Tailwind utility classes are allowed and preferred. Do not output <style>, <script>, JavaScript, onclick/onerror handlers, iframes, forms, SVG, or external embeds.
- Never invent a binding. Use only the supplied binding keys, wrapped exactly as {{ key }}.
- Preserve rich text by placing {{ content }} in a suitable article/prose container; never put it in an HTML attribute.
- Image src values may use {{ featured_image_url }}. Alt text should use {{ title }} where useful.
- For gallery fields, do not invent looping syntax. Treat {{ gallery }} as an optional display value until the renderer adds structured gallery loops.
- Make the layout feel production-ready: strong hierarchy, generous spacing, responsive grid decisions, polished cards/meta, accessible contrast, and Manrope-friendly typography.
- The active website theme is authoritative. Never invent a random brand palette or switch the design to unrelated violet/blue/emerald accents.
- The Builder page-style context and global header-overlay status supplied below are authoritative. A dynamic Single/Archive template is part of that website, not an independent microsite.
- If header overlay is DISABLED, the mini hero must begin as a visually separate section below the solid global header. Do not reserve transparent-header space, add navigation fades, or assume white overlay navigation.
- If header overlay is ENABLED, design the mini hero so the global header can sit over its top area without a duplicate blank band. The runtime adds the exact header-height safety spacing. Do not add a second large top spacer for the header yourself. Respect the supplied first semantic surface (white/surface/primary) so header contrast can be resolved consistently.
- For branded accents/surfaces, use inline CSS custom properties with fallbacks: var(--p, PRIMARY_HEX), var(--a, ACCENT_HEX), var(--s, SURFACE_HEX), var(--t, TEXT_HEX), var(--m, #64748B), var(--b, #E2E8F0), var(--bg, #FFFFFF). The resolved active palette is supplied below.
- Keep neutral Tailwind slate/white utilities for typography and structure where appropriate, but primary CTA, eyebrow, highlight, active border, and branded surface treatments must follow the supplied active theme.
- Every SINGLE design must visibly contain three semantic regions using data attributes: data-cosmic-mini-hero="true", data-cosmic-post-content="true", and at least one data-cosmic-post-spark="true". The mini hero should include title plus useful meta/excerpt; Post Content must contain {{ content }} and naturally place featured media/gallery where useful; Post Sparks are supporting branded sections/cards using existing bindings only.
- Every ARCHIVE design must visibly contain data-cosmic-mini-hero="true", data-cosmic-catalog="true", and at least one data-cosmic-post-spark="true". The catalog region must contain the single required entries loop.
- Avoid excessive gradients, glassmorphism, and decorative clutter.
- design_summary: one concise sentence describing the design.
- used_bindings: JSON array of binding keys actually used in markup.
- SINGLE mode: design one premium individual entry page.
- ARCHIVE mode: include exactly one {{#entries}} ... {{/entries}} wrapper around the repeated entry card/list item. Fields inside that wrapper refer to each entry. Use {{ url }} for the detail link.
PROMPT;

            $userContext = implode("\n\n", array_filter([
                'Template mode: '.strtoupper($mode),
                'Website name: '.($website->name ?: 'Website'),
                'Industry: '.($website->industry ?: 'General'),
                'Content type: '.$contentType->name.' (singular: '.$contentType->singular_name.')',
                'Content type description: '.($contentType->description ?: 'Not provided'),
                'Custom schema JSON: '.$schemaJson,
                'Website theme settings JSON: '.$themeJson,
                'Resolved active website palette JSON: '.$paletteJson,
                'Builder page style: '.$dynamicPageContext['page_style'].' (direction: '.$dynamicPageContext['page_style_direction'].')',
                'Builder first semantic surface: '.$dynamicPageContext['first_surface'],
                'Global header overlay: '.($dynamicPageContext['header_overlay_enabled'] ? 'ENABLED' : 'DISABLED'),
                "Allowed bindings:
".$bindingLines.($mode === SavedPageTemplate::TYPE_ARCHIVE ? "\n- url (url): Entry detail URL" : ''),
                $requestPrompt !== '' ? 'User art direction: '.$requestPrompt : 'User art direction: Create a premium editorial layout appropriate for this content type.',
            ]));

            $raw = trim($openAI->chat($system, $userContext));
            $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw);
            $generated = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $markup = $this->sanitizeGeneratedTemplate((string) ($generated['markup'] ?? ''));
            abort_if(trim($markup) === '', 422, 'Cosmic AI returned an empty template. Please try a more specific prompt.');

            $this->validateTemplateBindings($markup, $contentType, $mode);
            if ($mode === SavedPageTemplate::TYPE_ARCHIVE) {
                abort_unless(substr_count($markup, '{{#entries}}') === 1 && substr_count($markup, '{{/entries}}') === 1, 422, 'Archive design must contain exactly one entries loop.');
            }

            preg_match_all('/{{\s*([^{}#\/][^{}]*)\s*}}/', $markup, $matches);
            $usedBindings = collect($matches[1] ?? [])->map(fn ($key) => trim($key))->filter()->unique()->values()->all();

            return response()->json([
                'markup' => $markup,
                'design_summary' => Str::limit((string) ($generated['design_summary'] ?? 'Premium dynamic layout generated by Cosmic AI.'), 300, ''),
                'used_bindings' => $usedBindings,
                'credits_spent' => $cost,
                'first_design_free' => $isFirstDesign,
                'credit_balance' => $credits->balance($request->user()),
            ]);
        } catch (\Throwable $exception) {
            try {
                if ($cost > 0) $credits->refund(
                    $request->user(),
                    $cost,
                    'Refund for failed Cosmic AI dynamic template design',
                    $website,
                    $reference.'-refund',
                    ['content_type_id' => $contentType->id, 'category' => 'refund'],
                );
            } catch (\Throwable) {
                // Keep the original generation error.
            }

            report($exception);
            $fallbackMessage = $cost > 0
                ? 'Cosmic AI could not design this template. Your credits were refunded.'
                : 'Cosmic AI could not create the free first design. No credits were charged.';
            $message = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $exception->getStatusCode() === 422
                ? $exception->getMessage()
                : $fallbackMessage;

            return response()->json(['message' => $message], $message === $fallbackMessage ? 503 : 422);
        }
    }

    private function dynamicTemplatePageContext(Website $website, ContentType $contentType): array
    {
        $contextPage = $website->pages()->where('page_type', 'standard')->where('slug', $contentType->slug)->first();

        if (! $contextPage) {
            $contextPage = $website->pages()->where('page_type', 'standard')
                ->where(function ($query) {
                    $query->where('slug', 'home')->orWhere('slug', '');
                })->first();
        }

        if (! $contextPage) {
            $contextPage = $website->pages()->where('page_type', 'standard')->orderBy('id')->first();
        }

        $pageStyle = trim((string) ($website->page_style ?: $website->published_page_style ?: $contextPage?->page_style ?: $contextPage?->published_page_style ?: 'auto'));
        $style = PageStyleRegistry::all()[$pageStyle] ?? null;
        $pattern = PageStyleRegistry::pattern($pageStyle);
        $firstSurface = strtolower((string) ($pattern[0] ?? 'primary'));
        if (! in_array($firstSurface, ['white', 'surface', 'primary'], true)) $firstSurface = 'primary';

        $header = is_array($website->global_header) ? $website->global_header : $website->published_global_header;

        return [
            'page_style' => $pageStyle !== '' ? $pageStyle : 'auto',
            'page_style_direction' => (string) ($style['direction'] ?? 'clean'),
            'first_surface' => $firstSurface,
            'header_overlay_enabled' => is_array($header) && (bool) ($header['overlay_header_on_banner'] ?? false),
        ];
    }

    private function sanitizeGeneratedTemplate(string $markup): string
    {
        $markup = trim($markup);
        $markup = preg_replace('#<(script|style|iframe|object|embed|form|svg)\b[^>]*>.*?</\1>#is', '', $markup);
        $markup = preg_replace('#<(script|style|iframe|object|embed|form|svg)\b[^>]*/?>#is', '', $markup);
        $markup = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $markup);
        $markup = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $markup);
        return trim((string) $markup);
    }

    public function storeTemplate(Request $request, Website $website, ContentType $contentType)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);

        $validated = $request->validate([
            'template_type' => ['required', Rule::in([SavedPageTemplate::TYPE_SINGLE, SavedPageTemplate::TYPE_ARCHIVE])],
            'name' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:500'],
            'markup' => ['nullable', 'string', 'max:250000'],
            'mini_banner_image_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $bindings = self::bindingCatalog($contentType);
        $markup = trim((string) ($validated['markup'] ?? ''));
        if ($markup === '') {
            $markup = $validated['template_type'] === SavedPageTemplate::TYPE_SINGLE
                ? self::starterSingleMarkup($contentType)
                : self::starterArchiveMarkup($contentType);
        }
        $this->validateTemplateBindings($markup, $contentType, $validated['template_type']);

        $baseSlug = Str::slug($validated['name']) ?: $validated['template_type'].'-template';
        $slug = $baseSlug;
        $suffix = 2;
        while ($request->user()->savedPageTemplates()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        $template = $request->user()->savedPageTemplates()->create([
            'website_id' => $website->id,
            'content_type_id' => $contentType->id,
            'name' => trim($validated['name']),
            'slug' => $slug,
            'description' => $validated['description'] ?? ucfirst($validated['template_type']).' template for '.$contentType->name.'.',
            'source' => SavedPageTemplate::SOURCE_SAVED,
            'template_type' => $validated['template_type'],
            'status' => SavedPageTemplate::STATUS_ACTIVE,
            'blocks' => [],
            'markup' => $markup,
            'metadata' => [
                'saved_from' => 'content_workspace',
                'content_type_id' => $contentType->id,
                'content_type_slug' => $contentType->slug,
                'schema_snapshot' => $contentType->schema ?: [],
                'bindings' => $bindings,
                'binding_version' => 1,
                'mini_banner_image_url' => trim((string) ($validated['mini_banner_image_url'] ?? '')),
            ],
        ]);

        $column = $validated['template_type'] === SavedPageTemplate::TYPE_SINGLE ? 'single_template_id' : 'archive_template_id';
        $signatureColumn = $validated['template_type'] === SavedPageTemplate::TYPE_SINGLE ? 'single_template_schema_signature' : 'archive_template_schema_signature';
        $contentType->update([
            $column => $template->id,
            'schema_signature' => self::schemaSignature($contentType->schema ?: []),
            $signatureColumn => self::schemaSignature($contentType->schema ?: []),
        ]);

        return response()->json([
            'template' => self::templatePayload($template),
            'workspace' => self::payload($website->fresh()),
        ], 201);
    }

    public function updateTemplate(Request $request, Website $website, ContentType $contentType, SavedPageTemplate $template)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);
        $this->guardTemplate($request, $website, $contentType, $template);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:500'],
            'markup' => ['required', 'string', 'max:250000'],
            'mini_banner_image_url' => ['nullable', 'string', 'max:2048'],
        ]);
        $this->validateTemplateBindings($validated['markup'], $contentType, $template->template_type);

        $template->update([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? $template->description,
            'markup' => $validated['markup'],
            'metadata' => array_merge($template->metadata ?: [], [
                'schema_snapshot' => $contentType->schema ?: [],
                'bindings' => self::bindingCatalog($contentType),
                'binding_version' => 1,
                'mini_banner_image_url' => trim((string) ($validated['mini_banner_image_url'] ?? data_get($template->metadata, 'mini_banner_image_url', ''))),
            ]),
        ]);

        $signatureColumn = $template->template_type === SavedPageTemplate::TYPE_SINGLE ? 'single_template_schema_signature' : 'archive_template_schema_signature';
        $contentType->update([
            'schema_signature' => self::schemaSignature($contentType->schema ?: []),
            $signatureColumn => self::schemaSignature($contentType->schema ?: []),
        ]);

        return response()->json([
            'template' => self::templatePayload($template->fresh()),
            'workspace' => self::payload($website->fresh()),
        ]);
    }

    public function assignTemplate(Request $request, Website $website, ContentType $contentType)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);
        $validated = $request->validate([
            'template_type' => ['required', Rule::in([SavedPageTemplate::TYPE_SINGLE, SavedPageTemplate::TYPE_ARCHIVE])],
            'template_id' => ['nullable', 'integer', 'exists:saved_page_templates,id'],
        ]);

        $column = $validated['template_type'] === SavedPageTemplate::TYPE_SINGLE ? 'single_template_id' : 'archive_template_id';
        if (!empty($validated['template_id'])) {
            $template = $request->user()->savedPageTemplates()->whereKey($validated['template_id'])->firstOrFail();
            abort_unless($template->website_id === $website->id && $template->content_type_id === $contentType->id, 422, 'This template belongs to a different content type.');
            abort_unless($template->template_type === $validated['template_type'], 422, 'Template type does not match the requested slot.');
            abort_unless($template->status === SavedPageTemplate::STATUS_ACTIVE, 422, 'This template is not active.');
        }

        $signatureColumn = $validated['template_type'] === SavedPageTemplate::TYPE_SINGLE ? 'single_template_schema_signature' : 'archive_template_schema_signature';
        $contentType->update([
            $column => $validated['template_id'] ?? null,
            $signatureColumn => !empty($validated['template_id']) ? self::schemaSignature($contentType->schema ?: []) : null,
        ]);
        return response()->json(['workspace' => self::payload($website->fresh())]);
    }

    /** Upload media used by Posts / Updates core fields and custom image/gallery fields. */
    public function uploadMedia(Request $request, Website $website)
    {
        $this->authorize('update', $website);

        $data = $request->validate([
            'image' => [
                'required', 'file', 'max:8192',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/avif,image/heic,image/heif',
            ],
            'kind' => ['nullable', Rule::in(['featured', 'gallery', 'custom'])],
        ]);

        $file = $data['image'];
        abort_unless($file->isValid(), 422, 'The uploaded image could not be read. Please choose the file again.');

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        abort_unless(in_array($extension, ['jpg','jpeg','png','gif','webp','avif','heic','heif'], true), 422, 'Use JPG, PNG, WebP, GIF, AVIF, HEIC or HEIF images.');

        $kind = $data['kind'] ?? 'custom';
        $path = $file->storeAs("websites/{$website->id}/content", $kind.'-'.Str::uuid().'.'.$extension, 'public');
        $filename = basename($path);

        return response()->json([
            'url' => route('content.media.show', ['website' => $website->id, 'filename' => $filename], false),
            'path' => $path,
            'kind' => $kind,
            'mime_type' => $file->getMimeType(),
        ]);
    }

    /** Publicly serve uploaded Posts / Updates media without requiring a public/storage symlink. */
    public function showMedia(Website $website, string $filename)
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9._-]+$/', $filename), 404);
        $path = "websites/{$website->id}/content/{$filename}";
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, $filename, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * Generate structured entry data with Cosmic AI without saving it yet.
     * This is intentionally separate from generateTemplate(): data generation here,
     * design/layout generation there.
     */
    public function generateEntryContent(Request $request, Website $website, ContentType $contentType, OpenAIClient $openAI, CreditService $credits)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:3000'],
            'current' => ['nullable', 'array'],
        ]);

        $cost = ActionPricing::GENERATE_CONTENT_ENTRY;
        $reference = 'ai-content-entry-'.Str::uuid();
        $credits->consume(
            $request->user(),
            $cost,
            'Generate structured content with Cosmic AI',
            $website,
            $reference,
            ['content_type_id' => $contentType->id, 'category' => 'ai'],
        );

        try {
            $schemaForAi = $this->schemaForContentGeneration($contentType->schema ?: [], $website);
            $current = is_array($validated['current'] ?? null) ? $validated['current'] : [];
            $currentContext = collect($current)
                ->only(['title','slug','excerpt','content','category','tags','seo_title','seo_description','is_featured','custom_fields'])
                ->all();

            $system = <<<'PROMPT'
You are Cosmic AI, the structured content writer inside Cosmic CMS. Return VALID JSON only, with no markdown fences and no commentary.

Your job is CONTENT/DATA ONLY. Do not design a page or return HTML layout/Tailwind classes. Fill the entry data according to the supplied content type schema and the user's prompt.

Return this exact top-level shape:
{
  "title": "...",
  "slug": "...",
  "excerpt": "...",
  "content": "...",
  "category": "...",
  "tags": ["..."],
  "seo_title": "...",
  "seo_description": "...",
  "is_featured": false,
  "featured_image_query": "...",
  "gallery_queries": ["..."],
  "custom_fields": { ... }
}

Rules:
- title: concise, useful, natural, max about 90 characters.
- slug: lowercase URL slug using letters/numbers/hyphens only.
- excerpt: polished summary, usually 1-3 sentences.
- content: production-ready rich article/body HTML using only safe semantic tags such as p, h2, h3, ul, ol, li, strong, em, blockquote, a. Never output scripts, styles, iframes, forms, SVG, event handlers, or full html/head/body markup.
- category: one concise category relevant to the content.
- tags: 3-8 concise tags, no hashtags.
- seo_title and seo_description: search-friendly and human-readable; no keyword stuffing.
- featured_image_query: a short photographic search query describing the ideal hero/featured image. Do not return an image URL.
- gallery_queries: 0-6 distinct photographic search queries when a gallery is useful. Do not return image URLs.
- custom_fields: include every schema field key. Respect field types exactly.
- select fields: choose only from the supplied options. If none fit, use an empty string.
- boolean fields: true/false only.
- number fields: number or null.
- date fields: YYYY-MM-DD when populated.
- datetime fields: YYYY-MM-DDTHH:MM when populated.
- image fields: return {"__image_query":"short photographic search query"} rather than a URL.
- gallery fields: return an array of {"__image_query":"..."} objects, normally 1-6 items.
- relation fields: use only supplied valid entry IDs. For multiple relations return an array of IDs; otherwise one ID or null.
- group fields: return an object matching its child schema.
- repeater fields: return an array of objects matching its child schema, staying within max_rows.
- richtext custom fields may contain safe semantic HTML like the main content field.
- Never invent schema keys that were not supplied.
- Use current draft values only as helpful context. The user's new prompt is authoritative.
PROMPT;

            $user = implode("\n\n", [
                'Website: '.($website->name ?: 'Website'),
                'Industry: '.($website->industry ?: 'General'),
                'Content type: '.$contentType->name.' (singular: '.$contentType->singular_name.')',
                'Content type description: '.($contentType->description ?: 'Not provided'),
                'Schema with constraints/options/relations: '.json_encode($schemaForAi, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'Current draft context: '.json_encode($currentContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'User prompt: '.trim($validated['prompt']),
            ]);

            $raw = trim($openAI->chat($system, $user));
            $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw);
            $generated = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(is_array($generated), 422, 'Cosmic AI returned invalid entry data. Please try again.');

            $resolved = $this->resolveGeneratedEntryImages($generated, $website, $contentType);
            $draft = $this->normalizeGeneratedEntryDraft($resolved, $contentType, $website);

            return response()->json([
                'entry' => $draft,
                'credits_spent' => $cost,
                'first_design_free' => false,
                'credit_balance' => $credits->balance($request->user()),
            ]);
        } catch (\Throwable $exception) {
            try {
                if ($cost > 0) $credits->refund(
                    $request->user(),
                    $cost,
                    'Refund for failed Cosmic AI content generation',
                    $website,
                    $reference.'-refund',
                    ['content_type_id' => $contentType->id, 'category' => 'refund'],
                );
            } catch (\Throwable) {
                // Preserve the original generation failure.
            }

            report($exception);
            $message = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $exception->getStatusCode() === 422
                ? $exception->getMessage()
                : 'Cosmic AI could not generate this content. Your credits were refunded.';

            return response()->json(['message' => $message], $message === 'Cosmic AI could not generate this content. Your credits were refunded.' ? 503 : 422);
        }
    }

    private function schemaForContentGeneration(array $schema, Website $website): array
    {
        return collect($schema)->filter(fn ($field) => is_array($field) && !empty($field['key']))->map(function ($field) use ($website) {
            $item = [
                'key' => (string) $field['key'],
                'label' => (string) ($field['label'] ?? $field['key']),
                'type' => (string) ($field['type'] ?? 'text'),
                'placeholder' => (string) ($field['placeholder'] ?? ''),
            ];

            if (($field['type'] ?? '') === 'select') {
                $item['options'] = array_values(array_slice(is_array($field['options'] ?? null) ? $field['options'] : [], 0, 50));
            }
            if (($field['type'] ?? '') === 'relation') {
                $targetId = (int) ($field['related_type_id'] ?? 0);
                $target = $targetId ? $website->contentTypes()->whereKey($targetId)->first() : null;
                $item['multiple'] = (bool) ($field['multiple'] ?? false);
                $item['allowed_entries'] = $target
                    ? $target->entries()->orderBy('title')->limit(100)->get(['id','title'])->map(fn ($entry) => ['id' => $entry->id, 'title' => $entry->title])->values()->all()
                    : [];
            }
            if (in_array(($field['type'] ?? ''), ['group','repeater'], true)) {
                $item['fields'] = $this->schemaForContentGeneration($field['fields'] ?? [], $website);
                if (($field['type'] ?? '') === 'repeater') $item['max_rows'] = max(1, min(50, (int) ($field['max_rows'] ?? 20)));
            }

            return $item;
        })->values()->all();
    }

    private function resolveGeneratedEntryImages(array $generated, Website $website, ContentType $contentType): array
    {
        $slots = [];
        $push = function (string $path, string $query, string $role = 'gallery') use (&$slots) {
            $query = trim($query);
            if ($query === '' || count($slots) >= 10) return;
            $slots[] = ['path' => $path, 'query' => $query, 'role' => $role, 'block_type' => 'content_entry'];
        };

        $push('featured_image_url', (string) ($generated['featured_image_query'] ?? ''), 'hero');
        // Custom image/gallery fields are schema-owned, so prioritize them before the optional core gallery.
        $this->collectGeneratedCustomImageQueries($generated['custom_fields'] ?? [], $contentType->schema ?: [], 'custom_fields', $push);
        foreach (array_slice(is_array($generated['gallery_queries'] ?? null) ? $generated['gallery_queries'] : [], 0, 6) as $index => $query) {
            if (is_string($query)) $push('gallery.'.$index, $query, 'gallery');
        }

        if ($slots === []) return $generated;

        $visualIntent = [
            'business_type' => trim(implode(' ', array_filter([$website->industry, $contentType->name, $generated['category'] ?? null]))),
            'visual_style' => 'premium editorial photography',
            'image_keywords' => array_values(array_filter([(string)($generated['title'] ?? ''), (string)($generated['category'] ?? '')])),
        ];
        $images = app(\App\Services\TrialRemoteImageService::class)->resolveForQueries($visualIntent, $slots, 'RegisteredRemoteImages');
        $byPath = collect($images)->keyBy('path');

        if ($byPath->has('featured_image_url')) {
            $generated['featured_image_url'] = $byPath->get('featured_image_url')['url'] ?? '';
        }

        // The featured image is a core part of AI entry generation. If a very narrow
        // slot query returns no Unsplash result, make one broader title/category lookup
        // before leaving the field blank. This keeps Blog/Events/Projects/custom types
        // useful while still using the same registered Unsplash provider pipeline.
        if (blank($generated['featured_image_url'] ?? null)) {
            $fallbackIntent = [
                'business_type' => trim(implode(' ', array_filter([
                    $website->industry,
                    $contentType->name,
                    $generated['category'] ?? null,
                ]))),
                'visual_style' => 'premium editorial photography',
                'image_keywords' => array_values(array_filter([
                    (string) ($generated['title'] ?? ''),
                    (string) ($generated['category'] ?? ''),
                    (string) ($generated['featured_image_query'] ?? ''),
                ])),
            ];
            $fallback = app(\App\Services\TrialRemoteImageService::class)->resolveForRegistered($fallbackIntent, 1);
            if (!empty($fallback[0]['url'])) {
                $generated['featured_image_url'] = $fallback[0]['url'];
            }
        }

        $gallery = [];
        foreach ($byPath as $path => $image) {
            if (str_starts_with((string)$path, 'gallery.')) {
                $gallery[] = ['url' => $image['url'] ?? '', 'alt' => (string)($generated['title'] ?? '')];
            }
        }
        if ($gallery !== []) $generated['gallery'] = $gallery;

        $custom = is_array($generated['custom_fields'] ?? null) ? $generated['custom_fields'] : [];
        $this->applyGeneratedCustomImages($custom, $contentType->schema ?: [], 'custom_fields', $byPath, (string)($generated['title'] ?? ''));
        $generated['custom_fields'] = $custom;
        return $generated;
    }

    private function collectGeneratedCustomImageQueries(array $values, array $schema, string $prefix, callable $push): void
    {
        foreach ($schema as $field) {
            if (!is_array($field) || empty($field['key'])) continue;
            $key = (string)$field['key']; $type = (string)($field['type'] ?? 'text'); $value = $values[$key] ?? null; $path = $prefix.'.'.$key;
            if ($type === 'image' && is_array($value) && !empty($value['__image_query'])) $push($path, (string)$value['__image_query'], 'gallery');
            elseif ($type === 'gallery' && is_array($value)) {
                foreach (array_slice($value,0,6) as $i=>$item) if (is_array($item) && !empty($item['__image_query'])) $push($path.'.'.$i, (string)$item['__image_query'], 'gallery');
            } elseif ($type === 'group' && is_array($value)) $this->collectGeneratedCustomImageQueries($value, $field['fields'] ?? [], $path, $push);
            elseif ($type === 'repeater' && is_array($value)) foreach ($value as $i=>$row) if (is_array($row)) $this->collectGeneratedCustomImageQueries($row, $field['fields'] ?? [], $path.'.'.$i, $push);
        }
    }

    private function applyGeneratedCustomImages(array &$values, array $schema, string $prefix, $byPath, string $alt): void
    {
        foreach ($schema as $field) {
            if (!is_array($field) || empty($field['key'])) continue;
            $key=(string)$field['key']; $type=(string)($field['type'] ?? 'text'); $path=$prefix.'.'.$key;
            if ($type === 'image') {
                $values[$key] = $byPath->get($path)['url'] ?? '';
            } elseif ($type === 'gallery') {
                $items=[]; for($i=0;$i<6;$i++){ $img=$byPath->get($path.'.'.$i); if($img) $items[]=['url'=>$img['url'] ?? '','alt'=>$alt]; } $values[$key]=$items;
            } elseif ($type === 'group') {
                $nested=is_array($values[$key] ?? null)?$values[$key]:[]; $this->applyGeneratedCustomImages($nested,$field['fields'] ?? [],$path,$byPath,$alt); $values[$key]=$nested;
            } elseif ($type === 'repeater') {
                $rows=is_array($values[$key] ?? null)?array_values($values[$key]):[]; foreach($rows as $i=>&$row) if(is_array($row)) $this->applyGeneratedCustomImages($row,$field['fields'] ?? [],$path.'.'.$i,$byPath,$alt); unset($row); $values[$key]=$rows;
            }
        }
    }

    private function normalizeGeneratedEntryDraft(array $generated, ContentType $contentType, Website $website): array
    {
        $title = Str::limit(trim((string)($generated['title'] ?? '')), 180, '');
        abort_if($title === '', 422, 'Cosmic AI did not return a title. Please try again.');
        $slug = Str::slug((string)($generated['slug'] ?? $title)) ?: Str::slug($title);
        $tags = collect(is_array($generated['tags'] ?? null) ? $generated['tags'] : [])->filter(fn($v)=>is_string($v))->map(fn($v)=>Str::limit(trim($v),60,''))->filter()->unique()->take(20)->values()->all();

        return [
            'title' => $title,
            'slug' => Str::limit($slug, 180, ''),
            'excerpt' => Str::limit(strip_tags((string)($generated['excerpt'] ?? '')), 1000, ''),
            'content' => $this->sanitizeGeneratedRichText((string)($generated['content'] ?? '')),
            'status' => 'draft',
            'category' => Str::limit(strip_tags((string)($generated['category'] ?? '')), 100, ''),
            'tags' => $tags,
            'featured_image_url' => Str::limit((string)($generated['featured_image_url'] ?? ''), 2048, ''),
            'gallery' => array_values(array_slice(is_array($generated['gallery'] ?? null) ? $generated['gallery'] : [], 0, 30)),
            'custom_fields' => $this->normalizeEntryCustomFields(is_array($generated['custom_fields'] ?? null) ? $generated['custom_fields'] : [], $contentType->schema ?: [], $website),
            'seo_title' => Str::limit(strip_tags((string)($generated['seo_title'] ?? $title)), 180, ''),
            'seo_description' => Str::limit(strip_tags((string)($generated['seo_description'] ?? $generated['excerpt'] ?? '')), 500, ''),
            'og_image_url' => Str::limit((string)($generated['featured_image_url'] ?? ''), 2048, ''),
            'is_featured' => (bool)($generated['is_featured'] ?? false),
        ];
    }

    private function sanitizeGeneratedRichText(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|svg)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|form|svg)\b[^>]*/?>#is', '', $html);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html);
        return trim((string) strip_tags($html, '<p><br><h2><h3><h4><ul><ol><li><strong><b><em><i><blockquote><a>'));
    }

    public function storeEntry(Request $request, Website $website, ContentType $contentType)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);
        $validated = $this->validateEntry($request, $contentType);
        $slug = $this->uniqueEntrySlug($website, $contentType, Str::slug($validated['slug'] ?: $validated['title']) ?: 'entry');

        $entry = $contentType->entries()->create([
            ...$validated,
            'website_id' => $website->id,
            'slug' => $slug,
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ]);
        return response()->json(['entry' => self::entryPayloadWithUrl($website, $contentType, $entry)], 201);
    }

    public function updateEntry(Request $request, Website $website, ContentType $contentType, ContentEntry $contentEntry)
    {
        $this->authorize('update', $website);
        $this->guardEntry($website, $contentType, $contentEntry);
        $validated = $this->validateEntry($request, $contentType);
        $base = Str::slug($validated['slug'] ?: $validated['title']) ?: 'entry';
        $validated['slug'] = $this->uniqueEntrySlug($website, $contentType, $base, $contentEntry->id);
        $validated['published_at'] = $validated['status'] === 'published' ? ($contentEntry->published_at ?: now()) : null;
        $contentEntry->update($validated);
        return response()->json(['entry' => self::entryPayloadWithUrl($website, $contentType, $contentEntry->fresh())]);
    }

    public function duplicateEntry(Website $website, ContentType $contentType, ContentEntry $contentEntry)
    {
        $this->authorize('update', $website);
        $this->guardEntry($website, $contentType, $contentEntry);
        $copy = $contentEntry->replicate(['slug', 'status', 'published_at']);
        $copy->title = $contentEntry->title . ' Copy';
        $copy->slug = $this->uniqueEntrySlug($website, $contentType, Str::slug($copy->title));
        $copy->status = 'draft';
        $copy->published_at = null;
        $copy->save();
        return response()->json(['entry' => self::entryPayloadWithUrl($website, $contentType, $copy)], 201);
    }

    public function destroyEntry(Website $website, ContentType $contentType, ContentEntry $contentEntry)
    {
        $this->authorize('update', $website);
        $this->guardEntry($website, $contentType, $contentEntry);
        $contentEntry->delete();
        return response()->json(['status' => 'deleted']);
    }

    private function validateEntry(Request $request, ContentType $contentType): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string', 'max:100000'],
            'status' => ['required', Rule::in(['draft','published'])],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:60'],
            'featured_image_url' => ['nullable', 'string', 'max:2048'],
            'gallery' => ['nullable', 'array', 'max:30'],
            'custom_fields' => ['nullable', 'array'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'og_image_url' => ['nullable', 'string', 'max:2048'],
            'is_featured' => ['nullable', 'boolean'],
        ]);
        $validated['custom_fields'] = $this->normalizeEntryCustomFields(
            $validated['custom_fields'] ?? [],
            $contentType->schema ?: [],
            $contentType->website
        );
        return $validated;
    }

    private function normalizeEntryCustomFields(array $values, array $schema, Website $website): array
    {
        $normalized = [];
        foreach ($schema as $field) {
            if (!is_array($field) || empty($field['key'])) continue;
            $key = $field['key'];
            $type = $field['type'] ?? 'text';
            $value = $values[$key] ?? null;

            if ($type === 'boolean') {
                $normalized[$key] = (bool)$value;
                continue;
            }
            if ($type === 'relation') {
                $targetId = (int)($field['related_type_id'] ?? 0);
                $target = $targetId ? $website->contentTypes()->whereKey($targetId)->first() : null;
                if (!$target) { $normalized[$key] = !empty($field['multiple']) ? [] : null; continue; }
                if (!empty($field['multiple'])) {
                    $ids = collect(is_array($value) ? $value : [])->map(fn($id)=>(int)$id)->filter()->unique()->take(100);
                    $valid = $target->entries()->whereIn('id', $ids->all())->pluck('id')->map(fn($id)=>(int)$id)->all();
                    $normalized[$key] = $valid;
                } else {
                    $id = (int)$value;
                    $normalized[$key] = $id && $target->entries()->whereKey($id)->exists() ? $id : null;
                }
                continue;
            }
            if ($type === 'group') {
                $normalized[$key] = $this->normalizeEntryCustomFields(is_array($value) ? $value : [], $field['fields'] ?? [], $website);
                continue;
            }
            if ($type === 'repeater') {
                $rows = is_array($value) ? array_values($value) : [];
                $max = max(1, min(50, (int)($field['max_rows'] ?? 20)));
                $normalized[$key] = collect(array_slice($rows, 0, $max))->filter(fn($row)=>is_array($row))
                    ->map(fn($row)=>$this->normalizeEntryCustomFields($row, $field['fields'] ?? [], $website))->values()->all();
                continue;
            }
            if ($type === 'gallery') {
                $normalized[$key] = array_values(array_slice(is_array($value) ? $value : [], 0, 30));
                continue;
            }
            if ($type === 'number') {
                $normalized[$key] = $value === '' || $value === null ? null : (is_numeric($value) ? $value + 0 : null);
                continue;
            }
            if (is_array($value) || is_object($value)) $value = '';
            $normalized[$key] = $value === null ? '' : (string)$value;
        }
        return $normalized;
    }

    private static function entryPayloadWithUrl(Website $website, ContentType $type, ContentEntry $entry): array
    {
        $payload = self::entryPayload($entry);
        $payload['url'] = (string) app(\App\Services\PreviewDeploymentService::class)
            ->url($website, trim((string) $type->slug, '/').'/'.trim((string) $entry->slug, '/'));

        return $payload;
    }

    private static function entryPayload(ContentEntry $entry): array
    {
        return [
            'id' => $entry->id, 'content_type_id' => $entry->content_type_id,
            'title' => $entry->title, 'slug' => $entry->slug, 'excerpt' => $entry->excerpt,
            'content' => $entry->content, 'status' => $entry->status, 'category' => $entry->category,
            'tags' => $entry->tags ?: [], 'featured_image_url' => $entry->featured_image_url,
            'gallery' => $entry->gallery ?: [], 'custom_fields' => $entry->custom_fields ?: [],
            'seo_title' => $entry->seo_title, 'seo_description' => $entry->seo_description,
            'og_image_url' => $entry->og_image_url, 'is_featured' => $entry->is_featured,
            'published_at' => optional($entry->published_at)->toISOString(),
            'updated_at' => optional($entry->updated_at)->toISOString(),
        ];
    }


    private static function bindingCatalog(ContentType $type): array
    {
        $core = [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['key' => 'slug', 'label' => 'Slug', 'type' => 'text'],
            ['key' => 'excerpt', 'label' => 'Excerpt', 'type' => 'textarea'],
            ['key' => 'content', 'label' => 'Content', 'type' => 'richtext'],
            ['key' => 'category', 'label' => 'Category', 'type' => 'text'],
            ['key' => 'tags', 'label' => 'Tags', 'type' => 'list'],
            ['key' => 'featured_image_url', 'label' => 'Featured image', 'type' => 'image'],
            ['key' => 'gallery', 'label' => 'Gallery', 'type' => 'gallery'],
            ['key' => 'published_at', 'label' => 'Published date', 'type' => 'datetime'],
            ['key' => 'updated_at', 'label' => 'Updated date', 'type' => 'datetime'],
        ];

        $custom = self::schemaBindingCatalog($type->schema ?: []);

        return array_values(array_merge($core, $custom));
    }

    private static function schemaSignature(array $schema): string
    {
        $normalize = function (array $fields) use (&$normalize): array {
            return collect($fields)->map(function ($field) use (&$normalize) {
                $field = is_array($field) ? $field : [];
                $clean = [
                    'key' => (string) ($field['key'] ?? ''),
                    'label' => (string) ($field['label'] ?? ''),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'multiple' => (bool) ($field['multiple'] ?? false),
                    'related_type_id' => $field['related_type_id'] ?? null,
                    'max_rows' => $field['max_rows'] ?? null,
                    'options' => array_values($field['options'] ?? []),
                ];
                if (is_array($field['fields'] ?? null)) $clean['fields'] = $normalize($field['fields']);
                return $clean;
            })->values()->all();
        };

        return hash('sha256', json_encode($normalize($schema), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function schemaBindingCatalog(array $schema, string $prefix = 'custom_fields.'): array
    {
        $bindings = [];
        foreach ($schema as $field) {
            if (!is_array($field) || empty($field['key'])) continue;
            $key = $prefix.$field['key'];
            $type = $field['type'] ?? 'text';
            $bindings[] = [
                'key' => $key,
                'label' => $field['label'] ?? $field['key'],
                'type' => $type,
            ];
            if ($type === 'group' && !empty($field['fields']) && is_array($field['fields'])) {
                $bindings = array_merge($bindings, self::schemaBindingCatalog($field['fields'], $key.'.'));
            }
        }
        return $bindings;
    }

    private function normalizeSchemaFields(array $schema, Website $website, int $depth = 0): array
    {
        if ($depth > 2) {
            throw ValidationException::withMessages(['schema' => 'Nested custom field groups are limited to two levels.']);
        }

        $allowed = ['text','textarea','richtext','image','gallery','select','date','datetime','url','number','boolean','relation','group','repeater'];
        $nestedAllowed = ['text','textarea','richtext','image','gallery','select','date','datetime','url','number','boolean','relation'];
        $seen = [];
        $normalized = [];

        foreach (array_slice($schema, 0, 40) as $index => $field) {
            if (!is_array($field)) continue;
            $label = trim((string)($field['label'] ?? ''));
            $key = Str::snake(trim((string)($field['key'] ?? '')));
            $type = (string)($field['type'] ?? 'text');

            if ($label === '' || $key === '' || strlen($label) > 100 || strlen($key) > 60) {
                throw ValidationException::withMessages(["schema.$index" => 'Each custom field needs a valid label and key.']);
            }
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["schema.$index.key" => "The custom field key '$key' is duplicated."]);
            }
            if (!in_array($type, $depth > 0 ? $nestedAllowed : $allowed, true)) {
                throw ValidationException::withMessages(["schema.$index.type" => 'Unsupported custom field type.']);
            }
            $seen[$key] = true;

            $item = ['key' => $key, 'label' => $label, 'type' => $type];
            $placeholder = trim((string)($field['placeholder'] ?? ''));
            if ($placeholder !== '') $item['placeholder'] = Str::limit($placeholder, 180, '');

            if ($type === 'select') {
                $item['options'] = collect($field['options'] ?? [])->filter(fn($value) => is_scalar($value))
                    ->map(fn($value) => Str::limit(trim((string)$value), 100, ''))->filter()->unique()->take(50)->values()->all();
            }

            if ($type === 'relation') {
                $relatedId = (int)($field['related_type_id'] ?? 0);
                if ($relatedId > 0) {
                    $exists = $website->contentTypes()->whereKey($relatedId)->exists();
                    if (!$exists) throw ValidationException::withMessages(["schema.$index.related_type_id" => 'Choose a content type from this website.']);
                    $item['related_type_id'] = $relatedId;
                } else {
                    $item['related_type_id'] = null;
                }
                $item['multiple'] = (bool)($field['multiple'] ?? false);
            }

            if ($type === 'group' || $type === 'repeater') {
                $children = is_array($field['fields'] ?? null) ? $field['fields'] : [];
                if (count($children) > 20) throw ValidationException::withMessages(["schema.$index.fields" => 'Groups and repeaters support up to 20 sub-fields.']);
                $item['fields'] = $this->normalizeSchemaFields($children, $website, $depth + 1);
                if ($type === 'repeater') $item['max_rows'] = max(1, min(50, (int)($field['max_rows'] ?? 20)));
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    private static function starterSingleMarkup(ContentType $type): string
    {
        $custom = collect($type->schema ?: [])->take(4)->map(function ($field) {
            $key = 'custom_fields.'.($field['key'] ?? '');
            $label = e($field['label'] ?? $field['key'] ?? 'Detail');
            return '        <div class="rounded-2xl border border-slate-200 p-4"><dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">'.$label.'</dt><dd class="mt-1 text-base font-semibold text-slate-900">{{ '.$key.' }}</dd></div>';
        })->implode("\n");

        return <<<HTML
<article data-cosmic-dynamic-theme="starter" class="mx-auto max-w-5xl px-6 py-16 lg:py-24" style="--dt-primary:var(--p,var(--cosmic-primary,#047857))">
    <header data-cosmic-mini-hero="true" class="mx-auto max-w-3xl text-center">
        <p class="text-sm font-bold uppercase tracking-[0.18em]" style="color:var(--dt-primary)">{{ category }}</p>
        <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-slate-950 md:text-6xl">{{ title }}</h1>
        <p class="mt-5 text-lg leading-8 text-slate-600">{{ excerpt }}</p>
    </header>
    <img src="{{ featured_image_url }}" alt="{{ title }}" class="mt-10 aspect-[16/9] w-full rounded-3xl object-cover" />
    <div data-cosmic-post-content="true" class="prose prose-slate mx-auto mt-10 max-w-3xl">{{ content }}</div>
    <dl class="mx-auto mt-10 grid max-w-3xl gap-3 sm:grid-cols-2">
{$custom}
    </dl>
<section data-cosmic-post-spark="true" class="mx-auto mt-10 max-w-3xl border-t border-slate-200 pt-6 text-sm text-slate-500">Published {{ published_at }} · {{ tags }}</section>
</article>
HTML;
    }

    private static function starterArchiveMarkup(ContentType $type): string
    {
        return <<<HTML
<section data-cosmic-dynamic-theme="starter" class="mx-auto max-w-7xl px-6 py-16 lg:py-24" style="--dt-primary:var(--p,var(--cosmic-primary,#047857))">
    <header data-cosmic-mini-hero="true" class="max-w-3xl">
        <p class="text-sm font-bold uppercase tracking-[0.18em]" style="color:var(--dt-primary)">{$type->name}</p>
        <h1 class="mt-3 text-4xl font-extrabold tracking-tight text-slate-950 md:text-5xl">Latest {$type->name}</h1>
        <p class="mt-4 text-lg text-slate-600">{$type->description}</p>
    </header>
    <div data-cosmic-catalog="true" class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        {{#entries}}
        <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[16/10] w-full object-cover" />
            <div class="p-6">
                <p class="text-xs font-bold uppercase tracking-wider" style="color:var(--dt-primary)">{{ category }}</p>
                <h2 class="mt-2 text-xl font-bold text-slate-950">{{ title }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ excerpt }}</p>
                <a href="{{ url }}" class="mt-5 inline-flex font-bold text-slate-950">View {$type->singular_name} →</a>
            </div>
        </article>
        {{/entries}}
    </div>
    <div data-cosmic-post-spark="true" class="mt-12 rounded-3xl border border-slate-200 p-6"><p class="text-sm font-bold" style="color:var(--dt-primary)">Post spark</p><p class="mt-2 text-sm text-slate-500">New published entries automatically flow into this catalog.</p></div>
</section>
HTML;
    }

    private function validateTemplateBindings(string $markup, ContentType $type, string $templateType): void
    {
        preg_match_all('/{{\s*([^{}#\/][^{}]*)\s*}}/', $markup, $matches);
        $used = collect($matches[1] ?? [])->map(fn ($key) => trim($key))->filter()->unique();
        $allowed = collect(self::bindingCatalog($type))->pluck('key')->push('url');
        if ($templateType === SavedPageTemplate::TYPE_ARCHIVE) {
            $allowed = $allowed->push('entries');
        }
        $invalid = $used->reject(fn ($key) => $allowed->contains($key))->values();
        abort_if($invalid->isNotEmpty(), 422, 'Unknown template binding: '.$invalid->first());
    }

    private static function templatePayload(?SavedPageTemplate $template): ?array
    {
        if (!$template) return null;
        return [
            'id' => $template->id,
            'name' => $template->name,
            'slug' => $template->slug,
            'description' => $template->description,
            'template_type' => $template->template_type,
            'status' => $template->status,
            'markup' => $template->markup,
            'metadata' => $template->metadata ?: [],
            'updated_at' => optional($template->updated_at)->toISOString(),
        ];
    }

    private function guardTemplate(Request $request, Website $website, ContentType $type, SavedPageTemplate $template): void
    {
        abort_unless($template->user_id === $request->user()->id, 404);
        abort_unless($template->website_id === $website->id && $template->content_type_id === $type->id, 404);
        abort_unless(in_array($template->template_type, [SavedPageTemplate::TYPE_SINGLE, SavedPageTemplate::TYPE_ARCHIVE], true), 404);
    }

    private function guardType(Website $website, ContentType $type): void
    {
        abort_unless($type->website_id === $website->id, 404);
    }

    private function guardEntry(Website $website, ContentType $type, ContentEntry $entry): void
    {
        $this->guardType($website, $type);
        abort_unless($entry->website_id === $website->id && $entry->content_type_id === $type->id, 404);
    }

    private function uniqueTypeSlug(Website $website, string $base): string
    {
        $slug = $base; $suffix = 2;
        while ($website->contentTypes()->where('slug', $slug)->exists()) $slug = $base . '-' . $suffix++;
        return $slug;
    }

    private function uniqueEntrySlug(Website $website, ContentType $type, string $base, ?int $ignoreId = null): string
    {
        $slug = $base; $suffix = 2;
        while ($website->contentEntries()->where('content_type_id', $type->id)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }
}
