# Website Intelligence — Batch 10: Site Memory / Design DNA

## Goal
Luna remembers durable website-level design decisions across pages and future turns without confusing local edits with global design changes.

## Memory layers
- Brand: theme family, custom brand palette, heading/accent direction
- Typography: display/body fonts and typography tokens
- Components: buttons, cards, images, forms
- Layout: section spacing, container behavior, layout rhythm
- Media: image direction, crop/object-fit treatment
- Tone: page style, content/design direction

## Rules
- Site DNA is website-level memory, not a transcript.
- Explicit new global instructions override remembered DNA.
- Local element/section edits do not rewrite Site DNA.
- Verified global token/theme/rebrand changes may update DNA.
- Failed changes never update DNA.
- Partial execution persists only verified global parts.
- Sibling pages inherit the same visual language while using different compatible Spark compositions.
- Unknown values stay absent rather than being invented.

## Planner integration
Builder and Trial now provide SITE DESIGN DNA MEMORY to Luna.
Existing page-generation Design Continuity and Site DNA work together:
- continuity reads the current website state;
- Site DNA preserves durable decisions across turns/pages.

## Execution integration
Site DNA is updated after execution verification, never merely because the LLM proposed a change.

This prevents:
`Luna planned a new brand -> operation failed -> future pages still inherit failed brand`

and preserves:
`User changed all card radii -> verified global token -> future pages inherit new card treatment`.

## Local vs global
Example:
`Make this card square` -> local edit, Site DNA unchanged.
`Make all cards square` -> verified global component token, Site DNA updated.
