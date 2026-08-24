<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class LunaSmartSparkEditingService
{
    private array $contract;

    public function __construct(private readonly SparkEditMutationValidator $validator)
    {
        $path=resource_path('luna/smart_spark_editing.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->contract=is_array($decoded)?$decoded:[];
    }

    public function sanitizeEdit(array $block,array $changes,array $elementContext=[]): array
    {
        foreach($this->contract['protected_keys']??[] as $key) unset($changes[$key]);
        $target=(string)($elementContext['type']??$elementContext['target']??'');
        $validated=$this->validator->validateChanges(
            (string)($block['type']??''),
            $block,
            $changes,
            $target!==''?$target:null,
        );
        $changes=$validated['changes'];
        $rejected=$validated['rejected'];

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
            return ['block'=>$next,'paths'=>$applied,'mode'=>'element','rejected'=>$rejected];
        }

        $safe=array_intersect_key($changes,$block);
        return ['block'=>array_merge($block,$safe),'paths'=>array_keys($safe),'mode'=>'block','rejected'=>$rejected];
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

    public function resolveMatchedPaths(array $block,array $elementContext=[],string $prompt=''): array
    {
        $needles=array_values(array_filter([
            trim((string)($elementContext['currentValue']??'')),
            trim((string)($elementContext['url']??'')),
        ],fn(string $value)=>$value!==''));
        if($needles===[])return [];

        $paths=[];
        $walk=function($value,string $path='') use (&$walk,&$paths,$needles): void {
            if(!is_array($value))return;
            foreach($value as $key=>$item){
                $next=$path===''?(string)$key:$path.'.'.$key;
                if(is_scalar($item)){
                    foreach($needles as $needle){
                        if(trim((string)$item)===$needle){$paths[]=$next;break;}
                    }
                }elseif(is_array($item)){
                    $walk($item,$next);
                }
            }
        };

        $collectionKey=$this->collectionKey($block,$elementContext);
        $itemIndex=$this->itemIndex($elementContext);
        $wholeCollection=$this->requestsWholeCollection($prompt,$collectionKey);
        if(!$wholeCollection && $collectionKey!==null && $itemIndex!==null){
            $items=$block[$collectionKey]??null;
            if(is_array($items) && isset($items[$itemIndex]) && is_array($items[$itemIndex])){
                $walk($items[$itemIndex],$collectionKey.'.'.$itemIndex);
                $scopedPaths=array_values(array_unique($paths));
                if($scopedPaths!==[]) return $scopedPaths;

                // A visually broad wrapper can be mistaken for a repeater card
                // (for example, a section-level CTA below a card grid). Recover
                // only when every rendered value identifies one unique stored
                // field in the complete block. Ambiguous repeated copy remains
                // unresolved rather than changing a sibling card by accident.
                $fallback=[];
                foreach($needles as $needle){
                    $needlePaths=[];
                    $findNeedle=function($value,string $path='') use (&$findNeedle,&$needlePaths,$needle): void {
                        if(!is_array($value)) return;
                        foreach($value as $key=>$item){
                            $next=$path===''?(string)$key:$path.'.'.$key;
                            if(is_scalar($item) && trim((string)$item)===$needle) $needlePaths[]=$next;
                            elseif(is_array($item)) $findNeedle($item,$next);
                        }
                    };
                    $findNeedle($block);
                    $needlePaths=array_values(array_unique($needlePaths));
                    if(count($needlePaths)>1) return [];
                    if(count($needlePaths)===1) $fallback[]=$needlePaths[0];
                }
                return array_values(array_unique($fallback));
            }
        }

        $fieldPrefix=trim((string)($elementContext['fieldPrefix']??$elementContext['field_prefix']??''));
        if(!$wholeCollection && $fieldPrefix!==''){
            $scoped=array_filter($block,fn($value,$key)=>Str::startsWith((string)$key,$fieldPrefix),ARRAY_FILTER_USE_BOTH);
            $walk($scoped);
            return array_values(array_unique($paths));
        }

        $walk($block);
        return array_values(array_unique($paths));
    }

    public function responseTarget(array $elementContext=[],string $prompt=''): ?array
    {
        $collection=trim((string)($elementContext['collectionKey']??$elementContext['collection_key']??''));
        $index=$this->itemIndex($elementContext);
        $type=Str::lower(trim((string)($elementContext['type']??'content')));
        if($collection==='' || $index===null)return null;

        $names=[
            'slides'=>'slide','cards'=>'card','services'=>'service','features'=>'feature',
            'steps'=>'step','testimonials'=>'testimonial','faqs'=>'FAQ','team'=>'team member',
            'plans'=>'plan','logos'=>'logo','gallery'=>'gallery item','items'=>'item',
        ];
        $singular=$names[$collection]??Str::singular(str_replace('_',' ',$collection));
        $whole=$this->requestsWholeCollection($prompt,$collection);
        $label=$whole?'all '.str_replace('_',' ',$collection):Str::ucfirst($singular).' '.($index+1);
        return [
            'collection'=>$collection,
            'item_index'=>$index,
            'item_number'=>$index+1,
            'type'=>$type!==''?$type:'content',
            'whole_collection'=>$whole,
            'label'=>$label,
        ];
    }

    public function verifiedTargetReply(array $elementContext,string $prompt,string $status): ?string
    {
        $target=$this->responseTarget($elementContext,$prompt);
        if($target===null)return null;
        $label=$target['label'];
        $type=str_replace(['background image','image'],['image','image'],(string)$target['type']);
        $description=in_array($type,['heading','text','label','button','image'],true)?$type.' on '.$label:$label;

        return match($status){
            'complete'=>"Done — I updated {$description} only.",
            'partial'=>"I updated part of {$description}, but at least one requested change could not be verified.",
            default=>"I couldn't verify a change to {$description}, so I didn't claim it was completed.",
        };
    }

    public function contractDirective(): string
    {
        return "SMART SPARK EDIT CONTRACT\n".json_encode([
            'mode'=>$this->contract['mode']??'schema_preserving_edit',
            'rules'=>$this->contract['rules']??[],
            'collection_keys'=>$this->contract['collection_keys']??[],
            'request_response_examples'=>$this->contract['request_response_examples']??[],
            'response_rules'=>$this->contract['response_rules']??[],
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

    private function requestsWholeCollection(string $prompt,?string $collectionKey): bool
    {
        if($collectionKey===null || trim($prompt)==='')return false;
        $q=Str::lower($prompt);
        $collection=preg_quote(Str::lower(str_replace('_',' ',$collectionKey)),'/');
        $singular=preg_quote(Str::lower(Str::singular(str_replace('_',' ',$collectionKey))),'/');
        return (bool)preg_match('/\b(?:all|every|each)\s+(?:the\s+)?(?:'.$collection.'|'.$singular.')\b/u',$q)
            || (bool)preg_match('/\bacross\s+(?:all|every)\s+(?:the\s+)?(?:'.$collection.'|'.$singular.')\b/u',$q);
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
