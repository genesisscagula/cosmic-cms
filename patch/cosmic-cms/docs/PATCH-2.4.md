# Patch 2.4 — Cosmic Credits Foundation

## Included

- `users.credits` wallet balance with a 30-credit migration default for existing accounts.
- Immutable `credit_transactions` ledger.
- `CreditService` with atomic `consume`, `grant`, `refund`, `balance`, and `canAfford` operations.
- Row locking to prevent double spending during concurrent requests.
- `InsufficientCreditsException` for future AI and marketplace integrations.
- Authenticated `/credits` wallet and transaction-history page.
- Credit balance shared through Inertia and displayed in agency and client navigation.
- New accounts receive an opening ledger entry based on their selected plan:
  - Starter: 30
  - Growth: 70
  - Pro: 200
  - Normal registration fallback: 30

## Not included yet

Patch 2.4 does not charge existing AI, page, menu, block, or theme actions. Those integrations belong to Patch 2.5 after the wallet foundation is verified.

## Apply

```bash
php artisan migrate
php artisan optimize:clear
npm run build
```
