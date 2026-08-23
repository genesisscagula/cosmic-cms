<?php

namespace App\Services;

use Illuminate\Support\Arr;

final class LunaSiteDesignDnaService
{
    private array $contract;

    public function __construct()
    {
        $path=resource_path('luna/site_design_dna.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->contract=is_array($decoded)?$decoded:[];
    }

    public function hydrate(array $siteMemory,array $designDna=[]): array
    {
        $stored=is_array($siteMemory['design_dna']??null)?$siteMemory['design_dna']:[];
        return $this->clean(array_replace_recursive($stored,$designDna));
    }

    public function plannerDirective(array $siteMemory,array $designDna,bool $explicitOverride=false): string
    {
        $dna=$this->hydrate($siteMemory,$designDna);
        if($dna===[]) return '';
        return "SITE DESIGN DNA MEMORY\n".json_encode([
            'theme_family'=>$dna['theme_family']??null,
            'page_style'=>$dna['page_style']??null,
            'design_direction'=>$dna['design_direction']??null,
            'media_direction'=>$dna['media_direction']??null,
            'typography'=>$dna['typography']??[],
            'components'=>$dna['components']??[],
            'section_layout'=>$dna['section_layout']??[],
            'background_style'=>$dna['background_style']??[],
            'custom_brand_theme'=>$dna['custom_brand_theme']??null,
        ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
        ."\nRULE: ".($explicitOverride
            ? 'The user explicitly requested a global design change. A verified new global instruction may replace remembered DNA.'
            : 'Preserve these durable site-level decisions. Do not promote local section or element edits into website DNA.');
    }

    public function updateAfterExecution(
        array $siteMemory,
        array $resolvedDna,
        array $verification,
        array $scopeResolution,
        array $themeSettings=[],
        array $designPlan=[]
    ): array {
        $status=(string)($verification['status']??'failed');
        if(!in_array($status,['complete','partial'],true)) return $siteMemory;

        $ops=array_merge(
            (array)($verification['verified_operations']??[]),
            (array)($verification['additional_verified_operations']??[])
        );
        if(!$this->hasGlobalVerifiedOperation($ops,$scopeResolution)) return $siteMemory;

        $dna=$resolvedDna;
        foreach(['typography','components','section_layout','background_style','custom_brand_theme'] as $group){
            if(is_array($themeSettings[$group]??null) && $themeSettings[$group]!==[]) $dna[$group]=$themeSettings[$group];
        }
        if(!empty($themeSettings['primary'])) $dna['theme_family']=$themeSettings['primary'];
        foreach(['design_direction','media_direction','page_style'] as $key){
            if(!empty($designPlan[$key])) $dna[$key]=$designPlan[$key];
        }

        $next=$siteMemory;
        $next['design_dna']=$this->clean($dna);
        $next['design_dna_meta']=[
            'version'=>$this->contract['version']??2,
            'last_verified_status'=>$status,
            'source'=>'verified_global_execution',
        ];
        return $next;
    }

    private function hasGlobalVerifiedOperation(array $ops,array $scopeResolution): bool
    {
        if(in_array((string)($scopeResolution['scope']??''),['site','global_token'],true)) return true;
        foreach($ops as $op){
            if(!is_array($op))continue;
            if(in_array((string)($op['scope']??''),['site','page'],true)) return true;
            if(in_array((string)($op['action']??''),[
                'theme','brand_color_family','component_token_global',
                'typography_global','section_layout_global','background_global'
            ],true)) return true;
        }
        return false;
    }

    private function clean(array $data): array
    {
        return Arr::where($data,fn($v)=>!($v===null||$v===''||(is_array($v)&&$v===[])));
    }
}
