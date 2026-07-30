# Cosmic CMS v2.7.2 — AI & Marketplace Polish

## Included

- Theme unlock prices multiplied by 10 across the server-side pricing registry.
- Compact **Generate with AI** panel restored at the top of the Add Spark modal.
- Full-page generation uses the existing `/ai/select-sections` planning endpoint followed by `/ai/generate-content` with `generation_type: page`.
- Generated blocks replace the current page through the existing Builder `onReplace` flow.
- Every Spark card now includes a **Preview** action.
- Spark previews open in a large realistic desktop canvas and retain ownership/unlock/add actions.
- Insert Above/Below flows remain owned-only and do not show full-page generation.

## Verification

```bash
php artisan optimize:clear
npm install
npm run build
```

The PHP pricing registry passed `php -l`. The Vite build could not be run in the patch environment because its internal npm registry did not contain `@headlessui/react`; run the commands above in the normal project environment.
