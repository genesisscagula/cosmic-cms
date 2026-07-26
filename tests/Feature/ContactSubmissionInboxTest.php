<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactSubmissionInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_connector_can_store_a_valid_submission_for_its_website(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $website = $owner->websites()->create([
            'name' => 'North Star Studio',
            'api_token' => Str::random(60),
            'deployment_secret' => 'inbox-secret',
        ]);

        $this->postJson(route('api.websites.contact-submissions.store', $website), [
            'name' => 'Mia Santos',
            'email' => 'mia@example.com',
            'phone' => '+63 900 000 0000',
            'message' => 'I would like to request a consultation.',
            'fields' => [
                'service_needed' => 'Brand strategy',
                'budget_range' => 'PHP 50,000–100,000',
            ],
        ], ['X-Cosmic-Sync-Secret' => 'inbox-secret'])
            ->assertCreated()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('contact_submissions', [
            'website_id' => $website->id,
            'email' => 'mia@example.com',
        ]);
    }

    public function test_a_wrong_connector_secret_cannot_store_a_submission(): void
    {
        $website = User::factory()->create()->websites()->create([
            'name' => 'Protected Website',
            'api_token' => Str::random(60),
            'deployment_secret' => 'correct-secret',
        ]);

        $this->postJson(route('api.websites.contact-submissions.store', $website), [
            'name' => 'Unauthorised',
            'email' => 'no@example.com',
            'message' => 'Nope',
        ], ['X-Cosmic-Sync-Secret' => 'wrong-secret'])->assertUnauthorized();

        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_only_the_website_owner_can_open_its_inbox(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $website = $owner->websites()->create([
            'name' => 'Private Inbox',
            'api_token' => Str::random(60),
        ]);

        $this->actingAs($otherUser)
            ->get(route('websites.inquiries.index', $website))
            ->assertForbidden();
    }
}
