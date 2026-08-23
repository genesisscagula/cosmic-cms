<?php

namespace Tests\Unit;

use App\Services\LunaSiteBundlePlannerService;
use App\Services\PageTemplateCatalog;
use App\Services\SiteBundleCatalog;
use Tests\TestCase;

class SiteBundleCatalogTest extends TestCase
{
    public function test_catalog_contains_one_hundred_valid_centralized_site_bundles(): void
    {
        $bundles = app(SiteBundleCatalog::class)->all();
        $knownTemplates = collect(PageTemplateCatalog::all())->pluck('key')->flip();

        $this->assertCount(100, $bundles);
        $this->assertSame(100, collect($bundles)->pluck('key')->unique()->count());
        $this->assertSame(20, collect($bundles)->pluck('industry')->unique()->count());
        $this->assertSame(5, collect($bundles)->pluck('archetype')->unique()->count());

        foreach ($bundles as $bundle) {
            $this->assertGreaterThanOrEqual(15, $bundle['template_count']);
            $this->assertLessThanOrEqual(20, $bundle['template_count']);
            $this->assertGreaterThanOrEqual(4, $bundle['page_count']);
            $this->assertLessThanOrEqual(7, $bundle['page_count']);
            $this->assertCount($bundle['template_count'], array_unique($bundle['template_candidates']));
            foreach ($bundle['template_candidates'] as $key) {
                $this->assertTrue($knownTemplates->has($key), "Missing template [{$key}].");
            }
        }
    }

    public function test_luna_selects_and_adapts_a_restaurant_bundle_without_reusing_page_templates(): void
    {
        $plan = app(LunaSiteBundlePlannerService::class)->plan(
            'Build a premium restaurant website with reservations, catering, gallery and contact.',
            'Restaurant',
        );
        $slugs = array_column($plan['pages'], 'slug');
        $templateKeys = array_column($plan['pages'], 'template_key');

        $this->assertSame('restaurant-showcase', $plan['bundle_key']);
        $this->assertContains('home', $slugs);
        $this->assertContains('menu', $slugs);
        $this->assertContains('gallery', $slugs);
        $this->assertContains('reservations', $slugs);
        $this->assertContains('catering', $slugs);
        $this->assertContains('contact', $slugs);
        $this->assertCount(count($templateKeys), array_unique($templateKeys));
        foreach ($templateKeys as $key) {
            $this->assertNotNull(PageTemplateCatalog::find($key));
        }
    }
}
