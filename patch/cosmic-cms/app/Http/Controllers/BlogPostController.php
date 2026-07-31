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
Required keys: title, category, tags, excerpt, content.
Rules:
- title: compelling, specific, max 90 characters
- category: one concise category
- tags: array of 3 to 6 short tags
- excerpt: 1 to 2 sentences, max 300 characters
- content: polished long-form article in plain text with useful headings separated by blank lines
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
            $imageQuery = collect([
                $generated['title'] ?? null,
                $generated['category'] ?? null,
                ...array_slice((array) ($generated['tags'] ?? []), 0, 3),
                $website->industry ?: null,
                'professional editorial photography',
            ])->filter(fn ($value) => is_string($value) && trim($value) !== '')
              ->implode(' ');

            $imageUrl = $images->find($imageQuery, $folder);

            return response()->json([
                'post' => [
                    'title' => Str::limit((string) ($generated['title'] ?? ''), 180, ''),
                    'category' => Str::limit((string) ($generated['category'] ?? ''), 80, ''),
                    'tags' => array_slice(array_values(array_filter((array) ($generated['tags'] ?? []))), 0, 12),
                    'excerpt' => Str::limit((string) ($generated['excerpt'] ?? ''), 500, ''),
                    'content' => Str::limit((string) ($generated['content'] ?? ''), 20000, ''),
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
