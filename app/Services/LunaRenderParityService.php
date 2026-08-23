<?php

namespace App\Services;

final class LunaRenderParityService
{
    private array $manifest;

    public function __construct()
    {
        $path=resource_path('luna/render_parity.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->manifest=is_array($decoded)?$decoded:[];
    }

    public function audit(array $blocks,array $themeSettings=[]): array
    {
        $supported=array_flip((array)($this->manifest['registered_sparks']??[]));
        $unsupported=[];
        foreach(array_values($blocks) as $index=>$block){
            $type=is_array($block)?trim((string)($block['type']??'')):'';
            if($type==='' || !isset($supported[$type])){
                $unsupported[]=['index'=>$index,'type'=>$type?:'unknown'];
            }
        }

        $context=[
            'primary'=>$themeSettings['primary']??null,
            'page_style'=>$themeSettings['page_style']??null,
            'typography'=>$themeSettings['typography']??[],
            'section_layout'=>$themeSettings['section_layout']??[],
            'background_style'=>$themeSettings['background_style']??[],
            'components'=>$themeSettings['components']??[],
            'custom_brand_theme'=>$themeSettings['custom_brand_theme']??null,
        ];
        $fingerprint=hash('sha256',json_encode([
            'version'=>$this->manifest['version']??'unknown',
            'blocks'=>array_values($blocks),
            'context'=>$context,
        ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');

        return [
            'render_contract'=>$this->manifest['version']??'unknown',
            'registered_renderer_coverage'=>[
                'builder'=>(int)($this->manifest['builder_registry_count']??0),
                'live'=>(int)($this->manifest['live_compiler_coverage_count']??0),
                'missing'=>$this->manifest['unsupported_registered_sparks']??[],
            ],
            'page_supported'=>$unsupported===[],
            'unsupported_blocks'=>$unsupported,
            'data_context_fingerprint'=>$fingerprint,
            'shared_contracts'=>$this->manifest['shared_contracts']??[],
            'runtime_visual_parity_verified'=>false,
            'runtime_visual_parity_required'=>(bool)($this->manifest['runtime_visual_parity_required']??true),
            'note'=>'Renderer/data-contract coverage passed structurally where indicated. Visual/runtime parity still requires Builder vs published output comparison.',
        ];
    }
}
