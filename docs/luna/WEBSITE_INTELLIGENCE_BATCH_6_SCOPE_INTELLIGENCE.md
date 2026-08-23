# Website Intelligence — Batch 6: Scope Intelligence

## Goal
Luna resolves how far a request should reach before planning or executing it.

## Scope levels
- `element` — one clicked heading/text/button/image/link/logo;
- `item` — one repeater/card/service/testimonial item;
- `section` — one Spark;
- `page` — current page;
- `site` — website-level state where supported;
- `global_token` — centralized design primitive instead of per-Spark mutation.

## Core rules
- Explicit wording outranks the UI selection.
- `this`, `selected`, `only this`, and `just this` never widen automatically.
- `all/every + semantic primitive` prefers a global design token when available.
- Whole page and whole website are different scopes.
- Ambiguous prompts inherit current UI/element context.
- Item/card requests preserve sibling repeater items unless explicitly asked to change all items.

## Centralized token routing
Examples:
- `make all cards less rounded` -> global `card_radius` token;
- `make all buttons more rounded` -> global `button_radius` token;
- `make all images less rounded` -> global `image_radius` token;
- `make all headings smaller` -> existing global typography tokens;
- `increase spacing on all sections` -> existing global section-layout tokens.

This avoids looping through and writing local overrides to every Spark when the same result belongs in the global design system.

## Planner contract
The resolved SCOPE CONTRACT is now sent to the Luna planner in Builder and Trial chat.
The planner is explicitly forbidden from mutating outside the resolved scope.

## Pending plans
For the destructive-delete safety flow, scope is recomputed after the exact token is consumed. Normal actions do not use a stored pending plan.
