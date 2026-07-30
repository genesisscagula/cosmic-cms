# Patch 2.5.4 — Builder V2 UI Enhancement

## Changes

- Rebuilt the Builder toolbar into three clear zones:
  - navigation and page context
  - status, block count, and Cosmic Credits
  - theme and primary actions
- Added a dedicated status tray so metadata no longer competes with action buttons.
- Kept Generate as the only violet action and Publish as the only green action.
- Added responsive compact metadata and credit row for smaller screens.
- Preserved the publish reliability fix: Publish saves the exact current Builder state before publishing.
- Improved error visibility without showing a permanent unsaved-warning bar on desktop.

## No database changes

This patch is UI-only and requires no migrations.
