<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class LunaSparkSchemaEditorService
{
    public function __construct(
        private readonly SparkTailwindSchemaContract $contract,
        private readonly SparkTailwindSchemaValidator $validator,
        private readonly TailwindUtilityConflictResolver $conflicts,
        private readonly LunaModelDepartmentService $models,
        private readonly LunaAiFlexEditPolicyService $editPolicy,
        private readonly LunaStructuralActionService $structuralActions,
    ) {}

    /**
     * Selected-Spark editor: Luna receives the complete current context, but
     * returns only a minimal patch. The server expands that patch against the
     * authoritative current schema and runs the existing strict validator.
     */
    public function edit(string $request, array $block, array $sparkTarget, array $elementContext=[], array $conversation=[]): array
    {
        $sparkType=(string)($block['type']??'');
        $targetType=(string)($sparkTarget['type']??'');
        $semanticType=(string)($block['semantic_type']??$block['category']??'');
        $customSemanticMatch=$sparkType==='luna_custom_section' && $targetType!=='' && in_array($targetType, array_filter([$semanticType,(string)($block['category']??''),$sparkType]), true);
        if($sparkType==='' || $targetType==='' || ($sparkType!==$targetType && !$customSemanticMatch)){
            return ['ok'=>false,'reason'=>'spark_target_mismatch'];
        }
        if($customSemanticMatch && $targetType!==$sparkType){
            // Router speaks in semantic roles (hero/services/etc.), while the editor
            // must lock the physical renderer type for schema validation/persistence.
            $sparkTarget['semantic_type']=$targetType;
            $sparkTarget['implementation_type']=$sparkType;
            $sparkTarget['type']=$sparkType;
        }

        $contextTarget=filter_var($elementContext['tailwindTargetIndex']??null,FILTER_VALIDATE_INT);
        $lockedIndex=filter_var($sparkTarget['index']??null,FILTER_VALIDATE_INT);
        if($contextTarget!==false && $lockedIndex!==false && (int)$contextTarget!==(int)$lockedIndex){
            return ['ok'=>false,'reason'=>'tailwind_inventory_target_mismatch'];
        }

        $editable=$this->editableBlock($block);
        $tailwind=$this->currentTailwindSchema($block,$elementContext);
        if(($tailwind['slots']??[])===[] && $sparkType!=='luna_custom_section'){
            Log::warning('[LunaRouter] API 4 rejected',[
                'reason'=>'tailwind_inventory_missing',
                'spark_type'=>$sparkType,
                'target_index'=>$sparkTarget['index']??null,
            ]);
            return ['ok'=>false,'reason'=>'tailwind_inventory_missing'];
        }
        if(($tailwind['slots']??[])===[] && $sparkType==='luna_custom_section'){
            // AI Flex sections are structured-schema renderers. Their visual controls
            // live primarily in visual_style/style_overrides/elements[].style and do
            // not require a Tailwind DOM inventory to make a safe incremental edit.
            // Rejecting an empty inventory here made valid follow-ups such as
            // "reduce the section padding more" fail even though the current draft
            // already contained an editable visual_style path.
            Log::debug('[LunaRouter] AI Flex editable-only mode',[
                'spark_type'=>$sparkType,
                'target_index'=>$sparkTarget['index']??null,
            ]);
        }

        Log::debug('[LunaRouter] API 4 started',[
            'spark_type'=>$sparkType,
            'target_index'=>$sparkTarget['index']??null,
            'editable_keys'=>count($editable),
            'tailwind_slots'=>count($tailwind['slots']??[]),
            'slot_roles'=>array_slice(array_map(
                fn($slot,$definition)=>['slot'=>$slot,'role'=>$definition['role']??$slot],
                array_keys($tailwind['slots']??[]),
                array_values($tailwind['slots']??[])
            ),0,40),
        ]);

        $apiKey=(string)config('openai.api_key');
        if($apiKey==='') return ['ok'=>false,'reason'=>'api_unavailable'];

        $system=<<<'PROMPT'
You are Luna's selected-Spark editor inside Cosmic CMS.
The routing APIs have already locked one exact Spark instance. Edit ONLY that Spark.

You receive:
1. USER REQUEST
2. LOCKED SPARK TARGET
3. LOCKED ELEMENT CONTEXT (when the user clicked a specific card/item/element)
4. FULL EDITABLE SPARK SCHEMA
5. FULL CURRENT TAILWIND SCHEMA

Return JSON only. For ordinary edits use MINIMAL PATCHES; for structural add/remove/move/duplicate requests use STRUCTURAL ACTIONS:
{
  "spark_type":"EXACT_LOCKED_TYPE",
  "editable_patch": {
    "existing.dot.path": "new value"
  },
  "tailwind_patch": {
    "existing_slot_name": ["complete","desired","class","list"]
  },
  "structural_actions": []
}

STRUCTURAL ACTION objects are finite and server-validated. Use ONLY these action names:
- add_extra, update_extra, remove_extra, move_extra, duplicate_extra
- add_row, remove_row, move_row, duplicate_row
- add_column, remove_column, move_column, duplicate_column
Registered-Spark field extra example:
{"action":"add_extra","target_field":"heading","placement":"after","extra":{"type":"image","data":{"src":"","alt":""}}}
AI Flex examples:
{"action":"add_column","collection_path":"rows.0.columns"}
{"action":"add_extra","collection_path":"rows.0.columns.1.extras","extra":{"type":"button","label":"Learn more","url":"#"}}
{"action":"move_extra","collection_path":"rows.0.columns.0.extras","item_index":0,"destination_collection_path":"rows.0.columns.1.extras","to_index":0}

Rules:
- Understand the user's natural-language intent; do not keyword-match blindly.
- Negation/preservation is binding: "do not change radius", "keep layout", "leave image alone" means those fields/slots MUST remain untouched.
- BUILDER CONVERSATION is authoritative for follow-ups such as "it", "that", "a little more", "same one", and "same as before".
- LOCKED ELEMENT CONTEXT is authoritative when it identifies a specific item/card/element. Prefer an item-specific V2 slot/path over a shared slot when the user targeted one item.
- FULL schemas are context, NOT output. Return only paths/slots that actually need changing.
- editable_patch paths must already exist in FULL EDITABLE SPARK SCHEMA. Never invent a field/path.
- Never mutate field_extras directly through editable_patch. For "add X before/after/below/above this field", use structural_actions and an exact target_field anchor. "below/under/after" => placement=after; "above/before/over" => placement=before.
- Never change the length/order of AI Flex elements/children through editable_patch. Use structural_actions for Rows -> Columns -> Extras CRUD.
- Structural requests MUST return structural_actions with editable_patch={} and tailwind_patch={}. Do not mix structural actions with ordinary patches in one response.
- For add_extra, include the requested type. Registered Spark types: text,heading,image,button,icon,badge,video,divider,spacer. AI Flex also supports group,grid,stack,card,list,stat,form,background_image,background_video,overlay,slider,slide,button_group,media_group. slider contains slide children; each slide can contain arbitrary AI Flex content and background media. button_group contains only button children. media_group contains only image/video children and uses style.columns/gap for layout. background_image/background_video may contain children; overlay uses style.background + style.opacity behind its children. For a newly requested image with no supplied URL, return an empty src; the server resolves a contextual Unsplash asset or an explicitly requested generated image after structural validation. Never invent an external URL. For video, an empty src remains valid until the user chooses media.
- Use LOCKED ELEMENT CONTEXT fieldPath for exact registered-field targeting when available. For AI Flex, prefer logicalCollectionPath/logicalPath and stableId from LOCKED ELEMENT CONTEXT.
- add_row targets collection_path="rows". add_column targets a specific "rows.N.columns". AI Flex add_extra targets a specific "rows.N.columns.M.extras".
- remove/move/duplicate actions require an exact item_index (or the clicked item from LOCKED ELEMENT CONTEXT). Cross-column move_extra must include destination_collection_path.
- tailwind_patch keys must already exist in FULL CURRENT TAILWIND SCHEMA. Never invent or rename a slot. Exact repeater keys such as cards__1__title or services__2__card address one specific item and MUST be preferred for singular item requests.
- Each tailwind_patch value is the COMPLETE desired class list for that one slot after the edit.
- Preserve every unrelated field and slot by omitting it from the patch.
- If the user asks for multiple compatible visual changes, include all required paths/slots in the same response.
- If the user asks only for visual polish (premium/modern/polished/cleaner), KEEP the current Spark/layout and restyle its available slots. Do not turn that into a replacement/layout change.
- Structural renderer utilities must be preserved. Do not remove positioning/display/overflow utilities merely to change a color, gradient, radius, typography, or spacing treatment.
- Use valid Tailwind utilities only. No CSS, HTML, JS, style attributes, markdown, explanation, or reply prose.
- Preserve responsive/state variants unless the request explicitly changes them.
- For plural targets such as "buttons" or "cards", edit all appropriate customer-facing slots in this selected Spark; for "second card"/"this title", edit only the exact item-specific slot when available.
- When implementation_type/type is luna_custom_section, treat semantic_type as the logical role and edit its visible AI Flex fields directly.
- AI Flex is a structured renderer, not a Tailwind-only Spark. Prefer existing visual_style.*, style_overrides.*, and elements.*.style.* paths for spacing, geometry, colors, typography, layout, media presentation, and responsive changes when those paths already exist. An empty Tailwind inventory is valid for AI Flex; use editable_patch only in that case.
- For follow-up AI Flex requests such as "reduce more spacing", "a little smaller", "move it higher", or "make the cards tighter", use the CURRENT editable values as the baseline and make a further incremental change. Never reconstruct or regenerate the section unless the user explicitly asks for a redesign/rebuild.
- For section-level spacing on AI Flex, prefer visual_style.section_padding_y / section_padding_x / content_gap when present. For a specifically targeted nested element, prefer the exact existing elements.N.style.padding / padding_x / padding_y / gap path instead of changing the whole section.
- Design-only AI Flex edits must preserve heading/body/button copy, URLs, images, item counts, and semantic_type unless the user explicitly asks to change them.
- For AI Flex visual_style colors, semantic values are allowed: primary, surface, surface_alt, white, on_primary, on_surface, on_dark, accent, or an explicit HEX.
- If the request cannot be represented by existing editable paths/slots, return empty patches rather than changing an unrelated property.
PROMPT;
        $system .= "\n\n".$this->editPolicy->directive();

        try{
            $response=Http::withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(90)
                ->post($this->endpoint(),[
                    'model'=>$this->models->sparkEditor(),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"BUILDER CONVERSATION (oldest to newest):\n".json_encode($this->conversationContext($conversation),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nCURRENT USER REQUEST:\n{$request}\n\nLOCKED SPARK TARGET:\n".json_encode($sparkTarget,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nLOCKED ELEMENT CONTEXT:\n".json_encode($this->lockedElementContext($elementContext),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nFULL EDITABLE SPARK SCHEMA:\n".json_encode($editable,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nFULL CURRENT TAILWIND SCHEMA:\n".json_encode($tailwind,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                    ],
                ])->throw()->json();

            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            if(!is_array($decoded)){
                Log::warning('[LunaRouter] API 4 rejected',[
                    'reason'=>'invalid_json','spark_type'=>$sparkType,'target_index'=>$sparkTarget['index']??null,
                ]);
                return ['ok'=>false,'reason'=>'invalid_json'];
            }
            $policyGuard=$this->editPolicy->guard($request,$decoded,$elementContext);
            if(($policyGuard['ok']??false)!==true){
                Log::warning('[LunaRouter] API 4 policy rejected',[
                    'spark_type'=>$sparkType,
                    'target_index'=>$sparkTarget['index']??null,
                    'reason'=>$policyGuard['reason']??'ai_flex_policy_rejected',
                    'details'=>$policyGuard['details']??[],
                ]);
                return [
                    'ok'=>false,
                    'reason'=>$policyGuard['reason']??'ai_flex_policy_rejected',
                    'details'=>$policyGuard['details']??[],
                ];
            }
            $structuralPlan=$decoded['structural_actions']??[];
            if(is_array($structuralPlan) && $structuralPlan!==[]){
                $editablePatch=is_array($decoded['editable_patch']??null)?$decoded['editable_patch']:[];
                $tailwindPatch=is_array($decoded['tailwind_patch']??null)?$decoded['tailwind_patch']:[];
                if($editablePatch!==[] || $tailwindPatch!==[]){
                    return ['ok'=>false,'reason'=>'mixed_structural_and_patch_protocol'];
                }
                $structural=$this->structuralActions->apply($block,$structuralPlan,$elementContext);
                if(($structural['ok']??false)!==true){
                    Log::warning('[LunaRouter] API 4 structural action rejected',[
                        'spark_type'=>$sparkType,'target_index'=>$sparkTarget['index']??null,
                        'reason'=>$structural['reason']??'structural_action_rejected',
                        'failed_action_index'=>$structural['failed_action_index']??null,
                    ]);
                    return $structural;
                }
                Log::debug('[LunaRouter] API 4 structural action validated',[
                    'spark_type'=>$sparkType,'target_index'=>$sparkTarget['index']??null,
                    'operations'=>$structural['operations']??[],
                ]);
                return array_merge($structural,[
                    'protocol'=>'structural_actions_v1',
                    'editable'=>$this->editableBlock($structural['block']),
                    'tailwind'=>$tailwind,
                ]);
            }

            $expanded=$this->expandPatchResponse($sparkType,$editable,$tailwind,$decoded);
            if(($expanded['ok']??false)!==true){
                return $expanded;
            }
            $result=$this->validateResult($sparkType,$editable,$tailwind,$expanded['decoded'],$block);
            if(($result['ok']??false)===true){
                Log::debug('[LunaRouter] API 4 validated',[
                    'spark_type'=>$sparkType,
                    'target_index'=>$sparkTarget['index']??null,
                    'changed'=>(bool)($result['changed']??false),
                    'diff'=>$result['diff']??[],
                    'before_fingerprint'=>$result['before_fingerprint']??null,
                    'after_fingerprint'=>$result['after_fingerprint']??null,
                ]);
            }else{
                Log::warning('[LunaRouter] API 4 rejected',[
                    'spark_type'=>$sparkType,
                    'target_index'=>$sparkTarget['index']??null,
                    'reason'=>$result['reason']??'unknown',
                    'details'=>array_diff_key($result,['block'=>true,'editable'=>true,'tailwind'=>true,'persisted_tailwind'=>true]),
                ]);
            }
            return $result;
        }catch(\Throwable $e){
            Log::error('[LunaRouter] API 4 exception',[
                'spark_type'=>$sparkType,
                'target_index'=>$sparkTarget['index']??null,
                'message'=>$e->getMessage(),
            ]);
            report($e);
            return ['ok'=>false,'reason'=>'api_error'];
        }
    }

    /** Expand Luna's minimal patch into a complete candidate for the strict
     * validator. Full-schema responses remain accepted temporarily so rolling
     * deployments/older model prompts fail safely during the migration. */
    private function expandPatchResponse(string $sparkType,array $beforeEditable,array $beforeTailwind,array $decoded): array
    {
        if((string)($decoded['spark_type']??'')!==$sparkType){
            return ['ok'=>false,'reason'=>'spark_type_mismatch'];
        }

        if(is_array($decoded['editable']??null) && is_array($decoded['tailwind']??null)){
            return ['ok'=>true,'decoded'=>$decoded,'protocol'=>'legacy_full_schema'];
        }

        $editablePatch=$decoded['editable_patch']??[];
        $tailwindPatch=$decoded['tailwind_patch']??[];
        if(!is_array($editablePatch)||!is_array($tailwindPatch)){
            return ['ok'=>false,'reason'=>'patch_schema_invalid'];
        }

        $editable=$beforeEditable;
        foreach($editablePatch as $path=>$value){
            if(!is_string($path)||trim($path)===''||!Arr::has($beforeEditable,$path)){
                return ['ok'=>false,'reason'=>'editable_patch_path_invalid','path'=>$path];
            }

            // Batch 5 guard: collection structure is owned by the finite
            // structural_actions protocol. Ordinary editable patches may edit
            // fields inside an existing item (items.0.title), but they may not
            // replace an entire numeric list and thereby smuggle add/remove/
            // reorder operations around the structural validator.
            $currentValue=Arr::get($beforeEditable,$path);
            if(is_array($currentValue) && array_is_list($currentValue)){
                return ['ok'=>false,'reason'=>'editable_patch_collection_replace_forbidden','path'=>$path];
            }
            Arr::set($editable,$path,$value);
        }

        $tailwind=$beforeTailwind;
        foreach($tailwindPatch as $slot=>$classes){
            if(!is_string($slot)||!array_key_exists($slot,(array)($beforeTailwind['slots']??[]))){
                return ['ok'=>false,'reason'=>'tailwind_patch_slot_invalid','slot'=>$slot];
            }
            if(is_array($classes)&&array_key_exists('classes',$classes)) $classes=$classes['classes'];
            if(!is_string($classes)&&!is_array($classes)){
                return ['ok'=>false,'reason'=>'tailwind_patch_classes_invalid','slot'=>$slot];
            }
            $tailwind['slots'][$slot]['classes']=$this->contract->tokenize($classes);
        }

        return [
            'ok'=>true,
            'protocol'=>'minimal_patch_v2',
            'decoded'=>[
                'spark_type'=>$sparkType,
                'editable'=>$editable,
                'tailwind'=>$tailwind,
            ],
        ];
    }

    /** Keep clicked-element targeting compact and deterministic. Renderer
     * inventory is already supplied separately as the Tailwind schema. */
    private function lockedElementContext(array $context): array
    {
        $allowed=[
            'tailwindTargetIndex','tailwind_target_index','tailwindPath','tailwind_path',
            'tailwindSlot','tailwind_slot','itemIndex','item_index','collectionIndex',
            'collection_index','itemKey','item_key','collectionKey','collection_key',
            'tag','role','text','label','field','fieldPath','field_path','elementId','element_id',
            'collectionPath','collection_path','logicalCollectionPath','logical_collection_path',
            'logicalPath','logical_path','stableId','stable_id','extraId','extra_id','blockType','block_type',
        ];
        $out=Arr::only($context,$allowed);
        foreach($out as $key=>$value){
            if(is_string($value)) $out[$key]=Str::limit(trim($value),1000,'');
            elseif(is_array($value)) $out[$key]=array_slice($value,0,20,true);
        }
        return $out;
    }

    private function validateResult(string $sparkType,array $beforeEditable,array $beforeTailwind,array $decoded,array $originalBlock): array
    {
        if((string)($decoded['spark_type']??'')!==$sparkType) return ['ok'=>false,'reason'=>'spark_type_mismatch'];
        $editable=$decoded['editable']??null;
        $tailwind=$decoded['tailwind']??null;
        if(!is_array($editable)||!is_array($tailwind)) return ['ok'=>false,'reason'=>'schema_missing'];

        // Full-schema contract: Luna may not add/remove editable keys anywhere.
        // Arrays/lists may change values/items, but associative object structure
        // must remain anchored to the schema that was supplied.
        $shapeError=$this->shapeMismatch($beforeEditable,$editable);
        if($shapeError!==null) return ['ok'=>false,'reason'=>'editable_shape_changed','path'=>$shapeError];

        $beforeSlots=array_keys((array)($beforeTailwind['slots']??[])); sort($beforeSlots);
        $afterSlots=array_keys((array)($tailwind['slots']??[])); sort($afterSlots);
        if($beforeSlots!==$afterSlots) return ['ok'=>false,'reason'=>'tailwind_slot_shape_changed'];

        $validated=$this->validator->validate($sparkType,$tailwind);
        if(!($validated['valid']??false)){
            Log::warning('Luna full Spark schema Tailwind rejected',['spark_type'=>$sparkType,'errors'=>$validated['errors']??[]]);
            return ['ok'=>false,'reason'=>'tailwind_validation_failed','errors'=>$validated['errors']??[]];
        }

        $tailwindDiff=[];
        foreach((array)($beforeTailwind['slots']??[]) as $slot=>$beforeDefinition){
            $afterDefinition=$validated['schema']['slots'][$slot]??null;
            if(!is_array($beforeDefinition)||!is_array($afterDefinition)) {
                return ['ok'=>false,'reason'=>'tailwind_slot_invalid','slot'=>$slot];
            }

            if(($beforeDefinition['locked']??false)===true && $afterDefinition!==$beforeDefinition){
                return ['ok'=>false,'reason'=>'locked_tailwind_slot_changed','slot'=>$slot];
            }

            // Role and lock metadata are contract metadata, not design output.
            if((string)($afterDefinition['role']??'')!==(string)($beforeDefinition['role']??'')
                || (bool)($afterDefinition['locked']??false)!==(bool)($beforeDefinition['locked']??false)){
                return ['ok'=>false,'reason'=>'tailwind_slot_metadata_changed','slot'=>$slot];
            }

            $beforeClasses=$this->contract->tokenize($beforeDefinition['classes']??[]);
            $afterClasses=$this->contract->tokenize($afterDefinition['classes']??[]);
            $removed=array_values(array_diff($beforeClasses,$afterClasses));
            $added=array_values(array_diff($afterClasses,$beforeClasses));

            // Structural/protected utilities can never be removed by AI 4.
            $protectedRemoved=array_values(array_filter(
                $removed,
                fn(string $token):bool=>$this->validator->isProtectedUtility($token)
            ));
            if($protectedRemoved!==[]){
                return [
                    'ok'=>false,
                    'reason'=>'protected_tailwind_removed',
                    'slot'=>$slot,
                    'classes'=>$protectedRemoved,
                ];
            }

            // For newly-added utilities, reject unresolved conflicts only for
            // high-confidence mutually-exclusive families. Broad visual families
            // such as background/border color can legitimately coexist (for
            // example background color + gradient), so they are not blocked here.
            $exclusiveFamilies=[
                'text-size','text-align','font-weight','line-height','letter-spacing',
                'text-transform','font-style','display','flex-direction','flex-wrap',
                'align-items','justify-content','align-content','align-self',
                'place-items','place-content','grid-cols','grid-rows','col-span',
                'row-span','order','position','overflow','overflow-x','overflow-y',
                'padding','padding-x','padding-y','padding-top','padding-right',
                'padding-bottom','padding-left','margin','margin-x','margin-y',
                'margin-top','margin-right','margin-bottom','margin-left',
                'gap','gap-x','gap-y','width','min-width','max-width','height',
                'min-height','max-height','radius','radius-tl','radius-tr','radius-bl',
                'radius-br','radius-top','radius-right','radius-bottom','radius-left',
                'opacity','aspect-ratio','object-fit','object-position','z-index',
            ];
            foreach($added as $token){
                $family=$this->conflicts->describe($token)['family']??null;
                if($family===null||!in_array($family,$exclusiveFamilies,true)) continue;
                $conflicting=$this->conflicts->conflictsFor($token,$afterClasses);
                if($conflicting!==[]){
                    return [
                        'ok'=>false,
                        'reason'=>'tailwind_conflict',
                        'slot'=>$slot,
                        'class'=>$token,
                        'conflicts'=>$conflicting,
                    ];
                }
            }

            if($removed!==[]||$added!==[]){
                $tailwindDiff[$slot]=['removed'=>$removed,'added'=>$added];
            }
        }

        $editableDiff=$this->valueDiff($beforeEditable,$editable);

        $next=$originalBlock;
        foreach($editable as $key=>$value) $next[$key]=$value;

        // API 4 speaks in COMPLETE effective class lists because that is easiest
        // for the model to reason about. Persistence deliberately does NOT
        // replace each renderer fallback with that full list. Doing so would
        // freeze runtime/conditional classes (slider active states, animation
        // state, theme-dependent classes, etc.). Persist only the verified delta
        // as add/remove overlays over the Spark's original renderer classes.
        $persisted=$this->contract->fromBlock($sparkType,$originalBlock);
        foreach($tailwindDiff as $slot=>$delta){
            $existing=$this->persistedTailwindDefinition($persisted,$slot);
            $existingAdd=$this->contract->tokenize($existing['add']??[]);
            $existingRemove=$this->contract->tokenize($existing['remove']??[]);

            // Legacy full-class persisted slots are converted conservatively:
            // keep their currently effective full classes only if no delta is
            // requested. Changed slots become overlay patches from this point on.
            if(array_key_exists('classes',$existing)){
                $existingAdd=[];
                $existingRemove=[];
            }

            foreach((array)($delta['removed']??[]) as $token){
                $existingAdd=array_values(array_filter($existingAdd,fn(string $v):bool=>$v!==$token));
                if(!in_array($token,$existingRemove,true)) $existingRemove[]=$token;
            }
            foreach((array)($delta['added']??[]) as $token){
                $existingRemove=array_values(array_filter($existingRemove,fn(string $v):bool=>$v!==$token));
                if(!in_array($token,$existingAdd,true)) $existingAdd[]=$token;
            }

            $this->putPersistedTailwindDefinition($persisted,$slot,[
                'add'=>array_values(array_unique($existingAdd)),
                'remove'=>array_values(array_unique($existingRemove)),
                'role'=>(string)(data_get($beforeTailwind,"slots.{$slot}.role",$slot)),
                'locked'=>(bool)(data_get($beforeTailwind,"slots.{$slot}.locked",false)),
            ]);
        }
        Arr::set($next,$this->contract->storageKey(),$persisted);

        $changed=$editableDiff!==[]||$tailwindDiff!==[];
        return [
            'ok'=>true,
            'changed'=>$changed,
            'block'=>$next,
            'editable'=>$editable,
            'tailwind'=>$validated['schema'],
            'persisted_tailwind'=>$persisted,
            'diff'=>[
                'editable'=>$editableDiff,
                'tailwind'=>$tailwindDiff,
            ],
            'before_fingerprint'=>$this->fingerprint([
                'editable'=>$beforeEditable,
                'tailwind'=>$beforeTailwind,
            ]),
            'after_fingerprint'=>$this->fingerprint([
                'editable'=>$editable,
                'tailwind'=>$validated['schema'],
            ]),
        ];
    }

    /**
     * Protect the supplied editable schema recursively. Associative objects may
     * not gain/lose keys. Numeric lists may edit values inside existing items,
     * but list length/type changes are structural operations and must go through
     * structural_actions instead of an ordinary/full-schema patch.
     */
    private function shapeMismatch(mixed $before,mixed $after,string $path='editable'): ?string
    {
        if(!is_array($before)||!is_array($after)) return null;
        $beforeIsList=array_is_list($before);
        $afterIsList=array_is_list($after);
        if($beforeIsList!==$afterIsList) return $path;

        if($beforeIsList){
            if(count($before)!==count($after)) return $path;
            foreach($before as $index=>$value){
                $child=$this->shapeMismatch($value,$after[$index]??null,$path.'.'.$index);
                if($child!==null) return $child;
            }
            return null;
        }

        $beforeKeys=array_keys($before); sort($beforeKeys);
        $afterKeys=array_keys($after); sort($afterKeys);
        if($beforeKeys!==$afterKeys) return $path;

        foreach($beforeKeys as $key){
            $child=$this->shapeMismatch($before[$key]??null,$after[$key]??null,$path.'.'.$key);
            if($child!==null) return $child;
        }
        return null;
    }

    /** Compact deterministic before/after value diff for verification/logging. */
    private function valueDiff(mixed $before,mixed $after,string $path=''): array
    {
        if($before===$after) return [];
        if(is_array($before)&&is_array($after)&&!array_is_list($before)&&!array_is_list($after)){
            $changes=[];
            foreach(array_values(array_unique(array_merge(array_keys($before),array_keys($after)))) as $key){
                $child=$this->valueDiff($before[$key]??null,$after[$key]??null,$path===''?(string)$key:$path.'.'.$key);
                array_push($changes,...$child);
                if(count($changes)>=120) break;
            }
            return array_slice($changes,0,120);
        }
        return [[
            'path'=>$path===''?'editable':$path,
            'before'=>$this->compactValue($before),
            'after'=>$this->compactValue($after),
        ]];
    }

    private function compactValue(mixed $value): mixed
    {
        if(is_string($value)) return Str::limit($value,240,'…');
        if(is_array($value)){
            $json=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            return is_string($json)&&strlen($json)>500 ? Str::limit($json,500,'…') : $value;
        }
        return $value;
    }

    private function fingerprint(array $value): string
    {
        return hash('sha256',json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'');
    }

    private function editableBlock(array $block): array
    {
        // Full customer-editable Spark data, excluding runtime/system/schema keys.
        $forbidden=[
            'id','website_id','page_id','user_id','created_at','updated_at',
            '_renderKey','render_key','key','type',
            $this->contract->storageKey(),
        ];
        return collect($block)->reject(
            fn($value,$key)=>in_array((string)$key,$forbidden,true) || Str::startsWith((string)$key,'_')
        )->all();
    }

    /**
     * Exact repeater slots are flattened only for the Luna API payload:
     *   cards__1__title => collections.cards[1].styles.title
     *   plans__1__features__2__label => nested collection style
     * Shared aliases (card/title/button) remain in top-level slots.
     */
    private function parseScopedTailwindSlot(string $slot): ?array
    {
        $parts=array_values(array_filter(explode('__',$slot),fn($part)=>$part!==''));
        if(count($parts)<3 || count($parts)%2===0) return null;
        $style=(string)array_pop($parts);
        $path=[];
        foreach($parts as $index=>$part){
            if($index%2===0){
                $name=$this->contract->normalizeSlot((string)$part);
                if($name==='') return null;
                $path[]=$name;
            }else{
                $path[]=ctype_digit((string)$part)?(int)$part:(string)$part;
            }
        }
        $path=$this->contract->normalizeScopePath($path);
        if($path===[]) return null;
        return ['path'=>$path,'style'=>$this->contract->normalizeSlot($style)];
    }

    private function persistedTailwindDefinition(array $schema,string $slot): array
    {
        $scoped=$this->parseScopedTailwindSlot($slot);
        if($scoped===null){
            $definition=$schema['styles'][$slot]??$schema['slots'][$slot]??[];
            return is_array($definition)?$definition:[];
        }
        $scope=$this->contract->scopeAtPath($schema,$scoped['path']);
        $definition=is_array($scope)?($scope['styles'][$scoped['style']]??[]):[];
        return is_array($definition)?$definition:[];
    }

    private function putPersistedTailwindDefinition(array &$schema,string $slot,array $definition): void
    {
        $scoped=$this->parseScopedTailwindSlot($slot);
        if($scoped===null){
            // New semantic shared aliases live in V2 styles; old auto_* bindings
            // remain in legacy slots so existing sites are not rewritten en masse.
            if(array_key_exists($slot,(array)($schema['styles']??[])) || !array_key_exists($slot,(array)($schema['slots']??[]))){
                $schema['styles'][$slot]=$definition;
            }else{
                $schema['slots'][$slot]=$definition;
            }
            return;
        }

        $cursor=&$schema['collections'];
        $path=$scoped['path'];
        for($i=0;$i<count($path);$i+=2){
            $collection=(string)$path[$i];
            $selector=$path[$i+1];
            if(!isset($cursor[$collection]) || !is_array($cursor[$collection])) $cursor[$collection]=[];
            $index=is_int($selector)?$selector:null;
            if($index===null){
                foreach($cursor[$collection] as $candidateIndex=>$candidate){
                    if(is_array($candidate) && (string)($candidate['key']??'')===(string)$selector){$index=$candidateIndex;break;}
                }
                if($index===null){
                    $index=count($cursor[$collection]);
                    $cursor[$collection][$index]=['key'=>(string)$selector,'styles'=>[],'collections'=>[]];
                }
            }
            while(count($cursor[$collection])<=$index){
                $cursor[$collection][]=['styles'=>[],'collections'=>[]];
            }
            if(!is_array($cursor[$collection][$index]??null)) $cursor[$collection][$index]=['styles'=>[],'collections'=>[]];
            $cursor[$collection][$index]['styles']=is_array($cursor[$collection][$index]['styles']??null)?$cursor[$collection][$index]['styles']:[];
            $cursor[$collection][$index]['collections']=is_array($cursor[$collection][$index]['collections']??null)?$cursor[$collection][$index]['collections']:[];
            if($i===count($path)-2){
                $cursor[$collection][$index]['styles'][$scoped['style']]=$definition;
                return;
            }
            $cursor=&$cursor[$collection][$index]['collections'];
        }
    }

    private function currentTailwindSchema(array $block,array $elementContext): array
    {
        $sparkType=(string)($block['type']??'');
        $stored=$this->contract->fromBlock($sparkType,$block);
        $slots=[];

        // The renderer inventory is authoritative because it includes fallback
        // classes for migrated slots not yet materialized in persisted storage.
        $inventory=$elementContext['tailwindInventory']??$elementContext['tailwind_inventory']??[];
        if(is_array($inventory)){
            $customerButtonOrdinal=0;
            foreach($inventory as $row){
                if(!is_array($row)) continue;
                $slot=$this->contract->normalizeSlot((string)($row['slot']??''));
                if($slot==='') continue;
                $classes=$this->validator->validateClasses((string)($row['classes']??''));
                if(($classes['errors']??[])!==[]) continue;
                $existing=$this->persistedTailwindDefinition($stored,$slot);

                $tag=Str::lower(trim((string)($row['tag']??'')));
                $text=trim(preg_replace('/\s+/',' ',(string)($row['text']??'')));
                $rawRole=Str::lower(trim((string)($row['role']??'')));
                $semanticRole=isset($slots[$slot]['role'])
                    ? (string)$slots[$slot]['role']
                    : $this->semanticRole(
                        $slot,
                        $tag,
                        $rawRole,
                        $text,
                        $classes['classes'],
                        $customerButtonOrdinal
                    );

                $storedRole=is_array($existing)&&is_string($existing['role']??null)
                    ? Str::lower(trim((string)$existing['role']))
                    : '';
                // Generic migration roles such as auto_14 are not useful to API 4.
                // Preserve meaningful stored roles; otherwise enrich from DOM semantics.
                $role=($storedRole!=='' && $storedRole!==$slot && !preg_match('/^(?:auto|b\d+)_?\d*$/',$storedRole))
                    ? $storedRole
                    : $semanticRole;

                $slots[$slot]=[
                    'classes'=>$classes['classes'],
                    'role'=>$role!==''?$role:$slot,
                    'locked'=>is_array($existing)?(bool)($existing['locked']??false):false,
                ];
            }
        }

        // Preserve persisted shared V2 styles and legacy slots that are not
        // currently visible in the DOM inventory (responsive/conditional nodes).
        foreach(array_merge((array)($stored['slots']??[]),(array)($stored['styles']??[])) as $slot=>$definition){
            $slot=$this->contract->normalizeSlot((string)$slot);
            if($slot===''||isset($slots[$slot])) continue;
            $resolved=$this->contract->resolveSlot($block,$slot,'');
            $classes=$this->validator->validateClasses($resolved);
            if(($classes['errors']??[])!==[]) continue;
            $slots[$slot]=[
                'classes'=>$classes['classes'],
                'role'=>is_array($definition)&&is_string($definition['role']??null)?$definition['role']:$slot,
                'locked'=>is_array($definition)?(bool)($definition['locked']??false):false,
            ];
        }

        return [
            'version'=>(int)config('spark-tailwind-schema.version',1),
            'spark_type'=>$sparkType,
            'slots'=>$slots,
        ];
    }

    private function conversationContext(array $conversation): array
    {
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

    /**
     * Give old auto_* migration slots useful semantics to API 4 without renaming
     * the persisted slot key. This keeps every renderer/compiler binding stable.
     */
    private function semanticRole(
        string $slot,
        string $tag,
        string $rawRole,
        string $text,
        array $classes,
        int &$customerButtonOrdinal
    ): string {
        $haystack=Str::lower(trim($slot.' '.$rawRole.' '.$text.' '.implode(' ',$classes)));

        if(in_array($tag,['a','button'],true)){
            $builderOrNav=Str::contains($haystack,[
                'previous','next','show slide','edit slide','add slide',
                'slider-nav','cosmic-hero-slider-edit','cosmic-hero-slider-add',
            ]) || preg_match('/^(?:←|→|‹|›|•)?$/u',trim($text));

            if(!$builderOrNav && $text!==''){
                $customerButtonOrdinal++;
                if($customerButtonOrdinal===1) return 'primary button cta';
                return 'secondary button cta';
            }
            return 'control button';
        }

        if(preg_match('/^h[1-6]$/',$tag) || Str::contains($haystack,['heading','headline','title'])) return 'heading';
        if($tag==='img' || Str::contains($haystack,['image','photo','object-cover','object-contain'])) return 'image';
        if($tag==='section') return 'section';
        if(Str::contains($haystack,['card','panel'])) return 'card';
        if($tag==='p' || Str::contains($haystack,['description','body text','paragraph'])) return 'body text';

        return $rawRole!==''?$rawRole:$slot;
    }

    private function endpoint(): string
    {
        return rtrim((string)config('openai.base_url','https://api.openai.com/v1'),'/').'/chat/completions';
    }
}
