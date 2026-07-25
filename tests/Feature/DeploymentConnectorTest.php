<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use App\Services\PagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_verify_an_installed_deployment_connector(): void
    {
        [$user, $website] = $this->websiteWithConnector();

        Http::fake([
            'http://localhost/test-cms/cosmic-sync/sync.php?action=verify' => Http::response([
                'status' => 'success',
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('websites.deployment-connector.verify', $website))
            ->assertOk()
            ->assertJsonPath('status', 'connected');

        $website->refresh();

        $this->assertNotNull($website->deployment_verified_at);
        $this->assertNull($website->deployment_error);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Cosmic-Sync-Secret', 'connector-secret'));
    }

    public function test_a_failed_connector_verification_keeps_the_site_disconnected(): void
    {
        [$user, $website] = $this->websiteWithConnector();

        Http::fake([
            'http://localhost/test-cms/cosmic-sync/sync.php?action=verify' => Http::response([
                'status' => 'error',
            ], 401),
        ]);

        $this->actingAs($user)
            ->postJson(route('websites.deployment-connector.verify', $website))
            ->assertUnprocessable();

        $website->refresh();

        $this->assertNull($website->deployment_verified_at);
        $this->assertNotNull($website->deployment_error);
    }

    public function test_an_owner_can_push_only_published_pages_to_a_verified_connector(): void
    {
        [$user, $website] = $this->websiteWithConnector();
        $website->update(['deployment_verified_at' => now()]);
        $website->pages()->create([
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
            'blocks' => [['type' => 'hero', 'heading' => 'Draft fallback']],
            'published_blocks' => [['type' => 'hero', 'heading' => 'Published content']],
            'published_html' => '<section>Published content</section>',
        ]);
        $website->pages()->create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
            'blocks' => [['type' => 'hero', 'heading' => 'Draft only']],
        ]);

        Http::fake([
            'http://localhost/test-cms/cosmic-sync/sync.php?action=receive_package' => Http::response([
                'status' => 'success',
                'files' => ['index.html'],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('websites.deployment-connector.push', $website))
            ->assertOk()
            ->assertJsonPath('status', 'deployed')
            ->assertJsonPath('files.0', 'index.html');

        $website->refresh();

        $this->assertNotNull($website->last_deployed_at);
        $this->assertNull($website->deployment_error);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Cosmic-Sync-Secret', 'connector-secret')
            && str_contains($request->url(), 'receive_package'));
    }

    public function test_a_live_push_verifies_an_installed_connector_when_it_has_not_been_connected_yet(): void
    {
        [$user, $website] = $this->websiteWithConnector();
        $website->pages()->create([
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
            'published_blocks' => [['type' => 'hero', 'heading' => 'Published content']],
            'published_html' => '<section>Published content</section>',
        ]);

        Http::fake([
            'http://localhost/test-cms/cosmic-sync/sync.php?action=verify' => Http::response([
                'status' => 'success',
            ]),
            'http://localhost/test-cms/cosmic-sync/sync.php?action=receive_package' => Http::response([
                'status' => 'success',
                'files' => ['index.html'],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('websites.deployment-connector.push', $website))
            ->assertOk()
            ->assertJsonPath('status', 'deployed');

        $this->assertNotNull($website->fresh()->deployment_verified_at);
        Http::assertSentCount(2);
    }

    public function test_published_package_converts_known_menu_slugs_to_static_page_links(): void
    {
        [, $website] = $this->websiteWithConnector();
        $website->update([
            'global_header' => [
                'type' => 'glassmorphism_header',
                'cta_label' => 'Contact us',
                'cta_url' => 'about',
                'menu' => [
                    ['label' => 'Home', 'url' => 'home'],
                    ['label' => 'About', 'url' => 'about'],
                    ['label' => 'Contact', 'url' => 'contact'],
                    ['label' => 'Instagram', 'url' => 'https://instagram.com/cosmic'],
                ],
            ],
        ]);
        $website->pages()->create(['title' => 'Home', 'slug' => 'home', 'status' => 'published']);
        $website->pages()->create(['title' => 'About', 'slug' => 'about', 'status' => 'published']);
        $website->pages()->create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'draft']);

        $package = app(PagePublisher::class)->publishedPackage($website->fresh());

        $this->assertStringContainsString("href='./'", $package['global_header']);
        $this->assertStringContainsString("href='about'", $package['global_header']);
        $this->assertStringContainsString('Contact us', $package['global_header']);
        $this->assertStringContainsString("href='#'", $package['global_header']);
        $this->assertStringContainsString("href='https://instagram.com/cosmic'", $package['global_header']);
    }

    public function test_a_push_uses_the_published_website_shell_when_new_shell_changes_are_still_drafts(): void
    {
        [$user, $website] = $this->websiteWithConnector();
        $website->update([
            'deployment_verified_at' => now(),
            'global_header' => ['type' => 'glassmorphism_header', 'logo_text' => 'New header'],
            'published_global_header' => ['type' => 'glassmorphism_header', 'logo_text' => 'Published header'],
        ]);
        $website->pages()->create([
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
            'published_html' => '<section>Published page</section>',
        ]);

        Http::fake([
            'http://localhost/test-cms/cosmic-sync/sync.php?action=receive_package' => Http::response([
                'status' => 'success',
                'files' => ['index.html'],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('websites.deployment-connector.push', $website))
            ->assertOk()
            ->assertJsonPath('status', 'deployed');

        Http::assertSent(function ($request) {
            $package = $request->data();

            return str_contains($request->url(), 'receive_package')
                && str_contains($package['global_header'], 'Published header')
                && ! str_contains($package['global_header'], 'New header');
        });
    }

    private function websiteWithConnector(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $website = $user->websites()->create([
            'name' => 'Test CMS',
            'domain' => 'http://localhost/test-cms/',
            'api_token' => Str::random(60),
            'deployment_secret' => 'connector-secret',
        ]);

        return [$user, $website];
    }
}
