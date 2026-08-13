<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Website;
use App\AI\Clients\OpenAIClient;
use App\Cosmic\Pricing\ActionPricing;
use App\Services\CreditService;
use App\Services\SmartImageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogPostController extends Controller
{

    public function generate(Request $request, Website $website, Page $page, OpenAIClient $openAI, CreditService $credits, SmartImageService $images)
    {
        $this->authorize('update', $website);
        abort_unless($page->website_id === $website->id && $page->page_type === 'blog', 404);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:3000'],
        ]);

        $cost = ActionPricing::AI_REWRITE;
        $reference = 'ai-blog-' . Str::uuid();

        $credits->consume(
            $request->user(),
            $cost,
            'Write blog post with Cosmic AI',
            $website,
            $reference,
            ['page_id' => $page->id, 'prompt' => $validated['prompt']],
        );

        try {
            $system = <<<'PROMPT'
You are the blog writer inside Cosmic CMS. Return valid JSON only, with no markdown fences.
Required keys: title, category, tags, excerpt, content, image_query.
Rules:
- title: compelling, specific, max 90 characters
- category: one concise category
- tags: array of 3 to 6 short tags
- excerpt: 1 to 2 sentences, max 300 characters
- content: polished long-form article as clean semantic HTML using only h2, h3, p, ul, ol, li, strong, em, blockquote, and a tags
- Separate every paragraph with its own <p> element; never return one continuous text block
- image_query: a generic, non-branded visual stock-photo search phrase of 2 to 5 words
- image_query must avoid company names, product names, logos, trademarks, watermarks, branded interfaces, platform screenshots, and recognizable website-builder UI
- Prefer neutral imagery such as a designer workspace, abstract interface, creative process, architecture, people, products, or a relevant real-world subject
- Do not invent claims, awards, statistics, customer names, or certifications.
PROMPT;

            $context = implode("
", array_filter([
                'Business name: ' . ($website->name ?: 'Not provided'),
                'Industry: ' . ($website->industry ?: 'General business'),
                'Location: ' . ($website->location ?: 'Not provided'),
                'Business description: ' . ($website->business_description ?: 'Not provided'),
                'User request: ' . $validated['prompt'],
            ]));

            $raw = trim($openAI->chat($system, $context));
            $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw);
            $generated = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            $folder = Str::slug($website->industry ?: 'default') ?: 'default';
            $imageQuery = trim((string) ($generated['image_query'] ?? ''));

            if ($imageQuery === '') {
                $imageQuery = collect([
                    $generated['category'] ?? null,
                    ...array_slice((array) ($generated['tags'] ?? []), 0, 2),
                    $website->industry ?: null,
                    'editorial photography',
                ])->filter(fn ($value) => is_string($value) && trim($value) !== '')
                  ->implode(' ');
            }

            $imageUrl = $images->find($imageQuery, $folder);
            if (is_string($imageUrl) && str_starts_with($imageUrl, 'https://')) {
                $asset = app(\App\Services\MediaLibraryRegistry::class)->importRemoteImage($website, $imageUrl, 'unsplash', 'blog-featured', $request->user()?->id, ['query' => $imageQuery]);
                if ($asset) $imageUrl = app(\App\Services\MediaLibraryRegistry::class)->url($asset);
            }
            $content = $this->normalizeGeneratedContent((string) ($generated['content'] ?? ''));

            return response()->json([
                'post' => [
                    'title' => Str::limit((string) ($generated['title'] ?? ''), 180, ''),
                    'category' => Str::limit((string) ($generated['category'] ?? ''), 80, ''),
                    'tags' => array_slice(array_values(array_filter((array) ($generated['tags'] ?? []))), 0, 12),
                    'excerpt' => Str::limit((string) ($generated['excerpt'] ?? ''), 500, ''),
                    'content' => Str::limit($content, 20000, ''),
                    'image_url' => $imageUrl,
                ],
                'credits_spent' => $cost,
                'credit_balance' => $credits->balance($request->user()),
            ]);
        } catch (\Throwable $exception) {
            try {
                $credits->refund(
                    $request->user(),
                    $cost,
                    'Refund for failed AI blog generation',
                    $website,
                    $reference . '-refund',
                    ['page_id' => $page->id],
                );
            } catch (\Throwable) {
                // Preserve the original generation error response.
            }

            report($exception);

            return response()->json([
                'message' => 'Cosmic AI could not write this post. Your credits were refunded.',
            ], 503);
        }
    }

    private function normalizeGeneratedContent(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        // Preserve safe rich text returned by the AI. If it returned plain
        // text instead, convert headings and blank-line paragraphs to HTML.
        if (preg_match('/<(?:p|h2|h3|ul|ol|blockquote)\b/i', $content) === 1) {
            return strip_tags($content, '<h2><h3><p><ul><ol><li><strong><em><blockquote><a>');
        }

        $blocks = preg_split('/\R{2,}/', $content) ?: [];
        $html = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $escaped = e($block);
            $lines = preg_split('/\R/', $escaped) ?: [];

            if (count($lines) === 1 && mb_strlen($block) <= 100 && ! preg_match('/[.!?]$/', $block)) {
                $html[] = '<h2>' . $escaped . '</h2>';
                continue;
            }

            $html[] = '<p>' . implode('<br>', $lines) . '</p>';
        }

        return implode("\n", $html);
    }

    public function store(Request $request, Website $website, Page $page)
    {
        $this->authorize('update', $website);
        abort_unless($page->website_id === $website->id && $page->page_type === 'blog', 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:80'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'status' => ['nullable', 'in:draft,published'],
        ]);

        $baseSlug = Str::slug($validated['title']) ?: 'post';
        $slug = $baseSlug;
        $suffix = 2;
        while ($website->blogPosts()->where('slug', $slug)->exists()) $slug = "{$baseSlug}-" . $suffix++;

        $post = $website->blogPosts()->create([
            ...$validated,
            'page_id' => $page->id,
            'slug' => $slug,
            'status' => $validated['status'] ?? 'draft',
            'published_at' => ($validated['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        return response()->json(['post' => $post], 201);
    }

    public function update(Request $request, Website $website, Page $page, BlogPost $blogPost)
    {
        $this->authorize('update', $website);
        abort_unless($page->website_id === $website->id && $page->page_type === 'blog' && $blogPost->website_id === $website->id && $blogPost->page_id === $page->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:80'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'status' => ['required', 'in:draft,published'],
        ]);

        $blogPost->fill($validated);
        $blogPost->published_at = $validated['status'] === 'published'
            ? ($blogPost->published_at ?? now())
            : null;
        $blogPost->save();

        return response()->json(['post' => $blogPost->fresh()]);
    }

    public function destroy(Website $website, Page $page, BlogPost $blogPost)
    {
        $this->authorize('update', $website);
        abort_unless($page->website_id === $website->id && $page->page_type === 'blog' && $blogPost->website_id === $website->id && $blogPost->page_id === $page->id, 404);

        $blogPost->delete();

        return response()->json(['status' => 'deleted']);
    }
}
