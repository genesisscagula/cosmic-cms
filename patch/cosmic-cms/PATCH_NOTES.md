# Cosmic CMS v2.8.0 — Smart AI Image Engine

## Enhancement 1 — Smart Blog Featured Images

- Blog “Write with AI” now builds an image search from the generated title, category, tags, and website industry.
- Searches Unsplash for a relevant landscape image.
- Downloads the selected image into `storage/app/public/cms-images/remote/YYYY/MM`.
- Falls back to the website industry folder, then `cms-images/default`, then the background library.
- Blog content remains editable and is never auto-published.

## Enhancement 2 — Smart Generated Page Images

- Generated image blocks now receive a context-aware search query based on:
  - Business/profile context from the generation prompt
  - Block type
  - Block heading/title/text
- Searches and stores a different relevant image per image-bearing section.
- Falls back to the resolved industry folder and then the default local library.
- Works with niche industries even when no dedicated local folder exists.

## Setup

Add this to `.env`:

```env
SMART_IMAGE_PROVIDER=unsplash
UNSPLASH_ACCESS_KEY=your_unsplash_access_key
```

Then run:

```bash
php artisan optimize:clear
php artisan storage:link
npm run build
```

Without an Unsplash key, the system safely continues using the existing local industry/default image flow.
