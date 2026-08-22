# Cosmic Visual System — Batch 4 Master QA

Registered Sparks audited: 314

Centralized systems now active:
- Typography
- Section Wrapper / Responsive Rhythm
- Background / Theme Gradient / Media Overlay

Section layout hierarchy:
1. Global `theme_settings.section_layout`
2. Common Spark wrapper/container contract
3. Optional block `luna_section_overrides`

Background hierarchy:
1. Global `theme_settings.background_style`
2. Active theme family palette
3. Light / Primary / Cinematic semantic mode
4. Optional block `luna_background_overrides`

Master behavior:
- Standard and normal Hero Sparks receive centralized vertical + horizontal spacing.
- Cinematic/fullscreen Sparks preserve immersive vertical composition and inherit central horizontal spacing.
- Common top-level max-width containers inherit the centralized container width.
- Desktop/tablet/mobile padding uses the same variables in Builder and static export.
- Existing legacy Spark gradient family classes are compatibility aliases; active theme family owns colors.
- Surface media uses the light overlay; primary media uses the theme-family overlay; cinematic media uses stronger theme-family treatment.
- Luna distinguishes global vs selected-section spacing/background requests.
- Deterministic token changes are 0 credits.
- Future Sparks can use `CosmicSection`, `CosmicSectionStack`, `CosmicTypography`, and `CosmicBackground` helpers directly.

Builder / Preview / Publish / Export Live all carry:
- typography
- section_layout
- background_style
- local typography/section/background overrides
