<?php

namespace App\Services;

final class LunaSelfCorrectionService
{
    private array $contract;

    public function __construct()
    {
        $path=resource_path('luna/self_correction_recovery.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->contract=is_array($decoded)?$decoded:[];
    }

    public function prepare(array $verification,array $planned,array $applied,array $scopeResolution): array
    {
        $status=(string)($verification['status']??'failed');
        if(!in_array($status,['partial','failed'],true)){
            return $this->none('Execution does not require recovery.');
        }

        $safe=(array)($this->contract['safe_retry_actions']??['edit','theme']);
        $blocked=(array)($this->contract['blocked_retry_actions']??[]);
        $unverified=(array)($verification['unverified_operations']??[]);
        $retries=[];

        foreach($unverified as $item){
            $op=is_array($item['planned']??null)?$item['planned']:[];
            $action=(string)($op['action']??'');
            if($action==='' || in_array($action,$blocked,true) || !in_array($action,$safe,true)) continue;

            // Preserve the original target. Recovery never broadens the request.
            $retry=[
                'action'=>$action,
                'index'=>isset($op['index'])?(int)$op['index']:null,
                'to_index'=>isset($op['to_index'])?(int)$op['to_index']:null,
                'spark_key'=>$op['spark_key']??null,
                'theme_key'=>$op['theme_key']??null,
                'scope'=>$scopeResolution['scope']??null,
            ];
            $retries[]=$retry;
        }

        return [
            'attempted'=>$retries!==[],
            'pass'=>1,
            'max_passes'=>1,
            'mode'=>'deterministic_safe_retry',
            'retry_operations'=>$retries,
            'blocked_count'=>max(0,count($unverified)-count($retries)),
            'reason'=>$retries!==[]
                ? 'Safe unverified operations are eligible for one bounded recovery pass.'
                : 'No unverified operation is safe for automatic recovery.',
        ];
    }

    public function finalize(array $before,array $after,array $recoveredApplied): array
    {
        return [
            'attempted'=>(bool)($before['attempted']??false),
            'pass'=>(int)($before['pass']??0),
            'max_passes'=>1,
            'mode'=>$before['mode']??'none',
            'retry_operations'=>$before['retry_operations']??[],
            'recovered_operations'=>array_values($recoveredApplied),
            'before_status'=>$after['before_status']??null,
            'after_status'=>$after['status']??null,
            'resolved'=>($after['status']??null)==='complete',
            'blocked_count'=>(int)($before['blocked_count']??0),
            'reason'=>$before['reason']??null,
            'additional_ai_calls'=>0,
            'additional_credit_cost'=>0,
        ];
    }

    private function none(string $reason): array
    {
        return [
            'attempted'=>false,'pass'=>0,'max_passes'=>1,'mode'=>'none',
            'retry_operations'=>[],'blocked_count'=>0,'reason'=>$reason,
        ];
    }
}
