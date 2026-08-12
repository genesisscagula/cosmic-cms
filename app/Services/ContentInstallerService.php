<?php

namespace App\Services;

use App\Http\Controllers\ContentWorkspaceController;
use App\Models\ContentEntry;
use App\Models\ContentType;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class ContentInstallerService
{
    public function install(Website $website, bool $withDemo = false, bool $addNavigation = true): array
    {
        return DB::transaction(function () use ($website, $withDemo, $addNavigation): array {
            ContentWorkspaceController::ensureDefaults($website);
            $types = $website->contentTypes()->get()->keyBy('slug');

            $pages = 0;
            foreach ($this->pageDefinitions() as $definition) {
                $type = $types->get($definition['slug']);
                if (! $type) continue;
                $this->installPage($website, $type, $definition);
                $pages++;
            }

            $demo = $withDemo ? $this->installDemoEntries($website, $types) : 0;
            $navigation = $addNavigation ? $this->installNavigation($website) : false;

            return compact('pages', 'demo', 'navigation');
        });
    }

    /** Install or repair one editable Standard Page for a structured content type. */
    public function installTypePage(Website $website, ContentType $type, bool $addNavigation = true): array
    {
        return DB::transaction(function () use ($website, $type, $addNavigation): array {
            abort_unless((int) $type->website_id === (int) $website->id, 404);

            $definition = $this->definitionForType($type);
            $this->installPage($website, $type, $definition);
            $page = $website->pages()->where('slug', $type->slug)->where('page_type', 'standard')->firstOrFail();
            $navigation = $addNavigation ? $this->installTypeNavigation($website, $type) : false;

            return [
                'page_id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'navigation' => $navigation,
            ];
        });
    }

    private function definitionForType(ContentType $type): array
    {
        foreach ($this->pageDefinitions() as $definition) {
            if (($definition['slug'] ?? null) === $type->slug) return $definition;
        }

        $plural = trim((string) $type->name) ?: 'Updates';
        $singular = trim((string) $type->singular_name) ?: 'Update';
        $description = trim((string) $type->description) ?: "Explore the latest {$plural}.";
        $slug = trim((string) $type->slug, '/');
        $nameKey = strtolower($plural.' '.$singular.' '.$slug);
        $loopType = str_contains($nameKey, 'event') ? 'content_events_grid'
            : (str_contains($nameKey, 'project') || str_contains($nameKey, 'news') || str_contains($nameKey, 'team') ? 'content_grid_editorial' : 'content_grid_classic');

        $loop = [
            'type' => $loopType,
            'theme' => 'white',
            'eyebrow' => strtoupper($plural),
            'heading' => "Explore {$plural}",
            'text' => $description,
            'content_type_slug' => $slug,
            'sort' => $loopType === 'content_events_grid' ? 'event_date' : 'newest',
            'limit' => 9,
            'columns' => 3,
            'anchor' => 'latest',
        ];
        if ($loopType === 'content_events_grid') $loop['upcoming_only'] = true;

        return [
            'title' => $plural,
            'slug' => $slug,
            'sort_order' => max(730, (int) $type->sort_order + 700),
            'blocks' => [
                [
                    'type' => 'mini_hero_minimal',
                    'theme' => 'primary',
                    'eyebrow' => $singular,
                    'heading' => $plural,
                    'text' => $description,
                    'button_label' => "Explore {$plural}",
                    'button_url' => '#latest',
                ],
                $loop,
            ],
        ];
    }

    private function pageDefinitions(): array
    {
        return [
            [
                'title' => 'Blog', 'slug' => 'blog', 'sort_order' => 720,
                'blocks' => [
                    ['type'=>'mini_hero_minimal','theme'=>'primary','eyebrow'=>'Journal','heading'=>'Ideas, stories and updates','text'=>'Explore the latest thinking, guides and announcements.','button_label'=>'Latest stories','button_url'=>'#latest'],
                    ['type'=>'content_featured_entry','theme'=>'surface','eyebrow'=>'FEATURED','heading'=>'Featured story','text'=>'A highlighted story from the journal.','content_type_slug'=>'blog','featured_only'=>true,'sort'=>'newest','limit'=>1],
                    ['type'=>'content_grid_classic','theme'=>'white','eyebrow'=>'LATEST','heading'=>'Latest stories','text'=>'Browse recent articles and updates.','content_type_slug'=>'blog','sort'=>'newest','limit'=>6,'anchor'=>'latest'],
                ],
            ],
            [
                'title' => 'Events', 'slug' => 'events', 'sort_order' => 721,
                'blocks' => [
                    ['type'=>'mini_hero_promo','theme'=>'primary','eyebrow'=>'Events','heading'=>'What’s happening next','text'=>'Workshops, launches, gatherings and important dates.','button_label'=>'Upcoming events','button_url'=>'#events'],
                    ['type'=>'content_events_grid','theme'=>'white','eyebrow'=>'UPCOMING EVENTS','heading'=>'Save the date','text'=>'Discover upcoming events and gatherings.','content_type_slug'=>'events','sort'=>'event_date','upcoming_only'=>true,'limit'=>6,'anchor'=>'events'],
                ],
            ],
            [
                'title' => 'Projects', 'slug' => 'projects', 'sort_order' => 722,
                'blocks' => [
                    ['type'=>'mini_hero_split','theme'=>'primary','eyebrow'=>'Projects','heading'=>'Selected work and outcomes','text'=>'Explore projects, case studies and the thinking behind the work.','button_label'=>'View projects','button_url'=>'#projects','image_url'=>'/storage/cms-images/background/background-2.avif','image_alt'=>'Featured project'],
                    ['type'=>'content_grid_editorial','theme'=>'surface','eyebrow'=>'SELECTED WORK','heading'=>'Projects worth exploring','text'=>'A visual collection of recent work and results.','content_type_slug'=>'projects','sort'=>'newest','limit'=>5,'anchor'=>'projects'],
                ],
            ],
        ];
    }

    private function installPage(Website $website, ContentType $type, array $definition): void
    {
        $page = $website->pages()->firstOrNew(['slug' => $definition['slug']]);
        if ($page->exists && $page->page_type !== 'standard') {
            // Preserve legacy Builder/blog pages instead of hard-failing the structured-content installer.
            // The legacy page keeps all of its blocks/data under a deterministic, collision-free slug.
            $legacySlug = $this->uniqueLegacySlug($website, $definition['slug'].'-legacy', $page->id);
            $page->forceFill(['slug' => $legacySlug])->save();
            $page = $website->pages()->firstOrNew(['slug' => $definition['slug']]);
        }

        $existing = is_array($page->blocks) ? $page->blocks : [];
        if ($page->exists && $existing !== [] && ! $this->looksManaged($existing, $type->slug)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'content_pages' => "The /{$definition['slug']} page already contains custom content. Clear or rename it before installing the preset.",
            ]);
        }

        $page->fill([
            'title' => $definition['title'],
            'page_type' => 'standard',
            'parent_id' => null,
            'sort_order' => $definition['sort_order'],
            'status' => 'published',
            'blocks' => $definition['blocks'],
            'published_blocks' => $definition['blocks'],
        ])->save();
    }

    private function uniqueLegacySlug(Website $website, string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        $suffix = 2;

        while ($website->pages()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function looksManaged(array $blocks, string $slug): bool
    {
        $known = ['mini_hero_minimal','mini_hero_split','mini_hero_promo','content_featured_entry','content_grid_classic','content_grid_editorial','content_grid_compact','content_latest_entries','content_events_grid'];
        foreach ($blocks as $block) {
            if (! in_array((string)($block['type'] ?? ''), $known, true)) return false;
            if (str_starts_with((string)($block['type'] ?? ''), 'content_') && ! in_array((string)($block['content_type_slug'] ?? $slug), [$slug], true)) return false;
        }
        return true;
    }

    private function installDemoEntries(Website $website, $types): int
    {
        $rows = [
            'blog' => [
                ['welcome-to-the-journal','Welcome to the journal','Company news','A short introduction to your new journal and what readers can expect.','/storage/cms-images/background/background-1.avif',['author'=>'Editorial Team','reading_time'=>'3 min read'],true],
                ['designing-better-digital-experiences','Designing better digital experiences','Guides','A practical look at creating clearer, faster and more useful digital experiences.','/storage/cms-images/background/background-2.avif',['author'=>'Studio Team','reading_time'=>'6 min read'],false],
                ['behind-the-scenes','Behind the scenes','Updates','A glimpse into the process, decisions and details behind the latest work.','/storage/cms-images/background/background-3.avif',['author'=>'Creative Team','reading_time'=>'4 min read'],false],
            ],
            'events' => [
                ['community-open-day','Community Open Day','Community','Meet the team, explore what’s new, and connect with the community.','/storage/cms-images/background/background-4.avif',['start_date'=>now()->addDays(14)->toDateString(),'end_date'=>now()->addDays(14)->toDateString(),'time'=>'10:00 AM','venue'=>'Main Studio','address'=>'City Centre','registration_url'=>'#'],true],
                ['product-workshop','Product Workshop','Workshop','A hands-on session focused on practical ideas, workflows and better outcomes.','/storage/cms-images/background/background-5.avif',['start_date'=>now()->addDays(28)->toDateString(),'end_date'=>now()->addDays(28)->toDateString(),'time'=>'2:00 PM','venue'=>'Workshop Room','address'=>'City Centre','registration_url'=>'#'],false],
            ],
            'projects' => [
                ['northstar-launch','Northstar Launch','Digital','A focused launch project combining strategy, design and a streamlined digital experience.','/storage/cms-images/background/background-6.avif',['client'=>'Northstar','services'=>'Strategy, Design, Development','completion_date'=>now()->subMonth()->toDateString(),'project_url'=>'#'],true],
                ['studio-refresh','Studio Refresh','Brand','A brand and website refresh designed to create a clearer, more confident customer experience.','/storage/cms-images/background/background-7.avif',['client'=>'Studio Co.','services'=>'Brand, Web Design','completion_date'=>now()->subMonths(2)->toDateString(),'project_url'=>'#'],false],
            ],
        ];

        $created = 0;
        foreach ($rows as $typeSlug => $entries) {
            $type = $types->get($typeSlug);
            if (! $type) continue;
            foreach ($entries as [$slug,$title,$category,$excerpt,$image,$custom,$featured]) {
                $entry = ContentEntry::firstOrCreate([
                    'website_id'=>$website->id,'content_type_id'=>$type->id,'slug'=>$slug,
                ], [
                    'title'=>$title,'excerpt'=>$excerpt,'content'=>$excerpt."\n\nReplace this demo copy with your own content in Posts / Updates.",
                    'status'=>'published','category'=>$category,'tags'=>[$category],
                    'featured_image_url'=>$image,'gallery'=>[],'custom_fields'=>$custom,
                    'seo_title'=>$title,'seo_description'=>$excerpt,'og_image_url'=>$image,
                    'is_featured'=>$featured,'published_at'=>now(),
                ]);
                if ($entry->wasRecentlyCreated) $created++;
            }
        }
        return $created;
    }

    private function installTypeNavigation(Website $website, ContentType $type): bool
    {
        $header = $website->global_header;
        if (! is_array($header)) return false;
        $menu = is_array($header['menu'] ?? null) ? $header['menu'] : [];
        $target = '/'.trim((string) $type->slug, '/');
        $exists = collect($menu)->contains(function ($item) use ($target, $type) {
            $label = strtolower(trim((string) ($item['label'] ?? '')));
            $url = '/'.trim((string) ($item['url'] ?? ''), '/');
            return $url === $target || $label === strtolower(trim((string) $type->name));
        });
        if (! $exists) $menu[] = ['label' => $type->name, 'url' => $target, 'children' => []];
        $header['menu'] = $menu;
        $website->global_header = $header;
        $website->save();
        return ! $exists;
    }

    private function installNavigation(Website $website): bool
    {
        $header = $website->global_header;
        if (! is_array($header)) return false;
        $menu = is_array($header['menu'] ?? null) ? $header['menu'] : [];
        $existing = collect($menu)->map(fn($item)=>strtolower(trim((string)($item['label'] ?? ''))))->all();
        foreach ([['Blog','/blog'],['Events','/events'],['Projects','/projects']] as [$label,$url]) {
            if (! in_array(strtolower($label), $existing, true)) $menu[] = ['label'=>$label,'url'=>$url,'children'=>[]];
        }
        $header['menu'] = $menu;
        $website->global_header = $header;
        $website->save();
        return true;
    }
}
