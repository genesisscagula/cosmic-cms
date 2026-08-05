# Cosmic CMS V1 - Update Patch 1.0.0

## Completed in this release

### Responsive Spark spacing
- Standardized Spark vertical spacing in the Builder to 50px on mobile and 80px from tablet upward.
- Added the same spacing contract to published/exported static websites.
- Marked compiled Spark sections with `data-cosmic-spark` so live output follows one responsive rule.
- Preserved existing hero minimum heights and horizontal spacing.

### Theme-aware previews
- Fixed Spark previews that incorrectly fell back to Emerald.
- Preview helpers now accept either a theme object or a theme family ID.
- Midnight is now the default color family when a website has no selected theme.
- Added a working Template preview modal with responsive layout and a direct Use Template action.
- Removed a duplicate React state declaration that could break the Add Spark modal build.

### Global Cosmic Credits
- Inertia now resolves the account balance through the centralized `CreditWalletService`.
- Dashboard navigation and client dashboard now read from the shared live credit context.
- Initial authenticated screens no longer flash a misleading zero while the wallet refreshes.
- Credit badges display a neutral loading dash until a real account balance is available.

## Updated files
- `app/Helpers/CmsHtmlCompiler.php`
- `app/Http/Controllers/WebsiteController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Services/DeploymentConnectorArchive.php`
- `app/Services/PagePublisher.php`
- `resources/css/app.css`
- `resources/js/app.jsx`
- `resources/js/Components/CosmicCredits/CreditBalanceBadge.jsx`
- `resources/js/Components/CosmicCredits/CreditBalanceContext.jsx`
- `resources/js/Pages/Client/Dashboard.jsx`
- `resources/js/Pages/Dashboard/Components/Navigation.jsx`
- `resources/js/Pages/Dashboard/Tabs/Templates.jsx`
- `resources/js/Pages/Websites/Builder.jsx`
- `resources/js/Pages/Websites/Components/AddSectionModal.jsx`
- `resources/js/theme/Theme.js`

## Commands after replacing the project
```bash
php artisan optimize:clear
npm run build
```

No database migration is required for this release.
