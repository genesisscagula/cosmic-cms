# Patch 2.5 — Cosmic Credits Economy

## Included

- Central block pricing registry with Core (15), Growth, and Signature collections.
- Theme pricing from 1–5 Cosmic Credits.
- Action pricing:
  - Add page: 3 credits
  - Add menu item: 1 credit
  - Generate page: 5 credits
  - Generate section: based on the generated block
- AI generation charges credits before execution and automatically refunds failed requests.
- Page creation charges 3 credits and refunds on failure.
- New menu items are charged when the global header is saved; editing existing items is free.
- Themes are purchased once, saved as an unlock, and can then be reused without another charge.
- Builder receives the current pricing catalog and balance.
- Pricing badges added to page creation, theme cards, and block selection.
- `/cosmic-pricing` authenticated JSON endpoint for future marketplace UI.

## Apply

```bash
php artisan migrate
php artisan optimize:clear
npm run dev
```

## Notes

- Editing, rearranging, duplicating, publishing, and reusing already unlocked themes remain free.
- Manual premium block unlocking and the full Sparks marketplace remain planned for Patch 2.6.
