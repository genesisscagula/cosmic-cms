# Cosmic CMS v2.6.0 — Sparks Marketplace Foundation

## Included
- Marketplace and My Sparks views
- Search, categories, featured cards, responsive preview modal
- Credit-based unlock with idempotent ownership checks
- Real-time shared wallet balance update after unlock
- Transaction history integration through CreditService
- 9 starter Spark products seeded by migration
- Sparks navigation in authenticated and website workspace headers

## Install
```bash
php artisan migrate
php artisan optimize:clear
npm run dev
```

## Next v2.6 milestone
Install Spark → page name → business prompt → AI personalise → create page.
