# Website Intelligence — Batch 3: Content Intelligence

## Goal
Luna generates concise, specific, industry-aware website copy without inventing business facts.

## Content contract
ContentGenerator now receives a deterministic content-intelligence packet derived from the same page-intent/industry composition context used by the page planner.

Rules include:
- specific language over generic AI marketing filler;
- supplied business facts are preserved;
- unknown facts remain unknown;
- no invented awards, certifications, clients, reviews, ratings, years, locations, contact details, prices, metrics, guarantees, licences or performance claims;
- fake testimonial quotes are prohibited;
- headings/promises should not repeat across sections;
- copy density follows field/layout capacity;
- CTA language follows page intent;
- unsupplied URLs remain `#`.

## Industry focus
Initial profiles cover Automotive, Hospitality, Restaurant, Construction, Technology, Health and Professional Services.

## Diagnostics
Generated schema-valid blocks receive a content audit for repeated headings and known generic filler phrases. Diagnostics are returned alongside existing schema diagnostics.

Schema validation remains the hard structural boundary; content diagnostics do not discard otherwise valid blocks.

## Relationship to earlier batches
Batch 1: what sections the page needs.
Batch 2: what visual language the page inherits.
Batch 3: what the page should say and what it must never fabricate.
