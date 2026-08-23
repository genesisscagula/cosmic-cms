# Batch 7 — Feasibility + Conversation Gate

> Historical architecture note. The current verified direct-action pipeline bypasses generic pending-plan confirmation; only destructive delete is confirmed.

## Goal
Luna behaves as a conversational website agent before behaving as an executor.

Flow:
`user -> English intent -> capability/docs shortlist -> feasibility -> natural reply/proposal -> confirmation -> exact pending plan -> execution -> verification`

## Feasibility outcomes
- `supported`
- `supported_with_recommendation`
- `alternative_available`
- `unsupported`

## Hard invariants
- Capability/help questions never mutate website state.
- An unsupported capability never reaches the executor.
- If an unsupported capability has a documented fallback, Luna offers the fallback rather than pretending the original request is supported.
- If no supported fallback exists, Luna says the request is not currently possible.
- Executable Builder requests route directly to their executor after required clarification.
- Generic proceed/continue messages do not resume normal work.
- Conversation/proposal is distinct from execution.
- Luna may disagree/recommend when canonical documentation or guardrails justify it.

## Example
`What can you do?` -> documentation-grounded conversation -> 0 mutation.
`Can you create a header mega menu?` -> unsupported -> offer standard dropdown navigation up to 3 levels -> 0 mutation.
`Build me a luxury hotel website` -> supported -> design/composition -> content -> apply -> verify -> final reply.
