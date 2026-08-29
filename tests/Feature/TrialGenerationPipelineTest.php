<?php

namespace Tests\Feature;

use App\Jobs\BuildTrialSiteBundleJob;
use App\Jobs\SendTrialBundleReadyJob;
use App\Models\TrialGeneration;
use App\Models\User;
use App\Models\Website;
use App\Services\AiPageGenerationService;
use App\Services\LunaPexelsVideoService;
use App\Services\TrialStagingPublisherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrialGenerationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_home_claims_exactly_one_inner_page_with_two_minute_delay(): void
    {
        Queue::fake();
        config(['cosmic.trial_inner_page_delay_minutes' => 2]);
        [$trial, $home, $inner] = $this->trialBundle();

        (new BuildTrialSiteBundleJob($trial->id, $home->id))->handle(
            app(AiPageGenerationService::class),
            app(LunaPexelsVideoService::class),
            app(TrialStagingPublisherService::class),
        );

        $innerManifest = collect($trial->fresh()->bundle_manifest['pages'])->firstWhere('page_id', $inner->id);
        $this->assertSame('scheduled', $innerManifest['build_status']);

        Queue::assertPushed(BuildTrialSiteBundleJob::class, function (BuildTrialSiteBundleJob $job) use ($inner): bool {
            $this->assertSame($inner->id, $job->pageId);
            $this->assertNotNull($job->delay);
            $seconds = now()->diffInSeconds($job->delay, false);
            $this->assertGreaterThanOrEqual(118, $seconds);
            $this->assertLessThanOrEqual(122, $seconds);
            return true;
        });
        Queue::assertPushed(BuildTrialSiteBundleJob::class, 1);
    }

    public function test_bundle_ready_email_uses_verified_staging_and_private_builder_links(): void
    {
        [$trial, $home] = $this->trialBundle(false);
        $manifest = $trial->bundle_manifest;
        $manifest['pages'][0]['build_status'] = 'ready';
        $manifest['staging_url'] = 'https://preview.example.test/trial-site/';
        $trial->forceFill([
            'email' => 'visitor@example.test',
            'email_captured_at' => now(),
            'bundle_manifest' => $manifest,
            'bundle_status' => 'ready',
        ])->save();

        config([
            'cosmic-mail.trial_mail_enabled' => true,
            'cosmic-mail.public_url' => 'https://app.example.test',
            'mail.default' => 'array',
            'mail.from.address' => 'hello@example.test',
            'mail.from.name' => 'Cosmic CMS',
        ]);
        app('mail.manager')->purge();
        $transport = app('mail.manager')->mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        (new SendTrialBundleReadyJob($trial->id))->handle(app(TrialStagingPublisherService::class));

        $this->assertNotNull($trial->fresh()->bundle_ready_email_sent_at);
        $this->assertCount(1, $transport->messages());
        $html = (string) $transport->messages()[0]->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('https://preview.example.test/trial-site/', $html);
        $this->assertStringContainsString('/pages/'.$home->id.'/builder?token='.urlencode($trial->token), $html);
    }

    public function test_staging_status_exposes_a_clickable_preview_when_home_is_ready(): void
    {
        [$trial, $home] = $this->trialBundle();
        $manifest = $trial->bundle_manifest;
        $manifest['pages'][0]['build_status'] = 'ready';
        $trial->forceFill([
            'bundle_manifest' => $manifest,
            'bundle_status' => 'building',
        ])->save();

        $response = $this->getJson('/trials/'.$trial->token.'/staging-status');

        $response->assertOk()
            ->assertJsonPath('preview_ready', true)
            ->assertJsonPath('ready_pages', 1)
            ->assertJsonPath('page_count', 2);
        $this->assertNotEmpty($response->json('preview_url'));
        $this->assertSame('building', $trial->fresh()->bundle_status);
        $this->assertSame('published', $home->fresh()->status);
    }

    public function test_current_trial_can_save_its_existing_email_but_another_trial_cannot_claim_it(): void
    {
        Queue::fake();
        [$existing] = $this->trialBundle(false);
        $existing->forceFill([
            'email' => 'visitor@example.test',
            'email_captured_at' => now(),
            'welcome_email_sent_at' => now(),
            'welcome_email_address' => 'visitor@example.test',
            'bundle_status' => 'building',
        ])->save();

        $this->postJson('/trials/'.$existing->token.'/email', ['email' => 'VISITOR@example.test'])
            ->assertOk()
            ->assertJsonPath('email', 'visitor@example.test');

        [$other] = $this->trialBundle(false);
        $other->forceFill(['email' => null, 'email_captured_at' => null, 'bundle_status' => 'building'])->save();

        $this->postJson('/trials/'.$other->token.'/email', ['email' => 'visitor@example.test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'This email already has a trial website. Enter another email address.');
    }

    /** @return array{0: TrialGeneration, 1: \App\Models\Page, 2?: \App\Models\Page} */
    private function trialBundle(bool $withInner = true): array
    {
        $owner = User::factory()->create();
        $website = Website::query()->create([
            'user_id' => $owner->id,
            'name' => 'Trial Site',
            'api_token' => Str::random(60),
            'industry' => 'Professional Services',
            'location' => 'Ormoc City',
            'business_description' => 'A focused trial business website.',
            'website_type' => 'builder',
            'settings' => [],
            'theme_settings' => ['primary' => 'forest'],
            'global_header' => ['menu' => []],
            'global_footer' => [],
        ]);
        $home = $website->pages()->create([
            'title' => 'Home',
            'slug' => 'home',
            'sort_order' => 1,
            'page_type' => 'standard',
            'blocks' => [['type' => 'hero_centered', 'heading' => 'A useful Home page']],
            'status' => 'draft',
        ]);
        $inner = $withInner ? $website->pages()->create([
            'title' => 'About',
            'slug' => 'about',
            'sort_order' => 2,
            'page_type' => 'standard',
            'blocks' => [],
            'status' => 'draft',
        ]) : null;
        $pages = [[
            'title' => 'Home',
            'slug' => 'home',
            'is_home' => true,
            'sort_order' => 1,
            'page_id' => $home->id,
            'build_status' => 'queued',
            'sections' => ['hero'],
            'page_intent' => 'home',
        ]];
        if ($inner) {
            $pages[] = [
                'title' => 'About',
                'slug' => 'about',
                'is_home' => false,
                'sort_order' => 2,
                'page_id' => $inner->id,
                'build_status' => 'queued',
                'sections' => ['mini_hero', 'about'],
                'page_intent' => 'about',
            ];
        }

        $trial = TrialGeneration::query()->create([
            'token' => (string) Str::uuid(),
            'page_id' => $home->id,
            'website_id' => $website->id,
            'email' => null,
            'business_name' => 'Trial Site',
            'industry' => 'Professional Services',
            'location' => 'Ormoc City',
            'business_description' => 'A focused trial business website.',
            'latest_user_prompt' => 'Build a focused professional website.',
            'bundle_manifest' => ['bundle_name' => 'Professional', 'pages' => $pages],
            'bundle_status' => 'queued',
            'status' => 'ready',
        ]);

        return array_values(array_filter([$trial, $home, $inner]));
    }
}
