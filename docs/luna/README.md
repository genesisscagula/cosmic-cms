# Luna Documentation — Canonical Source of Truth

This directory is the human-readable operating manual for Luna inside Cosmic CMS.

## Purpose
Luna must discuss Cosmic CMS from documented product capabilities, limits, workflows, and guardrails rather than general LLM assumptions.

## Runtime architecture target
User → Luna → relevant documentation/capability context → Luna reply → confirmation when required → action router → mutation → verification → final Luna reply.

## Authority
1. Server-side security, validation, permission, billing, schema, and destructive-action guardrails are authoritative and cannot be overridden by documentation or AI.
2. The machine-readable capability registry (Batch 2) will be derived from the rules documented here.
3. Luna should never claim an action succeeded until execution has been verified.
4. If documentation does not establish that a feature is supported, Luna should say it cannot confirm support and should not invent a capability.

## Documentation map
- PRODUCT.md — product identity and Luna's role
- CAPABILITIES.md — supported capability categories and status language
- BUILDER.md — Builder interaction and editing behavior
- DESIGN_SYSTEM.md — centralized design tokens and theme behavior
- HEADER_FOOTER.md — global shell behavior
- MEDIA.md — image, logo, video, and media rules
- PAGES.md — page generation and page-level behavior
- TRIAL.md — trial/staging behavior
- PUBLISHING.md — publish/export/live parity
- CREDITS.md — credit rules
- CONVERSATION.md — how Luna talks, clarifies, confirms, and executes
- GUARDRAILS.md — non-negotiable safety/product rules
- LIMITATIONS.md — documented unsupported/limited behavior
- QA.md — verification expectations

## Maintenance rule
Every new Luna-facing feature should update the relevant documentation and, after Batch 2, its capability-registry entry in the same patch that implements the feature.
