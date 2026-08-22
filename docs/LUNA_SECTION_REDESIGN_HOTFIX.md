# Luna Section Redesign Hotfix

Targeted behavior:
- Generic selected-section requests such as "redesign this section", "another layout",
  "make this section better", "make this more premium/modern/polished", "refresh",
  "rework", and "restyle" now force a real structural alternative when the AI planner
  does not already provide one.
- Candidate selection prefers a different registered Spark in the same semantic category,
  then ranks shared intent, industry fit, position fit, and requested style.
- The current Spark key is explicitly excluded.
- Generated replacement preserves useful copy, CTA intent, semantic section purpose,
  and the current site theme.
- The existing before/after fingerprint verification remains in place, so Luna still
  cannot falsely claim a change when no state mutation occurred.
- Applied to both trial/public Builder chat and authenticated Builder chat.
