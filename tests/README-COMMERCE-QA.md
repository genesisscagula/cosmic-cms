# Commerce QA suite

The commerce tests can always be run directly without editing `phpunit.xml`:

```bash
php artisan test tests/Unit/CommercePayPalIntegrityTest.php tests/Feature/CommerceRefundIntegrityTest.php tests/Feature/CommerceInventoryReservationIntegrityTest.php
```

If your project uses named PHPUnit suites, add this inside `<testsuites>`:

```xml
<testsuite name="Commerce">
    <directory>tests/Unit</directory>
    <directory>tests/Feature</directory>
</testsuite>
```
