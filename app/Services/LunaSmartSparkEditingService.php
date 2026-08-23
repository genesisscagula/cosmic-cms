<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class LunaSmartSparkEditingService
{
    private array $contract;

    public function __construct()
    {
        $path=resource_path('luna/smart_spark_editing.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->contract=is_array($decoded)?$decoded:[];
    }

    public function sanitizeEdit(array $block,array $changes,array $elementContext=[]): array
    {
        foreach($this->contract['protected_keys']??[] as $key) unset($changes[$key]);

        $matched=$this->matchedPaths($elementContext);
        if($matched!==[]){
            $next=$block;
            $applied=[];
            foreach($matched as $path){
                $leaf=Str::afterLast($path,'.');
                if(array_key_exists($path,$changes)){
                    Arr::set($next,$path,$changes[$path]);$applied[]=$path;continue;
                }
                if(array_key_exists($leaf,$changes)){
                    Arr::set($next,$path,$changes[$leaf]);$applied[]=$path;
                }
            }
            return ['block'=>$next,'paths'=>$applied,'mode'=>'element'];
        }

        $safe=array_intersect_key($changes,$block);
        return ['block'=>array_merge($block,$safe),'paths'=>array_keys($safe),'mode'=>'block'];
    }

    public function applyRepeaterIntent(string $prompt,array $block,array $elementContext=[]): ?array
    {
        $q=Str::lower(trim($prompt));
        $collectionKey=$this->collectionKey($block,$elementContext);
        if($collectionKey===null)return null;
        $items=array_values((array)($block[$collectionKey]??[]));
        if($items===[])return null;

        $add=Str::contains($q,['add another','add one','add a new','add new','add more','one more','another card','another item','another service','another testimonial','another faq','another feature','another step']);
        $remove=Str::contains($q,['remove this','delete this','remove the','delete the','remove card','delete card','remove item','delete item','remove service','delete service']);
        if(!$add && !$remove)return null;

        $index=$this->itemIndex($elementContext);
        if($add){
            $source=$index!==null && isset($items[$index])?$items[$index]:$items[array_key_last($items)];
            $clone=is_array($source)?$source:[];
            $clone=$this->clearIdentityFields($clone);
            $items[]=$clone;
            $next=$block;$next[$collectionKey]=$items;
            return [
                'block'=>$next,'collection'=>$collectionKey,'action'=>'repeater_add',
                'item_index'=>count($items)-1,'source_index'=>$index??count($items)-2,
            ];
        }

        if($index===null){
            $index=$this->matchItemByPrompt($q,$items);
        }
        if($index===null || !isset($items[$index]))return [
            'block'=>$block,'collection'=>$collectionKey,'action'=>'repeater_remove_unresolved','item_index'=>null
        ];

        array_splice($items,$index,1);
        $next=$block;$next[$collectionKey]=$items;
        return ['block'=>$next,'collection'=>$collectionKey,'action'=>'repeater_remove','item_index'=>$index];
    }

    public function contractDirective(): string
    {
        return "SMART SPARK EDIT CONTRACT\n".json_encode([
            'mode'=>$this->contract['mode']??'schema_preserving_edit',
            'rules'=>$this->contract['rules']??[],
            'collection_keys'=>$this->contract['collection_keys']??[],
        ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }

    private function collectionKey(array $block,array $context): ?string
    {
        $hint=trim((string)($context['collectionKey']??$context['collection_key']??''));
        if($hint!=='' && isset($block[$hint]) && is_array($block[$hint]))return $hint;
        foreach($this->contract['collection_keys']??[] as $key){
            if(isset($block[$key]) && is_array($block[$key]) && $block[$key]!==[])return $key;
        }
        return null;
    }

    private function matchedPaths(array $context): array
    {
        $paths=$context['matched_paths']??$context['matchedPaths']??[];
        if(is_string($paths))$paths=[$paths];
        return array_values(array_filter((array)$paths,fn($p)=>is_string($p)&&trim($p)!==''));
    }

    private function itemIndex(array $context): ?int
    {
        foreach(['itemIndex','item_index','cardIndex','card_index'] as $key){
            if(isset($context[$key])&&is_numeric($context[$key]))return (int)$context[$key];
        }
        return null;
    }

    private function clearIdentityFields(array $item): array
    {
        foreach(['id','uuid','key','_key','slug'] as $key)unset($item[$key]);
        return $item;
    }

    private function matchItemByPrompt(string $q,array $items): ?int
    {
        $matches=[];
        foreach($items as $index=>$item){
            if(!is_array($item))continue;
            foreach(['title','heading','label','name','question'] as $key){
                $value=Str::lower(trim((string)($item[$key]??'')));
                if($value!=='' && Str::contains($q,$value)){$matches[]=$index;break;}
            }
        }
        $matches=array_values(array_unique($matches));
        return count($matches)===1?$matches[0]:null;
    }
}
