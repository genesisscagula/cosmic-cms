# Cosmic Background + Overlay Core — Batch 2

Registered Sparks preserved: 314

Centralized contract:
- surface/light background
- primary/theme-family background
- primary theme-family gradient
- light image/video overlay
- primary image/video overlay
- stronger cinematic overlay
- gradient from/via/to/glow/angle
- overlay strength settings
- per-section local overrides

Builder now derives universal image/video overlays from the active color family,
including My Brand Theme, instead of using a fixed violet/default gradient.
Light/surface states retain a white overlay. Primary states use the selected
theme family's gradient palette. Cinematic/video/fullscreen sections can use
the stronger cinematic treatment.

Hierarchy prepared:
Global `theme_settings.background_style`
-> active theme-family palette
-> semantic background/overlay mode
-> optional block `luna_background_overrides`

Export/Live:
PagePublisher and PageController pass `background_style` into CmsHtmlCompiler.
The compiler emits the same global and local background variables.

Batch 3 should migrate existing Spark-specific background/gradient CSS onto these
shared semantic roles and wire Luna global-vs-local background requests.
