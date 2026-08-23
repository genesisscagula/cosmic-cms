# Website Intelligence — Batch 1: Page Composition Engine

## Goal
Luna chooses page structure by purpose before visual effect.

## Composition contract
A local composition profile resolves:
- page intent;
- industry;
- required section roles;
- optional roles;
- preferred section count;
- industry priorities/content;
- layouts/content to avoid;
- page rhythm rules.

## Page intents
Home, About, Services, Contact, Pricing, Work/Portfolio, Team.

## Initial industry profiles
Automotive, Hospitality, Restaurant, Professional Services, Construction, Technology, Health.

## Planner behavior
Template candidates are now scored not only for keywords/quality/style but for page-purpose role coverage and composition rhythm.

Composition audit checks:
- exactly one hero;
- required page-purpose roles;
- no more than two dense card/grid sections consecutively;
- useful conversion/contact ending.

A planner-selected template that fails composition audit can fall back to the strongest shortlisted registered template that passes the contract.

## Important
This does not invent new Sparks. Luna still chooses registered template/Spark compositions and ContentGenerator remains schema-bound.

The locked design plan passed to content generation now includes page intent, composition industry, role sequence, and composition pass state.

## Example direction
Automotive Home:
hero -> services/value -> trust/proof -> process/media -> proof -> booking/contact CTA

Hotel Home:
hero -> experience/story -> rooms/amenities -> gallery -> proof -> booking CTA

The exact Spark types can vary; the purpose/rhythm contract remains.
