<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Services\PagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PagePublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.cosmic.static_sync_url', null);
        config()->set('services.cosmic.static_sync_token', null);
    }

    public function test_new_pages_default_to_draft(): void
    {
        $user = $this->verifiedUser();
        $website = $this->website($user);

        $this->actingAs($user)
            ->post(route('pages.store', $website), ['title' => 'About Us'])
            ->assertRedirect();

        $this->assertDatabaseHas('pages', [
            'website_id' => $website->id,
            'title' => 'About Us',
            'status' => 'draft',
        ]);
        $this->assertSame([], $website->pages()->where('title', 'About Us')->firstOrFail()->blocks);
    }

    public function test_new_websites_receive_an_editable_branded_shell(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)
            ->post(route('websites.store'), [
                'name' => 'North Star Studio',
                'domain' => 'https://northstar.example.test',
            ])
            ->assertRedirect();

        $website = $user->websites()->where('name', 'North Star Studio')->firstOrFail();

        $this->assertSame('glassmorphism_header', $website->global_header['type']);
        $this->assertSame('North Star Studio', $website->global_header['logo_text']);
        $this->assertSame('minimal_footer', $website->global_footer['type']);
        $this->assertSame('North Star Studio', $website->global_footer['logo_text']);
    }

    public function test_save_draft_persists_editable_state_without_publishing(): void
    {
        $user = $this->verifiedUser();
        [$website, $page] = $this->websiteWithPage($user);
        $payload = $this->builderPayload('Draft copy');

        $this->actingAs($user)
            ->postJson(route('pages.builder.save', $page), $payload)
            ->assertOk();

        $page->refresh();

        $this->assertSame('draft', $page->status);
        $this->assertSame($payload['blocks'], $page->blocks);
        $this->assertNull($page->published_blocks);

        $payload['blocks'][0]['heading'] = 'Draft copy updated';
        $this->actingAs($user)->postJson(route('pages.builder.save', $page), $payload)->assertOk();

        $this->assertSame('Draft copy updated', $page->fresh()->blocks[0]['heading']);
    }

    public function test_publish_creates_a_live_snapshot_without_running_the_manual_static_sync(): void
    {
        $user = $this->verifiedUser();
        [$website, $page] = $this->websiteWithPage($user);
        $payload = $this->builderPayload('Published copy');

        $this->actingAs($user)->postJson(route('pages.builder.save', $page), $payload)->assertOk();

        $this->actingAs($user)
            ->postJson(route('pages.publish', $page))
            ->assertOk()
            ->assertJsonPath('status', 'published');

        $page->refresh();
        $website->refresh();

        $this->assertSame('published', $page->status);
        $this->assertSame($payload['blocks'], $page->published_blocks);
        $this->assertNotNull($page->published_html);
        $this->assertNotNull($page->published_at);
        $this->assertNotNull($page->last_published_at);
        $this->assertNull($page->publish_error);
        $this->assertSame($payload['theme_settings'], $website->published_theme_settings);
    }

    public function test_failed_publish_preserves_the_current_live_version(): void
    {
        $user = $this->verifiedUser();
        [$website, $page] = $this->websiteWithPage($user, 'published');
        $liveBlocks = [['type' => 'hero', 'heading' => 'Live version']];
        $page->update([
            'published_blocks' => $liveBlocks,
            'published_html' => '<section>Live version</section>',
            'published_at' => now()->subDay(),
            'last_published_at' => now()->subDay(),
        ]);

        $payload = $this->builderPayload('Unpublished replacement');
        $this->actingAs($user)->postJson(route('pages.builder.save', $page), $payload)->assertOk();

        $this->mock(PagePublisher::class, function (MockInterface $mock) {
            $mock->shouldReceive('publish')
                ->once()
                ->andThrow(new RuntimeException('Deployment unavailable'));
        });

        $this->actingAs($user)
            ->postJson(route('pages.publish', $page))
            ->assertStatus(502)
            ->assertJsonPath('status', 'published');

        $page->refresh();

        $this->assertSame('published', $page->status);
        $this->assertSame($payload['blocks'], $page->blocks);
        $this->assertSame($liveBlocks, $page->published_blocks);
        $this->assertNotNull($page->publish_error);

        $this->getJson('/api/v1/sync', ['X-Cosmic-Token' => $website->api_token])
            ->assertOk()
            ->assertJsonPath('pages.0.blocks.0.heading', 'Live version');
    }

    public function test_static_sync_package_only_contains_successfully_published_html(): void
    {
        $user = $this->verifiedUser();
        [$website, $page] = $this->websiteWithPage($user, 'published');
        $page->update([
            'published_blocks' => [['type' => 'hero', 'heading' => 'Live version']],
            'published_html' => '<section>Published HTML</section>',
        ]);

        $this->getJson('/api/v1/published-package', ['X-Cosmic-Token' => $website->api_token])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('pages.0.html', '<section>Published HTML</section>');

        $page->update(['blocks' => [['type' => 'hero', 'heading' => 'Draft only']]]);

        $this->getJson('/api/v1/published-package', ['X-Cosmic-Token' => $website->api_token])
            ->assertOk()
            ->assertJsonPath('pages.0.html', '<section>Published HTML</section>');
    }

    public function test_publish_does_not_call_a_configured_manual_static_sync_receiver(): void
    {
        $user = $this->verifiedUser();
        [$website, $page] = $this->websiteWithPage($user);

        config()->set('services.cosmic.static_sync_url', 'https://static.example.test/sync.php?action=receive_package');
        config()->set('services.cosmic.static_sync_token', 'test-sync-secret');
        Http::fake(['https://static.example.test/*' => Http::response(['status' => 'success'], 200)]);

        $this->actingAs($user)
            ->postJson(route('pages.publish', $page))
            ->assertOk()
            ->assertJsonPath('status', 'published');

        $this->assertSame('published', $page->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_publish_succeeds_even_when_the_manual_static_sync_receiver_is_unavailable(): void
    {
        $user = $this->verifiedUser();
        [$website, $page] = $this->websiteWithPage($user);

        config()->set('services.cosmic.static_sync_url', 'https://static.example.test/sync.php?action=receive_package');
        config()->set('services.cosmic.static_sync_token', 'test-sync-secret');
        Http::fake(['https://static.example.test/*' => Http::response(['status' => 'error'], 500)]);

        $this->actingAs($user)
            ->postJson(route('pages.publish', $page))
            ->assertOk()
            ->assertJsonPath('status', 'published');

        $page->refresh();
        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_blocks);
        $this->assertNull($page->publish_error);
        Http::assertNothingSent();
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function website(User $user): Website
    {
        return $user->websites()->create([
            'name' => 'Publish Test',
            'domain' => 'https://example.test',
            'api_token' => Str::random(60),
            'theme_settings' => ['primary' => 'emerald'],
        ]);
    }

    private function websiteWithPage(User $user, string $status = 'draft'): array
    {
        $website = $this->website($user);
        $page = $website->pages()->create([
            'title' => 'Home',
            'slug' => 'home',
            'status' => $status,
            'blocks' => [['type' => 'hero', 'heading' => 'Before save']],
        ]);

        return [$website, $page];
    }

    private function builderPayload(string $heading): array
    {
        return [
            'blocks' => [['type' => 'hero', 'heading' => $heading]],
            'global_header' => ['type' => 'glassmorphism_header', 'logo_text' => 'Cosmic'],
            'global_footer' => ['type' => 'minimal_footer', 'copyright' => '© 2026 Cosmic'],
            'theme_settings' => ['primary' => 'violet', 'secondary' => 'white', 'tertiary' => 'stone'],
        ];
    }
}
