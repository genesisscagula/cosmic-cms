# Luna Verified Direct-Action Pipeline — Batches 1–3

## Batch 1 — Intent to action split

API 1 returns exactly `{"intent":"chat"}` or `{"intent":"action"}`.

- `chat` routes to `LunaNaturalReplyService` and stops.
- `action` routes to the machine-only structured classifier.
- The action classifier never emits customer-facing copy.

## Batch 2 — Structured execution

- Build: design/composition JSON → locked content generation → apply → verify.
- Update: target/change-plan JSON → apply → verify.
- Publish: save → health check → publish executor → verified final reply.
- Navigation: authorized destination resolver → navigation route.
- Intermediate model output is JSON only.
- Normal build, update, publish, and navigation requests never create a pending plan or proceed layer.
- Destructive delete retains one explicit token-backed safety confirmation.

## Batch 3 — Verification and final reply

Action replies are generated only from execution facts. A complete status may claim completion; partial and failed statuses must describe the verified result without success language.

The visible action phases are:

`Thinking… → Planning… → Designing… → Building… → Checking…`

Publish omits Designing when there is no design operation. Navigation uses Thinking then Checking.

## Regression expectations

- Chat never reaches the action classifier or mutation planner.
- Build executes directly and returns a verified build result.
- Update executes directly and returns a verified update result.
- Publish never runs through the design planner and never posts a final Luna message before the publish executor returns.
- Generic Yes/Proceed/Continue text cannot resume normal work.

