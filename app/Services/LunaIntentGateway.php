<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class LunaIntentGateway
{
    private array $contract;
    private array $leafSchemas;

    public function __construct(private readonly LunaContextResolverService $contextResolver)
    {
        $path=resource_path('luna/canonical_intent_schema.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->contract=is_array($decoded)?$decoded:[];

        $leafPath=resource_path('luna/leaf_action_schemas.json');
        $leafDecoded=is_file($leafPath)?json_decode((string)file_get_contents($leafPath),true):[];
        $this->leafSchemas=is_array($leafDecoded['domains']??null)?$leafDecoded['domains']:[];
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
        $prior=$this->priorContext($siteMemory);
        $resolution=$this->contextResolver->resolve($message,$prior,$context);
        if(($resolution['is_followup']??false)===true) $prior['resolved_followup']=$resolution;
        // Dimension 1 / API 1: always keep the model choice tiny: chat | action.
        // Local routing is a safety/fallback layer, not a replacement for the API call.
        $localIntent=$this->localRoute($message,$prior);
        $aiIntent=$this->aiRoute($message,$prior,$context);
        $intent=$aiIntent ?? $localIntent;
        if(($resolution['is_followup']??false)===true && ($resolution['execution_allowed']??false)===true) $intent='action';

        // Explicit executable language wins if the probabilistic router ever regresses.
        if($localIntent==='action') $intent='action';

        return ['intent'=>$intent==='action'?'action':'chat'];
    }

    /**
     * Nested action routing for action turns. API 2 locks the action family;
     * API 3 resolves domain/operation/target into the canonical execution JSON.
     * No intermediate output is customer-facing.
     */
    public function classifyAction(string $message,array $siteMemory=[],array $context=[]): array
    {
        $message=trim($message);
        $prior=$this->priorContext($siteMemory);
        $resolution=$this->contextResolver->resolve($message,$prior,$context);
        if(($resolution['is_followup']??false)===true) $prior['resolved_followup']=$resolution;
        // Dimension 2 / API 2: choose only the action family. No domain/target yet.
        $localFamily=$this->localActionFamily($message);
        $actionFamily=$this->aiActionFamily($message,$prior,$context) ?? $localFamily;
        if(!in_array($actionFamily,['build','update','inspect','publish','delete','navigate'],true)) $actionFamily=$localFamily;

        // Dimension 3 / API 3: resolve domain + atomic operation + target/change schema
        // while the already-chosen action family is locked and cannot drift.
        $schema=$this->aiDomainSchema($message,$actionFamily,$prior,$context)
            ?? $this->localActionSchema($message,$prior,$context,$actionFamily);
        $schema['action']=$actionFamily;
        $schema['routing']=[
            'intent'=>'action',
            'action_family'=>$actionFamily,
            'domain'=>$schema['domain']??null,
            'leaf_operation'=>$schema['leaf_operation']??null,
            'api_depth'=>3,
        ];

        $normalized=$this->normalizeAction($schema,$prior);
        $normalized=$this->contextResolver->apply($normalized,$resolution,$message);
        $normalized['routing']=array_replace(is_array($normalized['routing']??null)?$normalized['routing']:[],[
            'intent'=>'action',
            'action_family'=>$normalized['action']??$actionFamily,
            'domain'=>$normalized['domain']??null,
            'leaf_operation'=>$normalized['leaf_operation']??null,
            'api_depth'=>3,
        ]);
        if(($normalized['action']??'')==='inspect'){
            $surface=(string)($context['surface']??'');
            $ready=in_array($surface,['builder','trial_builder'],true);
            $normalized['execution_allowed']=$ready && !($normalized['needs_clarification']??false);
            $normalized['reason']=$ready
                ? 'Read-only inspect routed to the verified Builder inspector.'
                : 'Read-only inspect requires current Builder page state; no mutation fallback is permitted.';
        }
        return $normalized;
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

        $siteMemory['routing_context']=[
            'intent'=>'action',
            'action'=>$schema['action']??'update',
            'scope'=>$schema['scope']??'page',
            'domain'=>$schema['domain']??null,
            'operation'=>$schema['operation']??null,
            'leaf_operation'=>$schema['leaf_operation']??null,
            'target'=>$schema['target']??null,
            'changes'=>$schema['changes']??[],
            'entities'=>$schema['entities']??[],
            'missing'=>$schema['missing']??[],
        ];

        return $siteMemory;
    }

    public function clearCompleted(array $siteMemory): array
    {
        unset($siteMemory['routing_context']);
        return $siteMemory;
    }

    private function priorContext(array $siteMemory): array
    {
        $state=is_array($siteMemory['context_state']??null)?$siteMemory['context_state']:[];
        $verified=is_array($state['last_verified_action']??null)?$state['last_verified_action']:[];
        $current=is_array($state['current']??null)?$state['current']:[];
        $routing=is_array($siteMemory['routing_context']??null)?$siteMemory['routing_context']:[];
        return [
            'last_verified_action'=>$verified,
            'current'=>$current,
            'last_routing_context'=>$routing,
        ];
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
Use action when the user is asking Luna to actually build, create, update, edit, redesign, inspect/read current website state, publish, delete, or navigate somewhere.
"Can you build me a restaurant website?" is action when it is a concrete request to build it.
"Can you change the theme?", "Could you switch the palette?", and "Would you update the brand colors?" are ACTION requests, not capability questions.
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

    /**
     * API 2: action family only. This intentionally has a six-value choice set.
     */
    private function aiActionFamily(string $message,array $prior,array $context): ?string
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;

        $system=<<<'PROMPT'
You are Luna's second routing API inside Cosmic CMS. API 1 already decided this turn is ACTION.

Return JSON only and exactly one key:
{"action":"build|update|inspect|publish|delete|navigate"}

Definitions:
- build: create/compose a new website or page.
- update: mutate existing content, design, theme, layout, media, settings, posts, commerce, header/footer/navigation.
- inspect: read/audit current website state without mutation.
- publish: publish/go live/republish.
- delete: destructive deletion request.
- navigate: open/go to/take user to another CMS destination.

Do not return domain, operation, target, scope, changes, reasoning, reply, or any other key.
PROMPT;

        try{
            $response=Http::withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(60)
                ->post($this->endpoint(),[
                    'model'=>env('OPENAI_LUNA_ACTION_ROUTER_MODEL',env('OPENAI_LUNA_ROUTER_MODEL',env('OPENAI_MODEL','gpt-5-mini'))),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"CURRENT ACTION REQUEST:\n{$message}\n\nPRIOR VERIFIED/ROUTING CONTEXT:\n".json_encode($prior,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nUI CONTEXT:\n".json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                    ],
                ])->throw()->json();

            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            $action=is_array($decoded)?($decoded['action']??null):null;
            return in_array($action,['build','update','inspect','publish','delete','navigate'],true)?$action:null;
        }catch(\Throwable $e){
            report($e);
            return null;
        }
    }

    private function localActionFamily(string $message): string
    {
        if(preg_match('/\b(publish|republish|go live|make .* live)\b/i',$message)) return 'publish';
        if(preg_match('/\b(delete|destroy|permanently remove)\b/i',$message)) return 'delete';
        if(preg_match('/\b(open|go to|take me to|navigate to)\b/i',$message)) return 'navigate';
        if(preg_match('/\b(inspect|audit|check)\b/i',$message) && !preg_match('/\b(fix|repair|correct|change|update|improve)\b/i',$message)) return 'inspect';
        if(preg_match('/^(?:what|which|how many|does|is)\b.*\b(?:this|current|page|site|website|section|heading|font|font size|color|colour|image|theme|padding|spacing|cards?|sections?|h1|h2|h3)\b/i',$message)) return 'inspect';
        if(preg_match('/\b(build|create|generate|design)\b.{0,100}\b(website|site|homepage|home page|landing page|page)\b/i',$message)) return 'build';
        return 'update';
    }

    private function aiDomainSchema(string $message,string $actionFamily,array $prior,array $context): ?array
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;

        $system=<<<'PROMPT'
You are Luna's third routing API inside Cosmic CMS. API 1 established ACTION and API 2 already locked the action family. Resolve only the domain, atomic operation, scope, target and structured changes. Never change the supplied action family.

Return JSON only:
{
  "action": "LOCKED_ACTION_FAMILY",
  "domain": "site|page|section|element|content|theme|design|typography|layout|media|header|navigation|footer|form|seo|post|commerce|settings|responsive|qa|publishing",
  "leaf_operation": "finite domain-specific operation from LEAF ACTION CATALOG",
  "operation": "build|create|update|replace|redesign|add|remove|duplicate|reorder|move|read|inspect|search|generate|configure|publish|delete|navigate|audit_and_repair",
  "scope": "element|item|section|page|site|global_token",
  "target": null|{"type":null|string,"key":null|string,"id":null|string|number,"label":null|string,"selector":null|string,"index":null|number,"field":null|string,"level":null|string},
  "changes": {},
  "constraints": [],
  "operations": [],
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
- The action field MUST exactly equal LOCKED ACTION FAMILY supplied in the user payload. Never reinterpret it.
- build means create a page/site or compose an empty page.
- update means mutate existing content, structure, media, shell, theme, or design tokens.
- inspect means read actual current website/page/section/element state and must never mutate.
- domain identifies the feature family. leaf_operation MUST be selected from the finite LEAF ACTION CATALOG for that domain.
- operation is the backwards-compatible executor operation derived from the leaf operation; never invent a new executor operation.
- target must be a structured object when a concrete target exists.
- changes must contain structured desired mutations, never prose instructions.
- compound requests must be decomposed into operations[]; each operation should include domain, leaf_operation, operation, scope, target, changes, and constraints.
- Keep operation order identical to the user's requested order.
- For phrases such as "do the same to X", "same for X", or "and X too", create a separate operation for X and copy the immediately preceding operation's domain/leaf_operation/changes unless the user explicitly changes them.
- For follow-ups such as "apply that to all H2s", inherit only from PRIOR VERIFIED ACTION CONTEXT; never inherit from a pending/unverified operation.
- Never collapse multiple independent mutations into one vague operation. Each independently verifiable change gets its own operations[] row.
- For post actions, encode explicit values in changes using keys such as title, slug, excerpt, content, category, tags, image_url, status. Target an existing post by target.id or target.label when possible.
- For commerce actions, encode explicit values in changes using keys such as title, slug, sku, price, sale_price, status, stock_quantity, track_inventory, allow_backorders, categories, featured_image_url, type, fulfillment_type, is_featured. Target an existing product/category by target.id or target.label when possible.
- Creating a post/product/category should use operation=create or add. Updating price/stock/status/categories should use operation=update/configure. Deletion remains action=delete and requires confirmation.
- publish and navigate must be routed to their dedicated executors, not the design planner.
- normal build, update, inspect, publish, and navigate actions never require a proceed confirmation.
- only destructive delete may require an explicit safety confirmation.
- set execution_allowed=false only when a genuinely required target/detail is missing or the operation is impossible in the supplied context.
- preserve useful entities from prior action context when the current message is a follow-up.

PROMPT;
        $system.="\n\nLEAF ACTION CATALOG:\n".$this->leafPromptCatalog();

        try{
            $response=Http::withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(60)
                ->post($this->endpoint(),[
                    'model'=>env('OPENAI_LUNA_DOMAIN_ROUTER_MODEL',env('OPENAI_LUNA_ACTION_MODEL',env('OPENAI_MODEL','gpt-5-mini'))),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"LOCKED ACTION FAMILY: {$actionFamily}\n\nCURRENT ACTION REQUEST:\n{$message}\n\nPRIOR ACTION CONTEXT:\n".json_encode($prior,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nEXECUTION CONTEXT:\n".json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                    ],
                ])->throw()->json();

            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            if(!is_array($decoded)) return null;
            foreach(['reply','message','response','suggestion','question'] as $forbidden) unset($decoded[$forbidden]);
            $decoded['action']=$actionFamily;
            $decoded['source']='ai_domain_router';
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

        // Question-shaped mutation requests are still actions. This MUST run
        // before theme discovery/capability wording so "Can you change the theme?"
        // cannot be misread as "What themes can I use?".
        if(preg_match('/^(?:can|could|would|will)\s+you\s+(?:please\s+)?(?:change|switch|replace|update|set|apply|use)\b.*\b(?:theme|palette|colou?r scheme|brand colou?rs?)\b/i',$clean)) return 'action';

        // Theme discovery is conversational. Luna should present broad color-family
        // choices instead of treating "what themes can I use?" as a site-state inspect.
        if(preg_match('/\b(what|which|show|list|available|options?|choices?)\b.*\b(themes?|colou?r families|palettes?|colou?r schemes?)\b|\b(themes?|colou?r families|palettes?)\b.*\b(available|options?|choices?|can i use|could i use)\b/i',$clean)) return 'chat';

        // How-to/help questions are conversational even if they mention a mutation verb.
        // "How do I change the theme?" is chat; "Can you change the theme?" is action.
        if(preg_match('/^(how do i|how can i|help me understand|where can i|what is|why|when|who)\b/i',$clean)) return 'chat';

        // Pure capability questions remain chat even when they contain words such as change/build.
        if(preg_match('/\b(what can you do|what are your capabilities|what can luna do|what do you support|do you support|is .* supported)\b/i',$clean)) return 'chat';

        // Concrete mutation verbs win even when phrased politely as a question:
        // "Can you change the theme to charcoal?" is executable work, not a capability enquiry.
        if(preg_match('/\b(build|create|make|generate|design|change|update|edit|rewrite|replace|redesign|rebrand|adjust|increase|decrease|reduce|add|remove|apply|use|set|publish|go live|delete|open|go to|take me to|navigate|inspect|check|audit)\b/i',$clean)) return 'action';
        if(preg_match('/^(?:what|which|how many|does|is)\b.*\b(?:this|current|page|site|website|section|heading|font|font size|color|colour|image|theme|padding|spacing|cards?|sections?|h1|h2|h3)\b/i',$clean)) return 'action';
        if(($prior['intent']??null)==='action' && (
            preg_match('/\b(this|that|it|same|page|section|heading|image|theme|everywhere|whole|sitewide)\b/i',$clean)
            || preg_match('/#[0-9a-f]{3,6}\b/i',$clean)
        )) return 'action';

        return 'chat';
    }

    private function localActionSchema(string $message,array $prior,array $context,?string $forcedAction=null): array
    {
        $action=$forcedAction ?: 'update';
        if($forcedAction===null){
            $action=$this->localActionFamily($message);
        }

        $scope=(string)($context['ui_scope']??'page');
        if(!in_array($scope,$this->contract['scopes']??[],true) || $scope==='none') $scope='page';

        $target=$this->normalizeTarget($prior['target']??null);
        if(preg_match('/\b(homepage|home page)\b/i',$message)) $target=['type'=>'page','key'=>'home','label'=>'home'];
        elseif(preg_match('/\b(?:open|go to|navigate to)\s+(?:the\s+)?([^,.!?]+)/i',$message,$match)) $target=['type'=>'destination','key'=>trim($match[1]),'label'=>trim($match[1])];
        elseif($target===null && preg_match('/\b(h1|h2|h3|heading|button|image|logo|section|footer|header|theme)\b/i',$message,$match)) $target=['type'=>'element','key'=>strtolower($match[1]),'label'=>strtolower($match[1])];

        [$domain,$operation,$leafOperation]=$this->inferDomainOperation($message,$action,$scope);
        $changes=$this->inferChanges($message,$action);
        $operationItem=[
            'domain'=>$domain,
            'operation'=>$operation,
            'leaf_operation'=>$leafOperation,
            'scope'=>$scope,
            'target'=>$target,
            'changes'=>$changes,
            'constraints'=>[],
        ];
        $compoundOperations=$this->localCompoundOperations($message,$scope,$operationItem);

        return [
            'action'=>$action,
            'domain'=>$domain,
            'operation'=>$operation,
            'leaf_operation'=>$leafOperation,
            'scope'=>$scope,
            'target'=>$target,
            'changes'=>$changes,
            'constraints'=>[],
            'operations'=>$compoundOperations,
            'confidence'=>0.88,
            'entities'=>$this->extractEntities($message,$prior),
            'missing'=>[],
            'needs_docs'=>true,
            'needs_clarification'=>false,
            'requires_confirmation'=>$action==='delete',
            // Inspect execution is introduced in Batch 3. Classify it now, but never
            // let it fall through to a mutation executor in Batch 1.
            'execution_allowed'=>true,
            'reason'=>$action==='inspect'?'Read-only inspect classified for the verified Builder inspector.':'Deterministic action fallback.',
            'source'=>'local_action_fallback',
        ];
    }

    private function localCompoundOperations(string $message,string $scope,array $fallback): array
    {
        // Deterministic fallback for common multi-part commands when API 3 is unavailable.
        // Keep this conservative: only split clear conjunctions that describe another mutation
        // or explicitly say "same". Creative/complex decomposition remains API 3's job.
        $clauses=preg_split('/\s*(?:,?\s+and\s+|,?\s+then\s+|;\s*|,\s+also\s+)\s*/iu',trim($message))?:[];
        if(count($clauses)<2) return [$fallback];

        $ops=[];
        $previous=null;
        foreach($clauses as $clause){
            $clause=trim($clause);
            if($clause==='') continue;
            $same=(bool)preg_match('/\b(?:same|that too|do that|do the same)\b/iu',$clause);
            $hasMutation=(bool)preg_match('/\b(?:make|change|update|edit|replace|redesign|adjust|increase|decrease|reduce|add|remove|apply|set|use|move|reorder|hide|show)\b/iu',$clause);
            if(!$hasMutation && !$same) continue;

            if($same && is_array($previous)){
                $op=$previous;
                $op['inherit_previous']=true;
                $op['changes']=[]; // normalizeOperations inherits the verified same-turn mutation.
            }else{
                [$domain,$operation,$leaf]=$this->inferDomainOperation($clause,'update',$scope);
                $op=[
                    'domain'=>$domain,
                    'operation'=>$operation,
                    'leaf_operation'=>$leaf,
                    'scope'=>$scope,
                    'target'=>null,
                    'changes'=>$this->inferChanges($clause,'update'),
                    'constraints'=>[],
                ];
            }

            if(preg_match('/\b(hero|banner|header|footer|services?|pricing|testimonials?|contact|about|heading|h1|h2|h3|button|image|logo|section|cards?)\b/iu',$clause,$m)){
                $key=strtolower($m[1]);
                $op['target']=['type'=>'element','key'=>$key,'label'=>$key];
            }elseif(is_array($previous) && $same){
                $op['target']=$previous['target']??null;
            }
            $ops[]=$op;
            $previous=$op;
        }
        return count($ops)>1?$ops:[$fallback];
    }

    private function normalizeAction(array $schema,array $prior): array
    {
        $actions=array_values(array_filter(
            $this->contract['actions']??['build','update','inspect','publish','delete','navigate'],
            fn($action)=>$action!=='none'
        ));
        $action=in_array(($schema['action']??''),$actions,true)?(string)$schema['action']:'update';
        $scope=in_array(($schema['scope']??''),$this->contract['scopes']??[],true)?(string)$schema['scope']:'page';
        if($scope==='none') $scope='page';

        $domains=$this->contract['domains']??[];
        $domain=in_array(($schema['domain']??''),$domains,true)?(string)$schema['domain']:$this->defaultDomain($action,$scope);
        $allowedOperations=$this->contract['operations']??[];
        $requestedOperation=in_array(($schema['operation']??''),$allowedOperations,true)?(string)$schema['operation']:$this->defaultOperation($action);
        $leafOperation=$this->normalizeLeafOperation($domain,(string)($schema['leaf_operation']??''),$requestedOperation,$action);
        $operation=$this->executorOperationForLeaf($domain,$leafOperation,$requestedOperation);
        $target=$this->normalizeTarget($schema['target']??($prior['target']??null));
        $changes=is_array($schema['changes']??null)?$schema['changes']:[];
        $constraints=array_values(array_unique(array_filter((array)($schema['constraints']??[]),'is_string')));
        $leafMissing=$this->leafMissingChanges($domain,$leafOperation,$changes);
        $operations=$this->normalizeOperations($schema['operations']??[],[
            'domain'=>$domain,'operation'=>$operation,'leaf_operation'=>$leafOperation,'scope'=>$scope,'target'=>$target,'changes'=>$changes,'constraints'=>$constraints,
        ]);

        $entities=array_filter(array_replace(
            is_array($prior['entities']??null)?$prior['entities']:[],
            is_array($schema['entities']??null)?$schema['entities']:[]
        ),fn($value)=>$value!==null&&$value!=='');
        $missing=array_values(array_unique(array_merge(
            array_filter((array)($schema['missing']??[]),'is_string'),
            array_map(fn($key)=>'changes.'.$key,$leafMissing)
        )));
        $compoundMissing=[];
        foreach($operations as $index=>$op){
            foreach((array)($op['missing_changes']??[]) as $key) $compoundMissing[]='operations.'.$index.'.changes.'.$key;
        }
        if($compoundMissing!==[]) $missing=array_values(array_unique(array_merge($missing,$compoundMissing)));
        $needsClarification=(bool)($schema['needs_clarification']??($missing!==[]));
        $executionAllowed=!$needsClarification && (bool)($schema['execution_allowed']??true);
        // Batch 3: inspect has a dedicated read-only executor on Builder surfaces.

        return [
            'version'=>7,
            'intent'=>'action',
            'action'=>$action,
            'domain'=>$domain,
            'operation'=>$operation,
            'leaf_operation'=>$leafOperation,
            'scope'=>$scope,
            'target'=>$target,
            'changes'=>$changes,
            'constraints'=>$constraints,
            'operations'=>$operations,
            'compound'=>count($operations)>1,
            'operation_count'=>count($operations),
            'confidence'=>max(0,min(1,(float)($schema['confidence']??0))),
            'entities'=>$entities,
            'missing'=>$missing,
            'needs_docs'=>(bool)($schema['needs_docs']??true),
            'needs_clarification'=>$needsClarification,
            'requires_confirmation'=>$action==='delete' && (bool)($schema['requires_confirmation']??true),
            'execution_allowed'=>$executionAllowed,
            'reason'=>(string)($schema['reason']??''),
            'source'=>$schema['source']??'unknown',
            'routing'=>is_array($schema['routing']??null)?$schema['routing']:[],
        ];
    }

    private function normalizeOperations(mixed $operations,array $fallback): array
    {
        $out=[];
        $previous=null;
        foreach(is_array($operations)?array_values($operations):[] as $position=>$item){
            if(!is_array($item)) continue;
            $scope=in_array(($item['scope']??''),$this->contract['scopes']??[],true)?(string)$item['scope']:$fallback['scope'];
            $domain=in_array(($item['domain']??''),$this->contract['domains']??[],true)?(string)$item['domain']:$fallback['domain'];
            $requestedOperation=in_array(($item['operation']??''),$this->contract['operations']??[],true)?(string)$item['operation']:$fallback['operation'];
            $leafOperation=$this->normalizeLeafOperation($domain,(string)($item['leaf_operation']??($fallback['leaf_operation']??'')),$requestedOperation,'update');
            $operation=$this->executorOperationForLeaf($domain,$leafOperation,$requestedOperation);
            $itemChanges=is_array($item['changes']??null)?$item['changes']:[];
            // A structured compound row may explicitly declare that it reuses the
            // preceding mutation (e.g. "do the same to the services heading").
            // Only fill omitted values; explicit current-row values always win.
            $inheritPrevious=(bool)($item['inherit_previous']??false);
            if($inheritPrevious && is_array($previous)){
                if(($item['domain']??null)===null) $domain=$previous['domain'];
                if(($item['leaf_operation']??null)===null) $leafOperation=$previous['leaf_operation'];
                if(($item['operation']??null)===null) $operation=$previous['operation'];
                $itemChanges=array_replace_recursive(is_array($previous['changes']??null)?$previous['changes']:[],$itemChanges);
            }
            $missingChanges=$this->leafMissingChanges($domain,$leafOperation,$itemChanges);
            $normalized=[
                'operation_id'=>(string)($item['operation_id']??('op_'.($position+1))),
                'domain'=>$domain,
                'operation'=>$operation,
                'leaf_operation'=>$leafOperation,
                'scope'=>$scope==='none'?'page':$scope,
                'target'=>$this->normalizeTarget($item['target']??$fallback['target']),
                'changes'=>$itemChanges,
                'constraints'=>array_values(array_unique(array_filter((array)($item['constraints']??[]),'is_string'))),
                'leaf_valid'=>$missingChanges===[],
                'missing_changes'=>$missingChanges,
                'context_source'=>$inheritPrevious?'previous_operation':($item['context_source']??null),
            ];
            $out[]=$normalized;
            $previous=$normalized;
        }
        if($out!==[]) return $out;
        $fallback['operation_id']=$fallback['operation_id']??'op_1';
        return [$fallback];
    }

    private function normalizeTarget(mixed $target): ?array
    {
        if(is_string($target) && trim($target)!=='') return ['type'=>null,'key'=>trim($target),'label'=>trim($target)];
        if(!is_array($target)) return null;
        $allowed=$this->contract['target_contract']['allowed_keys']??['type','key','id','label','selector','index','field','level'];
        $out=[];
        foreach($allowed as $key){
            if(array_key_exists($key,$target) && $target[$key]!==null && $target[$key]!=='') $out[$key]=$target[$key];
        }
        return $out!==[]?$out:null;
    }

    private function defaultDomain(string $action,string $scope): string
    {
        return match($action){
            'publish'=>'publishing',
            'navigate'=>'navigation',
            'build'=>'page',
            'delete'=>$scope==='site'?'site':($scope==='page'?'page':'section'),
            default=>$scope==='site'?'site':($scope==='page'?'page':($scope==='section'?'section':'element')),
        };
    }

    private function defaultOperation(string $action): string
    {
        return match($action){
            'build'=>'build','inspect'=>'inspect','publish'=>'publish','delete'=>'delete','navigate'=>'navigate',default=>'update',
        };
    }

    private function inferDomainOperation(string $message,string $action,string $scope): array
    {
        $q=strtolower($message);
        $domain=$this->defaultDomain($action,$scope);
        if(preg_match('/\b(theme|palette|colou?r scheme|brand colou?rs?)\b/i',$message)) $domain='theme';
        elseif(preg_match('/\b(font|typography|h1|h2|h3|heading|line height|letter spacing)\b/i',$message)) $domain='typography';
        elseif(preg_match('/\b(image|photo|picture|logo|video|media)\b/i',$message)) $domain='media';
        elseif(preg_match('/\b(header|navigation|menu|nav)\b/i',$message)) $domain=stripos($q,'header')!==false?'header':'navigation';
        elseif(preg_match('/\bfooter\b/i',$message)) $domain='footer';
        elseif(preg_match('/\b(padding|margin|gap|columns?|grid|width|height|alignment|layout|bento|carousel)\b/i',$message)) $domain='layout';
        elseif(preg_match('/\b(color|colour|radius|rounded|shadow|border|opacity|overlay)\b/i',$message)) $domain='design';
        elseif(preg_match('/\b(seo|meta|slug|canonical|og image|indexing)\b/i',$message)) $domain='seo';
        elseif(preg_match('/\b(form|field|input|submission)\b/i',$message)) $domain='form';
        elseif(preg_match('/\b(?:site name|website name|business name|company name|contact email|business email|contact phone|phone number|business location|address|industry|favicon|site settings|website settings)\b/i',$message)) $domain='settings';
        elseif(preg_match('/\b(product|cart|checkout|inventory|variant|store|commerce|product category|collection)\b/i',$message)) $domain='commerce';
        elseif(preg_match('/\b(post|blog|blog category|tag|archive)\b/i',$message)) $domain='post';
        elseif(preg_match('/\b(mobile|tablet|desktop|responsive|breakpoint)\b/i',$message)) $domain='responsive';
        elseif(preg_match('/\b(audit|check|inconsistent|consistency|qa)\b/i',$message)) $domain='qa';

        $operation=$this->defaultOperation($action);
        if($action==='update'){
            if(preg_match('/\b(add|insert)\b/i',$message)) $operation='add';
            elseif(preg_match('/\b(remove)\b/i',$message)) $operation='remove';
            elseif(preg_match('/\b(duplicate|clone)\b/i',$message)) $operation='duplicate';
            elseif(preg_match('/\b(reorder|sort)\b/i',$message)) $operation='reorder';
            elseif(preg_match('/\b(move)\b/i',$message)) $operation='move';
            elseif(preg_match('/\b(replace|swap)\b/i',$message)) $operation='replace';
            elseif(preg_match('/\b(redesign|revamp|rework)\b/i',$message)) $operation='redesign';
            elseif($domain==='qa') {
                $operation=preg_match('/\b(fix|repair|correct|polish|resolve|clean up|improve)\b/i',$message) ? 'audit_and_repair' : 'inspect';
            }
        }
        $leafOperation=$this->inferLeafOperation($message,$domain,$operation,$action);
        $operation=$this->executorOperationForLeaf($domain,$leafOperation,$operation);
        return [$domain,$operation,$leafOperation];
    }

    private function inferChanges(string $message,string $action): array
    {
        if(in_array($action,['inspect','publish','navigate','delete'],true)) return [];
        $changes=[];
        if(preg_match('/#([0-9a-f]{3}|[0-9a-f]{6})\b/i',$message,$m)) $changes['color']='#'.strtoupper($m[1]);
        if(preg_match('/\b(green|blue|purple|violet|red|pink|rose|amber|gold|brown|earth|dark|monochrome|neutral|navy|teal|emerald|charcoal|terracotta|coffee|espresso|ocean|indigo|ruby|forest|obsidian|asphalt)\b/i',$message,$m) && preg_match('/\b(theme|palette|colou?r scheme|brand colou?rs?)\b/i',$message)) $changes['family']=strtolower($m[1]);
        if(preg_match('/\b(mobile|tablet|desktop)\b/i',$message,$m)) $changes['breakpoint']=strtolower($m[1]);
        if(preg_match('/\b([1-6])\s+columns?\b/i',$message,$m)) $changes['columns']=(int)$m[1];
        if(preg_match('/\b(background video|video background)\b/i',$message)) $changes['media_role']='background_video';
        elseif(preg_match('/\b(background image|image background|background photo)\b/i',$message)) $changes['media_role']='background_image';
        if(preg_match('/\b(remove|delete)\b.*\b(image|photo|video|media)\b/i',$message,$m)) $changes['media_action']='remove';
        elseif(preg_match('/\b(replace|change|swap)\b.*\b(image|photo|video|media)\b/i',$message,$m)) $changes['media_action']='replace';
        if(preg_match('/\b(slightly|a little)\s+(smaller|larger|bigger)\b/i',$message,$m)) $changes['relative_size']=['direction'=>in_array(strtolower($m[2]),['larger','bigger'],true)?'increase':'decrease','amount'=>'slight'];
        elseif(preg_match('/\b(smaller|decrease|reduce)\b/i',$message)) $changes['relative_size']=['direction'=>'decrease'];
        elseif(preg_match('/\b(larger|bigger|increase)\b/i',$message)) $changes['relative_size']=['direction'=>'increase'];

        // Batch 5 local fallback: capture explicit content/catalog values so the
        // deterministic executor does not depend on a probabilistic classifier.
        if(preg_match('/\b(?:title|name)\s*(?:to|as|:)\s*["“]([^"”]+)["”]/iu',$message,$m)) $changes['title']=trim($m[1]);
        if(preg_match('/\bsku\s*(?:to|as|:)\s*["“]?([A-Z0-9._\-]+)["”]?/iu',$message,$m)) $changes['sku']=trim($m[1]);
        if(preg_match('/\b(?:regular )?price\s*(?:to|as|:)?\s*(?:[A-Z]{3}|[$₱£€])?\s*([0-9]+(?:\.[0-9]{1,2})?)/iu',$message,$m)) $changes['price']=(float)$m[1];
        if(preg_match('/\bsale price\s*(?:to|as|:)?\s*(?:[A-Z]{3}|[$₱£€])?\s*([0-9]+(?:\.[0-9]{1,2})?)/iu',$message,$m)) $changes['sale_price']=(float)$m[1];
        if(preg_match('/\bstock(?: quantity)?\s*(?:to|as|:)?\s*(\d+)/iu',$message,$m)) $changes['stock_quantity']=(int)$m[1];
        if(preg_match('/\b(?:category|categories)\s*(?:to|as|:)?\s*["“]([^"”]+)["”]/iu',$message,$m)) $changes['category']=trim($m[1]);
        if(preg_match('/\btags?\s*(?:to|as|:)?\s*["“]([^"”]+)["”]/iu',$message,$m)) $changes['tags']=array_values(array_filter(array_map('trim',explode(',',$m[1]))));
        if(preg_match('/\b(published|publish)\b/iu',$message)) $changes['status']='published';
        elseif(preg_match('/\b(archived|archive)\b/iu',$message)) $changes['status']='archived';
        elseif(preg_match('/\b(draft|unpublish)\b/iu',$message)) $changes['status']='draft';
        return $changes;
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

    private function leafPromptCatalog(): string
    {
        $rows=[];
        foreach($this->leafSchemas as $domain=>$definition){
            $ops=array_keys(is_array($definition['leaf_operations']??null)?$definition['leaf_operations']:[]);
            if($ops!==[]) $rows[]=$domain.': '.implode(' | ',$ops);
        }
        return implode("\n",$rows);
    }

    private function normalizeLeafOperation(string $domain,string $leafOperation,string $executorOperation,string $action): string
    {
        $allowed=array_keys(is_array($this->leafSchemas[$domain]['leaf_operations']??null)?$this->leafSchemas[$domain]['leaf_operations']:[]);
        if($leafOperation!=='' && in_array($leafOperation,$allowed,true)) return $leafOperation;

        foreach($allowed as $candidate){
            $mapped=(string)($this->leafSchemas[$domain]['leaf_operations'][$candidate]['executor_operation']??'');
            if($mapped===$executorOperation) return $candidate;
        }

        $preferred=match($action){
            'build'=>'create','inspect'=>'inspect','publish'=>'publish','delete'=>'delete','navigate'=>'navigate',default=>'update',
        };
        if(in_array($preferred,$allowed,true)) return $preferred;
        return $allowed[0]??$preferred;
    }

    private function executorOperationForLeaf(string $domain,string $leafOperation,string $fallback): string
    {
        $mapped=(string)($this->leafSchemas[$domain]['leaf_operations'][$leafOperation]['executor_operation']??'');
        return in_array($mapped,$this->contract['operations']??[],true)?$mapped:$fallback;
    }

    private function leafMissingChanges(string $domain,string $leafOperation,array $changes): array
    {
        $required=(array)($this->leafSchemas[$domain]['leaf_operations'][$leafOperation]['required_changes']??[]);
        return array_values(array_filter($required,fn($key)=>!array_key_exists($key,$changes) || $changes[$key]===null || $changes[$key]===''));
    }

    private function inferLeafOperation(string $message,string $domain,string $operation,string $action): string
    {
        $q=strtolower($message);
        $leaf=match($domain){
            'theme'=>preg_match('/#[0-9a-f]{3,6}\b/i',$message)?'set_custom_hex':(
                preg_match('/\b(?:green|blue|purple|violet|red|pink|rose|amber|gold|brown|earth|dark|monochrome|neutral|navy|teal|emerald|charcoal|terracotta|coffee|espresso|ocean|indigo|ruby|forest|obsidian|asphalt)\b/i',$message)?'set_family':'switch_family'
            ),
            'typography'=>match(true){
                str_contains($q,'line height')=>'line_height',str_contains($q,'letter spacing')=>'letter_spacing',
                preg_match('/\bfont family|typeface\b/i',$message)=>'font_family',preg_match('/\bweight|bold|lighter\b/i',$message)=>'font_weight',
                preg_match('/\b(center|left|right|align)\b/i',$message)=>'text_align',default=>'font_size',
            },
            'layout'=>match(true){
                str_contains($q,'padding')=>'padding',str_contains($q,'margin')=>'margin',preg_match('/\bgap\b/i',$message)=>'gap',
                preg_match('/\bcolumns?|grid\b/i',$message)=>'columns',preg_match('/\bcontainer|width\b/i',$message)=>'container_width',
                preg_match('/\bheight|taller|shorter\b/i',$message)=>'section_height',preg_match('/\balign/i',$message)=>'alignment',
                $operation==='reorder'=>'reorder',$operation==='move'=>'move',$operation==='redesign'=>'redesign',default=>'redesign',
            },
            'design'=>match(true){
                preg_match('/\bbackground|bg\b/i',$message)=>'background',preg_match('/\bradius|rounded\b/i',$message)=>'border_radius',
                preg_match('/\bshadow\b/i',$message)=>'shadow',preg_match('/\bopacity\b/i',$message)=>'opacity',preg_match('/\boverlay\b/i',$message)=>'overlay',default=>'border',
            },
            'media'=>match(true){
                preg_match('/\blogo\b/i',$message)=>'logo',preg_match('/\bvideo\b/i',$message)=>$operation==='remove'?'remove_video':'replace_video',
                preg_match('/\bgenerate|create\b.*\bimage|photo|picture\b/i',$message)=>'generate_image',
                preg_match('/\bmedia library|library\b/i',$message)=>'select_library_image',$operation==='remove'=>'remove_image',default=>'replace_image',
            },
            'header'=>match(true){str_contains($q,'logo')=>'logo',str_contains($q,'overlay')=>'overlay',str_contains($q,'sticky')=>'sticky',str_contains($q,'cta')||str_contains($q,'button')=>'cta',default=>'redesign'},
            'navigation'=>match(true){$action==='navigate'=>'navigate',str_contains($q,'submenu')=>'add_submenu',$operation==='add'=>'add_link',$operation==='remove'=>'remove_link',$operation==='reorder'=>'reorder_links',preg_match('/\brename|change.*label\b/i',$message)=>'rename_link',default=>'navigate'},
            'footer'=>match(true){$operation==='add'=>'add_column',$operation==='remove'=>'remove_column',str_contains($q,'social')=>'social_links',$operation==='redesign'=>'redesign',default=>'update_content'},
            'form'=>match(true){preg_match('/\brecipient|send.*to\b/i',$message)=>'recipient',preg_match('/\bsubmit|button|cta\b/i',$message)=>'submit_cta',$operation==='add'=>'add_field',$operation==='remove'=>'remove_field',$operation==='reorder'=>'reorder_fields',default=>'update_field'},
            'seo'=>match(true){preg_match('/\bmeta description\b/i',$message)=>'meta_description',preg_match('/\bseo title|meta title\b/i',$message)=>'title',preg_match('/\bslug\b/i',$message)=>'slug',preg_match('/\bog image|open graph\b/i',$message)=>'og_image',preg_match('/\bcanonical\b/i',$message)=>'canonical',default=>'indexing'},
            'settings'=>match(true){preg_match('/\bbusiness name|company name|site name|website name\b/i',$message)=>'business_name',preg_match('/\bemail\b/i',$message)=>'contact_email',preg_match('/\bphone\b/i',$message)=>'contact_phone',preg_match('/\baddress\b/i',$message)=>'address',preg_match('/\blocation\b/i',$message)=>'location',preg_match('/\bindustry\b/i',$message)=>'industry',default=>'favicon'},
            'responsive'=>match(true){preg_match('/\bcolumns?|grid\b/i',$message)=>'columns',preg_match('/\bfont|heading|text|typography\b/i',$message)=>'typography',preg_match('/\bhide|show|visible|visibility\b/i',$message)=>'visibility',preg_match('/\balign/i',$message)=>'alignment',default=>'spacing'},
            'qa'=>$operation==='audit_and_repair'?'audit_and_repair':'audit',
            'post'=>match(true){$operation==='create'&&preg_match('/\bcategory\b/i',$message)=>'create_category',$operation==='create'&&preg_match('/\btag\b/i',$message)=>'create_tag',$operation==='create'=>'create_post',preg_match('/\bpublish|draft|archive\b/i',$message)=>'publish_post',default=>'update_post'},
            'commerce'=>match(true){$operation==='create'&&preg_match('/\bcategory|collection\b/i',$message)=>'create_category',$operation==='create'=>'create_product',preg_match('/\bprice|sale price\b/i',$message)=>'price',preg_match('/\bstock|inventory|backorder\b/i',$message)=>'inventory',preg_match('/\bcategor|collection\b/i',$message)=>'categories',preg_match('/\bvariant\b/i',$message)=>'variant',default=>'update_product'},
            'publishing'=>preg_match('/\brepublish\b/i',$message)?'republish':'publish',
            'section'=>match($operation){'add'=>'add','remove'=>'remove','duplicate'=>'duplicate','reorder'=>'reorder','redesign'=>'redesign','inspect'=>'inspect',default=>'update'},
            'page'=>match($action){'build'=>'create','inspect'=>'inspect','delete'=>'delete',default=>($operation==='duplicate'?'duplicate':'update')},
            'site'=>match($action){'inspect'=>'inspect','delete'=>'delete',default=>'update'},
            'element'=>$action==='inspect'?'inspect':($operation==='replace'?'replace':'update'),
            'content'=>match($operation){'add'=>'add_item','remove'=>'remove_item','duplicate'=>'duplicate_item','reorder'=>'reorder_items','replace'=>'replace_text',default=>'rewrite'},
            default=>$operation,
        };
        return $this->normalizeLeafOperation($domain,$leaf,$operation,$action);
    }

    private function endpoint(): string
    {
        return rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions';
    }
}
