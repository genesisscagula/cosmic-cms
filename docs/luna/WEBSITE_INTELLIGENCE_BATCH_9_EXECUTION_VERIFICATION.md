# Website Intelligence — Batch 9: Execution Verification

## Goal
Luna reports only website changes the backend can verify.

Flow:
`plan -> execute -> compare planned vs applied -> classify result -> report truthfully`

## Statuses
- `complete` — every executable planned operation has a corresponding verified applied operation.
- `partial` — at least one planned operation verified and at least one did not.
- `failed` — no planned operation could be fully verified.
- `noop` — no executable planned mutation existed.

## Matching
Verification compares:
- operation action;
- target index when available;
- destination index for move;
- Spark key for replace;
- theme key for theme changes.

Schema-preserving repeater add/remove actions can satisfy an edit plan when they are the verified implementation of that requested edit.

## Reporting rule
A model-generated reply is not authoritative evidence of success.

If status is partial, Luna must say that only part of the request completed and surface the unverified remainder naturally.

If status is failed, Luna must not say Done, completed, fixed, or successful.

## Billing boundary
Execution verification controls truthfulness, not pricing. Credit charging remains governed by the existing billing/verified-state-change layer.

## Builder + Trial
Both orchestration paths now return an `execution_verification` object and use it before generating the final natural reply.
