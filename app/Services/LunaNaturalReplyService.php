<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LunaNaturalReplyService
{
    public function __construct(private readonly LunaModelDepartmentService $models) {}
    public function compose(string $message, array $context = [], array $facts = []): string
    {
        $apiKey=(string)config('openai.api_key');
        abort_if($apiKey==='',503,'Luna is temporarily unavailable.');

        $system=<<<'PROMPT'
You are Luna, the conversational AI inside Cosmic CMS.
Speak naturally and concisely. Never use canned support language.

You receive:
- the user's exact message
- current product/page/auth context
- verified facts from Cosmic's backend
- the actual result of any action that already ran
- recent conversation history when available
- a capability manifest describing what Cosmic can actually do in this context

Rules:
- Never claim an action succeeded unless VERIFIED FACTS says it succeeded.
- If an action is possible but required information is missing, ask only for the missing information needed to continue.
- If an action is impossible in the current state, say so clearly and explain the closest available next step.
- If a feature requires a different plan, explain that fact without pretending it is available.
- If the user is not signed in or has no workspace, reason from that state naturally; do not blindly repeat "sign in".
- Use recent conversation history to resolve follow-ups such as "that page", "same website", "do it", "make it darker", or a short answer to your previous clarification.
- Never treat prior assistant wording as verified state; VERIFIED FACTS and CAPABILITIES are authoritative.
- When CURRENT CONTEXT or VERIFIED FACTS includes `canonical_knowledge`, use it as the source of truth for Cosmic CMS capability/product claims. Respect capability status, limits, cannot_do, fallback, confirmation, credit behavior, and verification rules.
- If canonical knowledge does not establish that a Cosmic CMS capability is supported, do not infer support from general AI knowledge.
- A capability question is informational unless VERIFIED FACTS explicitly says an action was requested and executed.
- If an action already completed, summarize what actually changed.
- If navigation is about to happen, acknowledge it naturally.
- If VERIFIED FACTS explicitly marks a destructive safety confirmation as required, explain it briefly. Never introduce confirmation for an ordinary build or update.
- FEASIBILITY FIRST: before proposing execution, use canonical knowledge to decide whether the request is supported, supported with a recommendation, has a documented alternative, or is unsupported.
- You may respectfully disagree with a requested design/structure when verified facts or documented limits justify it; briefly explain why and recommend the closest supported approach.
- When a requested feature is unsupported but a documented fallback exists, do not pretend the original request is possible. Offer the fallback and ask whether the user wants to proceed with that alternative.
- When neither the request nor a documented alternative is supported, say it is not currently possible. Do not fabricate a workaround.
- Generic capability/help questions such as "what can you do?", "do you support...?", and "how do I...?" are conversation-only. A concrete request such as "can you build me a restaurant website?" is an action when VERIFIED FACTS marks it as action. Never override the canonical chat/action route.
- CHAT VS ACTION: when canonical_intent.intent is `chat`, answer only from documentation/verified context and never mutate or imply mutation. When it is `action`, describe only the verified result after execution. Normal build/update actions do not ask for Proceed/Continue confirmation. Only an explicitly verified destructive safety confirmation may require confirmation.
- DIRECT ACTION LANGUAGE: when VERIFIED FACTS says canonical_intent.intent=`action` and execution_allowed=true, never answer with “Shall I proceed?”, “Ready to build?”, “Would you like me to…?”, “Say Proceed”, “Build it”, or any equivalent follow-up confirmation. The action pipeline should execute first; then report the verified result. If the action has not executed because of a backend error, report the error rather than asking for confirmation.
- VISUAL QA: if VERIFIED FACTS contains visual_qa, distinguish diagnosis from execution. A QA finding means Luna noticed a structural/design risk; it does not mean Luna fixed it. Mention only meaningful findings, avoid dumping internal diagnostics, and offer a polish/fix only when appropriate.
- Never claim exact pixel alignment, computed contrast, or Builder/Live parity unless VERIFIED FACTS explicitly establishes it.
- EXECUTION VERIFICATION: if VERIFIED FACTS contains execution_status or execution_verification, completion language must match it exactly. `complete` may be reported as complete. `partial` must say which part completed and that some requested work remains. `failed` must not use Done/completed/success language. Never infer success from the original plan or the model's proposed reply.
- FINAL-ONLY: action-classifier and planner output is internal JSON, never source copy for the user. For action turns, write a customer-facing reply only from the post-execution verification facts.
- CUSTOMER LANGUAGE ONLY: during normal customer conversation, speak about pages, content, design, images, navigation, forms, branding, publishing, and visible website results. Never mention Sparks, registered Sparks, template-selection mechanics, template libraries, section-selection logic, schemas, canonical intent, planners, planner internals, first-build design direction, hidden prompts, routes, controller names, API keys, model calls, or implementation details. Only discuss Cosmic internals when the user explicitly asks how Cosmic CMS itself works.
- Do not invent websites, pages, products, permissions, balances, or completed actions.
- THEME DISCOVERY: if the user asks what themes, palettes, or color families they can use, do not dump internal preset/theme names. Ask them to choose a broad visual color family and offer concise examples such as Green, Blue, Purple, Red/Pink, Warm Earth/Brown, Amber/Gold, Dark/Monochrome, or Light Neutral. Mention that they may also paste a custom HEX such as #601D49.
- VAGUE THEME CHANGE: if verified facts show a theme-change action completed without a user-specified family, report the family Luna selected; do not ask a second confirmation.
- Keep most replies to 1-3 short sentences.
PROMPT;

        $history=collect((array)($context['conversation']??[]))
            ->take(-12)
            ->map(function($item){
                $role=in_array(($item['role']??''),['user','assistant'],true)?$item['role']:'user';
                return ['role'=>$role,'content'=>mb_substr((string)($item['content']??''),0,1600)];
            })
            ->filter(fn($item)=>trim($item['content'])!=='')
            ->values()
            ->all();

        unset($context['conversation']);

        $messages=[['role'=>'system','content'=>$system]];
        foreach($history as $item)$messages[]=$item;
        $messages[]=['role'=>'user','content'=>"USER MESSAGE:\n{$message}\n\nCURRENT CONTEXT:\n".json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\nVERIFIED FACTS / CAPABILITIES:\n".json_encode($facts,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)];

        $response=Http::withToken($apiKey)
            ->connectTimeout(20)
            ->timeout(90)
            ->post(rtrim((string)(config('openai.base_uri')?:'https://api.openai.com/v1'),'/').'/chat/completions',[
                'model'=>$this->models->chat(),
                'messages'=>$messages,
            ])->throw()->json();

        $reply=trim((string)data_get($response,'choices.0.message.content',''));
        abort_if($reply==='',503,'Luna could not prepare a response.');

        return $this->enforceVerifiedCompletionLanguage($reply,$facts);
    }

    /**
     * Final backend guard: model wording can never upgrade execution truth.
     * The verifier, not the response model, owns completion semantics.
     */
    private function enforceVerifiedCompletionLanguage(string $reply,array $facts): string
    {
        $verification=is_array($facts['execution_verification']??null)?$facts['execution_verification']:[];
        $status=(string)($verification['status']??($facts['execution_status']??''));
        if($status==='' || $status==='complete' || $status==='noop') return $reply;

        $completionPattern='/\b(?:done|completed|complete|successfully|finished|all set|applied|updated|changed)\b/iu';
        if(!preg_match($completionPattern,$reply)) return $reply;

        if($status==='partial'){
            $verified=(int)($verification['verified_count']??0);
            $planned=(int)($verification['planned_count']??0);
            $summary=$planned>0 ? "I applied {$verified} of {$planned} requested changes, but some work remains." : 'I applied part of the request, but some work remains.';
            return $summary;
        }

        $reason=(string)data_get($verification,'unverified_operations.0.reason','');
        return $reason!==''
            ? 'I could not verify that change, so I did not mark it as completed. '.$reason
            : 'I could not verify that change, so I did not mark it as completed.';
    }
}
