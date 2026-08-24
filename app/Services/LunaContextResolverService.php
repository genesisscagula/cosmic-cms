<?php

namespace App\Services;

final class LunaContextResolverService
{
    public function resolve(string $message,array $prior,array $uiContext=[]): array
    {
        $q=trim(mb_strtolower($message));
        $last=is_array($prior['last_verified_action']??null)?$prior['last_verified_action']:[];
        $current=is_array($prior['current']??null)?$prior['current']:[];
        if($last===[]) return $this->empty();

        $repeat=(bool)preg_match('/^(?:please\s+)?(?:a\s+little\s+more|little\s+more|slightly\s+more|more|again|same\s+again|do\s+that\s+again)[.! ]*$/iu',$q);
        $reverse=(bool)preg_match('/\b(?:too\s+(?:much|small|big|large)|go\s+back\s+a\s+bit|reverse\s+that|opposite)\b/iu',$q);
        $undo=(bool)preg_match('/^(?:please\s+)?(?:undo|undo\s+that|revert|revert\s+that|go\s+back)[.! ]*$/iu',$q);
        $sameHere=(bool)preg_match('/\b(?:same\s+(?:for|on)\s+(?:this|this one|here)|do\s+the\s+same\s+(?:here|to this|for this))\b/iu',$q);
        $widen=(bool)preg_match('/\b(?:apply|use|do)\s+(?:that|the same)\s+(?:everywhere|sitewide|across\s+the\s+(?:site|website)|to\s+all)\b/iu',$q);
        $breakpoint=null;
        if(preg_match('/\b(?:only\s+)?(?:on|for)\s+(mobile|tablet|desktop)\b/iu',$q,$m)) $breakpoint=strtolower($m[1]);

        $pronounFollowup=(bool)preg_match('/\b(?:it|that|same)\b/iu',$q)
            && (bool)preg_match('/\b(?:make|change|increase|decrease|reduce|smaller|bigger|larger|more|less|bold|lighter|darker|apply|use)\b/iu',$q);
        $isFollowup=$repeat||$reverse||$undo||$sameHere||$widen||$breakpoint!==null||$pronounFollowup;
        if(!$isFollowup) return $this->empty();

        if($undo){
            $before=is_array($last['before']??null)?$last['before']:[];
            $reversible=array_key_exists('reversible_state',$before) || array_key_exists('value',$before) || array_key_exists('snapshot',$before);
            return [
                'is_followup'=>true,
                'execution_allowed'=>$reversible,
                'needs_clarification'=>!$reversible,
                'reason'=>$reversible?'Resolved an undo from reversible verified state.':'The previous action is verified, but no reversible state snapshot was stored for a safe undo.',
                'inherit'=>$reversible?[
                    'action'=>'update',
                    'domain'=>$last['domain']??null,
                    'leaf_operation'=>'restore_previous',
                    'operation'=>'update',
                    'scope'=>$last['scope']??'page',
                    'target'=>$last['target']??null,
                    'changes'=>['restore_from'=>$before],
                ]:[],
                'mode'=>'undo',
                'breakpoint'=>null,
            ];
        }

        $stale=(bool)($last['target_stale']??false);
        $target=$last['target']??null;
        if($sameHere){
            $selection=$uiContext['selection']??($uiContext['element_context']??($current['selection']??null));
            if($selection!==null) $target=is_array($selection)?$selection:['type'=>'element','key'=>(string)$selection,'label'=>(string)$selection];
        }

        $changes=is_array($last['changes']??null)?$last['changes']:[];
        if($repeat && isset($changes['relative_size']) && is_array($changes['relative_size'])){
            $changes['relative_size']['amount']='slight';
        }
        if($reverse && isset($changes['relative_size']) && is_array($changes['relative_size'])){
            $dir=(string)($changes['relative_size']['direction']??'');
            if($dir==='increase') $changes['relative_size']['direction']='decrease';
            elseif($dir==='decrease') $changes['relative_size']['direction']='increase';
            $changes['relative_size']['amount']='slight';
        }
        if($breakpoint!==null) $changes['breakpoint']=$breakpoint;

        $scope=$last['scope']??'page';
        if($widen) $scope='site';

        $canUseTarget=!$stale || $sameHere || $widen;
        return [
            'is_followup'=>true,
            'execution_allowed'=>$canUseTarget && $target!==null,
            'reason'=>$canUseTarget?'Resolved from last verified action context.':'Previous page-local target is stale after navigation.',
            'inherit'=>[
                'action'=>$last['action']??'update',
                'domain'=>$last['domain']??null,
                'leaf_operation'=>$last['leaf_operation']??null,
                'operation'=>$last['operation']??'update',
                'scope'=>$scope,
                'target'=>$target,
                'changes'=>$changes,
            ],
            'mode'=>$reverse?'reverse':($sameHere?'same_here':($widen?'widen_scope':($repeat?'repeat':'refine'))),
            'breakpoint'=>$breakpoint,
        ];
    }

