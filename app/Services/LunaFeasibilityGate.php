<?php

namespace App\Services;

use Illuminate\Support\Str;

class LunaFeasibilityGate
{
    public function __construct(
        private readonly LunaIntentIndex $intents,
        private readonly LunaCapabilityRegistry $capabilities
    ) {}

    public function evaluate(string $message, array $knowledgePacket, ?string $scope=null): array
    {
        $scan=$this->intents->classify($message);
        $intent=$scan['intent']??null;
        $intentId=(string)($intent['id']??'');
        $route=(string)($intent['route']??'');
        $informational=in_array($intentId,[
            'conversation.capability_question',
            'conversation.help_question',
        ],true) || (($knowledgePacket['query_type']??'')==='capability_question');

        $matched=array_values(array_filter(
            $knowledgePacket['capabilities']??[],
            fn($cap)=>is_array($cap)
        ));

        $unsupported=collect($matched)->first(fn(array $cap)=>
            ($cap['status']??'')==='unsupported'
            && $this->capabilityExplicitlyMentioned($message,$cap)
        );

        $supported=collect($matched)->first(fn(array $cap)=>
            in_array(($cap['status']??''),['supported','partially_supported'],true)
            && ($cap['luna']??false)
            && $this->scopeAllows($scope,$cap)
            && ($cap['category']??'')!=='conversation'
        );

        if($unsupported){
            $fallback=trim((string)($unsupported['fallback']??''));
            return [
                'feasibility'=>$fallback!==''?'alternative_available':'unsupported',
                'execution_allowed'=>false,
                'requires_confirmation'=>false,
                'informational'=>$informational,
                'requested_capability'=>$unsupported['id']??null,
                'matched_capability'=>$unsupported,
                'alternative'=>$fallback!==''?$fallback:null,
                'reason'=>'The canonical capability registry marks the requested capability as unsupported.',
            ];
        }

        if($informational){
            return [
                'feasibility'=>$supported?'supported':'informational',
                'execution_allowed'=>false,
                'requires_confirmation'=>false,
                'informational'=>true,
                'requested_capability'=>$supported['id']??null,
                'matched_capability'=>$supported,
                'alternative'=>null,
                'reason'=>'Questions and help requests are conversation-only until the user explicitly requests an action.',
            ];
        }

        if($supported){
            $partial=($supported['status']??'')==='partially_supported';
            $fallback=trim((string)($supported['fallback']??''));
            return [
                'feasibility'=>$partial?'supported_with_recommendation':'supported',
                'execution_allowed'=>true,
                'requires_confirmation'=>true,
                'informational'=>false,
                'requested_capability'=>$supported['id']??null,
                'matched_capability'=>$supported,
                'alternative'=>$partial&&$fallback!==''?$fallback:null,
                'reason'=>$partial
                    ? 'The request is supported within documented limits.'
                    : 'The request maps to a documented executable Luna capability.',
            ];
        }

        // Never let a conversational route fall through to mutation.
        if(in_array($route,['knowledge','local_reply','pending_cancellation'],true)){
            return [
                'feasibility'=>'informational',
                'execution_allowed'=>false,
                'requires_confirmation'=>false,
                'informational'=>true,
                'requested_capability'=>null,
                'matched_capability'=>null,
                'alternative'=>null,
                'reason'=>'The request is conversational and has no verified executable capability.',
            ];
        }

        return [
            'feasibility'=>'unsupported',
            'execution_allowed'=>false,
            'requires_confirmation'=>false,
            'informational'=>false,
            'requested_capability'=>null,
            'matched_capability'=>null,
            'alternative'=>null,
            'reason'=>'Canonical documentation did not establish an executable Luna capability for this request.',
        ];
    }

    private function scopeAllows(?string $scope,array $cap): bool
    {
        if(!$scope) return true;
        $scopes=$cap['scopes']??[];
        if(!is_array($scopes)||$scopes===[]) return true;
        if(in_array($scope,$scopes,true)) return true;
        if($scope==='page' && in_array('site',$scopes,true)) return true;
        return false;
    }

    private function capabilityExplicitlyMentioned(string $message,array $cap): bool
    {
        $q=Str::lower(trim($message));
        foreach($cap['aliases']??[] as $alias){
            $alias=Str::lower(trim((string)$alias));
            if(strlen($alias)>=4 && Str::contains($q,$alias)) return true;
        }
        $name=Str::lower(trim((string)($cap['name']??'')));
        return $name!=='' && Str::contains($q,$name);
    }
}
