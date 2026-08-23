<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class LunaIntentGateway
{
    private array $contract;

    public function __construct()
    {
        $path=resource_path('luna/canonical_intent_schema.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->contract=is_array($decoded)?$decoded:[];
    }

    /**
     * API 1: decide only whether this turn is conversation or executable work.
     * No action detail and no user-facing copy is allowed out of this stage.
     *
     * @return array{intent:string}
     */
    public function route(string $message,array $siteMemory=[],array $context=[]): array
    {
        $message=trim($message);
        $prior=is_array($siteMemory['intent_context']??null)?$siteMemory['intent_context']:[];
        // Explicit mutation language and contextual follow-ups are deterministic.
        // Do not let a probabilistic router turn "apply it", "use this #HEX", or
        // "apply to the whole page" back into a conversational question.
        $localIntent=$this->localRoute($message,$prior);
        $intent=$localIntent==='action'
            ? 'action'
            : ($this->aiRoute($message,$prior,$context) ?? $localIntent);

        return ['intent'=>$intent==='action'?'action':'chat'];
    }

    /**
     * API 2 for action turns: produce internal execution JSON only.
     * This method must never compose or return a customer-facing reply.
     */
    public function classifyAction(string $message,array $siteMemory=[],array $context=[]): array
    {
        $message=trim($message);
        $prior=is_array($siteMemory['intent_context']??null)?$siteMemory['intent_context']:[];
        $schema=$this->localRoute($message,$prior)==='action'
            ? $this->localActionSchema($message,$prior,$context)
            : ($this->aiActionSchema($message,$prior,$context)
                ?? $this->localActionSchema($message,$prior,$context));

        return $this->normalizeAction($schema,$prior);
    }

    /** Backwards-compatible wrapper for older callers. New code uses route() first. */
    public function resolve(string $message,array $siteMemory=[],array $context=[]): array
    {
        $route=$this->route($message,$siteMemory,$context);
        if($route['intent']==='chat') return ['intent'=>'chat'];

        return $this->classifyAction($message,$siteMemory,$context);
    }

    public function memory(array $siteMemory,array $schema): array
    {
        if(($schema['intent']??'chat')!=='action') return $siteMemory;

        $siteMemory['intent_context']=[
            'intent'=>'action',
            'action'=>$schema['action']??'update',
            'scope'=>$schema['scope']??'page',
            'target'=>$schema['target']??null,
            'entities'=>$schema['entities']??[],
            'missing'=>$schema['missing']??[],
        ];

        return $siteMemory;
    }

    public function clearCompleted(array $siteMemory): array
    {
        unset($siteMemory['intent_context']);
        return $siteMemory;
    }

    private function aiRoute(string $message,array $prior,array $context): ?string
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;

        $system=<<<'PROMPT'
You are Luna's first routing API inside Cosmic CMS.

Return JSON only and return exactly one key:
{"intent":"chat"}
or
{"intent":"action"}

Use chat for greetings, conversation, questions, help, explanations, and capability/product questions.
Use action when the user is asking Luna to actually build, create, update, edit, redesign, publish, delete, or navigate somewhere.
"Can you build me a restaurant website?" is action when it is a concrete request to build it.
Do not classify the specific action. Do not return scope, target, entities, reasoning, suggestions, confirmation language, or a user-facing reply.
PROMPT;

        try{
            $response=Http::withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(60)
                ->post($this->endpoint(),[
                    'model'=>env('OPENAI_LUNA_ROUTER_MODEL',env('OPENAI_MODEL','gpt-5-mini')),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"CURRENT MESSAGE:\n{$message}\n\nPRIOR ACTION CONTEXT:\n".json_encode($prior,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nUI CONTEXT:\n".json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                    ],
                ])->throw()->json();

            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            $intent=is_array($decoded)?($decoded['intent']??null):null;
            return in_array($intent,['chat','action'],true)?$intent:null;
        }catch(\Throwable $e){
            report($e);
            return null;
        }
    }

    private function aiActionSchema(string $message,array $prior,array $context): ?array
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;

        $system=<<<'PROMPT'
You are Luna's internal Action Classifier inside Cosmic CMS. API 1 has already established that this is an action turn.

Return JSON only:
{
  "action": "build|update|publish|delete|navigate",
  "scope": "element|item|section|page|site|global_token",
  "target": null|string,
  "confidence": 0.0,
  "entities": {
    "business_name": null|string,
    "industry": null|string,
    "location": null|string,
    "site_scope": null|string
  },
  "missing": [],
  "needs_docs": true|false,
  "needs_clarification": true|false,
  "requires_confirmation": true|false,
  "execution_allowed": true|false,
  "reason": "short internal reason"
}

Rules:
- This output is machine-only. Never include reply, message, response, suggestion, question, or customer-facing prose.
- build means create a page/site or compose an empty page.
- update means mutate existing content, structure, media, shell, theme, or design tokens.
- publish and navigate must be routed to their dedicated executors, not the design planner.
- normal build, update, publish, and navigate actions execute directly and never require confirmation.
- only destructive delete may require an explicit safety confirmation.
- set execution_allowed=false only when a genuinely required target/detail is missing or the operation is impossible in the supplied context.
- preserve useful entities from prior action context when the current message is a follow-up.
PROMPT;

        try{
            $response=Http::withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(60)
                ->post($this->endpoint(),[
                    'model'=>env('OPENAI_LUNA_ACTION_MODEL',env('OPENAI_MODEL','gpt-5-mini')),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"CURRENT ACTION REQUEST:\n{$message}\n\nPRIOR ACTION CONTEXT:\n".json_encode($prior,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nEXECUTION CONTEXT:\n".json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                    ],
                ])->throw()->json();

            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            if(!is_array($decoded)) return null;
            foreach(['reply','message','response','suggestion','question'] as $forbidden) unset($decoded[$forbidden]);
            $decoded['source']='ai_action_classifier';
            return $decoded;
        }catch(\Throwable $e){
            report($e);
            return null;
        }
    }

    private function localRoute(string $message,array $prior): string
    {
        $clean=trim(preg_replace(
            '/^(?:(?:hello|hi|hey|good\s+(?:morning|afternoon|evening))\b[\s,!.:-]*(?:luna\b[\s,!.:-]*)?)+/i',
            '',
            $message
        )??$message);

        if($clean==='' || preg_match('/^(hello|hi|hey)[.! ]*$/i',$clean)) return 'chat';
        if(preg_match('/\b(what can you do|what are your capabilities|what can luna do|what do you support|do you support|is .* supported)\b/i',$clean)) return 'chat';
        if(preg_match('/^(how do i|how can i|help me|help with|where can i|what is|why|when|who)\b/i',$clean)) return 'chat';
        if(preg_match('/\b(build|create|make|generate|design|change|update|edit|rewrite|replace|redesign|rebrand|adjust|increase|decrease|add|remove|apply|use|set|publish|go live|delete|open|go to|take me to|navigate)\b/i',$clean)) return 'action';
        if(($prior['intent']??null)==='action' && (
            preg_match('/\b(this|that|it|same|page|section|heading|image|theme|everywhere|whole|sitewide)\b/i',$clean)
            || preg_match('/#[0-9a-f]{3,6}\b/i',$clean)
        )) return 'action';

        return 'chat';
    }

    private function localActionSchema(string $message,array $prior,array $context): array
    {
        $action='update';
        if(preg_match('/\b(publish|go live|make .* live)\b/i',$message)) $action='publish';
        elseif(preg_match('/\b(delete)\b/i',$message)) $action='delete';
        elseif(preg_match('/\b(open|go to|take me to|navigate to)\b/i',$message)) $action='navigate';
        elseif(preg_match('/\b(build|create|generate|design)\b.{0,100}\b(website|site|homepage|home page|landing page|page)\b/i',$message)) $action='build';

        $scope=(string)($context['ui_scope']??'page');
        if(!in_array($scope,$this->contract['scopes']??[],true) || $scope==='none') $scope='page';

        $target=$prior['target']??null;
        if(preg_match('/\b(homepage|home page)\b/i',$message)) $target='home';
        elseif(preg_match('/\b(?:open|go to|navigate to)\s+(?:the\s+)?([^,.!?]+)/i',$message,$match)) $target=trim($match[1]);

        return [
            'action'=>$action,
            'scope'=>$scope,
            'target'=>$target,
            'confidence'=>0.88,
            'entities'=>$this->extractEntities($message,$prior),
            'missing'=>[],
            'needs_docs'=>true,
            'needs_clarification'=>false,
            'requires_confirmation'=>$action==='delete',
            'execution_allowed'=>true,
            'reason'=>'Deterministic action fallback.',
            'source'=>'local_action_fallback',
        ];
    }

    private function normalizeAction(array $schema,array $prior): array
    {
        $actions=array_values(array_filter(
            $this->contract['actions']??['build','update','publish','delete','navigate'],
            fn($action)=>$action!=='none'
        ));
        $action=in_array(($schema['action']??''),$actions,true)?(string)$schema['action']:'update';
        $scope=in_array(($schema['scope']??''),$this->contract['scopes']??[],true)?(string)$schema['scope']:'page';
        if($scope==='none') $scope='page';

        $entities=array_filter(array_replace(
            is_array($prior['entities']??null)?$prior['entities']:[],
            is_array($schema['entities']??null)?$schema['entities']:[]
        ),fn($value)=>$value!==null&&$value!=='');
        $missing=array_values(array_unique(array_filter((array)($schema['missing']??[]),'is_string')));
        $needsClarification=(bool)($schema['needs_clarification']??($missing!==[]));

        return [
            'version'=>4,
            'intent'=>'action',
            'action'=>$action,
            'scope'=>$scope,
            'target'=>$schema['target']??($prior['target']??null),
            'confidence'=>max(0,min(1,(float)($schema['confidence']??0))),
            'entities'=>$entities,
            'missing'=>$missing,
            'needs_docs'=>(bool)($schema['needs_docs']??true),
            'needs_clarification'=>$needsClarification,
            'requires_confirmation'=>$action==='delete' && (bool)($schema['requires_confirmation']??true),
            'execution_allowed'=>!$needsClarification && (bool)($schema['execution_allowed']??true),
            'reason'=>(string)($schema['reason']??''),
            'source'=>$schema['source']??'unknown',
        ];
    }

    private function extractEntities(string $message,array $prior=[]): array
    {
        $entities=is_array($prior['entities']??null)?$prior['entities']:[];
        if(preg_match('/restaurant name\s+(?:is\s+)?["\']?([^"\']+)["\']?/i',$message,$match)){
            $name=trim(preg_replace('/\s+(?:can you|please|build|create|make)\b.*$/i','',$match[1])??$match[1]);
            if($name!=='') $entities['business_name']=$name;
        }
        if(preg_match('/\brestaurant\b/i',$message)) $entities['industry']='restaurant';
        if(preg_match('/\bOrmoc(?: City)?\b/i',$message)) $entities['location']='Ormoc City';

        return $entities;
    }

    private function endpoint(): string
    {
        return rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions';
    }
}
