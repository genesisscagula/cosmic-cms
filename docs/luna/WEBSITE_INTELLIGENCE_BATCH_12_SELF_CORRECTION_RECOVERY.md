# Website Intelligence — Batch 12: Self-Correction / Recovery

## Goal
Give Luna one bounded recovery layer when execution verification says a requested mutation only partially completed or failed.

Flow:
`plan -> execute -> verify -> bounded recovery -> verify again -> truthful reply`

## Recovery policy
Recovery is deliberately conservative.

Automatic recovery:
- runs only after `partial` or `failed`
- makes no second LLM/API planning call
- retries only safe idempotent operations
- keeps the exact original target/scope
- runs at most once
- costs 0 additional AI credits
- is verified again before Luna reports success

Safe retry actions:
- edit
- explicit theme application

Never auto-retried:
- delete
- move
- replace
- insert before
- insert after

Those structural actions are intentionally blocked because a guessed retry could mutate the wrong section after indexes or layout state changed.

## Truthfulness
Recovery does not bypass Batch 9 Execution Verification.

If recovery fixes every planned operation, final status becomes `complete`.
If not, final status remains `partial` or `failed`, and Luna must not say Done.

## Guardrails
- no scope widening
- no silent rebrand
- no silent destructive action
- no unregistered Spark invention
- no recursive retry loop
- no extra AI/API charge

## Response
The Builder response now includes `self_correction` with:
- attempted
- retry operations
- recovered operations
- before/after status
- resolved
- blocked count
- additional AI calls = 0
- additional credit cost = 0
