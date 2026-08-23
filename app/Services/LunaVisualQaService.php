<?php

namespace App\Services;

use Illuminate\Support\Str;

final class LunaVisualQaService
{
    private array $rules;

    public function __construct(
        private readonly LunaPageCompositionService $composition,
        private readonly LunaContentIntelligenceService $content,
        private readonly LunaResponsiveIntelligenceService $responsive
    ) {
        $path=resource_path('luna/visual_qa.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->rules=is_array($decoded)?$decoded:[];
    }

    public function audit(string $prompt,array $sections,array $blocks,array $themeSettings=[]): array
    {
        $context=$this->composition->context($prompt);
        $composition=$this->composition->audit($sections,$context);
        $content=$this->content->audit($blocks);
        $responsive=$this->responsive->auditSections($sections);

        $findings=[];
        foreach($composition['issues']??[] as $issue){
            $findings[]=$this->finding('composition','high',$issue,'Review page structure before publishing.');
        }
        foreach($content['issues']??[] as $issue){
            $findings[]=$this->finding('content','medium',$issue,'Rewrite the affected copy while preserving supplied facts.');
        }
        foreach($responsive['risks']??[] as $risk){
            $types=implode(', ',(array)($risk['risks']??[]));
            $guidance=implode(' ',(array)($risk['guidance']??[]));
            $findings[]=$this->finding(
                'responsive','medium',
                "Responsive-risk Spark '{$risk['section']}' ({$types}).",
                $guidance!==''?$guidance:'Verify tablet/mobile behavior.'
            );
        }

        $findings=array_merge($findings,$this->tokenFindings($themeSettings));
        $findings=array_merge($findings,$this->blockFindings($blocks));

        $score=$this->score($findings);
        return [
            'mode'=>'diagnose_then_recommend',
            'auto_mutated'=>false,
            'score'=>$score,
            'grade'=>$this->grade($score),
            'pass'=>$score>=82 && !collect($findings)->contains(fn($f)=>in_array($f['severity'],['critical','high'],true)),
            'findings'=>$findings,
            'summary'=>$this->summary($findings),
            'limitations'=>[
                'Structural QA cannot prove exact visual contrast or pixel alignment.',
                'Builder/Live rendering parity is a separate audit.',
            ],
        ];
    }

    private function tokenFindings(array $settings): array
    {
        $findings=[];
        $layout=(array)($settings['section_layout']??[]);
        $type=(array)($settings['typography']??[]);
        $components=(array)($settings['components']??[]);

        foreach([
            ['section_layout',['py','py_tablet','py_mobile'],'Section vertical spacing lacks complete desktop/tablet/mobile tokens.'],
            ['section_layout',['grid_gap','grid_gap_tablet','grid_gap_mobile'],'Grid spacing lacks complete desktop/tablet/mobile tokens.'],
        ] as [$group,$keys,$message]){
            $values=$group==='section_layout'?$layout:[];
            if(count(array_filter($keys,fn($key)=>isset($values[$key])&&$values[$key]!==''))<count($keys)){
                $findings[]=$this->finding('design_consistency','medium',$message,'Use centralized responsive spacing tokens.');
            }
        }

        $headingResponsive=0;
        foreach(['h1','h2','h3'] as $role){
            if(isset($type[$role.'_size_tablet'])||isset($type[$role.'_size_mobile']))$headingResponsive++;
        }
        if($type!==[] && $headingResponsive===0){
            $findings[]=$this->finding('design_consistency','low','Typography has no explicit H1-H3 responsive overrides.','Keep the centralized clamp defaults or define responsive role tokens if the design needs custom scaling.');
        }

        foreach(['button_radius','card_radius','image_radius'] as $key){
            if(isset($components[$key]) && trim((string)$components[$key])===''){
                $findings[]=$this->finding('design_consistency','low',"Empty component token: {$key}.",'Use the centralized component radius token or remove the empty override.');
            }
        }
        return $findings;
    }

    private function blockFindings(array $blocks): array
    {
        $findings=[];
        foreach(array_values($blocks) as $index=>$block){
            if(!is_array($block))continue;
            foreach($this->flatten($block) as $path=>$value){
                $lower=Str::lower($path);
                if(Str::endsWith($lower,['.alt','_alt','alt_text']) && trim((string)$value)===''){
                    $findings[]=$this->finding('accessibility_visual','medium',"Block {$index} has empty image alt text at {$path}.",'Add concise meaningful alt text, or mark decorative media appropriately.');
                }
                if((Str::endsWith($lower,['button_label','cta_label','link_label'])||preg_match('/\.(label|button_text)$/',$lower)) && trim((string)$value)===''){
                    $findings[]=$this->finding('conversion','medium',"Block {$index} has an empty action label at {$path}.",'Add a short descriptive action label.');
                }
                if((Str::endsWith($lower,['image','image_url','video_url','src'])) && is_string($value) && trim($value)===''){
                    $findings[]=$this->finding('media','low',"Block {$index} has an empty media source at {$path}.",'Supply media or intentionally use the Spark fallback.');
                }
            }
        }
        return $findings;
    }

    private function flatten(array $value,string $prefix=''): array
    {
        $out=[];
        foreach($value as $key=>$item){
            $path=$prefix===''?(string)$key:$prefix.'.'.$key;
            if(is_scalar($item)||$item===null)$out[$path]=(string)$item;
            elseif(is_array($item))$out=array_merge($out,$this->flatten($item,$path));
        }
        return $out;
    }

    private function finding(string $category,string $severity,string $message,string $recommendation): array
    {
        return compact('category','severity','message','recommendation');
    }

    private function score(array $findings): int
    {
        $penalty=0;
        foreach($findings as $finding){
            $penalty+=match($finding['severity']??'low'){
                'critical'=>30,'high'=>18,'medium'=>8,default=>2,
            };
        }
        return max(0,100-$penalty);
    }

    private function grade(int $score): string
    {
        return match(true){$score>=92=>'excellent',$score>=82=>'good',$score>=70=>'needs_polish',default=>'needs_attention'};
    }

    private function summary(array $findings): array
    {
        $counts=['critical'=>0,'high'=>0,'medium'=>0,'low'=>0];
        foreach($findings as $finding){
            $severity=$finding['severity']??'low';
            if(isset($counts[$severity]))$counts[$severity]++;
        }
        return ['total'=>count($findings),'by_severity'=>$counts];
    }
}
