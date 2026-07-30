# Patch 2.1.1 — Very Hot Fix

## Changes

- Removed every landing-page preview section rendered below the pricing/plan selection screen.
- Saved the generated trial theme in `trial_generations.preview_theme`.
- The restricted Builder now receives the trial-specific theme instead of inheriting the shared demo website theme.
- Registration/purchase copies the same saved trial theme into the customer's website.
- Existing trials without a saved theme safely fall back to Midnight.

## Apply

1. Copy this patch over the project root.
2. Run `php artisan migrate`.
3. Run `php artisan optimize:clear`.
4. Run `npm run build`.

## Notes

The shared demo Website #14 is no longer used as the source of truth for a trial's Builder theme.
