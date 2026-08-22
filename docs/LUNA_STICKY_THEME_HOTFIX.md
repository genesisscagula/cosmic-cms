# Luna Sticky Theme Hotfix

- Fresh website: API 1 may choose the initial theme.
- Once a website has content or Luna has selected a theme, the theme becomes sticky.
- New prompts, page rebuilds, different industries, redesigns, and “make it premium” preserve the existing theme by default.
- Template/Spark composition may still change.
- Theme replacement requires an explicit user request: change/switch theme, color scheme/palette, a named theme, or rebrand.
- Server-side guard drops accidental theme operations when there is no explicit theme-change intent.
- `theme_settings.luna_theme_locked=true` records the established Luna visual identity.
