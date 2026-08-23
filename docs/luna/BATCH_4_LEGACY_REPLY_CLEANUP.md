# Batch 4 — Legacy Hardcoded Conversation Reply Cleanup

**PASS — static integration QA**

## What changed
User-facing replies for deterministic Builder actions now pass through `LunaNaturalReplyService` with:
- verified action facts;
- canonical Luna knowledge;
- actual scope/result;
- failure/constraint information.

This covers deterministic typography, background, section-layout, section reorder, Services CRUD result reporting, header shell actions, page/navigation updates, low-level-design guardrail replies, and empty/failure execution fallbacks.

Trial and authenticated page-chat capability fallbacks and mutation-result fallbacks are also AI-composed from verified facts rather than fixed canned sentences.

## What remains deterministic
The following are intentionally **not** handed to the LLM as authority:
- intent/action routing;
- permissions and authorization;
- credit checks/accounting;
- schema validation;
- menu-depth limits;
- destructive-action confirmation;
- state mutation;
- publish/mutation verification;
- safe color-family validation;
- design-system guardrails.

These components return facts/state. Luna formulates the conversational reply from those facts.

## Global Luna
Public/authenticated Global Luna was already using `LunaNaturalReplyService` for conversational wording. Its deterministic branches remain routing/fact construction only.

## Important
Private helpers may retain internal diagnostic/result notes for debugging or structured routing. Batch 4's invariant is that those notes are no longer trusted as the user-facing conversational answer on the page/trial Luna surfaces.

## Current replacement
The verified direct-action pipeline supersedes the old generic pending-plan layer. Normal actions execute and verify directly; only destructive delete retains explicit confirmation.
