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
    ) {}

    /**
     * API 4: Luna receives one selected Spark's complete editable block data plus
     * its complete CURRENT rendered Tailwind slot schema and returns that same
     * editable schema in full.
     */
    public function edit(string $request, array $block, array $sparkTarget, array $elementContext=[], array $conversation=[]): array
    {
        $sparkType=(string)($block['type']??'');
        $targetType=(string)($sparkTarget['type']??'');
        if($sparkType==='' || $targetType==='' || $sparkType!==$targetType){
            return ['ok'=>false,'reason'=>'spark_target_mismatch'];
        }

        $contextTarget=filter_var($elementContext['tailwindTargetIndex']??null,FILTER_VALIDATE_INT);
        $lockedIndex=filter_var($sparkTarget['index']??null,FILTER_VALIDATE_INT);
        if($contextTarget!==false && $lockedIndex!==false && (int)$contextTarget!==(int)$lockedIndex){
            return ['ok'=>false,'reason'=>'tailwind_inventory_target_mismatch'];
        }

        $editable=$this->editableBlock($block);
        $tailwind=$this->currentTailwindSchema($block,$elementContext);
        if(($tailwind['slots']??[])===[]){
            Log::warning('[LunaRouter] API 4 rejected',[
                'reason'=>'tailwind_inventory_missing',
                'spark_type'=>$sparkType,
                'target_index'=>$sparkTarget['index']??null,
            ]);
            return ['ok'=>false,'reason'=>'tailwind_inventory_missing'];
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
You are Luna's fourth routing/editing API inside Cosmic CMS.
APIs 1-3 have already selected one exact Spark instance. Edit ONLY that Spark.

You receive:
1. USER REQUEST
2. LOCKED SPARK TARGET
3. FULL EDITABLE SPARK SCHEMA
4. FULL CURRENT TAILWIND SCHEMA for the rendered slots in this Spark

Return JSON only:
{
  "spark_type":"EXACT_LOCKED_TYPE",
  "editable": { COMPLETE editable Spark schema },
  "tailwind": {
    "version": 1,
    "spark_type":"EXACT_LOCKED_TYPE",
    "slots": {
      "slot_name":{"classes":["complete","current","class","list"],"role":"slot_name","locked":false}
    }
  }
}

Rules:
- BUILDER CONVERSATION is the authoritative natural-language context for pronouns and follow-ups such as "it", "that", "a little more", "a little tighter", and "same as before". Resolve those references from the conversation before choosing schema changes.
- Return the COMPLETE editable object and COMPLETE Tailwind slots object, not a patch.
- Preserve every unrelated editable field and every unrelated Tailwind slot/class exactly.
- Change only fields/classes necessary to satisfy USER REQUEST.
- Never add/remove/rename internal database/system fields. They are intentionally absent.
- spark_type must exactly match LOCKED SPARK TARGET.
- Tailwind slot names must come only from FULL CURRENT TAILWIND SCHEMA. Never invent a slot.
- Each Tailwind slot's classes is the complete desired class list after the edit.
- Use valid Tailwind utilities only. No CSS, HTML, JS, style attributes, explanations, comments, markdown, or reply text.
- For plural natural targets such as "buttons", edit all appropriate customer-facing button/CTA slots in this selected Spark.
- For "primary"/"secondary"/specific wording, edit only the matching role/slot.
- For relative follow-ups, the supplied schema is CURRENT persisted/rendered state; modify from it, not from an original default.
- Preserve responsive/state variants unless the request explicitly changes them.
PROMPT;

        try{
            $response=Http::withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(90)
                ->post($this->endpoint(),[
                    'model'=>$this->models->sparkEditor(),
                    'response_format'=>['type'=>'json_object'],
                    'messages'=>[
                        ['role'=>'system','content'=>$system],
                        ['role'=>'user','content'=>"BUILDER CONVERSATION (oldest to newest):\n".json_encode($this->conversationContext($conversation),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nCURRENT USER REQUEST:\n{$request}\n\nLOCKED SPARK TARGET:\n".json_encode($sparkTarget,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nFULL EDITABLE SPARK SCHEMA:\n".json_encode($editable,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nFULL CURRENT TAILWIND SCHEMA:\n".json_encode($tailwind,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                    ],
                ])->throw()->json();

            $decoded=json_decode((string)data_get($response,'choices.0.message.content','{}'),true);
            if(!is_array($decoded)){
                Log::warning('[LunaRouter] API 4 rejected',[
                    'reason'=>'invalid_json','spark_type'=>$sparkType,'target_index'=>$sparkTarget['index']??null,
                ]);
                return ['ok'=>false,'reason'=>'invalid_json'];
            }
            $result=$this->validateResult($sparkType,$editable,$tailwind,$decoded,$block);
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
            $existing=is_array($persisted['slots'][$slot]??null)?$persisted['slots'][$slot]:[];
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

            $persisted['slots'][$slot]=[
                'add'=>array_values(array_unique($existingAdd)),
                'remove'=>array_values(array_unique($existingRemove)),
                'role'=>(string)(data_get($beforeTailwind,"slots.{$slot}.role",$slot)),
                'locked'=>(bool)(data_get($beforeTailwind,"slots.{$slot}.locked",false)),
            ];
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
     * Protect associative schema shape recursively. Numeric lists are editable
     * collections and may change length/content; associative objects may not
     * silently gain or lose keys.
     */
    private function shapeMismatch(mixed $before,mixed $after,string $path='editable'): ?string
    {
        if(!is_array($before)||!is_array($after)) return null;
        $beforeIsList=array_is_list($before);
        $afterIsList=array_is_list($after);
        if($beforeIsList!==$afterIsList) return $path;
        if($beforeIsList) return null;

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
                $existing=$stored['slots'][$slot]??[];

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

        // Preserve persisted slots that are not currently visible in inventory.
        foreach((array)($stored['slots']??[]) as $slot=>$definition){
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
