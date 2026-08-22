# Cosmic Spark QA Artisan Command

Command:

```bash
php artisan cosmic:sparks {PAGE_ID}
```

Defaults:
- inserts 10 Sparks
- uses the 100 new image-rich expansion Sparks
- appends to existing blocks
- skips Spark types already present, so running the command again automatically moves to the next unused set
- saves the page as draft

Examples:

```bash
php artisan cosmic:sparks 123
php artisan cosmic:sparks 123 --count=20
php artisan cosmic:sparks 123 --count=10 --offset=0
php artisan cosmic:sparks 123 --count=10 --offset=10
php artisan cosmic:sparks 123 --count=10 --offset=20
php artisan cosmic:sparks 123 --count=10 --random
php artisan cosmic:sparks 123 --all-new
php artisan cosmic:sparks 123 --count=10 --replace
php artisan cosmic:sparks 123 --count=10 --allow-duplicates
php artisan cosmic:sparks 123 --count=10 --dry-run
```

The catalog mirrors the full defaults from the five Expansion Spark batch files, so seeded blocks render with realistic sample content/images rather than only a `type` key.


## Exact QA ranges

`--offset` is zero-based and is applied to the fixed 100-Spark expansion catalog:

- `--count=10 --offset=0` → Sparks 1–10
- `--count=10 --offset=10` → Sparks 11–20
- `--count=10 --offset=20` → Sparks 21–30
- …
- `--count=10 --offset=90` → Sparks 91–100

Duplicate protection remains enabled by default. Use `--allow-duplicates` only when intentionally reseeding the same Spark types.
