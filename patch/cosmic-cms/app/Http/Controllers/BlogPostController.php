<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogPostController extends Controller
{
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
