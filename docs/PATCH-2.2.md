# Patch 2.2 — Industry-Based Website Initialization

## Summary

Patch 2.2 makes the AI-selected industry menu part of the trial record and uses that saved structure throughout the trial and purchase flow. Website #14 may still host the temporary generated homepage, but its navigation is no longer a source of truth.

## New flow

```text
Prompt
  → detect industry
  → IndustryMenuRegistry
  → save menu_structure on trial_generations
  → trial Builder receives virtual websitePages + trial header menu
  → registration/purchase creates the customer's website
  → create every industry page
  → copy generated blocks to Home only
  → leave all remaining pages empty
```

## Files added

- `app/AI/Registries/IndustryMenuRegistry.php`
- `database/migrations/2026_07_30_000009_add_menu_structure_to_trial_generations_table.php`
- `docs/PATCH-2.2.md`

## Files changed

- `app/Models/TrialGeneration.php`
- `app/Http/Controllers/TrialGenerationController.php`
- `app/Http/Controllers/PageController.php`
- `app/Http/Controllers/Auth/RegisteredUserController.php`

## Behavior

### Trial generation

The detected industry is normalized and mapped to a structured menu. The structure is saved in `trial_generations.menu_structure` at trial creation time, so later registry changes do not silently alter an existing trial.

### Trial Builder

The Builder receives its page targets and header navigation from the trial's saved `menu_structure`. It does not read Website #14 navigation.

Only the generated homepage exists physically during the anonymous trial. The other menu entries are virtual preview targets until the website is claimed.

### Website claim / registration

When a trial is claimed:

1. Create the customer's website.
2. Build its global header menu from the saved trial structure.
3. Create all default industry pages in the saved order.
4. Populate Home with the trial's generated blocks.
5. Create the other pages with empty `blocks` arrays.
6. Mark the trial as claimed.

## Compatibility

Older trials without `menu_structure` fall back to `IndustryMenuRegistry::for($trial->industry)` during claim. New trials always persist their structure.

## Run

```bash
php artisan migrate
php artisan optimize:clear
npm run build
```

## Verification checklist

1. Generate a Restaurant trial.
2. Confirm the Builder menu is Home, About, Menu, Gallery, Contact.
3. Change Website #14's header menu.
4. Refresh the trial Builder and confirm its menu does not change.
5. Register using the trial.
6. Confirm the new customer website has five pages.
7. Confirm Home contains generated blocks.
8. Confirm the other four pages have empty blocks.
