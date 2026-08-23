<?php

namespace App\Services;

use Illuminate\Support\Str;

final class LunaResponsiveIntelligenceService
{
    private array $rules;

    public function __construct()
    {
        $path=resource_path('luna/responsive_intelligence.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->rules=is_array($decoded)?$decoded:[];
    }

    public function contract(array $sections=[]): array
    {
        $audit=$this->auditSections($sections);
        return [
            'breakpoints'=>$this->rules['breakpoints']??['tablet_max_px'=>1024,'mobile_max_px'=>767],
            'defaults'=>$this->rules['defaults']??[],
            'rules'=>$this->rules['rules']??[],
            'section_risks'=>$audit['risks'],
            'risk_count'=>count($audit['risks']),
        ];
    }

    public function plannerDirective(array $sections=[]): string
    {
        return "RESPONSIVE DESIGN CONTRACT\n".json_encode(
            $this->contract($sections),
            JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
        );
    }

    public function auditSections(array $sections): array
    {
        $risks=[];
        foreach(array_values($sections) as $index=>$section){
            if(!is_string($section))continue;
            $name=Str::lower($section);
            $types=[];
            foreach($this->rules['risk_tokens']??[] as $risk=>$tokens){
                foreach($tokens as $token){
                    if(Str::contains($name,Str::lower((string)$token))){
                        $types[]=$risk;
                        break;
                    }
                }
            }
            if($types!==[]){
                $risks[]=[
                    'index'=>$index,
                    'section'=>$section,
                    'risks'=>array_values(array_unique($types)),
                    'guidance'=>$this->guidanceFor($types),
                ];
            }
        }
        return ['pass'=>true,'risks'=>$risks];
    }

    public function recommendedTokens(array $current=[]): array
    {
        $d=$this->rules['defaults']??[];
        return [
            'section_layout'=>array_replace([
                'py'=>$d['section_y']['desktop']??'100px',
                'py_tablet'=>$d['section_y']['tablet']??'80px',
                'py_mobile'=>$d['section_y']['mobile']??'56px',
                'px'=>$d['section_x']['desktop']??'28px',
                'px_tablet'=>$d['section_x']['tablet']??'24px',
                'px_mobile'=>$d['section_x']['mobile']??'20px',
                'grid_gap'=>$d['grid_gap']['desktop']??'24px',
                'grid_gap_tablet'=>$d['grid_gap']['tablet']??'20px',
                'grid_gap_mobile'=>$d['grid_gap']['mobile']??'16px',
                'card_padding'=>$d['card_padding']['desktop']??'32px',
                'card_padding_tablet'=>$d['card_padding']['tablet']??'28px',
                'card_padding_mobile'=>$d['card_padding']['mobile']??'22px',
            ],(array)($current['section_layout']??[])),
            'components'=>array_replace([
                'button_height'=>$d['button']['desktop_height']??'52px',
                'button_height_mobile'=>$d['button']['mobile_height']??'48px',
                'button_px'=>$d['button']['desktop_px']??'24px',
                'button_px_mobile'=>$d['button']['mobile_px']??'18px',
            ],(array)($current['components']??[])),
        ];
    }

    private function guidanceFor(array $types): array
    {
        $out=[];
        if(in_array('grid',$types,true))$out[]='Collapse multi-column cards progressively and keep card widths fluid.';
        if(in_array('media',$types,true))$out[]='Preserve media framing/aspect ratio and avoid oversized fixed-height media on small screens.';
        if(in_array('wide',$types,true))$out[]='Provide a contained small-screen strategy; never force viewport overflow.';
        if(in_array('interactive',$types,true))$out[]='Keep touch targets usable and do not depend on hover-only controls.';
        return $out;
    }
}
