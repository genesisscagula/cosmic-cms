<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class LunaSmartSparkEditingService
{
    private array $contract;

    public function __construct(
        private readonly SparkEditMutationValidator $validator,
        private readonly SparkTailwindSchemaContract $tailwindContract,
        private readonly NestedRepeaterMutationService $nestedRepeaters,
    )
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
        $repeater=$this->nestedRepeaters->repeaterForContext($block,$elementContext);
        if($repeater===null)return null;
        $collectionKey=(string)($repeater['collection']??'');
        $items=array_values((array)($repeater['items']??[]));
        if($collectionKey===''||$items===[])return null;

        $duplicate=Str::contains($q,['duplicate this','duplicate the','duplicate card','duplicate item','duplicate service','duplicate testimonial','duplicate faq','duplicate feature','duplicate step','copy this','copy the']);
        $add=Str::contains($q,['add another','add one','add a new','add new','add more','one more','another card','another item','another service','another testimonial','another faq','another feature','another step','another row','another column']);
        $remove=Str::contains($q,['remove this','delete this','remove the','delete the','remove card','delete card','remove item','delete item','remove service','delete service','remove row','delete row','remove column','delete column']);
        if(!$add && !$duplicate && !$remove)return null;

        $index=$this->itemIndex($elementContext);
        if(($add||$duplicate) && $index===null){
            $index=$this->ordinalIndexFromPrompt($q,count($items))??$this->matchItemByPrompt($q,$items);
        }

        if($add||$duplicate){
            $result=$this->nestedRepeaters->mutate(
                $block,
                $repeater['path'],
                $duplicate?'duplicate':'add',
                $index,
            );
            if(!($result['changed']??false)) return null;
            return [
                'block'=>$result['block'],
                'collection'=>$collectionKey,
                'collection_path'=>$result['path_string']??implode('.',$repeater['path']),
                'action'=>$duplicate?'repeater_duplicate':'repeater_add',
                'item_index'=>$result['item_index']??null,
                'source_index'=>$result['source_index']??$index,
            ];
        }

        if($index===null){
            $index=$this->ordinalIndexFromPrompt($q,count($items))??$this->matchItemByPrompt($q,$items);
        }
        if($index===null || !isset($items[$index]))return [
            'block'=>$block,'collection'=>$collectionKey,'collection_path'=>implode('.',$repeater['path']),'action'=>'repeater_remove_unresolved','item_index'=>null
        ];

        $result=$this->nestedRepeaters->mutate($block,$repeater['path'],'remove',$index);
        if(!($result['changed']??false)){
            return [
                'block'=>$block,
                'collection'=>$collectionKey,
                'collection_path'=>implode('.',$repeater['path']),
                'action'=>($result['reason']??'')==='minimum_items'?'repeater_remove_minimum':'repeater_remove_unresolved',
                'item_index'=>$index,
            ];
        }
        return [
            'block'=>$result['block'],
            'collection'=>$collectionKey,
            'collection_path'=>$result['path_string']??implode('.',$repeater['path']),
            'action'=>'repeater_remove',
            'item_index'=>$index,
        ];
    }

    /** Deterministic collection cardinality for every card/list Spark. */
    public function applyCardinalityIntent(string $prompt,array $block): ?array
    {
        $q=Str::lower(trim($prompt));
        $namesCollection=(bool)preg_match('/\b(cards?|services?|items?|tabs?|features?|testimonials?|reviews?|faqs?|questions?|plans?|members?|steps?|slides?)\b/i',$q);
        $countFollowup=(bool)preg_match('/\b(?:remove|delete|decrease|reduce|add|increase)\b.{0,30}\b\d{1,2}\b.{0,20}\b(?:existing|current|more|additional)?\b/i',$q);
        if(!$namesCollection&&!$countFollowup) return null;

        $number=null;
        if(preg_match('/\b(\d{1,2})\b/',$q,$match)) $number=(int)$match[1];
        $exact=(bool)preg_match('/\b(?:make|set|keep|show|display|use)\b.{0,35}\b(?:just|exactly|only)?\s*\d{1,2}\b/i',$q);
        $remove=(bool)preg_match('/\b(?:remove|delete|decrease|reduce|less|fewer)\b/i',$q);
        $add=(bool)preg_match('/\b(?:add|increase|more|additional|another)\b/i',$q);
        if(!$exact&&!$remove&&!$add) return null;

        $arrayKeys=['items','services','cards','features','testimonials','reviews','faqs','questions','plans','members','team','steps','slides','logos'];
        foreach($arrayKeys as $key){
            if(!isset($block[$key])||!is_array($block[$key])) continue;
            $items=array_values($block[$key]);
            if($items===[]) continue;
            $before=count($items);
            $target=$exact ? ($number??$before) : ($remove ? $before-($number??1) : $before+($number??1));
            $target=max(1,min(12,$target));
            if($target===$before) return ['block'=>$block,'collection'=>$key,'action'=>'cardinality_no_change','count_before'=>$before,'count_after'=>$before];
            while(count($items)>$target) array_pop($items);
            while(count($items)<$target){
                $clone=$this->clearIdentityFields((array)$items[array_key_last($items)]);
                $items[]=$clone;
            }
            $next=$block;$next[$key]=$items;
            return ['block'=>$next,'collection'=>$key,'action'=>$target>$before?'repeater_increase':'repeater_decrease','count_before'=>$before,'count_after'=>$target];
        }

        $countKeys=['service_count','item_count','card_count','tab_count','feature_count','testimonial_count','review_count','faq_count','member_count','step_count','slide_count'];
        foreach($countKeys as $countKey){
            if(!array_key_exists($countKey,$block)) continue;
            $before=max(1,(int)$block[$countKey]);
            $type=(string)($block['type']??'');
            $max=match(true){
                $countKey==='tab_count'=>4,
                $countKey==='service_count'&&in_array($type,['services_sticky_scroll','services_horizontal'],true)=>6,
                $countKey==='service_count'=>7,
                $countKey==='item_count'&&$type==='services_mega_grid'=>8,
                default=>12,
            };
            $target=$exact ? ($number??$before) : ($remove ? $before-($number??1) : $before+($number??1));
            $target=max(1,min($max,$target));
            if($target===$before) return ['block'=>$block,'collection'=>$countKey,'action'=>'cardinality_no_change','count_before'=>$before,'count_after'=>$before];
            $next=$block;
            if($target>$before) $this->fillNumberedCardSlots($next,$countKey,$before,$target);
            $next[$countKey]=$target;
            return ['block'=>$next,'collection'=>$countKey,'action'=>$target>$before?'repeater_increase':'repeater_decrease','count_before'=>$before,'count_after'=>$target];
        }
        return null;
    }

    private function fillNumberedCardSlots(array &$block,string $countKey,int $before,int $target): void
    {
        $prefix=Str::beforeLast($countKey,'_count');
        $words=['one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve'];
        $sourceWord=$words[max(0,$before-1)]??null;
        if($sourceWord===null) return;
        $sourcePrefix=$prefix.'_'.$sourceWord.'_';
        $source=[];
        foreach($block as $key=>$value) if(str_starts_with((string)$key,$sourcePrefix)) $source[Str::after((string)$key,$sourcePrefix)]=$value;
        // Premium Services use `featured_*` for item one and `service_two_*` onward.
        if($source===[] && $prefix==='service'){
            $premiumPrefix=$before===1?'featured_':'service_'.$sourceWord.'_';
            foreach($block as $key=>$value) if(str_starts_with((string)$key,$premiumPrefix)) $source[Str::after((string)$key,$premiumPrefix)]=$value;
        }
        if($source===[]) return;
        for($index=$before+1;$index<=$target;$index++){
            $word=$words[$index-1]??null;if($word===null) break;
            $targetPrefix=$prefix==='service'&&array_key_exists('featured_title',$block)?'service_'.$word.'_':$prefix.'_'.$word.'_';
            foreach($source as $field=>$value){
                $key=$targetPrefix.$field;
                if($field==='number') $block[$key]=str_pad((string)$index,2,'0',STR_PAD_LEFT);
                elseif(!isset($block[$key])||trim((string)$block[$key])==='') $block[$key]=$value;
            }
        }
    }

    private function insertTailwindScope(array &$block,string $collection,int $insertAt,?int $sourceIndex): void
    {
        $key=$this->tailwindContract->storageKey();
        $schema=$this->tailwindContract->fromBlock((string)($block['type']??''),$block);
        $scopes=$schema['collections'][$collection]??null;
        if(!is_array($scopes)) return;

        $scope=$sourceIndex!==null && is_array($scopes[$sourceIndex]??null)
            ? $scopes[$sourceIndex]
            : ['styles'=>[],'collections'=>[]];
        array_splice($scopes,$insertAt,0,[$scope]);
        $schema['collections'][$collection]=array_values($scopes);
        $block[$key]=$schema;
    }

    private function removeTailwindScope(array &$block,string $collection,int $index): void
    {
        $key=$this->tailwindContract->storageKey();
        $schema=$this->tailwindContract->fromBlock((string)($block['type']??''),$block);
        $scopes=$schema['collections'][$collection]??null;
        if(!is_array($scopes)||!array_key_exists($index,$scopes)) return;

        array_splice($scopes,$index,1);
        $schema['collections'][$collection]=array_values($scopes);
        $block[$key]=$schema;
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

        $repeater=$this->nestedRepeaters->repeaterForContext($block,$elementContext);
        $collectionKey=$repeater!==null?(string)($repeater['collection']??''):$this->collectionKey($block,$elementContext);
        $itemIndex=$this->itemIndex($elementContext);
        $wholeCollection=$this->requestsWholeCollection($prompt,$collectionKey);
        if(!$wholeCollection && $repeater!==null && $collectionKey!=='' && $itemIndex!==null){
            $items=$repeater['items']??null;
            if(is_array($items) && isset($items[$itemIndex]) && is_array($items[$itemIndex])){
                $basePath=implode('.',array_map('strval',(array)($repeater['path']??[])));
                $walk($items[$itemIndex],($basePath!==''?$basePath.'.':'').$itemIndex);
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
        $repeater=$this->nestedRepeaters->repeaterForContext($block,$context);
        if($repeater!==null && trim((string)($repeater['collection']??''))!=='') return (string)$repeater['collection'];
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

    private function ordinalIndexFromPrompt(string $prompt,int $count): ?int
    {
        $ordinals=[
            'first'=>0,'1st'=>0,'one'=>0,
            'second'=>1,'2nd'=>1,'two'=>1,
            'third'=>2,'3rd'=>2,'three'=>2,
            'fourth'=>3,'4th'=>3,'four'=>3,
            'fifth'=>4,'5th'=>4,'five'=>4,
            'sixth'=>5,'6th'=>5,'six'=>5,
        ];
        foreach($ordinals as $word=>$index){
            $ordinal=preg_quote($word,'/');
            $noun='(?:card|item|service|testimonial|faq|feature|step|plan|row|column|extra)';
            if($index<$count && (preg_match('/\b'.$ordinal.'\s+'.$noun.'\b/u',$prompt)
                || preg_match('/\b'.$noun.'\s+'.$ordinal.'\b/u',$prompt))) return $index;
        }
        if(preg_match('/\b(?:card|item|service|testimonial|faq|feature|step|plan|row|column|extra)\s*#?\s*(\d+)\b/u',$prompt,$match)){
            $index=(int)$match[1]-1;
            return $index>=0&&$index<$count?$index:null;
        }
        return null;
    }
}
