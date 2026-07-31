# Cosmic CMS v2.8.0 Smart Image Hotfix

## Fixed

- Remote Unsplash images are now downloaded to `public/cosmic-images/remote/`, so they do not depend on `storage:link`.
- Generated image URLs are root-relative, preventing incorrect `APP_URL`, host, or port values from breaking previews.
- Local fallback now checks the requested industry folder, then `default`, then `background`.
- Removed the external Picsum fallback.
- Added a bundled local SVG fallback that always renders even when no stock/local images exist.
- Added clearer logs for Unsplash search and download failures.

## Environment

```env
SMART_IMAGE_PROVIDER=unsplash
UNSPLASH_ACCESS_KEY=your_unsplash_access_key
```

After replacing the files:

```bash
php artisan optimize:clear
npm run build
```
