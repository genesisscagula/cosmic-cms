# Cosmic CMS v2.8.2 — Unsplash Downloader Test Command

Adds an isolated Artisan command for testing the Unsplash search/download pipeline before reconnecting it to generated page blocks.

## Added

- `app/Console/Commands/DownloadUnsplashImages.php`
- Searches Unsplash using a supplied phrase.
- Requests the latest matching photos.
- Downloads 1–30 images to local CMS storage.
- Uses landscape, portrait, or squarish orientation.
- Calls Unsplash download tracking for each successfully stored photo.

## Required `.env`

```env
SMART_IMAGE_PROVIDER=unsplash
UNSPLASH_ACCESS_KEY=your_real_unsplash_access_key
```

Then clear cached config:

```bash
php artisan optimize:clear
```

## Yacht test

```bash
php artisan storage:link
php artisan unsplash:download "luxury yacht Monaco Mediterranean" --count=10 --folder=yacht
```

Downloaded files:

```text
storage/app/public/cms-images/unsplash/yacht/
```

Public URL base:

```text
/storage/cms-images/unsplash/yacht/
```

## Other examples

```bash
php artisan unsplash:download "private yacht charter" --count=10 --folder=yacht-charter
php artisan unsplash:download "vertical hydroponic farm" --count=10 --folder=vertical-farming
php artisan unsplash:download "luxury Swiss watch" --count=10 --folder=luxury-watch
```
