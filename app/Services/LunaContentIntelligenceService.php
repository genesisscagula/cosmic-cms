<?php

namespace App\Services;

use Illuminate\Support\Str;

final class LunaContentIntelligenceService
{
    private array $rules;

    public function __construct()
    {
        $path=resource_path('luna/content_intelligence.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->rules=is_array($decoded)?$decoded:[];
    }

    public function contract(string $prompt,array $composition=[]): string
    {
        $packet=[
            'page_intent'=>$composition['page_intent']??'home',
            'industry'=>$composition['industry']??'professional-services',
            'industry_focus'=>$this->rules['industry_focus'][$composition['industry']??'professional-services']??[],
            'forbidden_inventions'=>$this->rules['forbidden_inventions']??[],
            'generic_phrases_to_avoid'=>$this->rules['generic_phrases']??[],
            'length_guidance'=>$this->rules['length_guidance']??[],
            'cta_examples'=>$this->rules['page_cta'][$composition['page_intent']??'home']??[],
            'principles'=>$this->rules['principles']??[],
        ];
        return "CONTENT INTELLIGENCE CONTRACT\n".json_encode($packet,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }

    public function audit(array $blocks): array
    {
        $issues=[];$seen=[];
        $generic=array_map('strtolower',$this->rules['generic_phrases']??[]);
        foreach($blocks as $index=>$block){
            if(!is_array($block))continue;
            foreach($this->strings($block) as $path=>$value){
                $lower=Str::lower(trim($value));
                if($lower==='')continue;
                foreach($generic as $phrase){
                    if($phrase!=='' && Str::contains($lower,$phrase)){
                        $issues[]="Block {$index} uses generic phrase '{$phrase}' at {$path}.";
                    }
                }
                if($this->looksLikeHeading($path)){
                    $key=preg_replace('/[^a-z0-9]+/',' ',$lower)??$lower;
                    $key=trim($key);
                    if(strlen($key)>=8){
                        if(isset($seen[$key]))$issues[]="Repeated heading/callout '{$value}'.";
                        $seen[$key]=true;
                    }
                }
            }
        }
        return ['pass'=>$issues===[],'issues'=>array_values(array_unique($issues))];
    }

    private function strings(array $value,string $prefix=''): array
    {
        $out=[];
        foreach($value as $key=>$item){
            $path=$prefix===''?(string)$key:$prefix.'.'.$key;
            if(is_string($item))$out[$path]=$item;
            elseif(is_array($item))$out=array_merge($out,$this->strings($item,$path));
        }
        return $out;
    }

    private function looksLikeHeading(string $path): bool
    {
        return (bool)preg_match('/(^|\.)(heading|title|headline|tagline)$/i',$path);
    }
}
