<?php

namespace App\Services;

use Illuminate\Support\Str;

final class LunaInspectService
{
    /**
     * Read-only inspection of state already supplied by the Builder. This service
     * never writes models, arrays passed by the caller, cache, files, or credits.
     */
    public function inspect(string $prompt, array $context): array
    {
        $q=Str::lower(trim($prompt));
        $blocks=array_values(is_array($context['blocks']??null)?$context['blocks']:[]);
        $header=is_array($context['header']??null)?$context['header']:[];
        $footer=is_array($context['footer']??null)?$context['footer']:[];
        $theme=$context['theme']??null;
        $typography=is_array($context['typography']??null)?$context['typography']:[];
        $sectionLayout=is_array($context['section_layout']??null)?$context['section_layout']:[];
        $components=is_array($context['components']??null)?$context['components']:[];
        $scope=(string)($context['scope']??'page');
        $targetIndex=is_numeric($context['target_index']??null)?(int)$context['target_index']:-1;
        $selected=($scope==='section' && isset($blocks[$targetIndex]) && is_array($blocks[$targetIndex]))?$blocks[$targetIndex]:null;

        $facts=[
            'read_only'=>true,
            'verified'=>true,
            'scope'=>$scope,
            'page'=>[
                'section_count'=>count($blocks),
                'section_types'=>array_values(array_map(fn($b)=>is_array($b)?(string)($b['type']??'unknown'):'unknown',$blocks)),
            ],
        ];

        if(Str::contains($q,['how many section','number of section'])){
            $facts['answer']=['kind'=>'count','label'=>'sections','value'=>count($blocks)];
        } elseif(Str::contains($q,['how many card','number of card','how many service'])){
            $source=$selected??$this->firstMatchingBlock($blocks,['service','card']);
            $count=$this->repeaterCount($source);
            $facts['answer']=['kind'=>'count','label'=>'items','value'=>$count,'section_type'=>is_array($source)?($source['type']??null):null];
        } elseif(Str::contains($q,['theme','brand color','brand colour','primary color','primary colour'])){
            $facts['answer']=['kind'=>'design_state','theme'=>$theme,'brand_color'=>$this->firstValue([$context,$theme],['primary','brand_color','brandColor','color'])];
        } elseif(Str::contains($q,['font','typography','h1','h2','h3','line height','letter spacing'])){
            $facts['answer']=['kind'=>'typography','values'=>$this->filterByPrompt($typography,$q,['font','h1','h2','h3','heading','body','line','letter','size','weight'])];
        } elseif(Str::contains($q,['padding','spacing','gap','margin','height','width'])){
            $source=$selected??[];
            $facts['answer']=['kind'=>'layout','section'=>$this->filterByPrompt($source,$q,['padding','spacing','gap','margin','height','width','py','px']),'global'=>$this->filterByPrompt($sectionLayout,$q,['padding','spacing','gap','margin','height','width','py','px'])];
        } elseif(Str::contains($q,['image','photo','picture','video','media','logo'])){
            $source=$selected??[];
            $facts['answer']=[
                'kind'=>'media',
                'section'=>$this->collectMedia($source),
                'header'=>$this->collectMedia($header),
                'footer'=>$this->collectMedia($footer),
            ];
        } elseif(Str::contains($q,['header','menu','navigation','nav'])){
            $facts['answer']=['kind'=>'header','values'=>$header];
        } elseif(Str::contains($q,['footer'])){
            $facts['answer']=['kind'=>'footer','values'=>$footer];
        } elseif(Str::contains($q,['button','cta'])){
            $facts['answer']=['kind'=>'component','values'=>$this->filterByPrompt($selected??$components,$q,['button','label','url','cta','height','radius'])];
        } elseif($selected){
            $facts['answer']=['kind'=>'section','index'=>$targetIndex,'type'=>$selected['type']??null,'heading'=>$selected['heading']??($selected['title']??null),'media'=>$this->collectMedia($selected)];
        } else {
            $facts['answer']=['kind'=>'page_summary','section_count'=>count($blocks),'header_present'=>$header!==[],'footer_present'=>$footer!==[],'theme'=>$theme];
        }

        return $facts;
    }

    private function firstMatchingBlock(array $blocks,array $tokens): ?array
    {
        foreach($blocks as $block){
            if(!is_array($block)) continue;
            $type=Str::lower((string)($block['type']??''));
            foreach($tokens as $token) if(Str::contains($type,$token)) return $block;
        }
        return null;
    }

    private function repeaterCount(?array $block): int
    {
        if(!$block) return 0;
        foreach(['items','services','cards','testimonials','faqs','team','features','logos','gallery','steps'] as $key){
            if(isset($block[$key]) && is_array($block[$key])) return count($block[$key]);
        }
        foreach(['service_count','item_count','card_count','count'] as $key){
            if(isset($block[$key]) && is_numeric($block[$key])) return max(0,(int)$block[$key]);
        }
        $count=0;
        foreach(array_keys($block) as $key){
            if(preg_match('/^(?:featured|service_(?:two|three|four|five|six|seven))_title$/',$key)) $count++;
        }
        return $count;
    }

    private function collectMedia(array $source): array
    {
        $out=[];
        $walk=function($value,$path='') use (&$walk,&$out){
            if(!is_array($value)) return;
            foreach($value as $key=>$item){
                $p=$path===''?(string)$key:$path.'.'.$key;
                if(is_array($item)){ $walk($item,$p); continue; }
                if(!is_scalar($item)) continue;
                $lk=Str::lower((string)$key);
                if(Str::contains($lk,['image','photo','video','logo','media','background']) && trim((string)$item)!=='') $out[$p]=$item;
            }
        };
        $walk($source);
        return $out;
    }

    private function filterByPrompt(array $source,string $q,array $tokens): array
    {
        $flat=[];
        $walk=function($value,$path='') use (&$walk,&$flat,$q,$tokens){
            if(!is_array($value)) return;
            foreach($value as $key=>$item){
                $p=$path===''?(string)$key:$path.'.'.$key;
                if(is_array($item)){ $walk($item,$p); continue; }
                if(!is_scalar($item)) continue;
                $lp=Str::lower($p);
                foreach($tokens as $token){
                    if(Str::contains($q,$token) && Str::contains($lp,$token)){ $flat[$p]=$item; break; }
                }
            }
        };
        $walk($source);
        if($flat!==[]) return $flat;
        $simple=[];
        foreach($source as $key=>$value) if(is_scalar($value)) $simple[$key]=$value;
        return array_slice($simple,0,24,true);
    }

    private function firstValue(array $sources,array $keys): mixed
    {
        foreach($sources as $source){
            if(!is_array($source)) continue;
            foreach($keys as $key) if(array_key_exists($key,$source) && is_scalar($source[$key])) return $source[$key];
        }
        return null;
    }
}
