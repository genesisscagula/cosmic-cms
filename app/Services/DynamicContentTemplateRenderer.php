<?php

namespace App\Services;

use App\Models\ContentEntry;
use App\Models\ContentType;
use App\Models\SavedPageTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DynamicContentTemplateRenderer
{
    public function renderSingle(ContentType $type, ContentEntry $entry, ?SavedPageTemplate $template = null): string
    {
        $markup = trim((string) ($template?->markup ?? ''));
        if ($markup === '') {
            $markup = $this->fallbackSingleMarkup($type);
        }

        $values = $this->entryValues($type, $entry);

        $rendered = $this->replaceBindings($markup, $values);

        // A stable runtime hook lets Preview and exported/live HTML apply the
        // same editorial polish without rewriting Luna's saved Tailwind markup.
        return '<div data-cosmic-dynamic-single="true" class="cosmic-dynamic-single">'.$rendered.'</div>';
    }

    /**
     * Static-export payload for a published entry. URLs are intentionally kept
     * relative/portable so the deployment connector can wrap the body exactly
     * like normal Builder pages.
     */
    public function exportSingle(ContentType $type, ContentEntry $entry, ?SavedPageTemplate $template = null): string
    {
        return $this->renderSingle($type, $entry, $template);
    }

    private function entryValues(ContentType $type, ContentEntry $entry): array
    {
        $values = [
            'title' => $entry->title,
            'slug' => $entry->slug,
            'excerpt' => $entry->excerpt,
            'content' => $entry->content,
            'category' => $entry->category,
            'tags' => $entry->tags ?: [],
            'featured_image_url' => $entry->featured_image_url,
            'gallery' => $entry->gallery ?: [],
            'published_at' => optional($entry->published_at)->toISOString(),
            'updated_at' => optional($entry->updated_at)->toISOString(),
            'custom_fields' => $entry->custom_fields ?: [],
        ];

        // Keep schema metadata available to the formatter without exposing it as
        // a public binding token.
        $values['_schema'] = $type->schema ?: [];

        return $values;
    }

    private function replaceBindings(string $markup, array $values): string
    {
        return preg_replace_callback('/{{\s*([^{}#\/][^{}]*)\s*}}/', function (array $match) use ($values): string {
            $key = trim((string) ($match[1] ?? ''));
            if ($key === '') return '';

            $value = data_get($values, $key);
            $type = $this->bindingType($key, $values['_schema'] ?? []);

            return $this->formatValue($value, $type, $key);
        }, $markup) ?? $markup;
    }

    private function bindingType(string $key, array $schema): string
    {
        return match ($key) {
            'content' => 'richtext',
            'featured_image_url' => 'image',
            'gallery' => 'gallery',
            'tags' => 'list',
            'published_at', 'updated_at' => 'datetime',
            default => str_starts_with($key, 'custom_fields.')
                ? $this->schemaType(Str::after($key, 'custom_fields.'), $schema)
                : 'text',
        };
    }

    private function schemaType(string $path, array $schema): string
    {
        $segments = explode('.', $path);
        $fields = $schema;
        foreach ($segments as $segment) {
            $field = collect($fields)->first(fn ($candidate) => is_array($candidate) && ($candidate['key'] ?? null) === $segment);
            if (! is_array($field)) return 'text';
            if ($segment === end($segments)) return (string) ($field['type'] ?? 'text');
            $fields = is_array($field['fields'] ?? null) ? $field['fields'] : [];
        }
        return 'text';
    }

    private function formatValue(mixed $value, string $type, string $key): string
    {
        if ($value === null || $value === '' || $value === false) return '';

        return match ($type) {
            'richtext' => '<div data-cosmic-richtext="true">'.$this->safeRichText((string) $value).'</div>',
            'image' => e((string) $value),
            'gallery' => $this->galleryHtml(is_array($value) ? $value : []),
            'list' => $this->listHtml(is_array($value) ? $value : [$value]),
            'boolean' => (bool) $value ? 'Yes' : 'No',
            'date' => $this->dateValue($value, false),
            'datetime' => $this->dateValue($value, true),
            'group' => $this->structuredHtml(is_array($value) ? $value : []),
            'repeater' => $this->repeaterHtml(is_array($value) ? $value : []),
            'relation' => $this->relationValue($value),
            default => is_array($value) ? $this->structuredHtml($value) : e((string) $value),
        };
    }

    private function safeRichText(string $html): string
    {
        $html = trim($html);
        if ($html === '') return '';

        // AI/manual rich text may already contain HTML. Preserve editorial
        // markup but strip executable/embedded content and inline JS handlers.
        $html = preg_replace('#<(script|style|iframe|object|embed|form|svg)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|style|iframe|object|embed|form|svg)\b[^>]*/?>#is', '', $html) ?? $html;
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html) ?? $html;

        $allowed = '<p><br><strong><b><em><i><u><s><mark><small><h2><h3><h4><h5><h6><ul><ol><li><blockquote><pre><code><a><img><figure><figcaption><hr><table><thead><tbody><tr><th><td><div><span>';
        return strip_tags($html, $allowed);
    }

    private function galleryHtml(array $gallery): string
    {
        $items = collect($gallery)->map(function ($item): string {
            $url = is_array($item) ? ($item['url'] ?? $item['src'] ?? '') : $item;
            if (! is_string($url) || trim($url) === '') return '';
            $alt = is_array($item) ? (string) ($item['alt'] ?? $item['alt_text'] ?? '') : '';
            return '<figure class="overflow-hidden rounded-2xl bg-slate-100"><img src="'.e($url).'" alt="'.e($alt).'" loading="lazy" class="h-full w-full object-cover"></figure>';
        })->filter()->implode('');

        return $items === '' ? '' : '<div data-cosmic-gallery="true" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">'.$items.'</div>';
    }

    private function listHtml(array $items): string
    {
        $html = collect($items)->filter(fn ($item) => is_scalar($item) && trim((string) $item) !== '')
            ->map(fn ($item) => '<span class="inline-flex rounded-full border border-slate-200 px-3 py-1 text-sm">'.e((string) $item).'</span>')
            ->implode(' ');

        return $html === '' ? '' : '<span data-cosmic-tags="true" class="inline-flex flex-wrap gap-2">'.$html.'</span>';
    }

    private function structuredHtml(array $values): string
    {
        if ($values === []) return '';
        $rows = collect($values)->map(function ($value, $label): string {
            if ($value === null || $value === '' || $value === false) return '';
            $display = is_array($value) ? e(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : e((string) $value);
            return '<div class="border-b border-slate-200 py-3 last:border-0"><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">'.e(Str::headline((string) $label)).'</dt><dd class="mt-1 text-sm text-slate-800">'.$display.'</dd></div>';
        })->filter()->implode('');
        return $rows === '' ? '' : '<dl>'.$rows.'</dl>';
    }

    private function repeaterHtml(array $rows): string
    {
        $items = collect($rows)->filter('is_array')->map(fn ($row) => '<div class="rounded-2xl border border-slate-200 p-4">'.$this->structuredHtml($row).'</div>')->implode('');
        return $items === '' ? '' : '<div class="grid gap-4">'.$items.'</div>';
    }

    private function relationValue(mixed $value): string
    {
        if (is_array($value)) {
            return collect(Arr::flatten($value))->filter(fn ($item) => is_scalar($item))->map(fn ($item) => e((string) $item))->implode(', ');
        }
        return e((string) $value);
    }

    private function dateValue(mixed $value, bool $withTime): string
    {
        try {
            $date = Carbon::parse((string) $value);
            return e($date->format($withTime ? 'M j, Y · g:i A' : 'M j, Y'));
        } catch (\Throwable) {
            return e((string) $value);
        }
    }

    private function fallbackSingleMarkup(ContentType $type): string
    {
        $label = e($type->singular_name ?: $type->name);
        return <<<HTML
<article class="bg-white text-slate-950" itemscope itemtype="https://schema.org/Article">
    <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8 lg:py-16">
        <div class="mx-auto max-w-4xl">
            <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-violet-600">{{ category }}</p>
            <h1 itemprop="headline" class="mt-4 text-4xl font-extrabold tracking-[-0.04em] text-slate-950 sm:text-5xl lg:text-6xl">{{ title }}</h1>
            <p class="mt-6 max-w-3xl text-lg leading-8 text-slate-600">{{ excerpt }}</p>
            <div class="mt-5 flex flex-wrap gap-2 text-sm text-slate-500">{{ tags }}</div>
        </div>
        <div class="mx-auto mt-10 max-w-6xl overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-100 shadow-xl shadow-slate-900/5">
            <img itemprop="image" src="{{ featured_image_url }}" alt="{{ title }}" fetchpriority="high" decoding="async" class="aspect-[16/9] w-full object-cover">
        </div>
        <div class="mx-auto mt-12 grid max-w-6xl gap-10 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="prose prose-lg prose-slate max-w-none leading-8">{{ content }}<div class="mt-10">{{ gallery }}</div></div>
            <aside class="h-fit rounded-3xl border border-slate-200 bg-slate-50 p-6"><p class="text-xs font-extrabold uppercase tracking-[0.18em] text-slate-500">{$label} details</p><div class="mt-4 text-sm leading-7 text-slate-700">Published {{ published_at }}</div></aside>
        </div>
    </div>
</article>
HTML;
    }
}
