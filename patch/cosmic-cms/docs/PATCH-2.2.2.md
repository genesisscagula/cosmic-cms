# Patch 2.2.2 — Workspace Pivot and Plan Selection Hotfix

## Fixes

- Explicitly configured the `workspace_user` pivot table in both `User` and `Workspace` relationships.
- Fixed registration failure caused by Laravel inferring the non-existent `user_workspace` table.
- Disabled claim/purchase continuation buttons until a plan is selected.
- Removed `#plans` anchor behavior that could unexpectedly scroll the user into the draft/landing preview below.

## Commands

```bash
php artisan optimize:clear
npm run build
```

No new migration is required for this hotfix. The existing `workspace_user` table is used.
