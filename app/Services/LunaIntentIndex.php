<?php

namespace App\Services;

use Illuminate\Support\Str;

class LunaIntentIndex
{
    private array $index;

    public function __construct()
    {
        $path=resource_path('luna/intent_index.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->index=is_array($decoded)?$decoded:[];
    }

    public function classify(string $message): array
    {
        $message=trim($message);
        $normalized=Str::lower($message);
        $matches=[];

        foreach($this->index['intents']??[] as $intent){
            $matchedPattern=null;
            foreach($intent['patterns']??[] as $pattern){
                $regex='~'.$pattern.'~iu';
                if(@preg_match($regex,$message)===1){
                    $matchedPattern=$pattern;
                    break;
                }
            }
            if($matchedPattern===null) continue;

            $matches[]=[
                'id'=>$intent['id']??'unknown',
                'category'=>$intent['category']??'conversation',
                'priority'=>(int)($intent['priority']??0),
                'terminal'=>(bool)($intent['terminal']??false),
                'route'=>$intent['route']??'knowledge',
                'mutation'=>(bool)($intent['mutation']??false),
                'ai_required'=>(bool)($intent['ai_required']??true),
                'credit_behavior'=>$intent['credit_behavior']??'runtime',
                'reply'=>$intent['reply']??null,
                'tags'=>$intent['tags']??[],
                'aliases'=>$intent['aliases']??[],
                'matched_pattern'=>$matchedPattern,
            ];
        }

        usort($matches,fn(array $a,array $b)=>$b['priority']<=>$a['priority']);
        $best=$matches[0]??null;

        return [
            'language'=>$this->index['language']??'en',
            'message'=>$message,
            'normalized'=>$normalized,
            'matched'=>$best!==null,
            'intent'=>$best,
            'matches'=>$matches,
        ];
    }

    public function localReply(string $message): ?array
    {
        $scan=$this->classify($message);
        $intent=$scan['intent']??null;
        if(!is_array($intent)) return null;
        if(($intent['route']??null)!=='local_reply') return null;
        if(($intent['mutation']??true)!==false) return null;
        if(($intent['ai_required']??true)!==false) return null;

        return $intent;
    }

    public function isCapabilityQuestion(string $message): bool
    {
        $scan=$this->classify($message);
        return ($scan['intent']['id']??null)==='conversation.capability_question';
    }
}
