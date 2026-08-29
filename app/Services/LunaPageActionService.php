<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Website;
use App\Support\PageStyleRegistry;
use Illuminate\Support\Str;

/**
 * Batch 5 bounded Page actions.
 *
 * Only deterministic page metadata/layout mutations are executed here. New-page
 * generation and page regeneration deliberately fall through to Cosmic's
 * existing generation/composition pipeline so this service never duplicates AI.
 */
final class LunaPageActionService
{
    /** @return array<string,mixed>|null */
    public function apply(Website $website, string $prompt, array $canonicalIntent, ?Page $currentPage = null): ?array
    {
        if ((string) data_get($canonicalIntent, 'routing.menu_scope') !== 'page') return null;
        $action=(string) data_get($canonicalIntent,'routing.scope_action','');
        if ($action==='') return null;

        if (in_array($action,['add_page','regenerate_page','edit_page','delete_page'],true)) {
            return ['handled'=>false,'scope_action'=>$action];
        }

        $page=$this->resolvePage($website,$prompt,$currentPage);
        if(!$page) return $this->fail($action,'I could not safely identify which page to change.');

        return match($action){
            'rename_page'=>$this->rename($page,$prompt),
            'duplicate_page'=>$this->duplicate($website,$page),
            'page_seo'=>$this->seo($page,$prompt),
            'page_layout'=>$this->layout($website,$page,$prompt),
            'page_settings'=>$this->settings($website,$page,$prompt),
            default=>['handled'=>false,'scope_action'=>$action],
        };
    }

    private function resolvePage(Website $website,string $prompt,?Page $current): ?Page
    {
        $pages=$website->pages()->orderBy('sort_order')->orderBy('id')->get();
        $q=Str::lower($prompt);
        foreach($pages as $page){
            $title=Str::lower(trim((string)$page->title));
            $slug=Str::lower(trim((string)$page->slug));
            if(($title!=='' && Str::contains($q,$title)) || ($slug!=='' && preg_match('/\\b'.preg_quote($slug,'/').'\\b/i',$prompt))) return $page;
        }
        return $current && $current->website_id===$website->id ? $current : null;
    }

    private function rename(Page $page,string $prompt): array
    {
        $name=null;
        foreach([
            '/\\brename(?:\\s+the)?(?:\\s+page)?(?:\\s+.+?)?\\s+to\\s+["“]?([^"”]+?)["”]?(?:[.!?]|$)/i',
            '/\\bchange(?:\\s+the)?\\s+page\\s+title\\s+to\\s+["“]?([^"”]+?)["”]?(?:[.!?]|$)/i',
        ] as $rx){ if(preg_match($rx,$prompt,$m)){ $name=trim($m[1]); break; } }
        if(!$name || mb_strlen($name)>255) return $this->fail('rename_page','Please include the new page name.');
        $old=$page->title; $page->title=$name; $page->save();
        return $this->ok('rename_page',$page,[['op'=>'page.rename','page_id'=>$page->id,'from'=>$old,'to'=>$name]]);
    }

    private function duplicate(Website $website,Page $page): array
    {
        $base=trim((string)($page->title?:'Untitled Page'));
        $title=$base.' Copy'; $n=2;
        while($website->pages()->whereRaw('LOWER(title) = ?',[mb_strtolower($title)])->exists()) $title=$base.' Copy '.$n++;
        $slug=Str::slug($title)?:'page-copy'; $baseSlug=$slug; $n=2;
        while($website->pages()->where('slug',$slug)->exists()) $slug=$baseSlug.'-'.$n++;
        $copy=$website->pages()->create([
            'title'=>$title,'slug'=>$slug,'parent_id'=>$page->parent_id,
            'sort_order'=>((int)$website->pages()->where('parent_id',$page->parent_id)->max('sort_order'))+1,
            'page_type'=>$page->page_type,'page_style'=>$page->page_style,'blocks'=>$page->blocks??[],
            'seo_title'=>$page->seo_title,'meta_description'=>$page->meta_description,'og_image_url'=>$page->og_image_url,
            'canonical_url'=>null,'is_indexable'=>$page->is_indexable,'status'=>'draft',
        ]);
        return $this->ok('duplicate_page',$copy,[['op'=>'page.duplicate','source_page_id'=>$page->id,'page_id'=>$copy->id]]);
    }

