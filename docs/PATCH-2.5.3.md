# Patch 2.5.3 — Builder UI Enhancement

## Builder toolbar

- Reduced toolbar height and visual noise.
- Kept page identity on the left and primary actions on the right.
- Shortened action labels to Generate, Save, and Publish.
- Kept status, block count, credits, and theme compact.
- Improved responsive behavior while preserving access to every action.

## Publish reliability

Publishing now always saves the exact current Builder state first, rather than relying only on React's dirty-state timing. This covers:

- AI-generated pages and sections
- manually added, removed, duplicated, reordered, or changed blocks
- theme changes
- header and footer edits

If saving fails, publishing stops and shows an error. Successful publishing also shows a confirmation notification.

## Installation

No migration is required.

```bash
php artisan optimize:clear
npm run dev
```
