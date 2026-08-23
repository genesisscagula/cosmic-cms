# Global Design System — Batch 1

Foundation added without mass-overriding existing Sparks yet.

## Centralized token families
- Brand: primary, secondary, accent, neutral, success, warning, error
- Semantic colors: page, surface, surface-alt, heading, body, muted, border, on-primary, on-dark, on-light
- Heading colors: H1–H6 independently addressable, inheriting the global heading color by default
- Eyebrow semantic color
- Radius scale + semantic radius: button, card, image, input, modal, section
- Border width/style
- Shadow scale + semantic card/modal shadows
- Existing H1–H6/body typography core retained and extended with color settings

## Integration
- Builder render shells expose the active theme palette as semantic CSS variables.
- Custom brand themes expose their palette to the same semantic variables.
- CosmicTypography global settings can now carry per-role color values.
- Semantic data attributes are available for component migration.

## Safety
Batch 1 intentionally does not globally override every raw Tailwind rounded/color class. Existing Sparks remain visually backward-compatible. Batch 2/4 migration will opt components into these tokens while preserving intentional dark/overlay/special-layout exceptions.
