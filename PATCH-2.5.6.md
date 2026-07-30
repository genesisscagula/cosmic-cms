# Cosmic CMS Patch 2.5.6

## Credit economy ×10

All existing action, block, fallback, and theme costs were multiplied by ten in both the PHP source of truth and the frontend fallback registry.

Examples:

- Add page: 30
- Add menu item: 10
- Generate page: 50
- Generate website: 100
- AI rewrite: 10
- Blocks: 10–50
- Themes: 10–50

## Buy Credits foundation

The Credits page now includes packages:

- 50 credits — $5 USD
- 120 credits — $10 USD
- 300 credits — $20 USD
- 900 credits — $50 USD
- 2000 credits — $99 USD

No payment gateway is connected yet. In local/testing environments, purchases can be simulated and are recorded through CreditService transaction history.

A local-only Developer Test Top-up button adds 1000 credits instantly.

## No migration required

## Hotfix 2.5.6.1

- Fixed the blank screen caused by calling Inertia `usePage()` outside the Inertia `<App>` context.
- The credit provider now receives its initial balance from `props.initialPage`.
- Added an Inertia success listener so the shared balance stays synchronized after navigation and refreshes.
