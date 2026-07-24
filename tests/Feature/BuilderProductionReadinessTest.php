<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuilderProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_call_customer_ai_routes(): void
    {
        $this->postJson(route('ai.select-sections'), ['prompt' => 'A restaurant website'])
            ->assertUnauthorized();

        $this->postJson(route('ai.generate-content'), [
            'prompt' => 'A restaurant website',
            'sections' => ['hero_background_image'],
        ])->assertUnauthorized();
    }

    public function test_non_owner_cannot_save_another_users_builder(): void
    {
        $owner = $this->verifiedUser();
        $otherUser = $this->verifiedUser();
        [, $page] = $this->websiteWithPage($owner);

        $this->actingAs($otherUser)
            ->postJson(route('pages.builder.save', $page), $this->builderPayload())
            ->assertForbidden();
    }

    public function test_non_owner_cannot_modify_another_users_website_settings(): void
    {
        $owner = $this->verifiedUser();
        $otherUser = $this->verifiedUser();
        [$website] = $this->websiteWithPage($owner);

        $this->actingAs($otherUser)
            ->postJson(route('websites.global-header.save', $website), ['header_block' => ['type' => 'glassmorphism_header']])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->postJson(route('websites.global-footer.save', $website), ['footer_block' => ['type' => 'minimal_footer']])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->postJson(route('websites.update-theme', $website), ['theme_settings' => ['primary' => 'violet']])
            ->assertForbidden();
    }

    public function test_owner_can_save_complete_builder_state_atomically(): void
    {
        $owner = $this->verifiedUser();
        [$website, $page] = $this->websiteWithPage($owner);
        $payload = $this->builderPayload();

        $this->actingAs($owner)
            ->postJson(route('pages.builder.save', $page), $payload)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $page->refresh();
        $website->refresh();

        $this->assertSame($payload['blocks'], $page->blocks);
        $this->assertSame($payload['global_header'], $website->global_header);
        $this->assertSame($payload['global_footer'], $website->global_footer);
        $this->assertSame($payload['theme_settings'], $website->theme_settings);
    }

    public function test_invalid_atomic_save_leaves_existing_page_and_website_state_unchanged(): void
    {
        $owner = $this->verifiedUser();
        [$website, $page] = $this->websiteWithPage($owner);
        $originalBlocks = $page->blocks;
        $originalTheme = $website->theme_settings;

        $payload = $this->builderPayload();
        $payload['theme_settings'] = 'not-an-array';

        $this->actingAs($owner)
            ->postJson(route('pages.builder.save', $page), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme_settings');

        $this->assertSame($originalBlocks, $page->fresh()->blocks);
        $this->assertSame($originalTheme, $website->fresh()->theme_settings);
    }

    public function test_owner_can_save_and_reload_global_header(): void
    {
        $owner = $this->verifiedUser();
        [$website] = $this->websiteWithPage($owner);
        $header = [
            'type' => 'glassmorphism_header',
            'logo_text' => 'Cosmic Restaurant',
            'links' => [],
        ];

        $this->actingAs($owner)
            ->postJson(route('websites.global-header.save', $website), ['header_block' => $header])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame($header, $website->fresh()->global_header);
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function websiteWithPage(User $user): array
    {
        $website = $user->websites()->create([
            'name' => 'Test Website',
            'domain' => 'https://example.test',
            'api_token' => Str::random(60),
            'theme_settings' => ['primary' => 'emerald'],
        ]);

        $page = $website->pages()->create([
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'draft',
            'blocks' => [['type' => 'hero', 'heading' => 'Before save']],
        ]);

        return [$website, $page];
    }

    private function builderPayload(): array
    {
        return [
            'blocks' => [['type' => 'hero', 'heading' => 'Saved atomically']],
            'global_header' => ['type' => 'glassmorphism_header', 'logo_text' => 'Cosmic'],
            'global_footer' => ['type' => 'minimal_footer', 'copyright' => '© 2026 Cosmic'],
            'theme_settings' => ['primary' => 'violet', 'secondary' => 'white', 'tertiary' => 'stone'],
        ];
    }
}
