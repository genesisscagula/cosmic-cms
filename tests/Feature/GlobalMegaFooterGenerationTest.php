<?php

namespace Tests\Feature;

use App\Cosmic\Pricing\ActionPricing;
use App\Jobs\BuildRegisteredSiteBundlePageJob;
use App\Models\TrialGeneration;
use App\Models\User;
use App\Models\Website;
use App\Services\GlobalMegaFooterService;
use App\Services\RegisteredSiteBundleService;
use App\Services\TrialSiteBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GlobalMegaFooterGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_flex_footer_uses_only_registered_navigation_and_keeps_every_page(): void
    {
        config(['openai.api_key' => 'test-key']);
        Http::fake(['*' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode(['footer' => [
                    'tagline' => 'Thoughtful dining and memorable gatherings.',
                    'primary_label' => 'Reserve a table',
                    'primary_url' => 'contact',
                    'columns' => [[
                        'title' => 'Dining',
                        'items' => [
                            ['label' => 'Menu', 'url' => 'menu'],
                            ['label' => 'Invented', 'url' => 'invented-page'],
                        ],
                    ]],
                ]])],
            ]],
        ])]);

        $footer = app(GlobalMegaFooterService::class)->compose(
            'Build a premium restaurant website.',
            ['business_name' => 'Milagrina', 'industry' => 'Restaurant'],
            [
                ['label' => 'Home', 'url' => 'home'],
                ['label' => 'Menu', 'url' => 'menu'],
                ['label' => 'Contact', 'url' => 'contact'],
            ],
        );

        $items = collect($footer['mega_footer']['columns'])->flatMap(fn (array $column) => $column['items']);
        $this->assertTrue($footer['mega_enabled']);
        $this->assertSame('ai_flex_mega_footer', $footer['generated_by']);
        $this->assertSame('sol', data_get($footer, 'mega_footer.ai_flex.source'));
        $this->assertEqualsCanonicalizing(['home', 'menu', 'contact'], $items->pluck('url')->all());
        $this->assertNotContains('invented-page', $items->pluck('url')->all());
    }

    public function test_trial_first_build_persists_the_prompt_aware_mega_footer_globally(): void
    {
        config(['openai.api_key' => '']);
        $owner = User::factory()->create(['email' => 'trial-owner@example.test']);
        config(['cosmic.trial_website_owner_email' => $owner->email]);
        $trial = TrialGeneration::query()->create([
            'token' => (string) Str::uuid(),
            'business_name' => 'Arbor & Ember',
            'industry' => 'Landscaping',
            'location' => 'Ormoc City',
            'business_description' => 'Custom outdoor spaces for gathering.',
            'latest_user_prompt' => 'Build a landscaping website with projects and contact pages.',
            'status' => 'generating',
        ]);
        $pages = [
            ['title' => 'Home', 'slug' => 'home', 'is_home' => true, 'sort_order' => 1, 'page_type' => 'standard'],
            ['title' => 'Projects', 'slug' => 'projects', 'is_home' => false, 'sort_order' => 2, 'page_type' => 'standard'],
            ['title' => 'Contact', 'slug' => 'contact', 'is_home' => false, 'sort_order' => 3, 'page_type' => 'standard'],
        ];

        $home = app(TrialSiteBundleService::class)->create(
            $trial,
            [
                'business_name' => 'Arbor & Ember',
                'industry' => 'Landscaping',
                'location' => 'Ormoc City',
                'business_description' => 'Custom outdoor spaces for gathering.',
            ],
            ['bundle_key' => 'landscaping-test', 'bundle_name' => 'Landscaping Test', 'pages' => $pages],
            ['blocks' => [['type' => 'hero_centered', 'heading' => 'Spaces made for staying']], 'sections' => ['hero_centered']],
        );

        $footer = Website::query()->findOrFail($home->website_id)->global_footer;
        $items = collect(data_get($footer, 'mega_footer.columns', []))->flatMap(fn (array $column) => $column['items']);
        $this->assertTrue($footer['mega_enabled']);
        $this->assertSame('ai_flex_mega_footer', $footer['generated_by']);
        $this->assertEqualsCanonicalizing(['home', 'projects', 'contact'], $items->pluck('url')->all());
    }

    public function test_registered_first_build_persists_mega_footer_and_queues_pages(): void
    {
        Bus::fake();
        config(['openai.api_key' => '']);
        $user = User::factory()->create(['credits' => 1000]);
        $website = Website::query()->create([
            'user_id' => $user->id,
            'name' => 'Milagrina',
            'api_token' => Str::random(60),
            'industry' => 'Restaurant',
            'location' => 'Ormoc City',
            'business_description' => 'A warm premium restaurant.',
            'website_type' => 'builder',
            'settings' => [],
            'theme_settings' => ['primary' => 'forest'],
            'global_header' => ['menu' => []],
            'global_footer' => ['type' => 'minimal_footer', 'mega_enabled' => false],
        ]);
        $pages = [
            ['title' => 'Home', 'slug' => 'home', 'is_home' => true, 'sort_order' => 1, 'page_type' => 'standard'],
            ['title' => 'Menu', 'slug' => 'menu', 'is_home' => false, 'sort_order' => 2, 'page_type' => 'standard'],
            ['title' => 'Contact', 'slug' => 'contact', 'is_home' => false, 'sort_order' => 3, 'page_type' => 'standard'],
        ];
        $plan = [
            'bundle_key' => 'restaurant-test',
            'bundle_name' => 'Restaurant Test',
            'bundle_version' => 1,
            'industry' => 'restaurant',
            'pages' => $pages,
            'build_page_count' => 3,
            'credit_cost' => 3 * ActionPricing::GENERATE_PAGE,
        ];

        app(RegisteredSiteBundleService::class)->install(
            $website,
            $user,
            'Build a restaurant website with a menu and contact page.',
            $plan,
        );

        $footer = $website->fresh()->global_footer;
        $items = collect(data_get($footer, 'mega_footer.columns', []))->flatMap(fn (array $column) => $column['items']);
        $this->assertTrue($footer['mega_enabled']);
        $this->assertSame('ai_flex_mega_footer', $footer['generated_by']);
        $this->assertEqualsCanonicalizing(['home', 'menu', 'contact'], $items->pluck('url')->all());
        Bus::assertDispatchedTimes(BuildRegisteredSiteBundlePageJob::class, 3);
    }

    public function test_starter_pages_button_is_rendered_only_when_the_website_has_no_pages(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Websites/Index.jsx'));
        $this->assertStringContainsString('(pages || []).length === 0 ? <button', $source);
    }
}
