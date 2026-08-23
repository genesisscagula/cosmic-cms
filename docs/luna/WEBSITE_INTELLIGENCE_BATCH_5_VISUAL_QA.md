# Website Intelligence — Batch 5: Visual QA Engine

## Goal
After a major page build, Luna performs a bounded structural/design QA pass before reporting the result.

## QA categories
- page composition;
- content quality;
- responsive risk;
- design-token consistency;
- visual accessibility structure;
- media completeness;
- conversion/action completeness.

## Behavior
This batch is intentionally `diagnose_then_recommend`.
QA does NOT automatically mutate the page.

Luna receives:
- score (0–100);
- grade;
- pass/fail;
- categorized findings with severity;
- concrete recommendations;
- explicit limitations.

## Important boundaries
Structural QA can detect issues such as:
- missing composition roles;
- repeated/generic copy;
- responsive-risk Spark types;
- missing responsive token coverage;
- empty alt/action labels;
- empty media fields.

Structural QA cannot truthfully claim:
- pixel-perfect alignment;
- exact foreground/background contrast;
- Builder/Live rendering parity.

Those require rendered-output inspection and parity testing. Builder/Live parity remains Batch 8.

## Reporting
Natural replies distinguish `noticed` from `fixed`.
A successful build may therefore say the page was built while noting meaningful QA risks; it must not imply those risks were repaired automatically.

## Future
Batch 12 may add a controlled polish pass, using this QA output as input, with bounded mutation and verification.
