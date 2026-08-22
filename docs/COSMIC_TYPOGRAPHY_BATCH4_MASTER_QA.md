# Cosmic Typography — Batch 4 Master QA

Registered Sparks: 314

Final editing contract:
- Manual edit is content-only: heading/text/label/button copy, button URL, and image selection/upload.
- Structural manual controls for section backgrounds, video backgrounds, section deletion,
  insert-above/below suggestions, and repeatable add/remove are hidden from the manual panel.
- Layout, spacing, backgrounds, adding/removing structural items, and full redesign remain Luna actions.
- Luna's section prompt explicitly offers redesign while explaining that Manual Edit is basic content/media only.

Typography hierarchy:
1. Website global typography (`theme_settings.typography`)
2. Semantic Spark role (H1/H2/H3/H4/Lead/Body/Eyebrow/Small/Button)
3. Optional selected-section `luna_typography_overrides`

Parity:
- Builder uses the same global/local CSS variables.
- Save Draft persists website typography and block-local overrides.
- Publish recompiles with saved typography context.
- Export/Live compiler emits both global and local variables.
- Existing and future registered Sparks inherit the common render-boundary contract.

Deterministic typography commands remain 0 credits because they do not call an AI/API.
