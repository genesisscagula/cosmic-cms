# Global Design System — Batch 5

## Status
PASS — static integration QA

## Luna design intelligence
Luna can now recognize site-wide design requests for headings, buttons, cards, images, forms, rounding and brand colors. Component requests map to centralized semantic tokens rather than patching every Spark.

Examples:
- Make all cards less rounded
- Make every button pill shaped
- Make the website more square
- Change my brand color to #2563EB

## Brand color family
An explicit brand hex produces a coherent semantic family:
primary, secondary, accent, background, surface, surface-muted, heading, body, muted, border, button text and hover. Primary-button foreground is contrast-aware.

The Builder installs the result as `My Brand`, marks theme settings dirty, and the existing Save Draft flow persists `theme_settings`.

## Scope
- Site/page-wide phrases use global design intent.
- Selected-section requests remain local via `luna_component_overrides`.
- Existing Luna design overrides remain supported.
- Registered Sparks consume the centralized render-shell token contract from Batch 4.

## Runtime QA still required
Static code checks cannot prove browser/server persistence or exported/live visual output. Test:
1. site-wide brand color + save + refresh
2. all-card radius
3. selected-section card radius only
4. pill buttons
5. heading brand color on light and dark/immersive sections
6. publish/export and hard refresh
