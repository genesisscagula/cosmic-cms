# Batch 5 — Pending Plan / Proceed Confirmation

> Deprecated by the verified direct-action pipeline. Normal build, update, publish, and navigation actions bypass this flow. Only destructive delete keeps token-backed confirmation.

**PASS — static integration QA**

## Conversation flow
Large creative requests now follow:

User request → Luna planner → exact plan stored → Luna asks to proceed → user replies `Yes`, `Proceed`, `Go ahead`, `Do it`, or uses the existing Continue button → stored plan resumes → execution → verification → final Luna reply.

## Exact-plan behavior
For Builder and Trial page Luna:
- the planner JSON and finalized operation list are cached;
- the current page-block fingerprint is cached with the plan;
- confirmation does not call the creative planner again;
- the cached operation list is restored before execution;
- if page state changed before confirmation, the plan is discarded rather than applied to stale content.

## Cancellation
`No`, `Cancel`, `Not now`, and equivalent negative replies clear the pending plan. Builder/public confirmation Cancel controls now clear server-side pending state too.

## Public trial build
A useful broad brief such as `Build me a luxury website for a hotel` now asks for confirmation before trial generation. On `Proceed`, the original stored brief—not the word `Proceed`—is sent into trial generation.

## Compatibility
The existing Builder `Continue · credits` button remains supported. It now consumes the same stored plan instead of asking the planner to reinterpret the request.

## What still remains separate
Destructive actions retain their existing stricter confirmation-token flow. Small, explicit low-risk edits continue to execute directly.
