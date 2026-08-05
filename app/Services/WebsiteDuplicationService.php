<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebsiteDuplicationService
{
    /**
     * Create a private draft copy owned by the requesting account.
     *
     * Published/deployment state, leads, billing records and provisioning state
     * are intentionally excluded. Any failure rolls the whole copy back.
     */
    public function duplicate(Website $source, User $owner, ?Workspace $workspace = null): Website
    {
        return DB::transaction(function () use ($source, $owner, $workspace) {
            $source->loadMissing(['pages', 'blogPosts']);

            $copy = $source->replicate([
                'user_id',
                'workspace_id',
                'domain',
                'api_token',
                'deployment_secret',
                'deployment_verified_at',
                'last_deployed_at',
                'deployment_error',
                'published_theme_settings',
                'published_global_header',
                'published_global_footer',
            ]);

            $copy->user_id = $owner->id;
            $copy->workspace_id = $workspace?->id;
            $copy->name = $this->copyName($source->name ?: 'Untitled Website', $owner, $workspace);
            $copy->domain = null;
            $copy->api_token = Str::random(60);
            $copy->deployment_secret = null;
            $copy->deployment_verified_at = null;
            $copy->last_deployed_at = null;
            $copy->deployment_error = null;
            $copy->published_theme_settings = null;
            $copy->published_global_header = null;
            $copy->published_global_footer = null;
            $copy->saveOrFail();

            $pageMap = [];
            $pages = $source->pages()->orderBy('sort_order')->orderBy('id')->get();

            foreach ($pages as $page) {
                $newPage = $page->replicate([
                    'website_id',
                    'parent_id',
                    'published_blocks',
                    'published_html',
                    'published_page_style',
                    'published_at',
                    'last_published_at',
                    'publish_error',
                ]);
                $newPage->website_id = $copy->id;
                $newPage->parent_id = null;
                $newPage->status = 'draft';
                $newPage->published_blocks = null;
                $newPage->published_html = null;
                $newPage->published_page_style = null;
                $newPage->published_at = null;
                $newPage->last_published_at = null;
                $newPage->publish_error = null;
                $newPage->saveOrFail();

                $pageMap[$page->id] = $newPage;
            }

            foreach ($pages as $page) {
                if ($page->parent_id && isset($pageMap[$page->id], $pageMap[$page->parent_id])) {
                    $pageMap[$page->id]->forceFill([
                        'parent_id' => $pageMap[$page->parent_id]->id,
                    ])->saveOrFail();
                }
            }

            $source->blogPosts()->orderBy('id')->get()->each(function ($post) use ($copy, $pageMap) {
                $newPost = $post->replicate(['website_id', 'page_id', 'published_at']);
                $newPost->website_id = $copy->id;
                $newPost->page_id = $post->page_id && isset($pageMap[$post->page_id])
                    ? $pageMap[$post->page_id]->id
                    : null;
                $newPost->status = 'draft';
                $newPost->published_at = null;
                $newPost->saveOrFail();
            });

            // Legacy global element rows are editable source content, not live state.
            if (DB::getSchemaBuilder()->hasTable('global_elements')) {
                DB::table('global_elements')
                    ->where('website_id', $source->id)
                    ->orderBy('id')
                    ->get()
                    ->each(function ($element) use ($copy) {
                        DB::table('global_elements')->insert([
                            'website_id' => $copy->id,
                            'type' => $element->type,
                            'content' => $element->content,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    });
            }

            return $copy->fresh(['pages', 'blogPosts']);
        }, 3);
    }

    private function copyName(string $name, User $owner, ?Workspace $workspace): string
    {
        $base = preg_replace('/\s+Copy(?:\s+\d+)?$/i', '', trim($name)) ?: 'Untitled Website';
        $candidate = "{$base} Copy";
        $number = 2;

        $query = Website::query()->where('user_id', $owner->id);
        $workspace
            ? $query->where('workspace_id', $workspace->id)
            : $query->whereNull('workspace_id');

        while ((clone $query)->where('name', $candidate)->exists()) {
            $candidate = "{$base} Copy {$number}";
            $number++;
        }

        return $candidate;
    }
}
