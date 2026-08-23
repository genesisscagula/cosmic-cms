# Website Intelligence — Batch 11: Design Critic

## Goal
Give Luna a deterministic preflight design-review layer between planning and content generation.

Flow:
`user request -> design planner -> Design Critic -> optional one-pass safe revision -> content composer -> Visual QA`

## What the critic checks
- composition and section sequencing
- repeated layout/section roles
- hero/opening hierarchy
- closing CTA/contact journey
- Site Design DNA/theme continuity
- media-direction completeness
- known responsive-risk concentration
- page length and content hierarchy

## Safe revision boundary
The critic may make one deterministic revision pass when the plan is in the `revise` range.

It may:
- reorder already selected registered Sparks when a clear structural issue exists

It may not:
- invent a Spark
- bypass the Spark registry
- silently rebrand the website
- overwrite explicit user requirements
- perform repeated self-revision loops

## Scoring
- 82+ with no high/critical finding: accept
- 68–81: revise
- below 68 or serious unresolved problems: reject/flag

The critic is deterministic and costs 0 AI credits.

## Relationship to other intelligence
- Site Design DNA protects durable website identity.
- Design Critic reviews the plan before generation.
- Execution Verification confirms requested edits actually happened.
- Visual QA diagnoses generated output after generation.
- Builder/Live Parity remains a separate runtime concern.

The critic does not claim pixel-perfect quality.
