# Cosmic Background + Overlay — Batch 3

Registered Sparks migrated through the common render boundary: 314

What changed:
- Existing `cosmic-theme-gradient--*` family modifiers are compatibility aliases only.
  The active theme family now owns gradient from/via/to/angle.
- Primary root sections inherit the centralized primary surface color.
- Light root sections inherit the centralized light/surface color.
- Universal image/video overlays use the centralized light/primary/cinematic contract.
- Builder recomputes universal overlays when either the theme family or global
  `background_style` changes.
- Export resolves the same active theme family through CmsHtmlCompiler.

Luna deterministic controls:
- "Make all overlays darker" -> global `theme_settings.background_style`
- "Make this overlay lighter" -> selected block `luna_background_overrides`
- "Set all gradient angle to 90deg" -> global
- "Set this gradient angle to 45deg" -> local
- Gradient color pairs/triples using hex values are supported.

These token-only background/overlay edits use 0 credits because no AI/API request is required.

Hierarchy:
Global Background Style -> Active Theme Family -> Spark semantic mode -> Optional local override.
