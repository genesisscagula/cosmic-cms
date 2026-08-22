# Cosmic Section Wrapper Core — Batch 1

Centralized section layout contract for current and future Sparks.

Global tokens:
- vertical padding: desktop / tablet / mobile
- horizontal padding: desktop / tablet / mobile
- default / wide / narrow container widths
- section content gap: desktop / tablet / mobile
- section min-height

Reusable React primitives:
- `CosmicSection`
- `CosmicSectionStack`
- `cosmicSectionVars`
- `cosmicLocalSectionVars`

Hierarchy prepared:
Global `theme_settings.section_layout`
-> Spark wrapper/container role
-> optional block `luna_section_overrides`

Existing legacy Luna section design values are bridged into the new local variables
without removing the old values yet. Batch 2 will migrate the full registered Spark
library to consume the common wrapper contract.

Export/Live receives the same global section-layout context through PagePublisher
and CmsHtmlCompiler.
