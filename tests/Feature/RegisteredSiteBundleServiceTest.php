<?php

namespace Tests\Feature;

use App\Cosmic\Pricing\ActionPricing;
use App\Jobs\BuildRegisteredSiteBundlePageJob;
use App\Models\User;
use App\Models\Website;
use App\Services\RegisteredSiteBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisteredSiteBundleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_site_plan_uses_one_curated_bundle_and_caps_the_starter_site_at_six_pages(): void
    {
        [$user, $website] = $this->website();

        $plan = app(RegisteredSiteBundleService::class)->plan(
            $website,
            'Build a premium restaurant website with Home, Menu, About, Gallery, Reservations, Catering and Contact.',
        );

        $this->assertStringStartsWith('restaurant-', $plan['bundle_key']);
        $this->assertGreaterThanOrEqual(5, $plan['page_count']);
        $this->assertLessThanOrEqual(6, $plan['page_count']);
        $this->assertSame('home', $plan['pages'][0]['slug']);
        $this->assertContains('contact', array_column($plan['pages'], 'slug'));
        $this->assertSame($plan['build_page_count'] * ActionPricing::GENERATE_PAGE, $plan['credit_cost']);
        foreach ($plan['pages'] as $page) {
            $this->assertContains($page['template_key'], $plan['template_candidates']);
        }
    }

    public function test_install_preserves_populated_pages_charges_only_empty_pages_and_queues_each_build(): void
    {
        Bus::fake();
        [$user, $website] = $this->website();
        $homeBlocks = [['type' => 'hero', 'content' => ['heading' => 'Keep this Home page']]];
        $website->pages()->create([
            'title' => 'Home',
            'slug' => 'home',
            'sort_order' => 1,
            'page_type' => 'standard',
            'blocks' => $homeBlocks,
            'status' => 'published',
        ]);
        $prompt = 'Build a premium restaurant website with Home, Menu, About, Gallery, Reservations and Contact.';
        $service = app(RegisteredSiteBundleService::class);
        $plan = $service->plan($website->fresh(), $prompt);
        $balanceBefore = (int) $user->fresh()->credits;

        $payload = $service->install($website->fresh(), $user->fresh(), $prompt, $plan);

        $this->assertTrue($payload['installed']);
        $this->assertSame('queued', $payload['status']);
        $this->assertSame($balanceBefore - $plan['credit_cost'], (int) $user->fresh()->credits);
        $this->assertSame($homeBlocks, $website->pages()->where('slug', 'home')->firstOrFail()->blocks);
        $this->assertSame('preserved', collect($payload['pages'])->firstWhere('slug', 'home')['build_status']);
        $this->assertCount($plan['page_count'], data_get($website->fresh()->settings, 'site_bundle.pages'));
        $this->assertCount($plan['page_count'], $website->fresh()->global_header['menu']);
        Bus::assertDispatchedTimes(BuildRegisteredSiteBundlePageJob::class, $plan['build_page_count']);
    }

    /** @return array{0: User, 1: Website} */
    private function website(): array
    {
        $user = User::factory()->create(['credits' => 1000]);
        $website = Website::query()->create([
            'user_id' => $user->id,
            'name' => 'Milagrina',
            'api_token' => Str::random(60),
            'industry' => 'Restaurant',
            'location' => 'Ormoc City',
            'business_description' => 'A warm premium restaurant for memorable gatherings.',
            'website_type' => 'builder',
            'settings' => [],
            'theme_settings' => ['primary' => 'forest'],
            'global_header' => ['menu' => []],
            'global_footer' => [],
        ]);

        return [$user, $website];
    }
}
