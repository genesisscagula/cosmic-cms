# Cosmic CMS Patch 2.7.0 — My Sparks Builder

## Included

- Replaces the Builder's customer-facing `Generate` button with `✨ Add Spark`.
- New Builder popup with `My Sparks` as the default tab and `Marketplace` as the second tab.
- Existing block registry is presented as customer-facing Sparks.
- Unlock once with Cosmic Credits and reuse on any page.
- Quick Content installs the existing generic block payload for free.
- AI Personalize generates content for the selected Spark for exactly 2 Credits.
- Sparks Marketplace now lists the real Builder block catalog rather than separate page-template records.
- Shared ownership uses `cosmic_unlocks` with `unlock_type = spark`.
- Search, category filters, owned counts, empty states, loading states, and real-time credit updates.

## Install

```bash
php artisan migrate
php artisan optimize:clear
npm install
npm run dev
```

## Test

1. Open `/sparks`, unlock a Spark, and confirm credits decrease.
2. Open any Builder and click `✨ Add Spark`.
3. Confirm `My Sparks` opens first and contains the unlocked Spark.
4. Choose `Quick Content` and confirm the section is added for free.
5. Choose `AI Personalize` and confirm exactly 2 Credits are deducted.
6. Refresh and confirm ownership remains.
