<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_aurora_agency_creates_an_editable_draft_homepage(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->post(route('websites.store'), [
            'name' => 'North Star Studio',
            'domain' => 'https://northstar.example.test',
            'industry' => 'technology',
            'location' => 'New York, NY',
            'business_description' => 'A practical digital studio for ambitious teams.',
            'template' => 'aurora-agency',
        ]);

        $website = $user->websites()->firstOrFail();
        $page = $website->pages()->where('slug', 'home')->firstOrFail();

        $response->assertRedirect(route('pages.index', $website));
        $this->assertSame('violet', $website->theme_settings['primary']);
        $this->assertSame('North Star Studio', $website->global_header['logo_text']);
        $this->assertSame('draft', $page->status);
        $this->assertCount(8, $page->blocks);
        $this->assertSame('hero_background_image', $page->blocks[0]['type']);
        $this->assertSame('stats_modern', $page->blocks[7]['type']);
    }

    public function test_unknown_template_is_rejected_without_creating_a_website(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->from(route('dashboard'))->post(route('websites.store'), [
            'name' => 'North Star Studio',
            'domain' => 'https://northstar.example.test',
            'industry' => 'technology',
            'location' => 'New York, NY',
            'business_description' => 'A practical digital studio for ambitious teams.',
            'template' => 'not-a-template',
        ])->assertRedirect(route('dashboard'))->assertSessionHasErrors('template');

        $this->assertSame(0, $user->websites()->count());
    }

    public function test_business_profile_is_saved_on_creation_and_can_be_updated(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post(route('websites.store'), [
            'name' => 'BrightSmile Dental Clinic',
            'domain' => 'https://brightsmile.example.test',
            'industry' => 'Dental clinic',
            'location' => 'Ormoc City, Leyte, Philippines',
            'business_description' => 'Friendly dental care for families and working professionals.',
        ])->assertRedirect();

        $website = $user->websites()->firstOrFail();
        $this->assertSame('Dental clinic', $website->industry);
        $this->assertSame('Ormoc City, Leyte, Philippines', $website->location);

        $this->actingAs($user)->putJson(route('websites.profile.update', $website), [
            'industry' => 'Family dental clinic',
            'location' => 'Ormoc City',
            'business_description' => 'Comfortable preventive and restorative dental care.',
        ])->assertOk();

        $this->assertDatabaseHas('websites', [
            'id' => $website->id,
            'industry' => 'Family dental clinic',
            'location' => 'Ormoc City',
        ]);
    }

    public function test_midnight_studio_uses_the_midnight_theme(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post(route('websites.store'), [
            'name' => 'Meridian',
            'domain' => 'https://meridian.example.test',
            'industry' => 'technology',
            'location' => 'New York, NY',
            'business_description' => 'A practical digital studio for ambitious teams.',
            'template' => 'midnight-studio',
        ])->assertRedirect();

        $website = $user->websites()->firstOrFail();
        $this->assertSame('midnight', $website->theme_settings['primary']);
        $this->assertCount(8, $website->pages()->firstOrFail()->blocks);
    }

    public function test_an_owner_can_delete_a_website_and_its_pages(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $website = $user->websites()->create(['name' => 'Delete me', 'api_token' => str()->random(60)]);
        $website->pages()->create(['title' => 'Home', 'slug' => 'home', 'blocks' => []]);

        $this->actingAs($user)->delete(route('websites.destroy', $website))
            ->assertStatus(303)
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('websites', ['id' => $website->id]);
        $this->assertDatabaseMissing('pages', ['website_id' => $website->id]);
    }
}
