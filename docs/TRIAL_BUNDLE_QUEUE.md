# Trial/demo multi-page queue

The initial Home page is completed during the demo request so the visitor can enter Builder immediately. Every remaining page is generated sequentially by `BuildTrialSiteBundleJob`; the browser only polls the existing staging-status endpoint.

## Required processes

The default and recommended local connection is Laravel's database queue. The standard Laravel `jobs`, `job_batches`, and `failed_jobs` migrations are already included.

```bash
php artisan migrate
php artisan queue:work database --queue=ai,default,mail --tries=3 --timeout=420
php artisan schedule:work
```

Production may supervise the worker and scheduler with Supervisor, systemd, or the host's process manager. Redis is optional; set `COSMIC_AI_QUEUE_CONNECTION` only when using another configured Laravel queue connection.

Relevant environment values:

```dotenv
QUEUE_CONNECTION=database
COSMIC_AI_QUEUE_CONNECTION=database
COSMIC_AI_QUEUE=ai
TRIAL_BUNDLE_STALE_MINUTES=15
DB_QUEUE_RETRY_AFTER=900
```

`retry_after` must remain longer than the worker/job timeout to prevent two workers from processing the same AI page concurrently.

## Recovery

Laravel retries transient page failures three times with 30/90/180-second backoff. Final failures are written to the existing page entry in `bundle_manifest`. Completed pages are never regenerated.

The scheduler runs this recovery command every five minutes:

```bash
php artisan trials:recover-bundles --limit=100
```

It queues the first unfinished page for a queued bundle, reclaims stale `scheduled`/`building` manifest entries, and retries missing final staging work. It does not regenerate `ready` pages.

## Focused QA

```bash
php artisan test --filter=TrialGenerationPipelineTest
```

Manual checks:

1. Start a six-page demo with the worker running and verify `1/6` advances to `6/6`.
2. Close the Builder at `1/6`; inspect the trial later and verify the database manifest reached `6/6`.
3. Refresh at any intermediate count; the Builder must restore progress from `/trials/{token}/staging-status`.
4. Stop and restart the worker during an inner page. Laravel retries the reserved database job after `retry_after`; the recovery command repairs stale manifest-only states.
5. Confirm every ready manifest entry maps to one existing page record with non-empty blocks and no duplicate slug/page.
6. Confirm Preview remains disabled until all required pages are ready and final staging has produced `staging_url`.
