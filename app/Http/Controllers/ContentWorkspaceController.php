<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Models\ContentType;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'types' => $website->contentTypes()
                ->withCount('entries')
                ->with(['entries' => fn ($query) => $query->latest('updated_at')->limit(100)])
                ->get()
                ->map(fn (ContentType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'singular_name' => $type->singular_name,
                    'slug' => $type->slug,
                    'icon' => $type->icon,
                    'description' => $type->description,
                    'schema' => $type->schema ?: [],
                    'is_system' => $type->is_system,
                    'entries_count' => $type->entries_count,
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

    public function storeType(Request $request, Website $website)
    {
        $this->authorize('update', $website);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'singular_name' => ['nullable', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:40'],
            'schema' => ['nullable', 'array', 'max:20'],
            'schema.*.key' => ['required_with:schema', 'string', 'max:60'],
            'schema.*.label' => ['required_with:schema', 'string', 'max:100'],
            'schema.*.type' => ['required_with:schema', Rule::in(['text','textarea','date','datetime','url','number','boolean'])],
        ]);

        $base = Str::slug($validated['slug'] ?: $validated['name']) ?: 'content';
        $slug = $this->uniqueTypeSlug($website, $base);
        $type = $website->contentTypes()->create([
            ...$validated,
            'singular_name' => $validated['singular_name'] ?: Str::singular($validated['name']),
            'slug' => $slug,
            'sort_order' => ((int) $website->contentTypes()->max('sort_order')) + 10,
        ]);

        return response()->json(['type' => $type], 201);
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
            'schema' => ['nullable', 'array', 'max:20'],
            'schema.*.key' => ['required_with:schema', 'string', 'max:60'],
            'schema.*.label' => ['required_with:schema', 'string', 'max:100'],
            'schema.*.type' => ['required_with:schema', Rule::in(['text','textarea','date','datetime','url','number','boolean'])],
        ]);
        $contentType->update($validated);
        return response()->json(['type' => $contentType->fresh()]);
    }

    public function destroyType(Website $website, ContentType $contentType)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);
        abort_if($contentType->is_system, 422, 'Default content types can be renamed but not deleted.');
        $contentType->delete();
        return response()->json(['status' => 'deleted']);
    }

    public function storeEntry(Request $request, Website $website, ContentType $contentType)
    {
        $this->authorize('update', $website);
        $this->guardType($website, $contentType);
        $validated = $this->validateEntry($request);
        $slug = $this->uniqueEntrySlug($website, $contentType, Str::slug($validated['slug'] ?: $validated['title']) ?: 'entry');

        $entry = $contentType->entries()->create([
            ...$validated,
            'website_id' => $website->id,
            'slug' => $slug,
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ]);
        return response()->json(['entry' => self::entryPayload($entry)], 201);
    }

    public function updateEntry(Request $request, Website $website, ContentType $contentType, ContentEntry $contentEntry)
    {
        $this->authorize('update', $website);
        $this->guardEntry($website, $contentType, $contentEntry);
        $validated = $this->validateEntry($request, $contentEntry);
        $base = Str::slug($validated['slug'] ?: $validated['title']) ?: 'entry';
        $validated['slug'] = $this->uniqueEntrySlug($website, $contentType, $base, $contentEntry->id);
        $validated['published_at'] = $validated['status'] === 'published' ? ($contentEntry->published_at ?: now()) : null;
        $contentEntry->update($validated);
        return response()->json(['entry' => self::entryPayload($contentEntry->fresh())]);
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
        return response()->json(['entry' => self::entryPayload($copy)], 201);
    }

    public function destroyEntry(Website $website, ContentType $contentType, ContentEntry $contentEntry)
    {
        $this->authorize('update', $website);
        $this->guardEntry($website, $contentType, $contentEntry);
        $contentEntry->delete();
        return response()->json(['status' => 'deleted']);
    }

    private function validateEntry(Request $request, ?ContentEntry $entry = null): array
    {
        return $request->validate([
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
