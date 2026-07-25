<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndustryLayoutSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospitality_prompts_select_their_matching_layout_family(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $cases = [
            'A specialty coffee shop with espresso and a calm workspace.' => 'coffee',
            'A neighborhood bakery for artisan bread, cakes, and pastries.' => 'bakery',
            'A boutique hotel with rooms and resort-style hospitality.' => 'hotel',
            'A guided travel and tourism company for island vacations.' => 'travel',
            'An Italian restaurant for dining, pizza, and pasta.' => 'restaurant',
        ];

        foreach ($cases as $prompt => $folder) {
            $this->actingAs($user)
                ->postJson(route('ai.select-sections'), ['prompt' => $prompt])
                ->assertOk()
                ->assertJsonPath('image_folder', $folder)
                ->assertJsonStructure(['sections']);
        }
    }

    public function test_unmatched_prompts_continue_to_use_the_default_layout_family(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->postJson(route('ai.select-sections'), ['prompt' => 'A handmade candle studio with seasonal collections.'])
            ->assertOk()
            ->assertJsonPath('image_folder', 'default');
    }

    public function test_trade_prompts_select_their_matching_layout_family(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $cases = [
            'A residential construction contractor for renovations and home builds.' => 'construction',
            'A licensed electrician for home wiring and electrical service.' => 'electrician',
            'A local plumber for pipe repair, drains, and water heaters.' => 'plumbing',
            'A roofing company for roof replacement and repairs.' => 'roofing',
            'An automotive garage for car detailing and vehicle service.' => 'automotive',
        ];

        foreach ($cases as $prompt => $folder) {
            $this->actingAs($user)
                ->postJson(route('ai.select-sections'), ['prompt' => $prompt])
                ->assertOk()
                ->assertJsonPath('image_folder', $folder)
                ->assertJsonStructure(['sections']);
        }
    }

    public function test_care_and_wellness_prompts_select_their_matching_layout_family(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $cases = [
            'A family dentist for dental care and teeth whitening.' => 'dentist',
            'A medical clinic with doctors and primary healthcare.' => 'medical',
            'A modern fitness gym with personal training.' => 'fitness',
            'A residential and commercial cleaning company.' => 'cleaning',
            'A landscaping company for lawn care and garden design.' => 'landscaping',
        ];

        foreach ($cases as $prompt => $folder) {
            $this->actingAs($user)
                ->postJson(route('ai.select-sections'), ['prompt' => $prompt])
                ->assertOk()
                ->assertJsonPath('image_folder', $folder)
                ->assertJsonStructure(['sections']);
        }
    }

    public function test_professional_prompts_select_their_matching_layout_family(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $cases = [
            'A law firm for legal counsel and client representation.' => 'lawyer',
            'A financial advisor for accounting and wealth management.' => 'finance',
            'A real estate agency for property listings and buyers.' => 'real-estate',
            'A SaaS software company for distributed teams.' => 'technology',
            'An education academy for tutoring and online courses.' => 'education',
            'A beauty salon offering hair styling and spa services.' => 'salon',
        ];

        foreach ($cases as $prompt => $folder) {
            $this->actingAs($user)
                ->postJson(route('ai.select-sections'), ['prompt' => $prompt])
                ->assertOk()
                ->assertJsonPath('image_folder', $folder)
                ->assertJsonStructure(['sections']);
        }
    }
}