    private function seo(Page $page,string $prompt): array
    {
        $changes=[];
        if(preg_match('/\\b(?:seo|meta)\\s+title\\s+(?:to|as)\\s+["“]?([^"”]+?)["”]?(?:[.!?]|$)/i',$prompt,$m)) $changes['seo_title']=trim($m[1]);
        if(preg_match('/\\bmeta\\s+description\\s+(?:to|as)\\s+["“]?([^"”]+?)["”]?(?:[.!?]|$)/i',$prompt,$m)) $changes['meta_description']=trim($m[1]);
        if(preg_match('/\\bcanonical(?:\\s+url)?\\s+(?:to|as)\\s+(https?:\\/\\/\\S+)/i',$prompt,$m)) $changes['canonical_url']=rtrim($m[1],'.');
        if(preg_match('/\\b(noindex|not indexable|do not index)\\b/i',$prompt)) $changes['is_indexable']=false;
        elseif(preg_match('/\\b(indexable|allow indexing|index this page)\\b/i',$prompt)) $changes['is_indexable']=true;
        if(!$changes) return $this->fail('page_seo','Please include the SEO field and value to update.');
        if(isset($changes['seo_title']) && mb_strlen($changes['seo_title'])>255) return $this->fail('page_seo','The SEO title is too long.');
        if(isset($changes['meta_description']) && mb_strlen($changes['meta_description'])>1000) return $this->fail('page_seo','The meta description is too long.');
        $page->fill($changes)->save();
        return $this->ok('page_seo',$page,[['op'=>'page.seo.update','page_id'=>$page->id,'fields'=>array_keys($changes)]]);
    }

    private function layout(Website $website,Page $page,string $prompt): array
    {
        $style=null;
        foreach(['balanced','clean','premium'] as $candidate) if(preg_match('/\\b'.preg_quote($candidate,'/').'\\b/i',$prompt)){$style=$candidate;break;}
        if(!$style || !PageStyleRegistry::exists($style)) return $this->fail('page_layout','Please choose Balanced, Clean, or Premium.');
        $blocks=collect($page->blocks??[])->map(function($block){
            if(!is_array($block)) return $block;
            $block['theme']='auto'; unset($block['resolvedTheme']); return $block;
        })->values()->all();
        $website->page_style=$style; $website->save();
        $website->pages()->update(['page_style'=>$style]);
        $page->blocks=$blocks; $page->status='draft'; $page->publish_error=null; $page->save();
        return $this->ok('page_layout',$page->fresh(),[['op'=>'page.layout','page_id'=>$page->id,'style'=>$style]],['page_style'=>$style,'blocks'=>$blocks]);
    }

    private function settings(Website $website,Page $page,string $prompt): array
    {
        $changes=[];
        if(preg_match('/\\bslug\\s+(?:to|as)\\s+["“]?([a-z0-9][a-z0-9_-]*)["”]?/i',$prompt,$m)){
            $slug=Str::slug($m[1]);
            if($website->pages()->where('slug',$slug)->whereKeyNot($page->id)->exists()) return $this->fail('page_settings','That page URL is already in use.');
            $changes['slug']=$slug;
        }
        if(preg_match('/\\bpage\\s+type\\s+(?:to|as)\\s+(standard|blog)\\b/i',$prompt,$m)) $changes['page_type']=Str::lower($m[1]);
        if(!$changes) return $this->fail('page_settings','Please include the page setting and value to update.');
        $page->fill($changes)->save();
        return $this->ok('page_settings',$page,[['op'=>'page.settings.update','page_id'=>$page->id,'fields'=>array_keys($changes)]]);
    }

    private function ok(string $action,Page $page,array $ops,array $extra=[]): array
    { return array_merge(['handled'=>true,'success'=>true,'scope_action'=>$action,'page'=>$page->fresh(),'operations'=>$ops],$extra); }
    private function fail(string $action,string $message): array
    { return ['handled'=>true,'success'=>false,'scope_action'=>$action,'message'=>$message,'operations'=>[]]; }
}
