# Cosmic CMS v2.8.1 — Smart Image Relevance Hotfix

## Fixed

- Page image searches now use a short visual subject extracted from the original business prompt instead of long generated section copy.
- Unsplash results are no longer selected randomly. The service scores returned photos against the search terms and chooses the most relevant result.
- Smart-image cache keys were versioned so previously cached unrelated images are not reused.
- Keeps the v2.8.0 storage-link-independent download path and local fallback chain.

## Example

Prompt:

`Create a luxury website for a private yacht charter company based in Monaco offering exclusive Mediterranean cruises.`

Search subjects now resemble:

- `luxury private yacht charter monaco exclusive mediterranean wide exterior lifestyle`
- `luxury private yacht charter monaco exclusive mediterranean detail lifestyle`

Instead of mixing the full section copy into the stock-photo query.

## Apply

Copy the patch over the project root and replace existing files, then run:

```bash
php artisan optimize:clear
php artisan cache:clear
npm run build
```

Generate a new page (or regenerate the affected page) so the old saved image URLs are replaced.
