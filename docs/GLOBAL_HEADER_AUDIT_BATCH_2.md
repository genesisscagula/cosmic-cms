# Global Header Audit — Batch 2

**PASS after patch**

## Navigation manual editing
- Add root menu item
- Add nested submenu item
- Edit label + URL
- Remove item
- Reorder siblings
- Manual changes: 0 credits
- Stable global target: `header.menu`
- Stable per-item paths: `header.menu.<path>`

## Mega menu
`global_header.mega_menu_enabled` is the global switch. Top-level items with children render as a two-column mega dropdown when enabled.

## Mobile parity
The Builder now has a mobile Menu control using the same recursive navigation tree. Export already has a responsive mobile panel and recursive submenu renderer.

## Luna
Navigation has a dedicated Luna target. Both planning paths receive CURRENT HEADER JSON. Luna must return the complete resulting menu for structural nav requests, and the server sanitizes depth/count/labels/URLs before applying it.

## Export/publish
PagePublisher recursively rewrites nested menu URLs. Static compilation supports mega menus and both registered header types.
