<?php

namespace App\Services;

final class LunaContextStateService
{
    public function hydrate(array $siteMemory,array $uiContext=[]): array
    {
        $state=is_array($siteMemory['context_state']??null)?$siteMemory['context_state']:[];
        $state['version']=2;
        $state['current']=$this->currentContext($state['current']??[],$uiContext);
        $state['last_verified_action']=is_array($state['last_verified_action']??null)?$state['last_verified_action']:null;
        return $state;
    }

    public function attach(array $siteMemory,array $uiContext=[]): array
    {
        $siteMemory['context_state']=$this->hydrate($siteMemory,$uiContext);
        return $siteMemory;
    }

    public function routingContext(array $siteMemory,array $uiContext=[]): array
    {
        $state=$this->hydrate($siteMemory,$uiContext);
        return [
            'current'=>$state['current']??[],
            'last_verified_action'=>$state['last_verified_action']??null,
        ];
    }

    public function rememberRouted(array $siteMemory,array $schema): array
    {
        if(($schema['intent']??'chat')!=='action') return $siteMemory;
        $state=$this->hydrate($siteMemory);
        $state['pending_action']=[
            'action'=>$schema['action']??null,
            'domain'=>$schema['domain']??null,
            'leaf_operation'=>$schema['leaf_operation']??null,
            'operation'=>$schema['operation']??null,
            'scope'=>$schema['scope']??null,
            'target'=>$schema['target']??null,
            'changes'=>is_array($schema['changes']??null)?$schema['changes']:[],
        ];
        $siteMemory['context_state']=$state;
        return $siteMemory;
    }

    public function rememberVerified(array $siteMemory,array $schema,array $verification,array $applied,array $uiContext=[],array $before=[],array $after=[]): array
    {
        $state=$this->hydrate($siteMemory,$uiContext);
        unset($state['pending_action']);
        if(!in_array((string)($verification['status']??''),['complete','partial'],true)){
            $state['last_execution_attempt']=[
                'status'=>$verification['status']??'failed',
                'can_claim_complete'=>(bool)($verification['can_claim_complete']??false),
                'failed_operations'=>array_values((array)($verification['unverified_operations']??[])),
                'attempted_at'=>now()->toIso8601String(),
            ];
            $siteMemory['context_state']=$state;
            return $siteMemory;
        }

        $verifiedApplied=array_values(array_filter($applied,fn($op)=>!is_array($op)||($op['verified']??true)!==false));
        $verifiedPlans=array_values(array_filter((array)($verification['verified_plans']??[]),'is_array'));
        if($verifiedApplied===[] && $verifiedPlans===[]){
            $siteMemory['context_state']=$state;
            return $siteMemory;
        }

        // For compound turns, the conversational reference must point to the latest
        // operation that was actually verified, never merely to the last planned row.
        // This makes "again" / "do that to this one" safe after partial execution.
        $referencePlan=$verifiedPlans!==[]?$verifiedPlans[array_key_last($verifiedPlans)]:[];
        $state['last_verified_action']=[
            'action'=>$schema['action']??'update',
            'domain'=>$referencePlan['domain']??($schema['domain']??null),
            'leaf_operation'=>$referencePlan['leaf_operation']??($schema['leaf_operation']??null),
            'operation'=>$referencePlan['operation']??($schema['operation']??null),
            'scope'=>$referencePlan['scope']??($schema['scope']??null),
            'target'=>$referencePlan['target']??($schema['target']??null),
            'changes'=>is_array($referencePlan['changes']??null)?$referencePlan['changes']:(is_array($schema['changes']??null)?$schema['changes']:[]),
            'operation_id'=>$referencePlan['operation_id']??null,
            'compound'=>(bool)($schema['compound']??(count((array)($schema['operations']??[]))>1)),
            'verification_status'=>$verification['status']??null,
            'verified_plans'=>$verifiedPlans,
            'verified_operations'=>$verifiedApplied,
            'failed_operations'=>array_values((array)($verification['unverified_operations']??[])),
            'before'=>$before,
            'after'=>$after,
            'page_id'=>$state['current']['page_id']??null,
            'section_index'=>$state['current']['section_index']??null,
            'selection'=>$state['current']['selection']??null,
            'verified_at'=>now()->toIso8601String(),
        ];
        $state['last_execution_attempt']=[
            'status'=>$verification['status']??null,
            'can_claim_complete'=>(bool)($verification['can_claim_complete']??false),
            'attempted_at'=>now()->toIso8601String(),
        ];
        $siteMemory['context_state']=$state;
        return $siteMemory;
    }

    public function invalidateForNavigation(array $siteMemory,array $uiContext=[]): array
    {
        $state=$this->hydrate($siteMemory,$uiContext);
        $last=$state['last_verified_action']??null;
        $currentPage=$state['current']['page_id']??null;
        if(is_array($last) && ($last['page_id']??null)!==null && $currentPage!==null && (string)$last['page_id']!==(string)$currentPage){
            // Keep the historical action, but mark its page-local target stale so a
            // follow-up cannot silently mutate the same section index on another page.
            $state['last_verified_action']['target_stale']=true;
        }
        $siteMemory['context_state']=$state;
        return $siteMemory;
    }

    private function currentContext(array $existing,array $ui): array
    {
        $selection=$ui['selection']??($ui['element_context']??null);
        return array_filter([
            'surface'=>$ui['surface']??($existing['surface']??null),
            'website_id'=>$ui['website_id']??($existing['website_id']??null),
            'website_name'=>$ui['website_name']??($existing['website_name']??null),
            'page_id'=>$ui['current_page_id']??($ui['page_id']??($existing['page_id']??null)),
            'page_title'=>$ui['current_page_title']??($existing['page_title']??null),
            'page_slug'=>$ui['current_page_slug']??($existing['page_slug']??null),
            'scope'=>$ui['ui_scope']??($existing['scope']??null),
            'section_index'=>array_key_exists('target_index',$ui)?$ui['target_index']:($existing['section_index']??null),
            'selection'=>$selection??($existing['selection']??null),
        ],fn($v)=>$v!==null&&$v!=='');
    }
}
