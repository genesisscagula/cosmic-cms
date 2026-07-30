# Cosmic CMS Patch 2.2.3 — Sales Demo Library + Very Hot Fixes

## Included fixes

- Includes the Patch 2.2.2 workspace pivot relationship hotfix.
- Includes the very-hot-fix pricing cleanup: all content below pricing is removed.
- Persists the AI-selected preview theme to `trial_generations.preview_theme`.
- Applies the saved trial theme inside the token Builder and copies it to the purchased website.
- Uses Midnight only as the fallback when an older trial has no saved theme.

## Sales page

A new authenticated route is available at:

`/sales`

It uses the same `auth` and `verified` middleware as `/dashboard`.

The page includes:

- Static sales hero and product feature cards.
- A dynamic library of the latest 24 completed trial generations.
- Secure token-based demo links that open the existing restricted Builder.
- Demo metadata: business name, industry, location, theme, section count, and last update.
- Empty state with a shortcut to `/start`.

## New files

- `app/Http/Controllers/SalesController.php`
- `resources/js/Pages/Sales/Index.jsx`
- `docs/PATCH-2.2.3.md`

## Updated files

- `routes/web.php`
- Patch 2.1.1 very-hot-fix files for pricing and theme persistence.

## Apply

```bash
php artisan migrate
php artisan optimize:clear
npm run build
```
