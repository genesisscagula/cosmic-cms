<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HeaderLogoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_a_logo_for_the_global_header(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $website = $user->websites()->create([
            'name' => 'North Star Studio',
            'domain' => 'https://northstar.example.test',
            'api_token' => Str::random(60),
        ]);

        $response = $this->actingAs($user)->post(route('websites.logo.upload'), [
            'website_id' => $website->id,
            'image' => UploadedFile::fake()->createWithContent(
                'brand-logo.svg',
                '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="80"><rect width="240" height="80" fill="#111827"/></svg>'
            ),
        ]);

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertCount(1, Storage::disk('public')->files("websites/{$website->id}/logos"));
    }
}
