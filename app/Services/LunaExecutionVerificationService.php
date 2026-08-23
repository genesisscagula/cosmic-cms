<?php

namespace App\Services;

final class LunaExecutionVerificationService
{
    public function verify(array $planned,array $applied,bool $stateChanged): array
    {
        $planned=$this->normalizePlanned($planned);
        $applied=$this->normalizeApplied($applied);

        if($planned===[]){
            return [
                'status'=>$stateChanged?'complete':'noop',
                'planned_count'=>0,
                'verified_count'=>count($applied),
                'failed_count'=>0,
                'verified_operations'=>$applied,
                'unverified_operations'=>[],
                'state_changed'=>$stateChanged,
                'can_claim_complete'=>$stateChanged,
            ];
        }

        $remaining=$applied;
        $verified=[];
        $unverified=[];

        foreach($planned as $plan){
            $matchIndex=$this->findMatch($plan,$remaining);
            if($matchIndex===null){
                $unverified[]=[
                    'planned'=>$plan,
                    'reason'=>'No corresponding verified applied operation was found.',
                ];
                continue;
            }
            $verified[]=$remaining[$matchIndex];
            array_splice($remaining,$matchIndex,1);
        }

        $verifiedCount=count($verified);
        $failedCount=count($unverified);
        $status=match(true){
            !$stateChanged || $verifiedCount===0 => 'failed',
            $failedCount===0 => 'complete',
            default => 'partial',
        };

        return [
            'status'=>$status,
            'planned_count'=>count($planned),
            'verified_count'=>$verifiedCount,
            'failed_count'=>$failedCount,
            'verified_operations'=>$verified,
            'additional_verified_operations'=>$remaining,
            'unverified_operations'=>$unverified,
            'state_changed'=>$stateChanged,
            'can_claim_complete'=>$status==='complete',
        ];
    }

    private function normalizePlanned(array $ops): array
    {
        $out=[];
        foreach($ops as $op){
            if(!is_array($op))continue;
            $action=(string)($op['action']??'');
            if($action==='' || !in_array($action,[
                'build_page','edit','replace','insert_before','insert_after','delete','move','theme',
                'publish','navigate','header_overlay','design_overrides','brand_color_family'
            ],true))continue;
            $out[]=[
                'action'=>$action,
                'index'=>isset($op['index'])?(int)$op['index']:null,
                'to_index'=>isset($op['to_index'])?(int)$op['to_index']:null,
                'spark_key'=>$op['spark_key']??null,
                'theme_key'=>$op['theme_key']??null,
            ];
        }
        return $out;
    }

    private function normalizeApplied(array $ops): array
    {
        return array_values(array_filter($ops,fn($op)=>is_array($op) && (($op['verified']??true)!==false)));
    }

    private function findMatch(array $plan,array $applied): ?int
    {
        foreach($applied as $i=>$actual){
            $plannedAction=$plan['action'];
            $actualAction=(string)($actual['action']??'');

            $actionMatches=$actualAction===$plannedAction
                || ($plannedAction==='edit' && in_array($actualAction,['edit','repeater_add','repeater_remove'],true));
            if(!$actionMatches)continue;

            if($plan['index']!==null && isset($actual['index']) && (int)$actual['index']!==$plan['index'])continue;
            if($plannedAction==='move' && $plan['to_index']!==null && isset($actual['to_index']) && (int)$actual['to_index']!==$plan['to_index'])continue;
            if($plannedAction==='replace' && $plan['spark_key']!==null){
                $actualSpark=$actual['spark_key']??$actual['type']??null;
                if($actualSpark===null || (string)$actualSpark!==(string)$plan['spark_key'])continue;
            }
            if($plannedAction==='theme' && $plan['theme_key']!==null && isset($actual['theme_key']) && (string)$actual['theme_key']!==(string)$plan['theme_key'])continue;

            return $i;
        }
        return null;
    }
}
