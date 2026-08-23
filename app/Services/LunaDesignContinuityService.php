<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Arr;

final class LunaDesignContinuityService
{
    private array $contract;

    public function __construct()
    {
        $path=resource_path('luna/design_continuity.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->contract=is_array($decoded)?$decoded:[];
    }

    public function designDna(Website $website,array $clientTheme=[],array $siteMemory=[]): array
    {
        $settings=(array)($website->theme_settings??[]);
        $theme=array_replace_recursive($settings,$clientTheme);
        $typography=$this->clean((array)($theme['typography']??[]));
        $components=$this->clean((array)($theme['components']??[]));
        $sectionLayout=$this->clean((array)($theme['section_layout']??[]));
        $background=$this->clean((array)($theme['background_style']??[]));

        $primary=trim((string)($theme['primary']??''));
        $custom=is_array($theme['custom_brand_theme']??null)?$theme['custom_brand_theme']:null;
        $established=(bool)($theme['luna_theme_locked']??false)
            || $website->pages()->get(['blocks'])->contains(fn($page)=>is_array($page->blocks??null)&&count($page->blocks)>0);

        return [
            'version'=>1,
            'established'=>$established,
            'theme_family'=>$primary!==''?$primary:null,
            'custom_brand_theme'=>$custom,
            'typography'=>$typography,
            'components'=>$components,
            'section_layout'=>$sectionLayout,
            'background_style'=>$background,
            'page_style'=>$website->page_style ?: ($siteMemory['page_style']??null),
            'design_direction'=>$siteMemory['design_direction']??null,
            'media_direction'=>$siteMemory['media_direction']??null,
            'source'=>'website_design_system',
        ];
    }

    public function plannerConstraint(array $dna,bool $explicitRebrand=false): string
    {
        if(!($dna['established']??false) || $explicitRebrand)return '';
        $packet=[
            'theme_family'=>$dna['theme_family']??null,
            'page_style'=>$dna['page_style']??null,
            'design_direction'=>$dna['design_direction']??null,
            'media_direction'=>$dna['media_direction']??null,
            'typography'=>$dna['typography']??[],
            'components'=>$dna['components']??[],
            'section_layout'=>$dna['section_layout']??[],
            'background_style'=>$dna['background_style']??[],
            'has_custom_brand_theme'=>is_array($dna['custom_brand_theme']??null),
        ];
        return "EXISTING SITE DESIGN DNA — AUTHORITATIVE FOR CONTINUITY:\n"
            .json_encode($packet,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
            ."\nPreserve this visual language. You may choose a different registered page composition appropriate to the requested page intent, but do not silently rebrand, replace fonts, reset radii/spacing, or clone the sibling page layout.";
    }

    public function memory(array $existing,array $dna,array $designPlan=[]): array
    {
        $next=$existing;
        $next['design_dna']=[
            'theme_family'=>$dna['theme_family']??($designPlan['theme']??null),
            'page_style'=>$dna['page_style']??null,
            'design_direction'=>$designPlan['design_direction']??($dna['design_direction']??null),
            'media_direction'=>$designPlan['media_direction']??($dna['media_direction']??null),
            'typography'=>$dna['typography']??[],
            'components'=>$dna['components']??[],
            'section_layout'=>$dna['section_layout']??[],
            'background_style'=>$dna['background_style']??[],
        ];
        return $next;
    }

    private function clean(array $values): array
    {
        return Arr::where($values,fn($value)=>$value!==null&&$value!=='');
    }
}
