# Batch 6.1 — English Intent Index + Capability Metadata Router

**PASS — static integration QA**

## Patch 1 — English Intent Index
Added `resources/luna/intent_index.json`.

The local scanner recognizes:
- greetings;
- thanks;
- acknowledgements;
- confirmations;
- cancellations;
- capability questions;
- help/how-to questions;
- broad website actions;
- edit/mutation actions.

Obvious greetings/thanks/acknowledgements use a local reply path: no AI call, no mutation, 0 credits.

Confirm/cancel remain owned by the pending-action flow so `Proceed` cannot accidentally become a new creative prompt.

## Patch 2 — Capability Metadata
All capability registry entries now expose:
- category;
- tags;
- aliases;
- keywords;
- doc_refs.

Aliases are English-first and include common product/user vocabulary such as `nav`, `main menu`, `mega menu`, `mega footer`, `brand palette`, `build website`, and `redesign section`.

## Patch 3 — Fast Local Matcher
Added `LunaIntentIndex`.

It performs deterministic regex classification before the AI/planner path. Builder, Trial, authenticated Global Luna, and public Global Luna now short-circuit obvious local conversation messages.

Example:
`hello` → `conversation.greeting` → local reply → 0 credits → no operations.

## Patch 4 — Knowledge Router Integration
`LunaKnowledgeRouter` now consumes the intent classification before capability scoring.

Capability scoring now includes:
- intent category boost;
- capability aliases;
- capability tags;
- capability keywords;
- legacy category aliases;
- scope.

Matched capability `doc_refs` are preferred before category fallback docs, reducing unrelated documentation in the grounding packet.

## Safety invariant
A local conversational match can never claim or perform a website mutation. Action requests still go through the normal grounded planner/action/verification pipeline.
