<?php

namespace App\Services;

final class LunaExecutionVerificationService
{
    /**
     * Verify planned operations against operations the executor actually reports.
     * Supports both legacy Builder action rows and Canonical Action Schema V5 rows.
     */
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
                'verified_plans'=>[],
                'additional_verified_operations'=>[],
                'unverified_operations'=>[],
                'state_changed'=>$stateChanged,
                'can_claim_complete'=>$stateChanged,
                'contract_version'=>5,
            ];
        }

        $remaining=$applied;
        $verified=[];
        $verifiedPlans=[];
        $unverified=[];

        foreach($planned as $plan){
            $matchIndex=$this->findMatch($plan,$remaining);
            if($matchIndex===null){
                $unverified[]=[
                    'planned'=>$plan,
                    'reason'=>'No corresponding verified applied operation was found for the planned target.',
                ];
                continue;
            }
            $verified[]=$remaining[$matchIndex];
            $verifiedPlans[]=$plan;
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
            'verified_plans'=>$verifiedPlans,
            'additional_verified_operations'=>$remaining,
            'unverified_operations'=>$unverified,
            'state_changed'=>$stateChanged,
            'can_claim_complete'=>$status==='complete',
            'contract_version'=>5,
        ];
    }

    /** Mark a verified mutation partial when an authoritative post-condition failed. */
    public function failPostcondition(array $verification,string $reason,array $facts=[]): array
    {
        if(($verification['status']??'failed')!=='complete') return $verification;
        $verification['status']='partial';
        $verification['can_claim_complete']=false;
        $verification['failed_count']=max(1,(int)($verification['failed_count']??0)+1);
        $verification['unverified_operations'][]=[
            'planned'=>['action'=>'postcondition_check'],
            'reason'=>$reason,
            'facts'=>$facts,
        ];
        return $verification;
    }

    private function normalizePlanned(array $ops): array
    {
        $out=[];
        foreach($ops as $position=>$op){
            if(!is_array($op))continue;
            $legacyAction=trim((string)($op['action']??''));
            $operation=trim((string)($op['operation']??''));
            $domain=trim((string)($op['domain']??''));

            // V5 canonical operation.
            if($operation!=='' || $domain!==''){
                $out[]=[
                    'contract'=>'v5',
                    'operation_id'=>$op['operation_id']??$op['id']??('op_'.($position+1)),
                    'leaf_operation'=>$op['leaf_operation']??null,
                    'changes'=>is_array($op['changes']??null)?$op['changes']:[],
                    'action'=>$legacyAction!==''?$legacyAction:null,
                    'operation'=>$operation!==''?$operation:($legacyAction!==''?$legacyAction:'update'),
                    'domain'=>$domain!==''?$domain:null,
                    'scope'=>$op['scope']??null,
                    'target'=>$this->normalizeTarget($op['target']??null),
                    'index'=>isset($op['index'])?(int)$op['index']:null,
                    'to_index'=>isset($op['to_index'])?(int)$op['to_index']:null,
                    'spark_key'=>$op['spark_key']??null,
                    'theme_key'=>$op['theme_key']??null,
                    'expected_state'=>$this->normalizeExpectedState($op),
                ];
                continue;
            }

            if($legacyAction==='' || !in_array($legacyAction,[
                'build_page','edit','replace','insert_before','insert_after','delete','move','theme',
                'publish','navigate','header_overlay','design_overrides','brand_color_family',
                'repeater_add','repeater_remove','repeater_reorder','create','update','remove','add',
                'inspect','configure','postcondition_check'
            ],true))continue;
            $out[]=[
                'contract'=>'legacy',
                'operation_id'=>$op['operation_id']??null,
                'leaf_operation'=>$op['leaf_operation']??null,
                'changes'=>is_array($op['changes']??null)?$op['changes']:[],
                'action'=>$legacyAction,
                'operation'=>$legacyAction,
                'domain'=>$op['domain']??null,
                'scope'=>$op['scope']??null,
                'target'=>$this->normalizeTarget($op['target']??null),
                'index'=>isset($op['index'])?(int)$op['index']:null,
                'to_index'=>isset($op['to_index'])?(int)$op['to_index']:null,
                'spark_key'=>$op['spark_key']??null,
                'theme_key'=>$op['theme_key']??null,
                'expected_state'=>$this->normalizeExpectedState($op),
            ];
        }
        return $out;
    }

    private function normalizeApplied(array $ops): array
    {
        $out=[];
        foreach($ops as $position=>$op){
            if(!is_array($op) || (($op['verified']??true)===false)) continue;
            $op['operation_id']=$op['operation_id']??$op['id']??null;
            $op['_normalized_operation']=trim((string)($op['operation']??$op['action']??''));
            $op['_normalized_domain']=trim((string)($op['domain']??''));
            $op['_normalized_target']=$this->normalizeTarget($op['target']??null);
            $op['_normalized_final_state']=$this->normalizeAppliedState($op);
            $op['_position']=$position;
            $out[]=$op;
        }
        return array_values($out);
    }

    private function findMatch(array $plan,array $applied): ?int
    {
        foreach($applied as $i=>$actual){
            if($plan['operation_id']!==null && ($actual['operation_id']??null)!==null
                && (string)$actual['operation_id']!==(string)$plan['operation_id']) continue;

            $plannedOperation=(string)($plan['operation']??$plan['action']??'');
            $actualOperation=(string)($actual['_normalized_operation']??'');
            if(!$this->operationMatches($plannedOperation,$actualOperation,(string)($plan['contract']??'legacy'))) continue;

            $plannedDomain=trim((string)($plan['domain']??''));
            $actualDomain=trim((string)($actual['_normalized_domain']??''));
            if($plannedDomain!=='' && $actualDomain!=='' && $plannedDomain!==$actualDomain) continue;

            $plannedScope=trim((string)($plan['scope']??''));
            $actualScope=trim((string)($actual['scope']??''));
            if($plannedScope!=='' && $actualScope!=='' && $plannedScope!==$actualScope) continue;

            if(($plan['contract']??'legacy')==='v5'){
                $plannedLeaf=trim((string)($plan['leaf_operation']??''));
                $actualLeaf=trim((string)($actual['leaf_operation']??''));
                if($plannedLeaf!=='' && $plannedLeaf!==$actualLeaf) continue;

                $plannedDirection=$this->relativeDirection((array)($plan['changes']??[]));
                $actualDirection=$this->normalizeDirection($actual['direction']??null);
                if($plannedDirection!==null && $plannedDirection!==$actualDirection) continue;
            }

            if($plan['index']!==null && (!$this->actualHasRequiredValue($plan,$actual,'index') || (int)$actual['index']!==$plan['index']))continue;
            if($plannedOperation==='move' && $plan['to_index']!==null && (!$this->actualHasRequiredValue($plan,$actual,'to_index') || (int)$actual['to_index']!==$plan['to_index']))continue;
            if(($plan['action']??null)==='replace' && $plan['spark_key']!==null){
                $actualSpark=$actual['spark_key']??$actual['type']??null;
                if($actualSpark===null || (string)$actualSpark!==(string)$plan['spark_key'])continue;
            }
            if(($plan['action']??null)==='theme' && $plan['theme_key']!==null && (!$this->actualHasRequiredValue($plan,$actual,'theme_key') || (string)$actual['theme_key']!==(string)$plan['theme_key']))continue;

            if(!$this->targetMatches($plan,(array)($actual['_normalized_target']??[]))) continue;
            if(!$this->expectedStateMatches((array)($plan['expected_state']??[]),(array)($actual['_normalized_final_state']??[]))) continue;
            return $i;
        }
        return null;
    }

    private function operationMatches(string $planned,string $actual,string $contract='legacy'): bool
    {
        if($planned===$actual) return true;
        if($contract==='v5'){
            $strictAliases=[
                'update'=>['edit','update','spark_full_schema_edit','design_overrides','header_overlay','brand_color_family'],
                'edit'=>['edit','update','spark_full_schema_edit','repeater_add','repeater_remove','repeater_reorder'],
                'add'=>['add','create','insert_before','insert_after','repeater_add'],
                'create'=>['create','add','build_page'],
                'remove'=>['remove','delete','repeater_remove'],
                'delete'=>['delete','remove','repeater_remove'],
                'reorder'=>['reorder','move','repeater_reorder'],
                'move'=>['move','reorder'],
                'redesign'=>['redesign','replace'],
                'replace'=>['replace'],
                'build'=>['build','build_page','create'],
                'configure'=>['configure','update'],
                'audit_and_repair'=>['audit_and_repair','design_overrides'],
            ];
            return in_array($actual,$strictAliases[$planned]??[],true);
        }
        $aliases=[
            'update'=>['edit','update','spark_full_schema_edit','design_overrides','header_overlay','brand_color_family'],
            'edit'=>['edit','update','spark_full_schema_edit','repeater_add','repeater_remove','repeater_reorder'],
            'add'=>['add','create','insert_before','insert_after','repeater_add'],
            'create'=>['create','add','build_page'],
            'remove'=>['remove','delete','repeater_remove'],
            'delete'=>['delete','remove','repeater_remove'],
            'reorder'=>['reorder','move','repeater_reorder'],
            'redesign'=>['redesign','replace','edit','update'],
            'replace'=>['replace','edit','update'],
            'build'=>['build','build_page','create'],
            'configure'=>['configure','update','edit'],
            'audit_and_repair'=>['audit_and_repair','edit','update','design_overrides'],
        ];
        return in_array($actual,$aliases[$planned]??[],true);
    }

    private function normalizeTarget(mixed $target): array
    {
        if(is_string($target) && trim($target)!=='') return ['label'=>trim($target)];
        if(!is_array($target)) return [];
        $out=[];
        foreach(['type','key','id','label','selector','index','field','level'] as $key){
            if(array_key_exists($key,$target) && (is_scalar($target[$key])||$target[$key]===null)) $out[$key]=$target[$key];
        }
        return $out;
    }

    private function targetMatches(array $plan,array $actual): bool
    {
        $planned=(array)($plan['target']??[]);
        if($planned===[]) return true;
        if($actual===[]) return ($plan['contract']??'legacy')!=='v5';
        foreach(['type','id','key','index','field','level'] as $key){
            if(array_key_exists($key,$planned) && !array_key_exists($key,$actual)) return false;
            if(isset($planned[$key]) && (string)$planned[$key]!== (string)$actual[$key]) return false;
        }
        return true;
    }

    private function actualHasRequiredValue(array $plan,array $actual,string $key): bool
    {
        return ($plan['contract']??'legacy')!=='v5' || array_key_exists($key,$actual);
    }

    private function normalizeExpectedState(array $op): array
    {
        foreach(['expected_state','after','expected','postcondition'] as $key){
            if(is_array($op[$key]??null)) return $op[$key];
        }
        return [];
    }

    private function normalizeAppliedState(array $op): array
    {
        foreach(['final_state','after','actual_state','verified_state'] as $key){
            if(is_array($op[$key]??null)) return $op[$key];
        }
        return [];
    }

    private function expectedStateMatches(array $expected,array $actual): bool
    {
        if($expected===[]) return true;
        if($actual===[]) return false;
        foreach($expected as $key=>$value){
            if(!array_key_exists($key,$actual)) return false;
            if(is_array($value)){
                if(!is_array($actual[$key]) || !$this->expectedStateMatches($value,$actual[$key])) return false;
                continue;
            }
            if($actual[$key]!==$value) return false;
        }
        return true;
    }

    private function relativeDirection(array $changes): ?string
    {
        foreach(['relative_size','relative','delta'] as $key){
            if(is_array($changes[$key]??null)){
                $direction=$this->normalizeDirection($changes[$key]['direction']??null);
                if($direction!==null)return $direction;
            }
        }
        return $this->normalizeDirection($changes['direction']??null);
    }

    private function normalizeDirection(mixed $direction): ?string
    {
        $direction=strtolower(trim((string)$direction));
        return match($direction){
            'increase','larger','bigger','more','up'=>'increase',
            'decrease','smaller','less','down'=>'decrease',
            default=>null,
        };
    }
}
