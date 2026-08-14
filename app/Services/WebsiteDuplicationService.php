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
            $source->loadMissing(['pages', 'blogPosts', 'contentTypes', 'contentEntries', 'commerceProducts']);

            $copy = $source->replicate([
                'user_id',
                'workspace_id',
                'domain',
                'api_token',
                'deployment_secret',
                'deployment_verified_at',
                'last_deployed_at',
                'deployment_error',
                'preview_slug',
                'last_preview_deployed_at',
                'preview_deployment_error',
            ]);

            $copy->user_id = $owner->id;
            $copy->workspace_id = $workspace?->id;
            $copy->name = $this->copyName($source->name ?: 'Untitled Website', $owner, $workspace);
            $copy->domain = null;
            $copy->preview_slug = $this->uniquePreviewSlug($copy->name);
            $copy->api_token = Str::random(60);
            $copy->deployment_secret = null;
            $copy->deployment_verified_at = null;
            $copy->last_deployed_at = null;
            $copy->deployment_error = null;
            $copy->saveOrFail();

            $pageMap = [];
            $pages = $source->pages()->orderBy('sort_order')->orderBy('id')->get();

            foreach ($pages as $page) {
                $newPage = $page->replicate(['website_id', 'parent_id']);
                $newPage->website_id = $copy->id;
                $newPage->parent_id = null;
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
                $newPost = $post->replicate(['website_id', 'page_id']);
                $newPost->website_id = $copy->id;
                $newPost->page_id = $post->page_id && isset($pageMap[$post->page_id])
                    ? $pageMap[$post->page_id]->id
                    : null;
                $newPost->saveOrFail();
            });

            $this->cloneContentCollections($source, $copy);
            $this->cloneMediaLibrary($source, $copy, $owner);
            $this->cloneCommerceCatalog($source, $copy);

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


    /** Clone Posts / Updates content types and entries while preserving status and publish state. */
    private function cloneContentCollections(Website $source, Website $copy): void
    {
        if (! DB::getSchemaBuilder()->hasTable('content_types') || ! DB::getSchemaBuilder()->hasTable('content_entries')) {
            return;
        }

        $typeMap = [];
        foreach (DB::table('content_types')->where('website_id', $source->id)->orderBy('id')->get() as $type) {
            $data = (array) $type;
            unset($data['id']);
            $data['website_id'] = $copy->id;
            $typeMap[$type->id] = DB::table('content_types')->insertGetId($data);
        }

        foreach (DB::table('content_entries')->where('website_id', $source->id)->orderBy('id')->get() as $entry) {
            $data = (array) $entry;
            unset($data['id']);
            $data['website_id'] = $copy->id;
            $data['content_type_id'] = $typeMap[$entry->content_type_id] ?? $entry->content_type_id;
            DB::table('content_entries')->insert($data);
        }
    }

    /** Clone the site's Media Library metadata. Files stay at their public paths, so no storage is duplicated. */
    private function cloneMediaLibrary(Website $source, Website $copy, User $owner): void
    {
        if (! DB::getSchemaBuilder()->hasTable('media_folders') || ! DB::getSchemaBuilder()->hasTable('media_assets')) {
            return;
        }

        $folderMap = [];
        $folders = DB::table('media_folders')->where('website_id', $source->id)->orderBy('id')->get();
        foreach ($folders as $folder) {
            $data = (array) $folder;
            unset($data['id']);
            $data['website_id'] = $copy->id;
            $data['parent_id'] = null;
            $data['created_by'] = $owner->id;
            $folderMap[$folder->id] = DB::table('media_folders')->insertGetId($data);
        }
        foreach ($folders as $folder) {
            if ($folder->parent_id && isset($folderMap[$folder->parent_id])) {
                DB::table('media_folders')->where('id', $folderMap[$folder->id])->update(['parent_id' => $folderMap[$folder->parent_id]]);
            }
        }

        foreach (DB::table('media_assets')->where('website_id', $source->id)->whereNull('deleted_at')->orderBy('id')->get() as $asset) {
            $data = (array) $asset;
            unset($data['id']);
            $data['uuid'] = (string) Str::uuid();
            $data['website_id'] = $copy->id;
            $data['folder_id'] = $asset->folder_id ? ($folderMap[$asset->folder_id] ?? null) : null;
            $data['uploaded_by'] = $owner->id;
            DB::table('media_assets')->insert($data);
        }
    }

    /**
     * Clone editable commerce configuration/catalog, not transactional history.
     * Orders, customers, payments, refunds and inventory audit rows intentionally stay with the source site.
     */
    private function cloneCommerceCatalog(Website $source, Website $copy): void
    {
        $schema = DB::getSchemaBuilder();

        foreach (['website_commerce_settings', 'commerce_tax_rules', 'commerce_coupons'] as $table) {
            if (! $schema->hasTable($table)) continue;
            foreach (DB::table($table)->where('website_id', $source->id)->orderBy('id')->get() as $row) {
                $data = (array) $row;
                unset($data['id']);
                $data['website_id'] = $copy->id;

                // Commerce settings carry a globally unique public API key. A duplicated
                // website must keep the editable commerce configuration, but it needs its
                // own identity so the copy cannot collide with or impersonate the source.
                if ($table === 'website_commerce_settings') {
                    $data['public_key'] = (string) Str::uuid();
                }

                DB::table($table)->insert($data);
            }
        }

        if ($schema->hasTable('commerce_shipping_zones')) {
            foreach (DB::table('commerce_shipping_zones')->where('website_id', $source->id)->orderBy('id')->get() as $zone) {
                $data = (array) $zone; unset($data['id']); $data['website_id'] = $copy->id;
                $newZoneId = DB::table('commerce_shipping_zones')->insertGetId($data);
                if ($schema->hasTable('commerce_shipping_rates')) {
                    foreach (DB::table('commerce_shipping_rates')->where('shipping_zone_id', $zone->id)->orderBy('id')->get() as $rate) {
                        $rateData = (array) $rate; unset($rateData['id']); $rateData['shipping_zone_id'] = $newZoneId; DB::table('commerce_shipping_rates')->insert($rateData);
                    }
                }
            }
        }

        if (! $schema->hasTable('commerce_products')) return;

        $categoryMap = [];
        if ($schema->hasTable('commerce_product_categories')) {
            $categories = DB::table('commerce_product_categories')->where('website_id', $source->id)->orderBy('id')->get();
            foreach ($categories as $category) {
                $data = (array) $category;
                unset($data['id']);
                $data['website_id'] = $copy->id;
                $data['parent_id'] = null;
                $data['public_id'] = (string) Str::uuid();
                $categoryMap[$category->id] = DB::table('commerce_product_categories')->insertGetId($data);
            }
            foreach ($categories as $category) {
                if ($category->parent_id && isset($categoryMap[$category->parent_id])) {
                    DB::table('commerce_product_categories')->where('id', $categoryMap[$category->id])->update(['parent_id' => $categoryMap[$category->parent_id]]);
                }
            }
        }

        $productMap = [];
        foreach (DB::table('commerce_products')->where('website_id', $source->id)->orderBy('id')->get() as $product) {
            $data = (array) $product;
            unset($data['id']);
            $data['website_id'] = $copy->id;
            $data['public_id'] = (string) Str::uuid();
            $productMap[$product->id] = DB::table('commerce_products')->insertGetId($data);
        }

        if ($schema->hasTable('commerce_product_images')) {
            foreach ($productMap as $oldProductId => $newProductId) {
                foreach (DB::table('commerce_product_images')->where('product_id', $oldProductId)->orderBy('id')->get() as $row) {
                    $data = (array) $row; unset($data['id']); $data['product_id'] = $newProductId; DB::table('commerce_product_images')->insert($data);
                }
            }
        }

        $optionMap = [];
        $valueMap = [];
        if ($schema->hasTable('commerce_product_options')) {
            foreach ($productMap as $oldProductId => $newProductId) {
                foreach (DB::table('commerce_product_options')->where('product_id', $oldProductId)->orderBy('id')->get() as $row) {
                    $data = (array) $row; unset($data['id']); $data['product_id'] = $newProductId;
                    $optionMap[$row->id] = DB::table('commerce_product_options')->insertGetId($data);
                }
            }
        }
        if ($schema->hasTable('commerce_product_option_values')) {
            foreach ($optionMap as $oldOptionId => $newOptionId) {
                foreach (DB::table('commerce_product_option_values')->where('option_id', $oldOptionId)->orderBy('id')->get() as $row) {
                    $data = (array) $row; unset($data['id']); $data['option_id'] = $newOptionId;
                    $valueMap[$row->id] = DB::table('commerce_product_option_values')->insertGetId($data);
                }
            }
        }

        $variantMap = [];
        if ($schema->hasTable('commerce_product_variants')) {
            foreach ($productMap as $oldProductId => $newProductId) {
                foreach (DB::table('commerce_product_variants')->where('product_id', $oldProductId)->orderBy('id')->get() as $row) {
                    $data = (array) $row; unset($data['id']); $data['website_id'] = $copy->id; $data['product_id'] = $newProductId; $data['public_id'] = (string) Str::uuid();
                    $variantMap[$row->id] = DB::table('commerce_product_variants')->insertGetId($data);
                }
            }
        }
        if ($schema->hasTable('commerce_product_variant_values')) {
            foreach ($variantMap as $oldVariantId => $newVariantId) {
                foreach (DB::table('commerce_product_variant_values')->where('variant_id', $oldVariantId)->orderBy('id')->get() as $row) {
                    if (!isset($optionMap[$row->option_id], $valueMap[$row->option_value_id])) continue;
                    $data = (array) $row; unset($data['id']); $data['variant_id']=$newVariantId; $data['option_id']=$optionMap[$row->option_id]; $data['option_value_id']=$valueMap[$row->option_value_id]; DB::table('commerce_product_variant_values')->insert($data);
                }
            }
        }
        if ($schema->hasTable('commerce_product_category_assignments')) {
            foreach ($productMap as $oldProductId => $newProductId) {
                foreach (DB::table('commerce_product_category_assignments')->where('product_id', $oldProductId)->orderBy('id')->get() as $row) {
                    if (!isset($categoryMap[$row->category_id])) continue;
                    $data=(array)$row; unset($data['id']); $data['product_id']=$newProductId; $data['category_id']=$categoryMap[$row->category_id]; DB::table('commerce_product_category_assignments')->insert($data);
                }
            }
        }
    }

    private function uniquePreviewSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'website-copy';
        $candidate = $base;
        $number = 2;
        while (Website::query()->where('preview_slug', $candidate)->exists()) {
            $candidate = $base.'-'.$number;
            $number++;
        }
        return $candidate;
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
