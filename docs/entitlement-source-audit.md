# Entitlement Source Audit — Patch 1

This patch is intentionally diagnostic. It does not change plan access.

## Current read path

The Builder reads `auth.effectivePlanKey` from Inertia. `HandleInertiaRequests` populates that value using `User::effectivePlanKey()`. The Theme Modal receives that effective plan and its 5 / 10 / all theme mapping is already correct.

For non-platform-owner accounts, however, `User::effectivePlanKey()` currently resolves directly from `users.plan_key`. Therefore, if a successful upgrade/payment exists but the user row was not synchronized, every Builder capability that trusts the effective plan can still see the stale Starter plan.

## Theme entitlement mapping verified

- Starter / Agency Starter: 5 themes
- Growth / Agency Growth: 10 themes
- Pro / Agency Pro: all themes

Both `ThemePlanAccessService` and `resources/js/Pages/Websites/Theme/ThemeAccess.js` use the same mapping.

## Raw plan reads found

Most capability services already use `effectivePlanKey()`. Remaining direct `users.plan_key` reads are concentrated in subscription/workspace write flows, where they compare or synchronize billing state. These should be reviewed in Patch 2 when the canonical resolver is introduced, rather than changed blindly in this audit patch.

## Diagnostic command

Audit one user:

```bash
php artisan cosmic:audit-entitlements --email=user@example.com
```

Show only drift across all users:

```bash
php artisan cosmic:audit-entitlements --only-mismatches
```

Machine-readable output:

```bash
php artisan cosmic:audit-entitlements --email=user@example.com --json
```

The command compares:

1. `users.plan_key`
2. `User::effectivePlanKey()`
3. latest fulfilled plan payment order
4. normalized plan status
5. calculated theme allowance

No database rows are modified.
