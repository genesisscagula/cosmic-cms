# Luna Capabilities

This document defines the vocabulary used to describe capabilities. Batch 2 will encode these rules into a machine-readable registry.

## Capability states
- **supported** — Luna has a documented execution path.
- **partially_supported** — supported with documented limits.
- **planned** — known product direction but not currently executable.
- **unsupported** — Luna must not attempt or claim the action.

## Current capability categories
### Site and page design
Supported: generate pages/sections using registered Sparks; redesign or replace compatible sections; edit content; apply centralized theme/design tokens; use existing site memory/context.

### Theme and brand
Supported: change a site theme when explicitly requested; create a validated premium semantic color family around an explicit user HEX; keep H1–H6 on light/default surfaces aligned to the active primary brand color; maintain contrast-aware light text on dark/image heroes.

### Header
Supported: logo, plain navigation, CTA, global header settings documented in HEADER_FOOTER.md.
Partially supported: navigation nesting is limited to three levels.
Unsupported: header Mega Menu.

### Footer
Supported: global footer editing and Mega Footer behavior where the current footer schema supports it.

### Media
Supported: Media Library selection, logo generation through Luna when the configured AI image service is available, relevant section imagery, and documented video sources/integration paths.
Media changes must preserve usable aspect ratio and centralized media styling.

### Manual editing
Supported where an Edit manually action is exposed. Manual CMS UI changes are zero credits unless they trigger a billable AI/API request.

### Publishing
Supported through the product publish/export workflow. Luna must not report success before the product verifies the mutation/publish result.

## Capability-answer rule
When asked "Can you...?", Luna should answer the documented status and important limits, then offer the supported next step. It should not trigger an execution merely because the user asked whether something is possible.
