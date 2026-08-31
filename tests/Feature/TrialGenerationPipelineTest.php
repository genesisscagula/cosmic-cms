<?php

namespace Tests\Feature;

use App\Jobs\BuildTrialSiteBundleJob;
use App\Jobs\FinalizeTrialSiteBundleJob;
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

    public function test_completed_home_claims_exactly_one_inner_page_without_browser_or_delay(): void
    {
        Queue::fake();
        [$trial, $home, $inner] = $this->trialBundle();

        (new BuildTrialSiteBundleJob($trial->id, $home->id))->handle(
            app(AiPageGenerationService::class),
            app(LunaPexelsVideoService::class),
        );

        $innerManifest = collect($trial->fresh()->bundle_manifest['pages'])->firstWhere('page_id', $inner->id);
        $this->assertSame('scheduled', $innerManifest['build_status']);

        Queue::assertPushed(BuildTrialSiteBundleJob::class, function (BuildTrialSiteBundleJob $job) use ($inner): bool {
            $this->assertSame($inner->id, $job->pageId);
            $this->assertNull($job->delay);
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

    public function test_staging_status_remains_poll_only_until_every_page_is_ready(): void
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
            ->assertJsonPath('preview_ready', false)
            ->assertJsonPath('pages_complete', false)
            ->assertJsonPath('ready_pages', 1)
            ->assertJsonPath('page_count', 2);
        $this->assertNull($response->json('preview_url'));
        $this->assertSame('building', $trial->fresh()->bundle_status);
        $this->assertSame('draft', $home->fresh()->status);
    }

    public function test_recovery_command_requeues_one_stale_scheduled_page_without_rebuilding_home(): void
    {
        Queue::fake();
        [$trial, $home, $inner] = $this->trialBundle();
        $manifest = $trial->bundle_manifest;
        $manifest['pages'][0]['build_status'] = 'ready';
        $manifest['pages'][1]['build_status'] = 'scheduled';
        $manifest['pages'][1]['scheduled_at'] = now()->subMinutes(30)->toIso8601String();
        $trial->forceFill(['bundle_manifest' => $manifest, 'bundle_status' => 'building'])->save();

        $this->artisan('trials:recover-bundles --limit=10')->assertSuccessful();

        Queue::assertPushed(BuildTrialSiteBundleJob::class, function (BuildTrialSiteBundleJob $job) use ($inner): bool {
            return $job->pageId === $inner->id;
        });
        $this->assertSame('ready', collect($trial->fresh()->bundle_manifest['pages'])->firstWhere('page_id', $home->id)['build_status']);
    }

    public function test_ready_page_job_is_idempotent_and_only_queues_final_staging(): void
    {
        Queue::fake();
        [$trial, $home, $inner] = $this->trialBundle();
        $inner->forceFill(['blocks' => [['type' => 'about', 'heading' => 'Already generated']]])->save();
        $manifest = $trial->bundle_manifest;
        $manifest['pages'][0]['build_status'] = 'ready';
        $manifest['pages'][1]['build_status'] = 'scheduled';
        $trial->forceFill(['bundle_manifest' => $manifest, 'bundle_status' => 'building'])->save();
        $pageCount = $trial->website->pages()->count();

        (new BuildTrialSiteBundleJob($trial->id, $inner->id))->handle(
            app(AiPageGenerationService::class),
            app(LunaPexelsVideoService::class),
        );

        $this->assertSame($pageCount, $trial->website->pages()->count());
        $this->assertSame('Already generated', data_get($inner->fresh()->blocks, '0.heading'));
        $this->assertSame('ready', $trial->fresh()->bundle_status);
        Queue::assertPushed(FinalizeTrialSiteBundleJob::class, fn (FinalizeTrialSiteBundleJob $job): bool => $job->trialId === $trial->id);
        Queue::assertNotPushed(BuildTrialSiteBundleJob::class);
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
