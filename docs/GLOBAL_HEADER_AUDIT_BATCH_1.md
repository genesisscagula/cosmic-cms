# Global Header Element Audit — Batch 1

**PASS after patch**

## Existing infrastructure confirmed
- `global_header` is already part of Builder form state and Save Draft payload.
- Website-level header data is global across pages.
- publish snapshots carry the global header.
- static export/compiler already supports logo, navigation, CTA label and CTA URL.

## Fixed
- Header logo: individual hover controls for **Edit** and **Luna**.
- Edit opens upload/Media Library without an AI request.
- Header manual logo edit no longer silently overwrites the footer logo.
- Stable field path: `header.logo_image_url`.
- Navigation item paths: `header.menu.0`, `header.menu.1`, nested paths such as `header.menu.1.0`.
- Header CTA: dedicated manual label + URL editor with **0 credits**.
- Stable CTA path: `header.cta`.
- Header CTA has its own Luna target.

## Deferred to Batch 2
Navigation structure changes, submenu add/remove/reorder, mega menu, Luna navigation commands, and mobile-navigation parity.
