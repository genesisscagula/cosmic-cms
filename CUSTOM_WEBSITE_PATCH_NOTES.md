# Custom Website Screenshot Fidelity Hotfix

- Removed required Global Design wizard for Custom Website creation.
- Screenshot is now Luna's single visual source of truth.
- Strengthened visual reconstruction prompt: dimensions, hierarchy, colors, spacing, forms, review badges, compact hero composition.
- Added screenshot-derived `visual_style`, `review`, and `form` structures.
- Custom Spark renderer now uses screenshot-derived values rather than website globals.
- Custom Spark controls reset to detected screenshot values, not Global Design.
- Generation remains 20 credits.

Note: photography embedded inside a screenshot cannot be recovered as the original standalone image asset. Luna intentionally leaves `image_url` empty rather than inventing an external image URL; the image can then be replaced through the editable image control/workflow.

## Screenshot Visual QA pass
- Custom screenshot generation now runs one automatic visual QA correction pass inside the same 20-credit action.
- Builder captures only the generated Custom Spark (not website header/footer) after first render.
- Luna receives the original reference screenshot, the current render screenshot, and the current block JSON.
- QA returns a 0–100 match score, prioritized visual issues, and a corrected block while preserving the custom spark key.
- Missing original photography is reported as a media/source-asset limitation; Luna is forbidden from inventing external image URLs.
- Added `html2canvas` frontend dependency for in-browser QA capture.

## 2026-08-20 — Cosmic AI per-Spark chat patch
- Custom screenshot modal now closes after a successful first generation even when the optional visual QA pass cannot complete.
- Visible Custom Website AI copy renamed from Luna to Cosmic AI while keeping legacy internal class/type names for compatibility.
- Screenshot generation accepts optional design instructions and an optional clean source/background image.
- Reference screenshots/source assets are stored per website under public custom-sites storage for continued Spark context.
- Custom Spark sections expose a per-section floating Cosmic AI button.
- Cosmic AI chat is a floating popup (not a sidebar), scoped to one selected Spark only.
- Per-Spark conversation history persists in custom_sparks.metadata.
- Chat supports a new source-image attachment and can either reply only or apply a focused block update.
- QA capture target is now explicitly marked with data-custom-spark-key.
