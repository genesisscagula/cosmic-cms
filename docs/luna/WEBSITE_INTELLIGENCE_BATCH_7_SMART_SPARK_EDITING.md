# Website Intelligence — Batch 7: Smart Spark Editing

## Goal
Luna edits existing Sparks safely before considering replacement.

## Editing contract
- schema-preserving by default;
- unknown edit keys are rejected;
- protected runtime/dynamic keys are preserved;
- element edits use resolved matched paths;
- repeater/list CRUD preserves sibling schema and layout;
- structural replacement remains reserved for capability/layout changes the current Spark cannot represent.

## Repeater intelligence
Known collection shapes include items, cards, services, features, steps, slides, images, testimonials, FAQs, team, plans, logos, and gallery.

### Add
`add another card/service/item`
- detects the current collection;
- clones the nearest selected item when available, otherwise the last valid item;
- strips identity fields;
- preserves the existing item shape.

### Remove
`remove this card/item`
- uses the selected item index when available;
- otherwise resolves a unique title/heading/label/name/question from the prompt;
- refuses unresolved removal instead of deleting an arbitrary sibling.

## Element safety
When element context supplies `matched_paths`, Luna changes only those resolved paths. A heading click cannot silently mutate an unrelated image, CTA, or sibling card.

## Builder + Trial
Both orchestration paths receive the SMART SPARK EDIT CONTRACT and both apply server-side sanitization.

## Replacement boundary
Copy-only, image-only, repeater CRUD, and bounded visual-token edits should stay on the current Spark. Replace remains valid for explicit structural changes such as converting a static gallery into a slider when the current Spark lacks that capability.
