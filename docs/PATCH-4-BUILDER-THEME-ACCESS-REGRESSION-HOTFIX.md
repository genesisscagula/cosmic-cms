# Patch 4 — Builder Theme Access Regression Hotfix

## Problem
The Builder Theme Modal could display `0 themes included with your plan` and lock every card even when the entitlement audit reported a healthy Growth account with 10 themes.

## Root cause
The token-aware Builder route relied on shared Inertia `auth.themeAccess`. When that shared payload was unavailable in the Builder request, the frontend correctly treated the missing keys as an empty set, resulting in zero allowed themes.

## Fix
- `PageController::builder()` now sends an explicit server-resolved `themeAccess` prop for authenticated non-trial Builder sessions.
- Builder uses this route-specific payload first and shared auth only as a fallback.
- The current active theme is never shown as locked, matching the existing backend grandfathering rule.
- Starter remains 5 themes, Growth 10 themes, and Pro/Agency Pro unlimited through `ThemePlanAccessService`.
