# Patch 2 — Builder Theme Entitlement Refactor

## Goal
Make the Theme Modal consume the server-authoritative theme entitlement rather than recalculating plan limits in JavaScript.

## Shared Inertia contract
`auth.themeAccess` now contains:
- `keys`: exact allowed theme keys, or `null` for unlimited
- `count`: exact included theme count, or `null` for unlimited
- `unlimited`: boolean
- `next_plan`: Growth, Pro, or null

## Builder behavior
`ThemeSelector` and `ThemeModal` consume only `auth.themeAccess` for card locks and the included-theme banner.

The existing `effectivePlanKey` remains available for other Builder behavior but is no longer used by the Theme Modal to derive entitlements.

## Expected access
- Starter / Agency Starter: 5
- Growth / Agency Growth: 10
- Pro / Agency Pro: unlimited

No migration required.
