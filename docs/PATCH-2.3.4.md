# Patch 2.3.4 — Sales Page Shell Fix

## Fixed

- Removed the default Laravel Breeze `AuthenticatedLayout` from `/sales`.
- Removed the white Laravel navigation bar, Laravel logo, duplicate Dashboard header, and default profile dropdown.
- Added a native Cosmic CMS dark header matching the application dashboard/client workspace styling.
- Kept `/sales` protected by its existing authenticated route middleware.
- Added Dashboard and Log out actions to the sales header.
- Preserved the existing sales hero, feature cards, token demo library, and demo links.

## Apply

Copy the included files into the project, then run:

```bash
php artisan optimize:clear
npm run build
```

For local Vite development, `npm run dev` is sufficient after copying the file.
