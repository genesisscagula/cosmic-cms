<?php

namespace Tests\Unit;

use App\Services\LunaSiteBundlePlannerService;
use App\Services\PageTemplateCatalog;
use App\Services\SiteBundleCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteBundleCatalogTest extends TestCase
{
    public function test_catalog_contains_two_hundred_twenty_unique_centralized_site_bundles(): void
    {
        $bundles = app(SiteBundleCatalog::class)->all();
        $knownTemplates = collect(PageTemplateCatalog::all())->pluck('key')->flip();

        $this->assertCount(220, $bundles);
        $this->assertSame(220, collect($bundles)->pluck('key')->unique()->count());
        $this->assertSame(20, collect($bundles)->pluck('industry')->unique()->count());
        $this->assertSame(11, collect($bundles)->pluck('archetype')->unique()->count());
        $this->assertSame(220, collect($bundles)->pluck('candidate_signature')->unique()->count());
        $this->assertSame(220, collect($bundles)->pluck('composition_signature')->unique()->count());

        foreach ($bundles as $bundle) {
            $this->assertGreaterThanOrEqual(15, $bundle['template_count']);
            $this->assertLessThanOrEqual(20, $bundle['template_count']);
            $this->assertGreaterThanOrEqual(4, $bundle['page_count']);
            $this->assertLessThanOrEqual(7, $bundle['page_count']);
            $this->assertCount($bundle['template_count'], array_unique($bundle['template_candidates']));
            $this->assertSame($bundle['page_count'], collect($bundle['pages'])->pluck('slug')->unique()->count());
            foreach ($bundle['template_candidates'] as $key) {
                $this->assertTrue($knownTemplates->has($key), "Missing template [{$key}].");
            }
            foreach ($bundle['pages'] as $page) {
                $this->assertCount(5, array_unique($page['candidate_template_keys']));
                $this->assertSame([], array_values(array_diff($page['candidate_template_keys'], $bundle['template_candidates'])));
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

        $this->assertStringStartsWith('restaurant-', $plan['bundle_key']);
        $this->assertSame('restaurant', $plan['industry']);
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

    #[DataProvider('newBundleDirectionProvider')]
    public function test_luna_can_select_each_new_bundle_direction(string $prompt, string $industry, string $expected): void
    {
        $plan = app(LunaSiteBundlePlannerService::class)->plan($prompt, $industry);

        $this->assertSame($expected, $plan['bundle_key']);
        $this->assertGreaterThanOrEqual(4, $plan['page_count']);
        $this->assertCount(
            count($plan['pages']),
            array_unique(array_column($plan['pages'], 'template_key')),
        );
    }

    public function test_signature_system_bundle_materially_uses_the_new_spark_templates(): void
    {
        $newTemplates = [
            'signature-operating-system', 'technology-signal-system', 'professional-evidence-system',
            'restaurant-service-notebook', 'care-navigation-system', 'learning-path-system',
            'creative-constellation-system', 'construction-delivery-system', 'hospitality-guest-path',
            'local-service-clarity-system', 'legal-confidence-system', 'universal-signature-system',
        ];
        $bundle = app(SiteBundleCatalog::class)->find('restaurant-signature-system');
        $plan = app(LunaSiteBundlePlannerService::class)->plan(
            'Build a distinctive low image restaurant website with a connected offer, process map, and evidence ledger.',
            'restaurant',
        );

        $this->assertNotNull($bundle);
        $this->assertNotEmpty(array_intersect($newTemplates, $bundle['template_candidates']));
        $this->assertNotEmpty(array_intersect($newTemplates, array_column($plan['pages'], 'template_key')));
    }

    public static function newBundleDirectionProvider(): array
    {
        return [
            'boutique' => ['Build a bespoke luxury dental clinic website.', 'dentist', 'dentist-boutique'],
            'local' => ['Build a friendly local plumber website covering our service area.', 'plumbing', 'plumbing-local'],
            'product' => ['Build a product catalog with comparison and pricing plans.', 'technology', 'technology-product'],
            'community' => ['Build a community-led gym website with member stories.', 'fitness', 'fitness-community'],
            'launch' => ['Build a grand opening campaign with a waitlist.', 'salon', 'salon-launch'],
            'signature system' => ['Build a distinctive low image restaurant website with a connected offer, process map, and evidence ledger.', 'restaurant', 'restaurant-signature-system'],
        ];
    }
}
