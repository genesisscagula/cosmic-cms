<?php

namespace App\Services;

use App\Models\Page;
use App\Models\TrialGeneration;
use App\Models\Website;
use App\Support\PageStyleRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TrialStagingPublisherService
{
    public function __construct(
        private readonly PagePublisher $publisher,
        private readonly PreviewDeploymentService $previews,
    ) {}

    /**
     * Return the stable trial preview URL when deployment already succeeded.
     *
     * Older/in-flight jobs can finish PreviewDeploymentService::deploy() and
     * then be interrupted before writing bundle_manifest.staging_url. Repairing
     * that final pointer here prevents an already-published site from remaining
     * stuck behind the Builder's "Preparing preview" state.
     */
    public function existingUrl(TrialGeneration $trial): ?string
    {
        $trial = TrialGeneration::query()->with('website.pages')->find($trial->id);
        if (! $trial || ! $trial->website) {
            return null;
        }

        $storedUrl = data_get($trial->bundle_manifest, 'staging_url');
        if (is_string($storedUrl) && trim($storedUrl) !== '') {
            return $storedUrl;
        }

        $website = $trial->website;
        if (! filled($website->preview_slug) || ! filled($website->last_preview_deployed_at)) {
            return null;
        }

        $home = $website->pages->firstWhere('id', (int) $trial->page_id)
            ?? $website->pages->sortBy([['sort_order', 'asc'], ['id', 'asc']])->first();
        if (! $home) {
            return null;
        }

        $url = $this->previews->urlForPage($website, $home);
        $this->rememberStagingUrl($trial->id, $url, $website->last_preview_deployed_at?->toIso8601String());

        return $url;
    }

    /** Publish a complete unclaimed trial bundle to its no-index staging site. */
    public function publish(TrialGeneration $trial): ?string
    {
        return Cache::lock('trial-staging-publish:'.$trial->id, 180)->block(10, function () use ($trial): ?string {
            $trial=TrialGeneration::query()->with('website.pages')->find($trial->id);
            if(!$trial || $trial->claimed_at || $trial->status!=='ready' || !$trial->website)return null;

            $manifestPages=collect(data_get($trial->bundle_manifest,'pages',[]));
            if($manifestPages->isEmpty() || !$manifestPages->every(fn($page)=>($page['build_status']??null)==='ready'))return null;

            $website=$trial->website;
            $pages=$website->pages()->orderBy('sort_order')->orderBy('id')->get();
            if($pages->isEmpty() || $pages->contains(fn(Page $page)=>!is_array($page->blocks) || $page->blocks===[]))return null;

            $this->syncTrialDesign($trial,$website);
            $website=$website->fresh();
            $compiled=[];
            foreach($pages as $page){
                $compiled[$page->id]=$this->publisher->publish($page,$website);
            }

            DB::transaction(function () use ($trial,$website,$pages,$compiled): void {
                $publishedAt=now();
                foreach($pages as $page){
                    Page::query()->whereKey($page->id)->update([
                        'published_blocks'=>json_encode($page->blocks??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
                        'published_page_style'=>$website->page_style?:$page->page_style,
                        'published_html'=>$compiled[$page->id],
                        'status'=>'published',
                        'published_at'=>$page->published_at?:$publishedAt,
                        'last_published_at'=>$publishedAt,
                        'publish_error'=>null,
                    ]);
                }

                $website->forceFill([
                    'published_page_style'=>$website->page_style?:$pages->first()?->page_style,
                    'published_theme_settings'=>$website->theme_settings,
                    'published_global_header'=>$website->global_header,
                    'published_global_footer'=>$website->global_footer,
                ])->save();

                $manifest=is_array($trial->bundle_manifest)?$trial->bundle_manifest:[];
                $manifest['staged_at']=$publishedAt->toIso8601String();
                $trial->forceFill(['bundle_manifest'=>$manifest,'bundle_status'=>'ready','bundle_error'=>null])->save();
            });

            $this->previews->deploy($website->fresh());
            $home=$website->pages()->find($trial->page_id)?:$website->pages()->orderBy('sort_order')->first();
            if(!$home)throw new RuntimeException('Trial staging Home page is missing.');
            $url=$this->previews->urlForPage($website->fresh(),$home);

            $this->rememberStagingUrl($trial->id, $url);

            return $url;
        });
    }

    private function rememberStagingUrl(int $trialId, string $url, ?string $stagedAt = null): void
    {
        DB::transaction(function () use ($trialId, $url, $stagedAt): void {
            $trial = TrialGeneration::query()->lockForUpdate()->find($trialId);
            if (! $trial) {
                return;
            }

            $manifest = is_array($trial->bundle_manifest) ? $trial->bundle_manifest : [];
            $manifest['staging_url'] = $url;
            $manifest['staged_at'] = $stagedAt ?: now()->toIso8601String();
            $trial->forceFill(['bundle_manifest' => $manifest])->save();
        });
    }

    private function syncTrialDesign(TrialGeneration $trial,Website $website): void
    {
        $theme=is_array($trial->preview_theme)?$trial->preview_theme:($website->theme_settings??[]);
        $header=is_array($website->global_header)?$website->global_header:[];
        $footer=is_array($website->global_footer)?$website->global_footer:[];
        $menu=collect($trial->menu_structure??[])->map(fn(array $page)=>[
            'label'=>(string)($page['title']??'Page'),
            'url'=>(bool)($page['is_home']??false)?'home':(string)($page['slug']??'#'),
        ])->values()->all();

        $header=array_merge($header,[
            'logo_text'=>$trial->business_name,
            'logo_image_url'=>$trial->logo_url?:($header['logo_image_url']??'/storage/branding/your-logo.png'),
            'logo_height'=>max(60,(int)($header['logo_height']??0)),
            'logo_max_width'=>max(300,(int)($header['logo_max_width']??0)),
            'logo_filter_key'=>data_get($theme,'primary','midnight'),
            'overlay_header_on_banner'=>(bool)data_get($theme,'overlay_header_on_banner',false),
            'menu'=>$menu,
        ]);
        $footer=array_merge($footer,[
            'logo_text'=>$trial->business_name,
            'logo_image_url'=>$trial->logo_url?:($footer['logo_image_url']??'/storage/branding/your-logo.png'),
            'logo_height'=>max(56,(int)($footer['logo_height']??0)),
            'logo_filter_key'=>data_get($theme,'primary','midnight'),
        ]);
        $style=PageStyleRegistry::normalize($website->page_style?:$website->pages()->orderBy('sort_order')->value('page_style'));

        $website->forceFill([
            'theme_settings'=>$theme,
            'page_style'=>$style,
            'global_header'=>$header,
            'global_footer'=>$footer,
        ])->save();
        $website->pages()->update(['page_style'=>$style]);
    }
}
