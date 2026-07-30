# Patch 2.5.6.3 — Persistent Credit Balance Hotfix

- Added authenticated `credits.balance` JSON endpoint.
- The global credit provider now fetches the authoritative database balance on first load and after every Inertia navigation.
- Removed stale page-level prop synchronization that could overwrite the wallet with `0`.
- Credit balance now persists correctly across browser refreshes and Builder navigation without running migrations.
