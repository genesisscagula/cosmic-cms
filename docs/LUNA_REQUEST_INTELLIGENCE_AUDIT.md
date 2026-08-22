# Luna Request Intelligence Audit

## First-build theme
- Midnight is no longer in the generic first-build fallback pool.
- Midnight is still allowed when the user explicitly asks for a midnight/night theme.
- First-build selection now uses industry/design intent pools before a neutral diversified fallback.
- Similar prompts remain deterministic, but unspecified prompts diversify across non-midnight families.

## Request routing supported

### Content / media
- Edit clicked heading, text, label, button copy/link, or image.
- Update image/media without changing the section layout.
- Repeater/list CRUD: add, remove, rename, update, expand, reduce, and reorder cards/services/testimonials/FAQs/team/pricing/features/logos/gallery/process items.

### Structural section requests
- “Show another layout”, “redesign this section”, “make this a slider”, “turn this into a video hero”, etc. route to registered Spark replacement.
- Insert a relevant section above/below.
- Move section up/down/top/bottom.
- Delete section.

### Global vs local design tokens
- Typography: H1/H2/H3/H4/body/eyebrow/button size, line-height, weight, tracking.
- Section layout: vertical/horizontal padding, top/bottom padding, container width, content gap, min-height.
- Background/overlay: darker/lighter overlays, gradient angle, gradient colors.
- Explicit global wording updates site tokens; “this section” wording writes local overrides.
- Deterministic token-only requests use 0 credits.

### Header / navigation / logo
- Header overlay on/off and related shell requests.
- Logo request flow can ask for the company/logo title before generation.
- Page + navigation creation checks existing pages/menu items and adds missing entries.
- Header/footer changes remain global shell state, not body Spark edits.

### Theme behavior
- Explicit theme/color-family requests may change theme.
- Vague “more premium/modern/polished” requests preserve the current theme and art-direct within it.
- Midnight must never be emitted as a generic fallback by Luna planners.

### Multi-step requests
- Planner is instructed to execute compatible multi-action requests in order instead of stopping after the first change.

## Guardrails
- Raw CSS/Tailwind/DOM implementation instructions are rejected when they would break Builder → Export parity.
- Normal visual intent is still accepted and translated into the centralized design system.
- URLs used as actual image/video/media content are not blocked by the low-level styling guardrail.

## Remaining intentional boundary
Luna may redesign using registered/export-safe Sparks and centralized tokens. It should not generate arbitrary fragile DOM/CSS just to satisfy a visual request.
