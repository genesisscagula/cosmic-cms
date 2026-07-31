# Cosmic CMS v2.7.4.2 — Random Blog Spark Composition

## Included

- New Posts / updates pages receive one random Spark from each Blog group:
  - Blog Mini Header: 1 of 3
  - Blog Cards: 1 of 3
  - Blog Newsletter: 1 of 3
  - Blog Latest Resources: 1 of 3
- Produces up to **81 initial Blog page combinations** from the 12 free Sparks.
- Randomization runs only during initial Blog page creation.
- Every selected `layout_variant` is stored in the page block payload and survives edit, save, publish, refresh, and theme changes.
- Existing Blog pages are not re-randomized or modified.
- Manual Change Spark selections from v2.7.4.1 remain persistent and independent per section.
- A central `BlogSparkRegistry` keeps the creation logic ready for future free or premium Spark expansion.
- Each group safely falls back to Spark 01 if secure random selection is unavailable.
- Newsletter starts with the active Primary variant; Latest Resources starts with the White variant.

## Scope note

The four-section composition belongs to a Posts / updates **page**. Individual Blog Post records continue using their existing article content model and do not receive page-level section layouts.
