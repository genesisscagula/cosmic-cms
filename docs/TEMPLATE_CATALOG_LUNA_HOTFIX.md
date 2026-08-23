# Template Catalog / Luna Hotfix

- PageTemplateCatalog records: 523
- Duplicate template keys after hotfix: 0
- Template section renderer/schema gaps after hotfix: 0
- Template-used section types missing Luna planner metadata after hotfix: 0

Fixes:
1. Registered `hero_centered_cta` in the frontend BlockRegistry using the existing `HeroCenteredCTA` component and schema.
2. Renamed the later duplicate `electrician-service-pro` record to `electrician-visual-premium`.
3. Renamed the later duplicate `commercial-cleaning-pro` record to `commercial-cleaning-visual-premium`.
4. Updated names/aliases of the renamed templates so Luna can distinguish the visual premium variants.
5. Added Luna planner metadata for `commerce_product_grid`, `commerce_benefits_strip`, `latest_resources`, and `blog_hub`.

The catalog remains searchable through `PageTemplateCatalog::plannerIndex()` and `LunaTemplatePlannerService`.
