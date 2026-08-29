<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class LunaIntentGateway
{
    private array $contract;
    private array $leafSchemas;

    public function __construct(
        private readonly LunaContextResolverService $contextResolver,
        private readonly LunaModelDepartmentService $models,
        private readonly LunaActionContractRegistry $actionContracts,
    )
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
        $prior=$this->priorContextForSurface($siteMemory,$context);
        $resolution=$this->contextResolver->resolve($message,$prior,$context);
        if(($resolution['is_followup']??false)===true) $prior['resolved_followup']=$resolution;
        // Split entrance: V1 handles ordinary turns; V2 handles turns carrying
        // a real screenshot/reference image. Both expose only chat|action, but V2
        // is explicitly reference-aware and never leaks into the generic build router.
        if(($context['reference_image_attached']??false)===true){
            $referenceIntent=$this->aiReferenceRoute($message,$prior,$context);
            if(!in_array($referenceIntent,['chat','action'],true)){
                $referenceIntent=$this->looksLikeReferenceMutation($message)?'action':'chat';
            }
            Log::debug('[LunaRouter] API 1 V2 reference resolved', [
                'intent'=>$referenceIntent,
                'router'=>'v2_reference',
                'surface'=>(string)($context['surface']??''),
                'reference_image_name'=>(string)($context['reference_image_name']??''),
            ]);
            return ['intent'=>$referenceIntent];
        }

        // Dimension 1 / API 1 is deliberately authoritative and tiny:
        // the model may choose only chat | action. No scope, target, mutation,
        // confirmation, or user-facing reply is permitted at this stage.
        //
        // Local regex routing remains only as an outage/failure fallback. It must
        // never override a valid API-1 decision; otherwise this first menu is not
        // actually a model-selected routing boundary.
        $aiIntent=$this->aiRoute($message,$prior,$context);
        $source='api';
        if(!in_array($aiIntent,['chat','action'],true)){
            $aiIntent=$this->localRoute($message,$prior);
            $source='local_fallback';
        }

        $intent=$aiIntent==='action'?'action':'chat';

        Log::debug('[LunaRouter] API 1 resolved', [
            'intent'=>$intent,
            'source'=>$source,
            'surface'=>(string)($context['surface']??''),
            'followup'=>(bool)($resolution['is_followup']??false),
        ]);

        return ['intent'=>$intent];
    }

    /**
     * Nested action routing for action turns. API 2 locks the CMS scope. For
     * Sparks, API 3 locks the Spark action branch and API 4 may resolve the
     * current-page target before branch-specific execution. No intermediate
     * output is customer-facing.
     */
    public function classifyAction(string $message,array $siteMemory=[],array $context=[]): array
    {
        $message=trim($message);
        $prior=$this->priorContextForSurface($siteMemory,$context);
        $resolution=$this->contextResolver->resolve($message,$prior,$context);
        if(($resolution['is_followup']??false)===true) $prior['resolved_followup']=$resolution;
        // Split router contract. Once API 1 V2 has classified an attached-image
        // turn as ACTION, reference is already known from transport state. Do not
        // spend or trust another standard|reference classifier call here.
        $hasReferenceAttachment=($context['reference_image_attached']??false)===true;
        $actionSource=$hasReferenceAttachment?'reference':'standard';
        Log::debug('[LunaRouter] API 2 transport branch resolved', [
            'action_source'=>$actionSource,
            'router'=>$hasReferenceAttachment?'v2_reference':'v1_standard',
            'reference_image_attached'=>$hasReferenceAttachment,
            'surface'=>(string)($context['surface']??''),
        ]);

        // API 3 selects CMS scope only for STANDARD actions. REFERENCE actions
        // enter the dedicated visual-reference branch directly; internally that
        // branch remains represented as sparks/reference_spark for executor compatibility.
        $localScope=$this->localActionScope($message,$prior,$context);
        $aiScope=$actionSource==='standard' ? $this->aiActionScope($message,$prior,$context) : null;
        $actionScope=$actionSource==='reference' ? 'sparks' : ($aiScope ?? $localScope);
        if(!in_array($actionScope,$this->actionScopes(),true)) $actionScope=$localScope;

        Log::debug('[LunaRouter] API 3 CMS scope resolved', [
            'scope'=>$actionScope,
            'source'=>$actionSource==='reference'?'reference_branch':($aiScope!==null?'api':'local_fallback'),
            'surface'=>(string)($context['surface']??''),
        ]);

        // API 4 exists only for the STANDARD Sparks menu. It chooses the Spark action
        // branch before any concrete Spark instance is selected. This keeps
        // edit/change/add/remove/custom/reorder semantics from overlapping.
        $sparkAction=null;
        $sparkTarget=null;
        $referenceScope=null;
        $referenceMode=null;
        $scopeAction=$actionScope==='sparks' ? null : $this->localScopeAction($message,$actionScope);
        if($scopeAction!==null && !$this->actionContracts->hasAction($actionScope,$scopeAction)) $scopeAction=null;
        if($actionScope==='sparks'){
            $sparkAction=$actionSource==='reference'
                ? 'reference_spark'
                : ($this->aiSparkAction($message,$prior,$context)
                    ?? $this->localSparkAction($message,$prior,$context));
            if(!in_array($sparkAction,$this->sparkActions(),true)) $sparkAction='edit_spark';

            Log::debug('[LunaRouter] API 3 Spark action resolved', [
                'spark_action'=>$sparkAction,
                'surface'=>(string)($context['surface']??''),
            ]);

            // API 4 is branch-specific target selection. add_spark creates a new
            // section and therefore has no existing target. custom_spark is special:
            // it may either create a new section OR replace an existing named section.
            // Resolve a current-page target when possible so requests such as
            // "redesign the hero into a unique ..." can reach AI Flex from Whole
            // Page context without forcing the user to click/select the hero first.
            // Downstream branch executors remain authoritative about whether the
            // resolved target is used as a replacement source or only as context.
            // reference_spark owns a deeper visual-reference sub-router, so it
            // deliberately does not consume the normal API-4 target selector.
            // API 4 = whole_page|single_spark and API 5 = layout_only|layout_and_theme.
            if($sparkAction==='reference_spark'){
                $referenceScope=$this->aiReferenceScope($message,$prior,$context)
                    ?? $this->localReferenceScope($message,$prior,$context);
                if(!in_array($referenceScope,$this->referenceScopes(),true)) $referenceScope='single_spark';
                Log::debug('[LunaRouter] API 4 Reference scope resolved',[
                    'reference_scope'=>$referenceScope,
                    'surface'=>(string)($context['surface']??''),
                ]);

                $referenceMode=$this->aiReferenceMode($message,$prior,$context,$referenceScope)
                    ?? $this->localReferenceMode($message,$prior,$context);
                if(!in_array($referenceMode,$this->referenceModes(),true)) $referenceMode='layout_only';
                Log::debug('[LunaRouter] API 5 Reference mode resolved',[
                    'reference_scope'=>$referenceScope,
                    'reference_mode'=>$referenceMode,
                    'surface'=>(string)($context['surface']??''),
                ]);
            }

            $shouldResolveExistingTarget=$sparkAction!=='reference_spark'
                && in_array($sparkAction,['edit_spark','change_spark','remove_spark','reorder_spark','custom_spark'],true);
            if($shouldResolveExistingTarget){
                $pageSparks=$this->normalizePageSparkMenu($context['page_sparks']??[]);
                $sparkTarget=$this->aiSparkTarget($message,$pageSparks,$prior,$context,$sparkAction)
                    ?? $this->localSparkTarget($message,$pageSparks,$prior,$context);

                if(is_array($sparkTarget)){
                    $context['selected_spark']=$sparkTarget;
                    $context['target_index']=$sparkTarget['index'];
                    Log::debug('[LunaRouter] API 4 Spark target resolved', [
                        'spark_action'=>$sparkAction,
                        'index'=>$sparkTarget['index'],
                        'type'=>$sparkTarget['type'],
                        'label'=>$sparkTarget['label']??null,
                        'page_spark_count'=>count($pageSparks),
                    ]);
                }else{
                    Log::warning('[LunaRouter] API 4 Spark target unresolved', [
                        'spark_action'=>$sparkAction,
                        'page_spark_count'=>count($pageSparks),
                        'surface'=>(string)($context['surface']??''),
                    ]);
                }
            }
        }

        // Transitional compatibility only: existing downstream executors still
        // expect build/update/inspect/publish/delete/navigate. Derive that family
        // locally after the scope is locked; do NOT spend another AI call here.
        $actionFamily=$this->legacyActionFamilyForScope($message,$actionScope);

        // Transitional downstream route. The explicit Spark action branch is now
        // locked before target selection; Batch 2 extracts each structural branch
        // into its dedicated executor workflow.
        $compatContext=array_replace($context,['menu_scope'=>$actionScope]);
        $schema=$actionScope==='sparks'
            ? $this->localActionSchema($message,$prior,$compatContext,$actionFamily)
            : ($this->aiDomainSchema($message,$actionFamily,$prior,$context,$actionScope)
                ?? $this->localActionSchema($message,$prior,$compatContext,$actionFamily));
        $schema['action']=$actionFamily;
        $schema['routing']=[
            'intent'=>'action',
            'action_source'=>$actionSource,
            'menu_scope'=>$actionScope,
            'spark_action'=>$sparkAction,
            'model_department'=>$actionScope==='sparks' ? $this->models->departmentForSparkAction($sparkAction) : 'terra',
            'scope_action'=>$scopeAction,
            'action_contract'=>$this->actionContracts->routingMetadata($actionScope,$actionScope==='sparks'?$sparkAction:$scopeAction),
            'spark_target'=>$sparkTarget,
            'reference_scope'=>$referenceScope,
            'reference_mode'=>$referenceMode,
            'action_family'=>$actionFamily,
            'domain'=>$schema['domain']??null,
            'leaf_operation'=>$schema['leaf_operation']??null,
            'api_depth'=>$actionScope==='sparks'?($sparkAction==='reference_spark'?5:(in_array($sparkAction,['edit_spark','change_spark','remove_spark','reorder_spark'],true)?4:3)):3,
        ];

        $normalized=$this->normalizeAction($schema,$prior);
        $normalized=$this->contextResolver->apply($normalized,$resolution,$message);
        if($actionScope==='sparks' && is_array($sparkTarget)){
            $normalized['scope']='section';
            $normalized['target']=array_replace(
                is_array($normalized['target']??null)?$normalized['target']:[],
                [
                    'type'=>'spark',
                    'key'=>$sparkTarget['type'],
                    'label'=>$sparkTarget['label']?:$sparkTarget['type'],
                    'index'=>$sparkTarget['index'],
                ]
            );
            $normalized['needs_clarification']=false;
        }
        $normalized['routing']=array_replace(is_array($normalized['routing']??null)?$normalized['routing']:[],[
            'intent'=>'action',
            'action_source'=>$actionSource,
            'menu_scope'=>$actionScope,
            'spark_action'=>$sparkAction,
            'model_department'=>$actionScope==='sparks' ? $this->models->departmentForSparkAction($sparkAction) : 'terra',
            'scope_action'=>$scopeAction,
            'action_contract'=>$this->actionContracts->routingMetadata($actionScope,$actionScope==='sparks'?$sparkAction:$scopeAction),
            'spark_target'=>$sparkTarget,
            'reference_scope'=>$referenceScope,
            'reference_mode'=>$referenceMode,
            'action_family'=>$normalized['action']??$actionFamily,
            'domain'=>$normalized['domain']??null,
            'leaf_operation'=>$normalized['leaf_operation']??null,
            'api_depth'=>$actionScope==='sparks'?($sparkAction==='reference_spark'?5:(in_array($sparkAction,['edit_spark','change_spark','remove_spark','reorder_spark'],true)?4:3)):3,
        ]);
        if($actionScope==='sparks' && $sparkAction==='reference_spark'){
            $normalized['scope']=$referenceScope==='whole_page'?'page':'section';
            $normalized['execution_allowed']=true;
            $normalized['needs_clarification']=false;
            $normalized['reason']='A valid uploaded design reference is routed to the Sol visual-reference pipeline.';
        }

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
            'action_source'=>data_get($schema,'routing.action_source','standard'),
            'menu_scope'=>data_get($schema,'routing.menu_scope'),
            'spark_action'=>data_get($schema,'routing.spark_action'),
            'spark_target'=>data_get($schema,'routing.spark_target'),
            'action_contract'=>data_get($schema,'routing.action_contract'),
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


    /**
     * Batch 7: isolate routing memory by assistant surface. The contextual popup
     * has an explicit transport target and must not inherit Global Luna's last
     * routed action. Global Luna may keep page/site routing continuity, but must
     * not inherit a stale local section/item target from a prior popup edit.
     */
    private function priorContextForSurface(array $siteMemory,array $context=[]): array
    {
        $prior=$this->priorContext($siteMemory);
        $surface=(string)($context['assistant_surface']??'global_builder');
        if($surface==='contextual_popup'){
            return [
                'last_verified_action'=>[],
                'current'=>[],
                'last_routing_context'=>[],
            ];
        }

        $verified=is_array($prior['last_verified_action']??null)?$prior['last_verified_action']:[];
        $routing=is_array($prior['last_routing_context']??null)?$prior['last_routing_context']:[];
        $verifiedScope=(string)($verified['scope']??data_get($verified,'target.type',''));
        $routingScope=(string)($routing['scope']??data_get($routing,'target.type',''));
        if(in_array($verifiedScope,['section','item','element','spark'],true)) $prior['last_verified_action']=[];
        if(in_array($routingScope,['section','item','element','spark'],true)) $prior['last_routing_context']=[];
        return $prior;
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

    private function aiReferenceRoute(string $message,array $prior,array $context): ?string
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;
        $system=<<<'PROMPT'
You are Luna's screenshot/reference routing API (V2) inside Cosmic CMS.
A valid reference image is already attached. Return JSON only: {"intent":"chat"} or {"intent":"action"}.
Choose ACTION when the user asks to build, recreate, copy, match, redesign, insert, replace, or otherwise change website/CMS state using the attached image.
Choose CHAT when the user only wants to discuss, critique, explain, identify, or ask questions about the image without changing the site.
Do not choose scope, section, placement, theme mode, or produce user-facing prose.
PROMPT;
        try{
            $response=Http::withToken($apiKey)->connectTimeout(20)->timeout(60)->post($this->endpoint(),[
                'model'=>$this->models->router(),'response_format'=>['type'=>'json_object'],
                'messages'=>[
                    ['role'=>'system','content'=>$system],
                    ['role'=>'user','content'=>"CURRENT MESSAGE:
{$message}

REFERENCE IMAGE NAME: ".(string)($context['reference_image_name']??'reference image')."

PRIOR ACTION CONTEXT:
".json_encode($this->intentPriorContext($prior),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                ],
            ])->throw()->json();
            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            $intent=is_array($decoded)?($decoded['intent']??null):null;
            return in_array($intent,['chat','action'],true)?$intent:null;
        }catch(\Throwable $e){ report($e); return null; }
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

Choose ACTION only when the user wants Cosmic/Luna to do something to website or CMS state now, including build, create, update, edit, redesign, inspect/read current website state, publish, delete, or navigate.
Choose CHAT for greetings, conversation, explanations, help/how-to questions, capability/product questions, brainstorming, and requests that only ask what is possible.
Question grammar does not make a request CHAT: "Can you change the theme?" is ACTION because it asks Luna to perform the change.
A short follow-up such as "a little more", "make it smaller again", or "same for mobile" is ACTION when PRIOR ACTION CONTEXT clearly shows it continues executable work.
Do not identify what action it is. Do not return action family, scope, target, entities, reasoning, suggestions, confirmation language, or a user-facing reply.
PROMPT;

        try{
            $response=Http::withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(60)
                ->post($this->endpoint(),[
                    'model'=>$this->models->router(),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"BUILDER CONVERSATION (oldest to newest):\n".json_encode($this->builderConversationContext($context),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nCURRENT MESSAGE:\n{$message}\n\nPRIOR ACTION CONTEXT:\n".json_encode($this->intentPriorContext($prior),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nUI CONTEXT:\n".json_encode($this->intentUiContext($context),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
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
     * Full ephemeral Builder-session transcript. This is user/Luna prose only;
     * no page schemas, secrets, internal IDs, or hidden execution payloads.
     */
    private function builderConversationContext(array $context): array
    {
        $conversation=is_array($context['conversation']??null)?$context['conversation']:[];
        $out=[];
        foreach($conversation as $turn){
            if(!is_array($turn)) continue;
            $role=(string)($turn['role']??'');
            $content=trim((string)($turn['content']??''));
            if(!in_array($role,['user','assistant'],true)||$content==='') continue;
            $out[]=['role'=>$role,'content'=>Str::limit($content,6000,'')];
            if(count($out)>=120) break;
        }
        return $out;
    }

    /** API 1 needs only enough state to recognize an executable follow-up. */
    private function intentPriorContext(array $prior): array
    {
        $verified=is_array($prior['last_verified_action']??null)?$prior['last_verified_action']:[];
        $routing=is_array($prior['last_routing_context']??null)?$prior['last_routing_context']:[];
        $current=is_array($prior['current']??null)?$prior['current']:[];

        return array_filter([
            'last_verified_action'=>array_filter([
                'action'=>$verified['action']??null,
                'scope'=>$verified['scope']??null,
                'domain'=>$verified['domain']??null,
                'target'=>$verified['target']??null,
            ],fn($v)=>$v!==null&&$v!==[]&&$v!==''),
            'last_routing_context'=>array_filter([
                'intent'=>$routing['intent']??null,
                'action'=>$routing['action']??null,
                'scope'=>$routing['scope']??null,
                'domain'=>$routing['domain']??null,
                'target'=>$routing['target']??null,
            ],fn($v)=>$v!==null&&$v!==[]&&$v!==''),
            'current'=>array_filter([
                'surface'=>$current['surface']??null,
                'page_id'=>$current['current_page_id']??($current['page_id']??null),
                'target_index'=>$current['target_index']??null,
            ],fn($v)=>$v!==null&&$v!==[]&&$v!==''),
        ],fn($v)=>$v!==[]);
    }

    /** Never send Spark schemas, DOM inventories, or page payloads to API 1. */
    private function intentUiContext(array $context): array
    {
        return array_filter([
            'surface'=>$context['surface']??null,
            'ui_scope'=>$context['ui_scope']??null,
            'target_index'=>$context['target_index']??null,
            'has_blocks'=>$context['has_blocks']??null,
            'current_page_id'=>$context['current_page_id']??null,
            'current_page_title'=>$context['current_page_title']??null,
            'current_page_slug'=>$context['current_page_slug']??null,
        ],fn($v)=>$v!==null&&$v!=='');
    }

    /** API 3 Spark menu. Keep branch names stable because executors key off them. */
    private function sparkActions(): array
    {
        return $this->actionContracts->actions('sparks');
    }

    /**
     * API 3: select only the Spark action branch. It must not choose a Spark
     * instance, library replacement, position, schema mutation, or reply.
     */
    private function aiSparkAction(string $message,array $prior,array $context): ?string
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;

        $system=<<<'PROMPT'
You are Luna's third routing API inside Cosmic CMS.
API 1 already chose ACTION and API 2 already chose SPARKS.

Return JSON only and exactly one key:
{"spark_action":"edit_spark|change_spark|add_spark|remove_spark|custom_spark|reference_spark|reorder_spark"}

Definitions:
- edit_spark: keep the same existing Spark design and modify content/style/layout values inside its editable schema. This is the default and most common branch.
- change_spark: keep the same section purpose but replace the current section with a different registered/premade Spark design.
- add_spark: add a new section using a registered/premade Spark.
- remove_spark: delete an existing section.
- custom_spark: create or replace a section with a highly specific/unique composition that registered Sparks may not satisfy; this is the AI Flex fallback branch.
- reference_spark: reproduce or reinterpret a supplied screenshot/image design reference. This branch owns both single-section and whole-page screenshot workflows; later routing decides which one.
- reorder_spark: move existing sections to a different page order without changing their content/schema.

Examples:
"Make the hero buttons square" => edit_spark
"Make the heading a little smaller" => edit_spark
"Try a completely different hero" => change_spark
"Use another services layout" => change_spark
"Add testimonials after services" => add_spark
"Remove the FAQ" => remove_spark
"Move testimonials above services" => reorder_spark
"Create a hero with a diagonal image collage and floating booking widget" => custom_spark
"Make this section look like the attached screenshot" => reference_spark
"Rebuild this whole page from this screenshot" => reference_spark

Rules:
- Prefer edit_spark whenever the request can be achieved by editing the current registered Spark schema.
- Prefer registered Sparks over custom_spark unless the user clearly requests a unique/specific composition that normal registered Sparks are unlikely to satisfy.
- Choose reference_spark whenever the user explicitly supplies/refers to a screenshot, reference image, mockup image, or says to match/copy a visual reference. Do not confuse a text-only custom design request with reference_spark.
- "more modern", "more premium", or "cleaner" alone is edit_spark unless the user explicitly asks for a different layout/template/section design.
- "another", "different", "replace this layout", "try another design" normally means change_spark.
- Do not choose a target Spark, replacement Spark, insertion position, changes, Tailwind classes, reasoning, confirmation, or user-facing reply.
PROMPT;

        try{
            $response=Http::withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(60)
                ->post($this->endpoint(),[
                    'model'=>$this->models->sparkActionRouter(),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"BUILDER CONVERSATION (oldest to newest):\n".json_encode($this->builderConversationContext($context),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nCURRENT SPARK REQUEST:\n{$message}\n\nPRIOR SPARK CONTEXT:\n".json_encode($this->sparkPriorContext($prior),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                    ],
                ])->throw()->json();

            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            $sparkAction=is_array($decoded)?($decoded['spark_action']??null):null;
            return in_array($sparkAction,$this->sparkActions(),true)?$sparkAction:null;
        }catch(\Throwable $e){
            report($e);
            return null;
        }
    }

    /** @return array<int,string> */
    private function referenceScopes(): array
    {
        return ['whole_page','single_spark'];
    }

    /** @return array<int,string> */
    private function referenceModes(): array
    {
        return ['layout_only','layout_and_theme'];
    }

    /**
     * API 4 for reference_spark only: decide whether the supplied visual
     * reference represents one section or an entire page. No placement or
     * implementation decision is allowed here.
     */
    private function aiReferenceScope(string $message,array $prior,array $context): ?string
    {
        // Deterministic precedence: an explicitly named website section is always
        // a single-Spark reference, even on an empty page. Do not let the model
        // widen "build that hero/section" into a whole-page reconstruction.
        if(preg_match('/\b(hero|banner|services?|features?|about|testimonials?|reviews?|faq|pricing|cta|call[ -]to[ -]action|contact|gallery|team|process|stats?|section|spark)\b/i',$message)
            && !preg_match('/\b(whole|entire|full)\s+(page|homepage|home page|landing page|website page)\b/i',$message)) return 'single_spark';

        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;

        $system=<<<'PROMPT'
You are Luna's visual-reference scope router inside Cosmic CMS.
API 1 chose ACTION, API 2 chose SPARKS, and API 3 chose reference_spark.

Return JSON only and exactly one key:
{"reference_scope":"whole_page|single_spark"}

Definitions:
- single_spark: the screenshot/reference is one website section/component such as a hero, services section, testimonials, FAQ, pricing, CTA, contact section, gallery, or similar single section.
- whole_page: the screenshot/reference represents a complete page or the user explicitly asks to reconstruct/divide an entire page from the reference.

Rules:
- Explicit "whole page", "entire page", "full page", "landing page screenshot", or equivalent => whole_page.
- Explicit hero/services/FAQ/testimonial/etc. section wording => single_spark.
- If ambiguous, prefer single_spark because it is the safer, narrower scope.
- Do not choose target section, placement, theme mode, Spark implementation, schema, or user-facing text.
PROMPT;

        try{
            $response=Http::withToken($apiKey)->connectTimeout(20)->timeout(60)->post($this->endpoint(),[
                'model'=>$this->models->sparkActionRouter(),
                'response_format'=>['type'=>'json_object'],
                'messages'=>[
                    ['role'=>'system','content'=>$system],
                    ['role'=>'user','content'=>"CURRENT REFERENCE REQUEST:\n{$message}\n\nUI CONTEXT:\n".json_encode($this->intentUiContext($context),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                ],
            ])->throw()->json();
            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            $scope=is_array($decoded)?($decoded['reference_scope']??null):null;
            return is_string($scope)&&in_array($scope,$this->referenceScopes(),true)?$scope:null;
        }catch(\Throwable $e){ report($e); return null; }
    }

    private function localReferenceScope(string $message,array $prior,array $context): string
    {
        if(preg_match('/\b(whole|entire|full)\s+(page|homepage|home page|landing page|website page)\b/i',$message)
            || preg_match('/\b(rebuild|recreate|copy|match|convert)\b.*\b(page|homepage|landing page)\b.*\b(screenshot|reference|mockup|image)\b/i',$message)) return 'whole_page';
        return 'single_spark';
    }

    /**
     * API 5 for reference_spark only: decide whether the current Cosmic theme
     * remains authoritative or the reference is also allowed to seed a new theme.
     */
    private function aiReferenceMode(string $message,array $prior,array $context,string $referenceScope): ?string
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;

        $system=<<<'PROMPT'
You are Luna's visual-reference theme-mode router inside Cosmic CMS.
A screenshot/reference workflow is already selected.

Return JSON only and exactly one key:
{"reference_mode":"layout_only|layout_and_theme"}

Definitions:
- layout_only: copy/reinterpret composition, structure, hierarchy, spacing and visual arrangement while preserving the current Cosmic theme/brand.
- layout_and_theme: the user explicitly wants the reference's colors/theme/branding/design language included, or this is a from-scratch/new-site reference workflow with no established theme.

Rules:
- Existing site + no explicit request to copy colors/theme => layout_only.
- "include the theme", "copy the colors", "match the theme", "same branding/colors", or equivalent => layout_and_theme.
- "keep my current theme/brand/colors" => layout_only.
- Do not infer layout_and_theme merely because a screenshot exists.
PROMPT;

        try{
            $response=Http::withToken($apiKey)->connectTimeout(20)->timeout(60)->post($this->endpoint(),[
                'model'=>$this->models->sparkActionRouter(),
                'response_format'=>['type'=>'json_object'],
                'messages'=>[
                    ['role'=>'system','content'=>$system],
                    ['role'=>'user','content'=>"REFERENCE SCOPE: {$referenceScope}\nCURRENT REQUEST:\n{$message}\n\nUI CONTEXT:\n".json_encode($this->intentUiContext($context),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                ],
            ])->throw()->json();
            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            $mode=is_array($decoded)?($decoded['reference_mode']??null):null;
            return is_string($mode)&&in_array($mode,$this->referenceModes(),true)?$mode:null;
        }catch(\Throwable $e){ report($e); return null; }
    }

    private function localReferenceMode(string $message,array $prior,array $context): string
    {
        if(preg_match('/\b(keep|preserve|retain|use)\b.{0,35}\b(current|existing|my)\b.{0,20}\b(theme|brand|branding|colors?|colours?)\b/i',$message)) return 'layout_only';
        if(preg_match('/\b(include|copy|match|follow|use|same)\b.{0,35}\b(theme|colors?|colours?|palette|branding|brand style|design language)\b/i',$message)
            || preg_match('/\b(theme|colors?|colours?|palette|branding)\b.{0,25}\b(from|of)\b.{0,20}\b(screenshot|reference|image|mockup)\b/i',$message)) return 'layout_and_theme';
        return 'layout_only';
    }

    /** Deterministic outage fallback for API 3. */
    private function localSparkAction(string $message,array $prior,array $context): string
    {
        $q=Str::lower(trim($message));

        if(preg_match('/\b(move|reorder|put|place)\b.*\b(above|below|before|after|first|second|third|last|top|bottom)\b/i',$message)
            || preg_match('/\b(move|reorder)\s+(this|that|the)?\s*(section|spark)\b/i',$message)) return 'reorder_spark';

        if(preg_match('/\b(remove|delete|get rid of|drop)\b.*\b(section|hero|services?|testimonials?|reviews?|pricing|faq|contact|cta|team|stats?|process|gallery|spark)\b/i',$message)) return 'remove_spark';

        if(preg_match('/\b(screenshot|screen shot|reference image|design reference|mockup|mock-up|attached image|attached screenshot)\b/i',$message)
            || preg_match('/\b(copy|match|follow|recreate|rebuild|convert)\b.*\b(screenshot|screen shot|reference|mockup|image)\b/i',$message)) return 'reference_spark';

        if(preg_match('/\b(custom|unique|bespoke|from scratch|completely custom|specific composition)\b/i',$message)
            || preg_match('/\b(create|build|design|make)\b.*\b(diagonal|floating|overlap|orbit|interactive|unusual|experimental)\b/i',$message)) return 'custom_spark';

        if(preg_match('/\b(add|insert|create|include|need)\b.*\b(section|hero|services?|testimonials?|reviews?|pricing|faq|contact|cta|team|stats?|process|gallery)\b/i',$message)) return 'add_spark';

        if(preg_match('/\b(another|different|alternative|replace|swap|switch|try another|new layout|different layout|different design)\b/i',$message)
            && preg_match('/\b(hero|banner|section|services?|testimonials?|reviews?|pricing|faq|contact|cta|team|stats?|process|gallery|layout|design|spark)\b/i',$message)) return 'change_spark';

        $priorAction=(string)(data_get($prior,'last_routing_context.spark_action')
            ?? data_get($prior,'last_verified_action.routing.spark_action')
            ?? '');
        if($q!=='' && preg_match('/^(a little|little|more|less|same|again|also|and|make it|do that)/i',$q)
            && in_array($priorAction,$this->sparkActions(),true)) return $priorAction;

        return 'edit_spark';
    }

    /**
     * Normalize the page's actual Spark menu for API 4. No block content or
     * Tailwind/schema payload is exposed at this stage.
     */
    private function normalizePageSparkMenu(array $sparks): array
    {
        $clean=[];
        foreach(array_slice($sparks,0,120) as $row){
            if(!is_array($row)) continue;
            $index=filter_var($row['index']??null,FILTER_VALIDATE_INT);
            $type=Str::lower(trim((string)($row['type']??'')));
            if($index===false || $index<0 || $type==='') continue;
            $clean[]=[
                'index'=>(int)$index,
                'type'=>Str::limit($type,120,''),
                'label'=>Str::limit(trim((string)($row['label']??'')),80,''),
            ];
        }
        return $clean;
    }

    /**
     * API 4: choose exactly one Spark instance from the current page.
     * It must never invent a library Spark or return schema edits.
     */
    private function aiSparkTarget(string $message,array $pageSparks,array $prior,array $context,string $sparkAction='edit_spark'): ?array
    {
        if($pageSparks===[]) return null;
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;

        $system=<<<'PROMPT'
You are Luna's fourth routing API inside Cosmic CMS.
API 1 already chose ACTION, API 2 chose SPARKS, and API 3 already chose a SPARK ACTION.

You receive the exact Spark instances currently present on this page.
Choose exactly one existing Spark instance that the user's request targets when that branch needs an existing source/target.

Return JSON only:
{"index":0,"type":"hero_slider_fade"}

Rules:
- index and type MUST exactly match one row from AVAILABLE PAGE SPARKS.
- Never invent a Spark type or choose from the global Spark library.
- Use the user's natural language plus each row's type/label.
- "hero", "banner", "top section" normally means the most relevant hero/banner Spark.
- "services", "pricing", "testimonials", "faq", "contact", etc. should choose the corresponding page Spark.
- A request about a button/card/image/heading inside a named section targets that section's Spark.
- For short follow-ups such as "a little more", prefer the previously verified/routed Spark when it still exists on this page.
- If multiple same-type Sparks exist, use label/context and prior target to select one.
- SPARK ACTION is authoritative. Do not change or reinterpret it.
- Do not return operation, changes, schema, Tailwind classes, reasoning, confirmation, or reply text.
PROMPT;

        try{
            $response=Http::withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(60)
                ->post($this->endpoint(),[
                    'model'=>$this->models->sparkTargetRouter(),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"BUILDER CONVERSATION (oldest to newest):\n".json_encode($this->builderConversationContext($context),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nCURRENT ACTION REQUEST:\n{$message}\n\nSPARK ACTION:\n{$sparkAction}\n\nAVAILABLE PAGE SPARKS:\n".json_encode($pageSparks,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nPRIOR SPARK CONTEXT:\n".json_encode($this->sparkPriorContext($prior),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                    ],
                ])->throw()->json();

            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            if(!is_array($decoded)) return null;
            $index=filter_var($decoded['index']??null,FILTER_VALIDATE_INT);
            $type=Str::lower(trim((string)($decoded['type']??'')));
            if($index===false || $type==='') return null;

            foreach($pageSparks as $row){
                if((int)$row['index']===(int)$index && $row['type']===$type) return $row;
            }
            return null;
        }catch(\Throwable $e){
            report($e);
            return null;
        }
    }

    private function sparkPriorContext(array $prior): array
    {
        $verified=is_array($prior['last_verified_action']??null)?$prior['last_verified_action']:[];
        $routing=is_array($prior['last_routing_context']??null)?$prior['last_routing_context']:[];
        return array_filter([
            'verified_action'=>data_get($verified,'routing.spark_action'),
            'verified_index'=>data_get($verified,'routing.spark_target.index') ?? data_get($verified,'target.index'),
            'verified_type'=>data_get($verified,'routing.spark_target.type') ?? data_get($verified,'target.key'),
            'routed_action'=>$routing['spark_action']??null,
            'routed_index'=>data_get($routing,'spark_target.index') ?? data_get($routing,'target.index'),
            'routed_type'=>data_get($routing,'spark_target.type') ?? data_get($routing,'target.key'),
        ],fn($v)=>$v!==null&&$v!=='');
    }

    /** Deterministic outage fallback for API 4. */
    private function localSparkTarget(string $message,array $pageSparks,array $prior,array $context): ?array
    {
        if($pageSparks===[]) return null;

        $explicitIndex=filter_var($context['target_index']??null,FILTER_VALIDATE_INT);
        if($explicitIndex!==false){
            foreach($pageSparks as $row) if((int)$row['index']===(int)$explicitIndex) return $row;
        }

        $q=Str::lower($message);
        $terms=[
            'hero'=>['hero','banner'],
            'services'=>['service'],
            'features'=>['feature'],
            'pricing'=>['pricing','price'],
            'testimonials'=>['testimonial','review'],
            'team'=>['team','staff'],
            'portfolio'=>['portfolio','project','work'],
            'faq'=>['faq','frequently'],
            'contact'=>['contact'],
            'cta'=>['cta','call to action'],
            'stats'=>['stat','metric','number'],
            'process'=>['process','steps','timeline'],
            'blog'=>['blog','post'],
            'footer'=>['footer'],
        ];
        foreach($terms as $needle=>$typeTerms){
            if(!Str::contains($q,$needle)) continue;
            foreach($pageSparks as $row){
                $hay=Str::lower($row['type'].' '.$row['label']);
                if(Str::contains($hay,$typeTerms)) return $row;
            }
        }

        $priorCtx=$this->sparkPriorContext($prior);
        $priorIndex=filter_var($priorCtx['verified_index']??$priorCtx['routed_index']??null,FILTER_VALIDATE_INT);
        if($priorIndex!==false){
            foreach($pageSparks as $row) if((int)$row['index']===(int)$priorIndex) return $row;
        }

        return count($pageSparks)===1?$pageSparks[0]:null;
    }

    /** API 2: standard action vs visual-reference action. */
    private function aiActionSource(string $message,array $prior,array $context): ?string
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;
        $system=<<<'PROMPT'
You are Luna's second routing API inside Cosmic CMS.
API 1 already decided this turn is ACTION.

Return JSON only and exactly one key:
{"action_source":"standard|reference"}

Definitions:
- reference: the requested mutation/build/redesign depends on an attached screenshot, mockup, design image, or other visual reference. Examples: "build this section like the screenshot", "copy this hero", "rebuild this page from this image".
- standard: the requested action does not depend on a visual reference. An image upload by itself does not force reference when the user's action is unrelated to that image.

Rules:
- If a valid reference image is attached AND the user asks to build, copy, match, recreate, redesign, replace, insert, or style something from/like/as the image, choose reference.
- If the user asks a normal CMS action that does not use the image as design input, choose standard.
- Do not choose CMS scope, Spark action, target, placement, theme mode, schema, changes, or user-facing text.
PROMPT;
        try{
            $response=Http::withToken($apiKey)->connectTimeout(20)->timeout(60)->post($this->endpoint(),[
                'model'=>$this->models->scopeRouter(),
                'response_format'=>['type'=>'json_object'],
                'messages'=>[
                    ['role'=>'system','content'=>$system],
                    ['role'=>'user','content'=>"CURRENT ACTION REQUEST:\n{$message}\n\nREFERENCE IMAGE ATTACHED: ".(($context['reference_image_attached']??false)?'yes':'no')."\nREFERENCE IMAGE NAME: ".(string)($context['reference_image_name']??'')],
                ],
            ])->throw()->json();
            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            $source=is_array($decoded)?($decoded['action_source']??null):null;
            return in_array($source,['standard','reference'],true)?$source:null;
        }catch(\Throwable $e){ report($e); return null; }
    }

    private function localActionSource(string $message,array $prior,array $context): string
    {
        return (($context['reference_image_attached']??false)===true && $this->looksLikeReferenceMutation($message))
            ? 'reference'
            : 'standard';
    }

    private function looksLikeReferenceMutation(string $message): bool
    {
        return (bool)preg_match('/\b(build|create|make|copy|match|recreate|rebuild|convert|redesign|replace|insert|use|follow|style)\b.*\b(this|that|screenshot|screen ?shot|reference|image|mockup|design|hero|section|page)\b/i',$message)
            || (bool)preg_match('/\b(same as|like|based on|from)\b.*\b(screenshot|screen ?shot|reference|image|mockup|design|this|that)\b/i',$message);
    }

    /** API 3 menu for STANDARD actions. Keep this intentionally small and stable. */
    private function actionScopes(): array
    {
        return $this->actionContracts->scopes();
    }

    /**
     * API 2: select only the top-level CMS scope/menu for an ACTION turn.
     * No operation, Spark target, changes, schema, or response copy is allowed.
     */
    private function aiActionScope(string $message,array $prior,array $context): ?string
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;

        $allowed=implode('|',$this->actionScopes());
        $system=<<<PROMPT
You are Luna's second routing API inside Cosmic CMS.
API 1 already decided this turn is ACTION.

Return JSON only and exactly one key:
{"scope":"{$allowed}"}

Choose the single top-level CMS menu that owns the requested action:
- sparks: edit/add/remove/reorder/redesign a page section/Spark or an element inside a Spark, including screenshot/reference-driven reconstruction. Screenshot/reference actions route here even when the supplied screenshot represents a whole page; the deeper reference_spark router decides whole_page vs single_spark.
- global: site-wide design/content tokens or changes explicitly applying across the whole website.
- header: header shell/layout/logo/header-specific styling, excluding navigation structure when navigation is the main request.
- footer: footer shell/layout/content/footer-specific styling.
- navigation: menus, menu links, submenus, ordering, destinations, or navigation structure.
- theme: site theme, brand/color family, theme palette, fonts when requested as a theme/brand-wide change.
- page: page creation/deletion/rename/slug/page-level settings or whole-page composition when no specific Spark is the target.
- publish: publish, republish, unpublish/go-live workflow.
- media: media-library operations or site media not specifically tied to one Spark.
- posts: posts/updates/blog content management.
- commerce: products, categories, cart, checkout, orders, shipping, store configuration.
- seo: SEO metadata, sitemap, robots, indexing settings.
- settings: CMS/site settings not better owned by another menu.
- navigate: open/go to/take the user to a CMS destination.

Target wording wins over implementation details. Example:
"Make the hero buttons square" => sparks
"Change all H2s site-wide" => global
"Change the website theme to navy" => theme
"Add Services to the main menu" => navigation
"Publish this page" => publish
"Match this attached screenshot" => sparks
"Rebuild this whole page from this screenshot" => sparks

For short executable follow-ups such as "a little more", preserve the previously verified/routed menu when PRIOR ACTION CONTEXT makes it clear.
Do not return operation, action family, target, Spark name/index, schema, changes, reasoning, confirmation text, or user-facing prose.
PROMPT;

        try{
            $response=Http::withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(60)
                ->post($this->endpoint(),[
                    'model'=>$this->models->scopeRouter(),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"BUILDER CONVERSATION (oldest to newest):\n".json_encode($this->builderConversationContext($context),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nCURRENT ACTION REQUEST:\n{$message}\n\nPRIOR ACTION CONTEXT:\n".json_encode($this->scopePriorContext($prior),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nUI CONTEXT:\n".json_encode($this->intentUiContext($context),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                    ],
                ])->throw()->json();

            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            $scope=is_array($decoded)?($decoded['scope']??null):null;
            return is_string($scope)&&in_array($scope,$this->actionScopes(),true)?$scope:null;
        }catch(\Throwable $e){
            report($e);
            return null;
        }
    }

    /** Tiny prior context for API 2. Never send page schemas or Tailwind inventories. */
    private function scopePriorContext(array $prior): array
    {
        $verified=is_array($prior['last_verified_action']??null)?$prior['last_verified_action']:[];
        $routing=is_array($prior['last_routing_context']??null)?$prior['last_routing_context']:[];
        return [
            'last_verified'=>array_filter([
                'menu_scope'=>data_get($verified,'routing.menu_scope') ?? ($verified['menu_scope']??null),
                'domain'=>$verified['domain']??null,
                'scope'=>$verified['scope']??null,
                'target_type'=>data_get($verified,'target.type'),
                'target_label'=>data_get($verified,'target.label'),
            ],fn($v)=>$v!==null&&$v!==''),
            'last_routed'=>array_filter([
                'menu_scope'=>$routing['menu_scope']??null,
                'domain'=>$routing['domain']??null,
                'scope'=>$routing['scope']??null,
                'target_type'=>data_get($routing,'target.type'),
                'target_label'=>data_get($routing,'target.label'),
            ],fn($v)=>$v!==null&&$v!==''),
        ];
    }

    /** Deterministic fallback only when API 2 is unavailable/invalid. */
    private function localActionScope(string $message,array $prior,array $context): string
    {
        $q=Str::lower(trim($message));

        // Screenshot/design-reference workflows always enter the Sparks menu.
        // reference_spark owns the deeper whole_page|single_spark distinction.
        if(preg_match('/\b(screenshot|screen shot|reference image|design reference|mockup|mock-up|attached image|attached screenshot)\b/i',$message)) return 'sparks';

        // Explicitly named top-level features first.
        if(preg_match('/\b(publish|republish|unpublish|go live|make (?:it|this|the (?:page|site|website)) live)\b/i',$message)) return 'publish';
        if(preg_match('/\b(menu|menus|navigation|nav link|menu link|submenu|sub-menu)\b/i',$message)) return 'navigation';
        if(preg_match('/\b(header)\b/i',$message)) return 'header';
        if(preg_match('/\b(footer)\b/i',$message)) return 'footer';
        if(preg_match('/\b(theme|brand palette|color family|colour family)\b/i',$message)) return 'theme';
        if(preg_match('/\b(product|products|cart|checkout|order|orders|shipping|store|commerce)\b/i',$message)) return 'commerce';
        if(preg_match('/\b(post|posts|blog|update post|news post)\b/i',$message)) return 'posts';
        if(preg_match('/\b(seo|meta title|meta description|sitemap|robots\.txt|indexing)\b/i',$message)) return 'seo';
        if(preg_match('/\b(media library|upload media|media folder)\b/i',$message)) return 'media';
        if(preg_match('/\b(open|go to|take me to|navigate to)\b/i',$message)) return 'navigate';
        if(preg_match('/\b(site[- ]wide|website[- ]wide|across (?:the )?(?:whole )?(?:site|website)|all (?:h1|h2|h3|headings?|buttons?|links?))\b/i',$message)) return 'global';

        // A concrete section/visual primitive on the current page is a Spark edit.
        if(preg_match('/\b(hero|banner|section|spark|services?|features?|pricing|testimonials?|team|portfolio|faq|contact|cta|call to action|cards?|buttons?|heading|headline|image|photo|spacing|padding|gap|columns?|rounded|radius)\b/i',$message)) return 'sparks';

        if(preg_match('/\b(page|homepage|home page|landing page|slug)\b/i',$message)) return 'page';

        // Follow-ups inherit only the previous menu hint.
        $priorScope=(string)(data_get($prior,'last_verified_action.routing.menu_scope')
            ?? data_get($prior,'last_routing_context.menu_scope')
            ?? '');
        if(in_array($priorScope,$this->actionScopes(),true)) return $priorScope;

        return 'settings';
    }

    /** Batch 2 bounded sub-router for Global + Theme contracts. */
    private function localScopeAction(string $message,string $scope): ?string
    {
        if($scope==='global'){
            if(preg_match('/\b(reset|restore defaults?|default settings?)\b/i',$message)) return 'reset_global';
            if(preg_match('/\b(font|fonts|typography|h1|h2|h3|headings?|body text|paragraph)\b/i',$message)) return 'typography';
            if(preg_match('/\b(button|buttons|button radius|pill|fully rounded|square buttons?|sharp buttons?)\b/i',$message)) return 'buttons';
            if(preg_match('/\b(container|content width|max width|max-width)\b/i',$message)) return 'container';
            if(preg_match('/\b(spacing|padding|gap|section spacing|vertical rhythm)\b/i',$message)) return 'spacing';
            if(preg_match('/\b(background|backgrounds|page background|site background)\b/i',$message)) return 'backgrounds';
            if(preg_match('/\b(colors?|colours?|palette)\b/i',$message)) return 'colors';
            if(preg_match('/\b(custom|unique|bespoke)\b/i',$message)) return 'custom_global';
            return 'edit_global';
        }
        if($scope==='header'){
            if(preg_match('/\b(custom|unique|bespoke)\b.{0,25}\bheader\b|\bheader\b.{0,25}\b(custom|unique|bespoke)\b/i',$message)) return 'custom_header';
            if(preg_match('/\b(overlay|over the hero|over hero|over the banner|over banner)\b/i',$message)) return 'overlay_header';
            if(preg_match('/\btransparent\b/i',$message)) return 'transparent_header';
            if(preg_match('/\bsticky\b/i',$message)) return 'sticky_header';
            if(preg_match('/\blogo\b/i',$message)) return 'logo';
            if(preg_match('/\b(cta|call to action|button)\b/i',$message)) return 'header_cta';
            if(preg_match('/\b(spacing|padding|height|compact|taller|shorter)\b/i',$message)) return 'header_spacing';
            if(preg_match('/\b(mobile|hamburger)\b/i',$message)) return 'mobile_header';
            if(preg_match('/\b(change|switch|use|apply)\b.{0,30}\bheader\b|\bheader\b.{0,25}\b(layout|style|variant)\b/i',$message)) return 'change_header';
            return 'edit_header';
        }
        if($scope==='footer'){
            if(preg_match('/\b(custom|unique|bespoke)\b.{0,25}\bfooter\b|\bfooter\b.{0,25}\b(custom|unique|bespoke)\b/i',$message)) return 'custom_footer';
            if(preg_match('/\b(mega footer|mega-footer)\b/i',$message)) return 'mega_footer';
            if(preg_match('/\b(simple|minimal)\b.{0,20}\bfooter\b|\bfooter\b.{0,20}\b(simple|minimal)\b/i',$message)) return 'simple_footer';
            if(preg_match('/\b(columns?|column count)\b/i',$message)) return 'footer_columns';
            if(preg_match('/\b(cta|call to action|button)\b/i',$message)) return 'footer_cta';
            if(preg_match('/\b(social|facebook|instagram|linkedin|youtube|tiktok|x\.com|twitter)\b/i',$message)) return 'footer_socials';
            if(preg_match('/\b(logo|brand|branding|copyright)\b/i',$message)) return 'footer_brand';
            if(preg_match('/\b(background|theme|primary|surface|white)\b/i',$message)) return 'footer_background';
            if(preg_match('/\b(change|switch|use|apply)\b.{0,30}\bfooter\b|\bfooter\b.{0,25}\b(layout|style|variant)\b/i',$message)) return 'change_footer';
            return 'edit_footer';
        }
        if($scope==='navigation'){
            if(preg_match('/\b(?:create|build|make)\s+(?:a\s+)?(?:new\s+)?(?:main\s+)?(?:menu|navigation)\b/i',$message)) return 'create_menu';
            if(preg_match('/\b(?:put|add|move)\s+.+?\s+(?:under|inside|into)\s+.+/i',$message) || preg_match('/\b(?:submenu|sub-menu)\b.*\badd\b|\badd\b.*\b(?:submenu|sub-menu)\b/i',$message)) return 'add_submenu_item';
            if(preg_match('/\b(?:remove|delete)\b.*\b(?:submenu|sub-menu)\b|\b(?:remove|delete)\s+.+?\s+(?:under|from under)\s+.+/i',$message)) return 'remove_submenu_item';
            if(preg_match('/\b(?:rename|change\s+(?:the\s+)?label)\b/i',$message)) return 'rename_menu_item';
            if(preg_match('/\b(?:change|set|update)\b.*\b(?:url|link|destination)\b|\b(?:url|link|destination)\b.*\b(?:to|as)\b/i',$message)) return 'change_menu_link';
            if(preg_match('/\b(?:move|reorder)\b.*\b(?:menu|navigation|nav|before|after|first|last|start|end)\b/i',$message)) return 'reorder_menu';
            if(preg_match('/\b(?:remove|delete)\b/i',$message)) return 'remove_menu_item';
            if(preg_match('/\badd\b/i',$message)) return 'add_menu_item';
            return 'edit_menu_item';
        }
        if($scope==='publish'){
            if(preg_match('/\b(?:status|state|what(?:\'s| is) published|published status|publish status)\b/i',$message)) return 'publish_status';
            if(preg_match('/\b(?:export|download)\b.{0,30}\b(?:site|website|package|connector)\b|\bdeployment connector\b/i',$message)) return 'export_site';
            if(preg_match('/\bpreview\b/i',$message)){
                return preg_match('/\b(?:site|website|all pages|whole site|entire site)\b/i',$message) ? 'preview_site' : 'preview_page';
            }
            if(preg_match('/\bunpublish\b/i',$message)) return 'unpublish_page';
            $site=preg_match('/\b(?:site|website|all pages|whole site|entire site)\b/i',$message);
            $republish=preg_match('/\brepublish\b/i',$message);
            if($site) return $republish ? 'republish_site' : 'publish_site';
            return $republish ? 'republish_page' : 'publish_page';
        }
        if($scope==='page'){
            if(preg_match('/\b(regenerate|rebuild|generate again|redo)\b.{0,30}\bpage\b|\bpage\b.{0,30}\b(regenerate|rebuild|redo)\b/i',$message)) return 'regenerate_page';
            if(preg_match('/\b(duplicate|clone|copy)\b.{0,25}\bpage\b|\bpage\b.{0,25}\b(duplicate|clone|copy)\b/i',$message)) return 'duplicate_page';
            if(preg_match('/\b(delete|remove)\b.{0,25}\bpage\b|\b(delete|remove)\s+(?:the\s+)?(?:about|services|pricing|contact|home)\b/i',$message)) return 'delete_page';
            if(preg_match('/\b(rename|change the name|change page title)\b/i',$message)) return 'rename_page';
            if(preg_match('/\b(seo|meta description|meta title|seo title|canonical|indexable|noindex|og image|open graph)\b/i',$message)) return 'page_seo';
            if(preg_match('/\b(page style|layout|clean layout|premium layout|balanced layout)\b/i',$message)) return 'page_layout';
            if(preg_match('/\b(slug|parent page|page type|page settings|settings)\b/i',$message)) return 'page_settings';
            if(preg_match('/\b(add|create|build|generate|make)\b.{0,35}\bpage\b/i',$message)) return 'add_page';
            return 'edit_page';
        }
        if($scope==='theme'){
            if(preg_match('/\b(from|based on|match|use)\b.{0,30}\blogo\b|\blogo\b.{0,30}\b(theme|palette|colors?|colours?)\b/i',$message)) return 'theme_from_logo';
            if(preg_match('/\b(reset|restore)\b.{0,20}\b(theme|palette)\b|\bdefault theme\b/i',$message)) return 'reset_theme';
            if(preg_match('/#[0-9a-f]{6}\b/i',$message) || preg_match('/\bbrand(?:ing)?\b.{0,25}\b(theme|palette|colors?|colours?)\b/i',$message)) return 'brand_theme';
            if(preg_match('/\b(custom|unique|bespoke|create a new)\b.{0,20}\b(theme|palette)\b/i',$message)) return 'custom_theme';
            if(preg_match('/\b(change|switch|use|apply|set)\b.{0,35}\b(theme|palette|color family|colour family)\b|\b(theme|palette)\b.{0,25}\b(to|as)\b/i',$message)) return 'change_theme';
            return 'edit_theme';
        }
        return null;
    }

    /**
     * Temporary adapter for the pre-nested executors. API 2 does not choose this;
     * it is derived locally until the downstream branches are replaced.
     */
    private function legacyActionFamilyForScope(string $message,string $scope): string
    {
        if($scope==='publish') return 'publish';
        if($scope==='navigate') return 'navigate';
        if(preg_match('/\b(delete|destroy|permanently remove)\b/i',$message)) return 'delete';
        if(preg_match('/\b(inspect|audit|check|show me|what is|what are|which|how many)\b/i',$message)
            && !preg_match('/\b(fix|repair|correct|change|update|improve|make|set|add|remove|delete|create|build)\b/i',$message)) return 'inspect';
        if($scope==='page' && preg_match('/\b(build|create|generate|design)\b/i',$message)) return 'build';
        return 'update';
    }

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
                    'model'=>$this->models->actionRouter(),
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

    private function aiDomainSchema(string $message,string $actionFamily,array $prior,array $context,string $menuScope='settings'): ?array
    {
        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return null;

        $system=<<<'PROMPT'
You are Luna's transitional downstream routing API inside Cosmic CMS. API 1 established ACTION and API 2 already locked the top-level CMS MENU SCOPE. Resolve only the compatible domain, atomic operation, executor scope, target and structured changes. Never escape the supplied MENU SCOPE or change the supplied legacy action family.

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
- LOCKED MENU SCOPE is authoritative. Keep domain/target compatible with it:
  sparks => section|element|content|design|typography|layout|media|responsive|form when section-local
  global => site|design|typography|layout|responsive
  header => header
  footer => footer
  navigation => navigation
  theme => theme
  page => page
  publish => publishing
  media => media
  posts => post
  commerce => commerce
  seo => seo
  settings => settings
  navigate => page|site
- Do not silently route a request to a different top-level menu.
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
                    'model'=>$this->models->domainRouter(),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"LOCKED MENU SCOPE: {$menuScope}\nLOCKED ACTION FAMILY: {$actionFamily}\n\nCURRENT ACTION REQUEST:\n{$message}\n\nPRIOR ACTION CONTEXT:\n".json_encode($prior,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nEXECUTION CONTEXT:\n".json_encode(array_replace($context,['menu_scope'=>$menuScope]),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
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
