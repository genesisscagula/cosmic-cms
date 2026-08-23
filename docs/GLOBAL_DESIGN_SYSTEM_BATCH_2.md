# Global Design System — Batch 2

Centralized layout and responsive token foundation.

## Containers
- narrow
- content
- default
- wide
- full

## Spacing
- desktop/tablet/mobile section Y and X padding
- stack spacing
- grid gap
- card padding
- reusable inline/block spacing primitives

## Responsive typography
- H1/H2/H3/body/lead responsive defaults
- site settings can supply per-role tablet/mobile size tokens
- desktop role values remain the source design contract

## Compatibility
Existing `--cosmic-section-*` variables are now aliases of the new semantic layout tokens, so older Sparks continue working while later migration batches adopt the new roles directly.

No mass visual migration was performed in this batch. Existing Spark layout intent remains intact.
