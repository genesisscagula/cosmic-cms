# Cosmic Typography — Batch 3

Luna now distinguishes global typography requests from section-specific requests.

Examples:
- "Increase all H2 headings" -> website `theme_settings.typography.h2_size`
- "Make every heading larger" -> H1-H4 global tokens
- "Make this section heading smaller" -> selected block `luna_typography_overrides`
- "Set H2 to 5rem" -> explicit global/local value depending on scope wording

Typography commands handled deterministically do not use AI/API credits.

Builder:
- website typography is converted into global `--cosmic-type-*` variables
- section overrides are converted into `--cosmic-local-*` variables

Export / Live:
- `PagePublisher` passes saved website typography to `CmsHtmlCompiler`
- compiler emits global typography settings
- compiler emits section-local override variables
- saved drafts therefore preserve the same global/local hierarchy on live output

Hierarchy:
Global typography -> semantic Spark role -> optional local section override.