    public function apply(array $schema,array $resolution,string $message): array
    {
        if(($resolution['is_followup']??false)!==true) return $schema;
        if(($resolution['execution_allowed']??false)!==true){
            $schema['execution_allowed']=false;
            $schema['needs_clarification']=true;
            $schema['reason']=$resolution['reason']??'Follow-up target could not be resolved safely.';
            $schema['context_resolution']=$resolution;
            return $schema;
        }

        $inherit=is_array($resolution['inherit']??null)?$resolution['inherit']:[];
        $explicitDomain=$this->explicitDomain($message);
        $explicitTarget=$this->explicitTarget($message);
        $explicitScope=$this->explicitScope($message);

        if(!$explicitDomain && !empty($inherit['domain'])) $schema['domain']=$inherit['domain'];
        if(!$explicitDomain && !empty($inherit['leaf_operation'])) $schema['leaf_operation']=$inherit['leaf_operation'];
        if(!$explicitDomain && !empty($inherit['operation'])) $schema['operation']=$inherit['operation'];
        // In a same-here follow-up, "this heading/one" is a deictic reference to
        // the live Builder selection, not a competing explicit target. The current
        // selection must therefore override the model's stale prior target.
        if(($resolution['mode']??'')==='same_here' && array_key_exists('target',$inherit)){
            $schema['target']=$inherit['target'];
        }elseif(!$explicitTarget && array_key_exists('target',$inherit)){
            $schema['target']=$inherit['target'];
        }
        if(!$explicitScope && !empty($inherit['scope'])) $schema['scope']=$inherit['scope'];

        $inheritedChanges=is_array($inherit['changes']??null)?$inherit['changes']:[];
        $currentChanges=is_array($schema['changes']??null)?$schema['changes']:[];
        // Current explicit structured values win; inherited context only fills gaps.
        $schema['changes']=array_replace_recursive($inheritedChanges,$currentChanges);
        if(($resolution['breakpoint']??null)!==null) $schema['changes']['breakpoint']=$resolution['breakpoint'];

        // normalizeAction() runs before contextual follow-up inheritance. Keep the
        // executable operation row in sync with the resolved top-level contract;
        // otherwise downstream deterministic executors still see the model's
        // guess for "again" / "too much" instead of the verified prior action.
        $existingOperations=array_values(array_filter((array)($schema['operations']??[]),'is_array'));
        $existing=$existingOperations[0]??[];
        $schema['operations']=[[
            'operation_id'=>$existing['operation_id']??'op_1',
            'domain'=>$schema['domain']??($inherit['domain']??null),
            'leaf_operation'=>$schema['leaf_operation']??($inherit['leaf_operation']??null),
            'operation'=>$schema['operation']??($inherit['operation']??'update'),
            'scope'=>$schema['scope']??($inherit['scope']??'page'),
            'target'=>$schema['target']??($inherit['target']??null),
            'changes'=>is_array($schema['changes']??null)?$schema['changes']:[],
            'constraints'=>is_array($existing['constraints']??null)?$existing['constraints']:[],
            'missing_changes'=>[],
        ]];
        $schema['compound']=false;
        $schema['operation_count']=1;

        $schema['context_resolution']=[
            'resolved'=>true,
            'mode'=>$resolution['mode']??'refine',
            'source'=>'last_verified_action',
            'reason'=>$resolution['reason']??null,
        ];
        $schema['needs_clarification']=false;
        $schema['execution_allowed']=true;
        return $schema;
    }

    private function explicitDomain(string $message): bool
    {
        return (bool)preg_match('/\b(theme|palette|font|typography|heading|h1|h2|h3|image|photo|video|header|footer|menu|navigation|padding|margin|gap|layout|grid|color|colour|seo|meta|form|product|inventory|post|blog|mobile|tablet|desktop)\b/iu',$message);
    }

    private function explicitTarget(string $message): bool
    {
        return (bool)preg_match('/\b(hero|banner|header|footer|services?|pricing|testimonial|contact|about|heading|h1|h2|h3|button|image|logo|section|card|page)\b/iu',$message);
    }

    private function explicitScope(string $message): bool
    {
        return (bool)preg_match('/\b(sitewide|everywhere|whole\s+(?:site|website|page)|all\s+(?:pages|sections|headings|h1s|h2s|h3s)|this\s+(?:page|section|element))\b/iu',$message);
    }

    private function empty(): array
    {
        return ['is_followup'=>false,'execution_allowed'=>false,'inherit'=>[]];
    }
}
