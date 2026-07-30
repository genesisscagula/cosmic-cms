# Patch 2.5.1 — Credit UX Enhancement

## Goal
Make every credit-consuming action show its cost before the user confirms it.

## Added
- Reusable `CreditPrice` component.
- Reusable `CreditBalancePreview` component.
- Create Page modal now shows current balance, cost, and balance after.
- Generate Page now shows its 5-credit price and balance preview.
- AI section generation shows the 1–5 credit range.
- Specific layout generation shows the exact block price and balance preview.
- Root menu, submenu, and nested-link actions show the 1-credit price.
- Save Header shows the combined pending menu cost when multiple new menu items were added.
- Existing theme cards continue to show their 1–5 credit prices.

## Pricing UX rule
Opening a modal, editing content, rearranging blocks, saving, and publishing remain free. A price label is shown only on the action that actually consumes credits.

## Apply
No migration is required.

```bash
php artisan optimize:clear
npm run dev
```
