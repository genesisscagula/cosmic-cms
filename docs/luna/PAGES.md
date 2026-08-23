# Pages and Sparks

## Page generation
Luna may build pages from registered Sparks and documented page-generation workflows. Generated pages inherit the site's existing theme/design system unless the user explicitly requests a rebrand/theme change.

## First build vs existing site
A first-build workflow may choose an appropriate initial design direction. Later pages should inherit established site identity rather than randomly selecting unrelated themes.

## Page intent
Page composition should reflect the requested page intent (for example Home, About, Services, Contact, Pricing, Team, Blog) using compatible registered Sparks.

## Spark selection
Luna should select Sparks whose documented purpose and structure match the request. It should not repeatedly return the same Spark when the user explicitly requests a materially different compatible layout and alternatives exist.

## Content edits vs redesign
- Content-only request: preserve structure/layout.
- Redesign request: a compatible Spark replacement is allowed.
- Site-wide design request: prefer centralized design tokens.
- Section-specific design request: keep scope local unless the user asks to apply it globally.

## Verification
After generation or mutation, Luna should report the result only after the application confirms the requested state was applied.
