<?php

namespace App\Services;

class LunaCreditPricingService
{
    /**
     * Deterministic mutation pricing. Conversation/advice is free.
     * The model proposes operations; the server prices the actual plan.
     */
    public function estimate(string $prompt, array $operations, string $scope = 'page'): array
    {
        $text=strtolower(trim($prompt));
        $mutationWords=['change','make','use ','add ','remove','delete','replace','move','build','create','generate','update','edit','float','overlay','color','colour','image','photo','video','layout','style','font','spacing','redesign'];
        $looksMutating=collect($mutationWords)->contains(fn($word)=>str_contains($text,$word));

        if(!$looksMutating && count($operations)===0){
            return ['credits'=>0,'kind'=>'conversation','requires_confirmation'=>false,'breakdown'=>[]];
        }

        $credits=0; $breakdown=[];
        foreach($operations as $op){
            $action=(string)($op['action']??'');
            $cost=match($action){
                'edit'=>5,
                'theme','header_overlay'=>10,
                'refresh_images'=>10,
                'replace'=>15,
                'insert_before','insert_after'=>15,
                'move','delete'=>5,
                'build_page'=>40,
                default=>0,
            };
            if($cost>0){$credits+=$cost;$breakdown[]=['action'=>$action,'credits'=>$cost];}
        }

        if(str_contains($text,'video')){$credits+=10;$breakdown[]=['action'=>'video_complexity','credits'=>10];}
        if(preg_match('/#[0-9a-f]{6}\b/i',$prompt)){$credits+=5;$breakdown[]=['action'=>'brand_palette','credits'=>5];}
        if($scope==='page' && preg_match('/whole|entire|redesign|all section|whole website|whole page/i',$prompt)){
            $credits=max($credits,30);
        }

        $credits=max(0,min(100,$credits));
        return [
            'credits'=>$credits,
            'kind'=>$credits>0?'mutation':'conversation',
            'requires_confirmation'=>$credits>=50,
            'breakdown'=>$breakdown,
        ];
    }
}
