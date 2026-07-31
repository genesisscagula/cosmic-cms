# Cosmic CMS v2.7.5 — Credits Economy UI Sync

## Enhancement #1 — Create Page Cost

- Updated the frontend Create Page price from **3** to **30 Cosmic Credits**.
- The server-side `ActionPricing::ADD_PAGE` value was already 30, so the modal and actual deduction are now synchronized.

## Enhancement #2 — Theme Prices ×10

- Updated the Theme picker badges from **1–5** to **10–50 Cosmic Credits**.
- Synchronized the server-side theme unlock registry to the same 10–50 pricing scale.
- Added explicit prices for Void, Sapphire, Plum, Olive, and Slate so the UI and backend do not rely on mismatched fallbacks.
- Active and previously unlocked themes remain free through the existing unlock logic.

## Files changed

- `resources/js/cosmic/pricing.js`
- `resources/js/Pages/Websites/Theme/ThemeCard.jsx`
- `app/Cosmic/Pricing/ThemePricingRegistry.php`

## After copying the patch

```bash
php artisan optimize:clear
npm run build
```

No database migration is required.
