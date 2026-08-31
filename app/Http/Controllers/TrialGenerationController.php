<?php

namespace App\Http\Controllers;

use App\AI\Registries\IndustryMenuRegistry;
use App\Jobs\BuildTrialSiteBundleJob;
use App\Jobs\SendTrialBundleReadyJob;
use App\Jobs\SendTrialAccessLinkJob;
use App\Models\MediaPack;
use App\Models\Page;
use App\Models\TrialGeneration;
use App\Services\TrialCreditService;
use App\Models\Website;
use App\Services\AiPageGenerationService;
use App\Services\IndustryResolver;
use App\Services\MyBrandThemeService;
use App\Services\TrialBrandContextService;
use App\Services\InitialTrialLogoService;
use App\Services\LunaPexelsVideoService;
use App\Services\LunaSiteBundlePlannerService;
use App\Services\TrialSiteBundleService;
use App\Services\TrialStagingPublisherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use OpenAI\Exceptions\TransporterException;

class TrialGenerationController extends Controller
{
    private const PLANS = ['starter', 'growth', 'pro', 'agency_starter', 'agency_growth', 'agency_pro'];

    private const INDUSTRY_FOLDERS = [
        'Automotive' => 'automotive', 'Bakery' => 'bakery', 'Cleaning' => 'cleaning',
        'Coffee Shop' => 'coffee', 'Construction' => 'construction', 'Dental Clinic' => 'dentist',
        'Education' => 'education', 'Electrician' => 'electrician', 'Finance' => 'finance',
        'Fitness' => 'fitness', 'Hotel & Resort' => 'hotel', 'Landscaping' => 'landscaping',
        'Law Firm' => 'lawyer', 'Medical Clinic' => 'medical', 'Plumbing' => 'plumbing',
        'Real Estate' => 'real-estate', 'Restaurant' => 'restaurant', 'Roofing' => 'roofing',
        'Salon & Beauty' => 'salon', 'Technology' => 'technology', 'Travel' => 'travel',
    ];

    private const INDUSTRY_KEYWORDS = [
        'Automotive' => ['automotive', 'car', 'auto repair', 'vehicle', 'garage', 'dealership'],
        'Bakery' => ['bakery', 'baker', 'pastry', 'bread', 'cake'],
        'Coffee Shop' => ['coffee shop', 'coffee', 'cafe', 'café'],
        'Construction' => ['construction', 'contractor', 'renovation', 'builder'],
        'Dental Clinic' => ['dental', 'dentist', 'dentistry', 'teeth', 'implant'],
        'Fitness' => ['fitness', 'gym', 'workout', 'personal training'],
        'Hotel & Resort' => ['hotel', 'resort', 'accommodation', 'rooms', 'hospitality'],
        'Law Firm' => ['law firm', 'lawyer', 'attorney', 'legal'],
        'Medical Clinic' => ['medical', 'clinic', 'healthcare', 'doctor'],
        'Real Estate' => ['real estate', 'property', 'realtor', 'realty'],
        'Restaurant' => ['restaurant', 'dining', 'food', 'pizza', 'pasta', 'catering'],
        'Salon & Beauty' => ['salon', 'beauty', 'spa', 'hair'],
        'Technology' => ['technology', 'software', 'saas', 'ecommerce', 'digital marketing', 'website development'],
        'Travel' => ['travel', 'tour', 'tourism', 'holiday'],
    ];

    public function uploadImage(Request $request, TrialGeneration $trial)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp,avif,heic,heif|max:8192',
        ]);

        $file = $request->file('image');
        $path = $file->store("trials/{$trial->id}/images", 'public');

        return response()->json([
            'success' => true,
            'url' => rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/' . $path,
        ]);
    }

    public function __construct(
        private readonly AiPageGenerationService $pageGenerationService,
        private readonly IndustryResolver $industryResolver,
        private readonly MyBrandThemeService $myBrandThemes,
        private readonly TrialBrandContextService $trialBrandContext,
        private readonly InitialTrialLogoService $initialTrialLogo,
        private readonly LunaPexelsVideoService $lunaVideos,
        private readonly LunaSiteBundlePlannerService $siteBundlePlanner,
        private readonly TrialSiteBundleService $trialSiteBundles,
        private readonly TrialStagingPublisherService $trialStagingPublisher,
    ) {
    }

    public function create(Request $request)
    {
        $trial = null;
        if ($request->filled('trial')) {
            $trial = TrialGeneration::where('token', $request->string('trial'))->first();

            // H14: every trial owns one persistent My Brand Theme from first load.
            // Backfill older local/test tokens so the Theme modal is never empty.
            if ($trial) {
                $themeSettings = is_array($trial->preview_theme)
                    ? $trial->preview_theme
                    : $this->previewThemeForIndustry($trial->industry);
                $seededSettings = $this->myBrandThemes->ensureInSettings($themeSettings);

                if ($seededSettings !== $themeSettings) {
                    $trial->update(['preview_theme' => $seededSettings]);
                    $trial->setAttribute('preview_theme', $seededSettings);
                }
            }
        }

        return Inertia::render('Start', [
            'trial' => $trial ? [
                'token' => $trial->token,
                'business_name' => $trial->business_name,
                'industry' => $trial->industry,
                'preview_theme' => $trial->preview_theme ?? $this->previewThemeForIndustry($trial->industry),
                'navigation' => collect($trial->menu_structure ?? IndustryMenuRegistry::for($trial->industry))
                    ->pluck('title')
                    ->values()
                    ->all(),
                'status' => $trial->status,
                'sections' => $trial->sections,
                'generated_blocks' => $trial->status === 'ready' && ! $trial->claimed_at
                    ? $trial->generated_blocks
                    : [],
                'blocks_count' => is_array($trial->generated_blocks) ? count($trial->generated_blocks) : 0,
                'error_message' => $trial->error_message,
                'claimed_at' => $trial->claimed_at?->toIso8601String(),
                'selected_plan' => $trial->selected_plan,
            ] : null,
            'industries' => array_keys(self::INDUSTRY_FOLDERS),
        ]);
    }

    public function store(Request $request)
    {
        set_time_limit(480);

        // Batch 2 keeps the legacy prompt-only contract working for Luna while
        // adding the structured Create Free Demo payload used by Welcome.
        $structuredRequest = $request->filled('website_name')
            || $request->filled('industry')
            || $request->filled('email')
            || $request->filled('additional_prompt');

        $validated = $request->validate($structuredRequest ? [
            'website_name' => ['required', 'string', 'min:2', 'max:120'],
            'industry' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'additional_prompt' => ['nullable', 'string', 'max:1000'],
            // Accepted only for forwards compatibility; the structured fields
            // remain the source of truth for business name, industry and email.
            'prompt' => ['nullable', 'string', 'max:2000'],
        ] : [
            'prompt' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $email = $structuredRequest
            ? Str::lower(trim((string) $validated['email']))
            : null;

        if ($structuredRequest) {
            $emailAlreadyOwnsAnotherTrial = TrialGeneration::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereNotNull('email_captured_at')
                ->where('status', '!=', 'failed')
                ->exists();

            if ($emailAlreadyOwnsAnotherTrial) {
                throw ValidationException::withMessages([
                    'email' => 'This email already has a trial website. Enter another email address.',
                ]);
            }
        }

        $ipKey = 'trial-generation:ip:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($ipKey, 2)) {
            if ($structuredRequest) {
                throw ValidationException::withMessages([
                    'email' => 'You have already generated two drafts today. Please try again tomorrow.',
                ]);
            }

            return back()->withErrors([
                'prompt' => 'You have already generated two drafts today. Please try again tomorrow.',
            ]);
        }

        RateLimiter::hit($ipKey, 86400);

        if ($structuredRequest) {
            $websiteName = Str::of((string) $validated['website_name'])->squish()->limit(120, '')->toString();
            $industry = Str::of((string) $validated['industry'])->squish()->limit(120, '')->toString();
            $additionalPrompt = Str::of((string) ($validated['additional_prompt'] ?? ''))->squish()->limit(1000, '')->toString();
            $userPrompt = $this->structuredDemoPrompt($websiteName, $industry, $additionalPrompt);
            $profile = [
                'email' => $email,
                'business_name' => $websiteName,
                'industry' => $industry,
                'location' => 'Not specified',
                'business_description' => $additionalPrompt !== ''
                    ? $additionalPrompt
                    : "{$websiteName} is a {$industry} business.",
            ];
        } else {
            $userPrompt = (string) $validated['prompt'];
            $profile = $this->profileFromPrompt($userPrompt);
        }

        $bundlePlan = $this->siteBundlePlanner->plan($userPrompt, $profile['industry']);
        $bundleMenu = collect($bundlePlan['pages'] ?? [])->map(fn (array $page) => [
            'title' => (string) $page['title'],
            'slug' => (string) $page['slug'],
            'is_home' => (bool) ($page['is_home'] ?? false),
            'sort_order' => (int) ($page['sort_order'] ?? 1),
            'page_type' => (string) ($page['page_type'] ?? 'standard'),
        ])->values()->all();

        // Provisional theme only while the trial row is being created. The
        // final prompt-aware theme is committed after page/content generation
        // and before logo generation, so Luna always receives the finished
        // website direction rather than an early placeholder family.
        $initialThemeSettings = $this->myBrandThemes->ensureInSettings(
            $this->previewThemeForIndustry($profile['industry'])
        );

        $brandContext = $this->trialBrandContext->build($profile, $userPrompt);

        $trial = TrialGeneration::create([
            ...$profile,
            'token' => (string) Str::uuid(),
            // `prompt` stays backward-compatible; brand_prompt is immutable brand intent.
            'prompt' => $userPrompt,
            'brand_prompt' => $userPrompt,
            'latest_user_prompt' => $userPrompt,
            'prompt_history' => $this->trialBrandContext->appendPromptHistory([], $userPrompt, 'initial_generation'),
            'brand_context' => $brandContext,
            'status' => 'generating',
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'menu_structure' => $bundleMenu,
            'bundle_manifest' => $bundlePlan,
            'bundle_status' => 'planning',
            'preview_theme' => $initialThemeSettings,
            'guest_credits' => TrialCreditService::STARTING_BALANCE,
            // Structured Welcome demos capture ownership before any AI work so
            // Builder never needs to ask for the same email again.
            'email_captured_at' => $structuredRequest ? now() : null,
            'last_saved_at' => $structuredRequest ? now() : null,
        ]);

        $mediaPack = MediaPack::create([
            'uuid' => (string) Str::uuid(),
            'owner_type' => 'trial',
            'owner_id' => $trial->id,
            'trial_generation_id' => $trial->id,
            'status' => 'pending',
            'target_image_count' => 0,
            'keywords' => [],
        ]);

        $trial->update(['media_pack_id' => $mediaPack->id]);

        try {
            // Build Home before handing the visitor to Builder. This keeps the
            // first screen useful while every inner page remains asynchronous.
            Log::info('[TrialGeneration] Creating Home-first trial shell.', [
                'trial' => $trial->id,
                'structured' => $structuredRequest,
                'industry' => $profile['industry'],
            ]);

            $finalThemeSettings = $this->myBrandThemes->ensureInSettings(
                $this->regenerationThemeForPrompt($profile['industry'], $userPrompt)
            );
            $finalThemeSettings['overlay_header_on_banner'] = false;
            $trial->update(['preview_theme' => $finalThemeSettings]);
            $trial->setAttribute('preview_theme', $finalThemeSettings);

            $mediaPack->update([
                'keywords' => [],
                'target_image_count' => 0,
                'status' => 'ready',
                'manifest' => [
                    'mode' => 'remote_trial_bundle',
                    'provider' => 'unsplash',
                    'remote_only' => true,
                    'image_count' => 0,
                    'target_image_count' => 0,
                    'images' => [],
                    'updated_at' => now()->toIso8601String(),
                ],
                'queued_at' => null,
                'completed_at' => now(),
                'last_error' => null,
            ]);

            $page = $this->trialSiteBundles->create($trial, $profile, $bundlePlan, [
                'sections' => [],
                'blocks' => [],
            ]);

            $freshTrial = $trial->fresh();
            $homeRecipe = collect((array) data_get($freshTrial->bundle_manifest, 'pages', []))
                ->first(fn (array $item) => (bool) ($item['is_home'] ?? false));

            if (is_array($homeRecipe) && (int) ($homeRecipe['page_id'] ?? 0) > 0) {
                BuildTrialSiteBundleJob::dispatchSync($trial->id, (int) $homeRecipe['page_id']);
            }

            $page->refresh();
            $freshTrial = $trial->fresh();
            $homeStatus = collect((array) data_get($freshTrial->bundle_manifest, 'pages', []))
                ->firstWhere('page_id', $page->id)['build_status'] ?? null;
            if (! is_array($page->blocks) || $page->blocks === [] || $homeStatus !== 'ready') {
                throw new \RuntimeException($freshTrial->bundle_error ?: 'The trial Home page could not be completed.');
            }

            // Structured Welcome demos also receive one complimentary starter logo.
            // The Builder already recognizes this exact sync source and opens its
            // existing 650x200 cropper on first entry, so no duplicate crop UI is
            // needed here. Logo generation is best-effort and never blocks a demo.
            $initialLogoUrl = null;
            if ($structuredRequest) {
                $initialLogoUrl = $this->initialTrialLogo->generate($trial->fresh());
                $trial->refresh();
            }

            $builderUrl = route('pages.builder', [
                'page' => $page,
                'token' => $trial->token,
            ], false);

            // Email is already captured by the structured Welcome form. Send
            // the private Builder link automatically after Home is confirmed,
            // but never strand a successfully built demo because mail delivery
            // itself is temporarily unavailable.
            if ($structuredRequest) {
                $freshTrial = $trial->fresh();
                try {
                    $this->sendTrialAccessEmail($freshTrial, false);
                } catch (\Throwable $mailException) {
                    Log::error('[TrialGeneration] Welcome email could not be queued/sent after build.', [
                        'trial' => $trial->id,
                        'email' => $freshTrial->email,
                        'message' => $mailException->getMessage(),
                    ]);
                    report($mailException);
                }

                $this->sendTrialLeadNotification($freshTrial);
            }

            Log::info('[TrialGeneration] Home-first Builder redirect prepared.', [
                'trial' => $trial->id,
                'page' => $page->id,
                'builder_url' => $builderUrl,
                'email_captured' => $structuredRequest,
            ]);

            return response()->json([
                'status' => 'building',
                'trial_id' => $trial->id,
                'page_id' => $page->id,
                'token' => $trial->token,
                'trial_token' => $trial->token,
                'builder_url' => $builderUrl,
                'redirect_url' => $builderUrl,
                'url' => $builderUrl,
                'media_pack_uuid' => $mediaPack->uuid,
                'media_pack_status' => $mediaPack->fresh()->status,
                'bundle_key' => data_get($freshTrial->bundle_manifest, 'bundle_key'),
                'bundle_status' => $freshTrial->bundle_status,
                'bundle_page_count' => (int) data_get($freshTrial->bundle_manifest, 'page_count', 1),
                'email_captured' => $structuredRequest,
                'welcome_email_requested' => $structuredRequest,
                'initial_logo_generated' => filled($initialLogoUrl),
                'message' => 'Your Home page is ready. The remaining pages will continue automatically in the background.',
            ])->header('X-Cosmic-Builder-Url', $builderUrl);
        } catch (TransporterException $exception) {
            Log::warning('Public trial generation unavailable', [
                'trial' => $trial->id,
                'message' => $exception->getMessage(),
            ]);

            RateLimiter::clear($ipKey);
            $trial->update([
                'status' => 'failed',
                'error_message' => 'Cosmic AI took too long to respond. Please try again in a moment.',
            ]);
        } catch (\Throwable $exception) {
            Log::error('Public trial generation failed', [
                'trial' => $trial->id,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            RateLimiter::clear($ipKey);
            $trial->update([
                'status' => 'failed',
                'error_message' => 'We could not generate this draft. Please try again later.',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $trial->error_message ?: 'We could not generate this draft. Please try again later.',
                'trial' => $trial->token,
            ], 500);
        }

        return redirect()->route('start', ['trial' => $trial->token]);
    }

    public function mediaPackStatus(TrialGeneration $trial)
    {
        $pack = $trial->mediaPack;

        $status = (string) ($pack?->status ?? 'missing');
        $imageCount = $status === 'localizing'
            ? (int) data_get($pack?->manifest, 'localized_image_count', 0)
            : (int) data_get($pack?->manifest, 'image_count', 0);
        $target = (int) ($pack?->target_image_count ?? 0);
        $ratio = $target > 0 ? min(1, $imageCount / $target) : 0;
        $progress = match ($status) {
            'pending' => 5,
            'queued' => 12,
            'downloading', 'localizing' => min(95, 20 + (int) round($ratio * 75)),
            'ready', 'partial', 'failed' => 100,
            default => 0,
        };
        $delayed = in_array($status, ['queued', 'downloading', 'localizing'], true)
            && $pack?->queued_at
            && $pack->queued_at->lt(now()->subSeconds(45));

        return response()->json([
            'status' => $status,
            'queue' => config('openai.media_pack_queue', 'images-high'),
            'image_count' => $imageCount,
            'target_image_count' => $target,
            'progress' => $progress,
            'completed_at' => $pack?->completed_at?->toIso8601String(),
            'ready' => in_array($status, ['ready', 'partial'], true),
            'terminal' => in_array($status, ['ready', 'partial', 'failed'], true),
            'delayed' => (bool) $delayed,
            'message' => match (true) {
                $status === 'ready' && data_get($pack?->manifest, 'remote_only') => 'Preview images ready',
                $status === 'ready' => 'Images ready',
                $status === 'partial' => 'Using the best available images',
                $status === 'failed' => 'Using safe fallback images',
                $delayed => 'Images are taking longer than usual; you can keep editing',
                $status === 'localizing' => 'Saving your preview images securely in the background',
                $status === 'downloading' => 'Optimizing images in the background',
                default => 'Preparing images',
            },
        ]);
    }

    public function stagingStatus(TrialGeneration $trial)
    {
        abort_unless($trial->status === 'ready' && ! $trial->claimed_at, 404);

        $trial = $trial->fresh(['website']);
        $manifestPages = collect(data_get($trial?->bundle_manifest, 'pages', []))->sortBy('sort_order')->values();
        $readyPages = $manifestPages->where('build_status', 'ready')->count();
        $pageCount = $manifestPages->count();
        $bundleStatus = (string) ($trial?->bundle_status ?? 'missing');
        $pagesComplete = $pageCount > 0 && $readyPages >= $pageCount && $bundleStatus === 'ready';
        $activePage = $manifestPages->first(fn (array $page): bool => in_array(($page['build_status'] ?? null), ['building', 'scheduled'], true))
            ?: $manifestPages->first(fn (array $page): bool => ($page['build_status'] ?? null) === 'queued');

        $previewUrl = null;
        $previewError = null;

        if ($pagesComplete) {
            try {
                // Preview is intentionally withheld until the complete bundle is
                // built. This keeps the trial CTA deterministic: 100% means the
                // user can open the entire demo, not a progressively staged subset.
                $previewUrl = $this->trialStagingPublisher->existingUrl($trial);
            } catch (\Throwable $exception) {
                Log::warning('[TrialStaging] Complete preview finalization deferred.', [
                    'trial_id' => $trial->id,
                    'message' => $exception->getMessage(),
                ]);
                $previewError = 'All pages are built, but the final preview link is still being prepared.';
            }
        }

        $previewReady = $pagesComplete && filled($previewUrl);
        $progress = $pageCount > 0 ? (int) round(($readyPages / $pageCount) * 100) : 0;

        return response()->json([
            'status' => $previewReady ? 'ready' : $bundleStatus,
            'bundle_status' => $bundleStatus,
            'preview_url' => $previewReady ? $previewUrl : null,
            'preview_ready' => $previewReady,
            'pages_complete' => $pagesComplete,
            'ready_pages' => $readyPages,
            'page_count' => $pageCount,
            'progress' => $progress,
            'current_page_title' => is_array($activePage) ? (string) ($activePage['title'] ?? '') : null,
            'pages' => $manifestPages->map(fn (array $page): array => [
                'id' => (int) ($page['page_id'] ?? 0),
                'title' => (string) ($page['title'] ?? 'Page'),
                'slug' => (string) ($page['slug'] ?? ''),
                'is_home' => (bool) ($page['is_home'] ?? false),
                'status' => (string) ($page['build_status'] ?? 'queued'),
                'attempts' => (int) ($page['build_attempts'] ?? 0),
                'error' => $page['build_error'] ?? null,
            ])->values()->all(),
            'terminal' => $previewReady || in_array($bundleStatus, ['failed', 'partial'], true),
            'poll_after_ms' => 2500,
            'preview_error' => $previewError ?: ($trial?->bundle_error ?: null),
            'message' => match (true) {
                $previewReady => 'Your complete demo website is ready.',
                $pagesComplete => 'All pages are built. Finalizing your preview.',
                $bundleStatus === 'failed' => 'The demo build stopped because one or more pages could not be prepared.',
                $bundleStatus === 'partial' => 'The demo build finished with an incomplete page.',
                filled(data_get($activePage, 'title')) => 'Creating '.data_get($activePage, 'title').' page…',
                default => "Building {$readyPages} of {$pageCount} pages.",
            },
        ])->header('Cache-Control', 'no-store, private');
    }

    public function selectPlan(Request $request, TrialGeneration $trial)
    {
        abort_unless($trial->status === 'ready' && ! $trial->claimed_at, 404);

        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:'.implode(',', self::PLANS)],
        ]);

        $trial->update([
            'selected_plan' => $validated['plan'],
            'plan_selected_at' => now(),
        ]);

        return redirect()->route('start', ['trial' => $trial->token]);
    }

    public function captureEmail(Request $request, TrialGeneration $trial)
    {
        abort_unless($trial->status === 'ready' && ! $trial->claimed_at, 404);

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $previousEmail = Str::lower((string) $trial->email);
        $emailAlreadyOwnsAnotherTrial = TrialGeneration::query()
            ->whereKeyNot($trial->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', 'ready')
            ->whereNotNull('email_captured_at')
            ->exists();

        if ($emailAlreadyOwnsAnotherTrial) {
            throw ValidationException::withMessages([
                'email' => 'This email already has a trial website. Enter another email address.',
            ]);
        }

        $isNewLeadEmail = $previousEmail !== $email || blank($trial->email_captured_at);
        $welcomeAlreadySentToThisEmail = $trial->welcome_email_sent_at
            && Str::lower((string) $trial->welcome_email_address) === $email;

        $trial->update([
            'email' => $email,
            'email_captured_at' => now(),
            'last_saved_at' => now(),
        ]);

        if ($trial->fresh()->bundle_status === 'ready') {
            SendTrialBundleReadyJob::dispatch($trial->id);
        }

        $trial->refresh();

        if (! $welcomeAlreadySentToThisEmail) {
            $this->sendTrialAccessEmail($trial, false);
        }

        // Admin lead alert is deliberately separate from the visitor welcome email.
        // A failure here must never prevent the visitor from saving the trial.
        if ($isNewLeadEmail) {
            $this->sendTrialLeadNotification($trial);
        }

        return response()->json([
            'message' => $welcomeAlreadySentToThisEmail
                ? 'Your email is saved and your private Builder link is still active.'
                : 'Your Cosmic CMS welcome email and private Builder link are on the way.',
            'email' => $trial->email,
            'welcome_email_queued' => ! $welcomeAlreadySentToThisEmail,
        ]);
    }

    public function regenerate(Request $request, TrialGeneration $trial, TrialCreditService $trialCredits)
    {
        set_time_limit(240);
        abort_unless($trial->status === 'ready' && ! $trial->claimed_at && $trial->page_id, 404);
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:10', 'max:2000'],
            // R2 safety: this endpoint is reserved for the main Trial Regenerate action.
            // Block/section/image AI actions must keep using their own endpoints.
            'mode' => ['required', 'string', 'in:full_trial'],
        ]);

        $trialCredits->ensureCanSpend($trial, TrialCreditService::REGENERATE_PAGE, 'Page regeneration');

        // R1: the main Trial Regenerate action is a full website regeneration.
        // Re-resolve the business/industry from the NEW prompt so a user can pivot
        // from (for example) a cruise company to an AI SaaS without stale brand data.
        $profile = $this->profileFromPrompt($validated['prompt']);
        $profile['prompt'] = $validated['prompt'];
        // Theme is a website-level decision. Full regeneration keeps the established
        // family unless the visitor explicitly asks for a new color/theme direction.
        $freshThemeSettings = $this->promptRequestsThemeChange($validated['prompt'])
            ? $this->myBrandThemes->ensureInSettings(
                $this->regenerationThemeForPrompt(
                    $profile['industry'],
                    $validated['prompt'],
                    (string) data_get($trial->preview_theme, 'primary', '')
                )
            )
            : (array) $trial->preview_theme;
        $freshBundlePlan = $this->siteBundlePlanner->plan($validated['prompt'], $profile['industry']);
        $freshMenuStructure = collect($freshBundlePlan['pages'] ?? [])->map(fn (array $page) => [
            'title' => (string) $page['title'],
            'slug' => (string) $page['slug'],
            'is_home' => (bool) ($page['is_home'] ?? false),
            'sort_order' => (int) ($page['sort_order'] ?? 1),
            'page_type' => (string) ($page['page_type'] ?? 'standard'),
        ])->values()->all();
        $freshBrandContext = $this->trialBrandContext->build($profile, $validated['prompt']);

        try {
            $currentPage = $trial->page()->firstOrFail();
            // Keep a safe branding fallback until the fresh AI logo is successfully generated.
            $previousBranding = [
                'logo_url' => $trial->logo_url,
                'logo_company_name' => $trial->logo_company_name,
                'logo_source' => $trial->logo_source,
                'logo_theme_sync_state' => $trial->logo_theme_sync_state,
                'logo_theme_sync_source' => $trial->logo_theme_sync_source,
                'logo_theme_synced_theme' => $trial->logo_theme_synced_theme,
                'logo_updated_at' => $trial->logo_updated_at,
                'brand_original_logo_url' => data_get($trial->preview_theme, 'brand_original_logo_url'),
                'brand_active_logo_url' => data_get($trial->preview_theme, 'brand_active_logo_url'),
                'brand_favicon_url' => data_get($trial->preview_theme, 'brand_favicon_url'),
                'brand_logo_variants' => data_get($trial->preview_theme, 'brand_logo_variants', []),
            ];
            $previousSections = collect($currentPage->blocks ?? [])
                ->filter(fn ($block) => is_array($block))
                ->map(fn ($block) => (string) ($block['type'] ?? ''))
                ->filter()
                ->values()
                ->all();

            $generated = $this->pageGenerationService->generateTrialPage(
                $this->buildPrompt($profile),
                $trial->id,
                [
                    'previous_sections' => $previousSections,
                    // Forces a fresh planner-cache key on each regeneration.
                    'nonce' => (string) Str::uuid(),
                ]
            );
            $generated['blocks'] = $this->lunaVideos->apply($this->buildPrompt($profile), $generated['blocks'] ?? []);
            // A full trial regeneration also returns to the safe, clean default.
            // Luna's visual-intent overlay suggestion is intentionally ignored
            // for trial generations; the visitor can opt in again from Builder.
            $freshThemeSettings['overlay_header_on_banner'] = false;
            $newToken = (string) Str::uuid();

            DB::transaction(function () use ($trial, $generated, $validated, $newToken, $profile, $freshThemeSettings, $freshMenuStructure, $freshBundlePlan, $freshBrandContext) {
                $this->trialSiteBundles->rebuild($trial, $profile, $freshBundlePlan, $generated, $freshThemeSettings);

                $remoteImages = array_values($generated['remote_images'] ?? []);
                $targetImageCount = min(10, max(0, (int) ($generated['target_image_count'] ?? 0)));
                if ($trial->mediaPack) {
                    $trial->mediaPack->update([
                        'keywords' => array_values($generated['media_keywords'] ?? []),
                        'target_image_count' => $targetImageCount,
                        'status' => 'ready',
                        'manifest' => [
                            'mode' => 'remote_trial_preview',
                            'provider' => 'unsplash',
                            'remote_only' => true,
                            'image_count' => count($remoteImages),
                            'target_image_count' => $targetImageCount,
                            'images' => $remoteImages,
                            'updated_at' => now()->toIso8601String(),
                        ],
                        'queued_at' => null,
                        'completed_at' => now(),
                        'last_error' => null,
                    ]);
                }

                DB::table('trial_regenerations')->insert([
                    'trial_generation_id' => $trial->id,
                    'email' => Str::lower($trial->email ?: ('guest-'.$trial->id.'@trial.local')),
                    'prompt' => $validated['prompt'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $trial->update([
                    'token' => $newToken,
                    // A full regeneration establishes a fresh brand/site direction.
                    'business_name' => $profile['business_name'],
                    'industry' => $profile['industry'],
                    'location' => $profile['location'],
                    'business_description' => $profile['business_description'],
                    'prompt' => $validated['prompt'],
                    'brand_prompt' => $validated['prompt'],
                    'latest_user_prompt' => $validated['prompt'],
                    'prompt_history' => $this->trialBrandContext->appendPromptHistory(
                        $trial->prompt_history,
                        $validated['prompt'],
                        'full_regeneration'
                    ),
                    'brand_context' => $freshBrandContext,
                    'menu_structure' => $freshMenuStructure,
                    'preview_theme' => $freshThemeSettings,
                    'sections' => $generated['sections'],
                    'generated_blocks' => $generated['blocks'],
                    // Do not carry branding from a previous company into a newly regenerated site.
                    // The old file is left on disk for safety; only the trial reference is reset.
                    'logo_url' => null,
                    'logo_company_name' => null,
                    'logo_source' => null,
                    'logo_theme_sync_state' => null,
                    'logo_theme_sync_source' => null,
                    'logo_theme_synced_theme' => null,
                    'logo_updated_at' => null,
                    'last_saved_at' => now(),
                    'error_message' => null,
                ]);
            });

            $trial->refresh();

            // Regeneration follows the same database-owned sequential queue
            // contract as a fresh demo. Dispatch before returning so closing the
            // HTTP client cannot prevent the first inner page from being queued.
            BuildTrialSiteBundleJob::dispatchNext($trial->id);
            if($trial->fresh()->bundle_status==='ready'){
                \App\Jobs\FinalizeTrialSiteBundleJob::dispatch($trial->id);
            }

            // A full regeneration creates a fresh logo + favicon after the
            // new prompt, brand context and theme are committed. The Builder can
            // then reuse the same crop workflow for generated or uploaded logos.
            $freshLogoUrl = $this->initialTrialLogo->generate($trial);
            $logoRegenerated = filled($freshLogoUrl);

            if (! $logoRegenerated && filled($previousBranding['logo_url'])) {
                // Logo generation is best-effort. Never leave a previously branded
                // trial blank because the image provider had a transient failure.
                $fallbackTheme = is_array($trial->preview_theme) ? $trial->preview_theme : [];
                $fallbackTheme['brand_original_logo_url'] = $previousBranding['brand_original_logo_url'] ?: $previousBranding['logo_url'];
                $fallbackTheme['brand_active_logo_url'] = $previousBranding['brand_active_logo_url'] ?: $previousBranding['logo_url'];
                $fallbackTheme['brand_favicon_url'] = $previousBranding['brand_favicon_url'] ?: $previousBranding['logo_url'];
                $fallbackTheme['brand_logo_variants'] = is_array($previousBranding['brand_logo_variants']) ? $previousBranding['brand_logo_variants'] : [];

                $trial->update([
                    'logo_url' => $previousBranding['logo_url'],
                    'logo_company_name' => $previousBranding['logo_company_name'],
                    'logo_source' => 'regeneration-fallback',
                    'logo_theme_sync_state' => 'fallback',
                    'logo_theme_sync_source' => 'full_regeneration_logo_failure',
                    'logo_theme_synced_theme' => data_get($trial->preview_theme, 'primary'),
                    'logo_updated_at' => now(),
                    'preview_theme' => $fallbackTheme,
                ]);
                $trial->refresh();
            }

            $balance = $trialCredits->consume($trial, TrialCreditService::REGENERATE_PAGE, 'regenerate_page', [
                'prompt' => $validated['prompt'],
                'scope' => 'logo+favicon+theme+sparks+images+content+layout',
            ]);
            $this->sendTrialAccessEmail($trial, true);

            return response()->json([
                'message' => $logoRegenerated
                    ? 'Your full trial website and brand were regenerated successfully.'
                    : 'Your website was regenerated. The previous logo was kept because fresh logo generation was temporarily unavailable.',
                'cost' => TrialCreditService::REGENERATE_PAGE,
                'credit_balance' => $balance,
                'redirect_url' => route('pages.builder', ['page' => $trial->page_id, 'token' => $trial->token]),
                'token' => $trial->token,
                'regeneration_scope' => 'full_trial',
                'regenerated' => [
                    'logo' => $logoRegenerated,
                    'favicon' => $logoRegenerated,
                    'theme' => true,
                    'sparks' => true,
                    'images' => true,
                    'content' => true,
                    'layout' => true,
                ],
            ]);
        } catch (TransporterException $exception) {
            Log::warning('Trial regeneration unavailable', ['trial' => $trial->id, 'message' => $exception->getMessage()]);
            return response()->json(['message' => 'Cosmic AI took too long to respond. Your existing page was preserved.'], 503);
        } catch (\Throwable $exception) {
            Log::error('Trial regeneration failed', ['trial' => $trial->id, 'message' => $exception->getMessage()]);
            return response()->json(['message' => 'Regeneration failed. Your existing page was preserved.'], 500);
        }
    }

    private function sendTrialLeadNotification(TrialGeneration $trial): void
    {
        $recipient = strtolower(trim((string) config('cosmic-mail.lead_recipient')));
        if ($recipient === '' || $recipient === strtolower((string) $trial->email)) {
            return;
        }

        try {
            $trial->loadMissing('page.website');
            Mail::send('emails.trial-lead', ['trial' => $trial], function ($message) use ($recipient, $trial): void {
                $message
                    ->to($recipient)
                    ->from(
                        (string) config('mail.from.address', 'hello@cosmiccms.com'),
                        (string) config('mail.from.name', 'Cosmic CMS')
                    )
                    ->replyTo((string) $trial->email)
                    ->subject('New Cosmic CMS trial lead · '.(string) $trial->email);
            });

            Log::info('[TrialLeadMail] Sent.', [
                'trial_id' => $trial->id,
                'visitor_email' => $trial->email,
                'lead_recipient' => $recipient,
            ]);
        } catch (\Throwable $exception) {
            Log::error('[TrialLeadMail] Send failed.', [
                'trial_id' => $trial->id,
                'visitor_email' => $trial->email,
                'lead_recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
            report($exception);
        }
    }

    private function sendTrialAccessEmail(TrialGeneration $trial, bool $regenerated): void
    {
        if (blank($trial->email) || ! $trial->page_id) {
            return;
        }

        if ((bool) config('cosmic-mail.trial_mail_sync', true)) {
            SendTrialAccessLinkJob::dispatchSync($trial->id, $regenerated);
            return;
        }

        SendTrialAccessLinkJob::dispatch($trial->id, $regenerated);
    }

    private function uniqueDemoSlug(Website $website, string $businessName): string
    {
        $base = Str::slug($businessName) ?: 'demo';
        $slug = $base.'-'.Str::lower(Str::random(6));

        while ($website->pages()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }

    private function structuredDemoPrompt(string $websiteName, string $industry, string $additionalPrompt = ''): string
    {
        $prompt = "Create a premium, modern, responsive website for {$websiteName}, a {$industry} business. "
            ."Use suitable sections, professional content, relevant imagery, clear calls to action, and a visual direction appropriate for this industry.";

        if ($additionalPrompt !== '') {
            $prompt .= " Additional instructions: {$additionalPrompt}";
        }

        return $prompt;
    }

    private function buildPrompt(array $data): string
    {
        $request = trim((string) ($data['prompt'] ?? ''));

        return "Generate a professional website draft for the following business.\n\n"
            ."Business Name: {$data['business_name']}\n"
            ."Industry: {$data['industry']}\n"
            ."Location: {$data['location']}\n"
            ."About the business: {$data['business_description']}\n"
            .($request !== '' ? "\nAdditional request: {$request}\n" : '')
            ."\nUse a clear, trustworthy tone. Do not invent awards, certifications, customer statistics, or other unverifiable claims.";
    }

    private function profileFromPrompt(string $prompt): array
    {
        $industrySource = $prompt;

        // Prefer the business phrase supplied by the user. This lets Luna's
        // inferred industry become a stable reusable folder even when it is
        // outside the original hard-coded industry list.
        if (preg_match('/\bfor\s+(?:an?\s+)?(.+?)(?:\s+in\s+[^,.]+|[,.])/i', $prompt, $matches) === 1) {
            $industrySource = Str::of($matches[1])->squish()->limit(80, '')->toString();
        }

        $industry = $this->industryResolver->displayName($industrySource, 'Technology');

        $businessName = 'Your new website';
        if (preg_match('/\bfor\s+(?:an?\s+)?(.+?)(?:\s+in\s+[^,.]+|[,.])/i', $prompt, $matches)) {
            $businessName = Str::of($matches[1])->squish()->limit(80, '')->toString();
        }

        return [
            'email' => null,
            'business_name' => $businessName,
            'industry' => $industry,
            'location' => 'Not specified',
            'business_description' => $prompt,
        ];
    }

    private function regenerationThemeForPrompt(?string $industry, string $prompt, string $previousPrimary = ''): array
    {
        $promptLower = Str::lower($prompt);
        $preferred = match (true) {
            Str::contains($promptLower, ['luxury', 'premium', 'elegant', 'exclusive']) => 'obsidian',
            Str::contains($promptLower, ['playful', 'creative', 'colorful', 'fun']) => 'violet',
            Str::contains($promptLower, ['warm', 'organic', 'earthy', 'natural']) => 'terracotta',
            Str::contains($promptLower, ['clean', 'minimal', 'bright', 'simple']) => 'slate-light',
            Str::contains($promptLower, ['bold', 'dark', 'futuristic', 'ai', 'tech']) => 'void',
            Str::contains($promptLower, ['blue', 'trust', 'corporate', 'professional']) => 'sapphire',
            Str::contains($promptLower, ['green', 'eco', 'growth', 'fresh']) => 'emerald',
            default => (string) data_get($this->previewThemeForIndustry($industry), 'primary', 'midnight'),
        };

        // A full Regenerate should feel materially fresh. If the inferred family
        // equals the current one, choose a deterministic compatible alternate so
        // theme direction is regenerated too, not merely re-saved.
        if ($preferred === $previousPrimary) {
            $alternates = ['midnight', 'sapphire', 'emerald', 'terracotta', 'obsidian', 'violet', 'navy', 'forest'];
            $candidates = array_values(array_filter($alternates, fn ($key) => $key !== $previousPrimary));
            $preferred = $candidates[abs(crc32($prompt.'|'.$industry)) % count($candidates)] ?? 'midnight';
        }

        $settings = $this->previewThemeForIndustry($industry);
        $settings['primary'] = $preferred;

        return $settings;
    }

    private function previewThemeForIndustry(?string $industry): array
    {
        $industryKey = IndustryMenuRegistry::normalizeIndustry((string) $industry);
        $primary = match ($industryKey) {
            'restaurant', 'coffee', 'bakery' => 'terracotta',
            'automotive', 'construction', 'electrician', 'plumbing', 'roofing' => 'asphalt',
            'fitness', 'real-estate', 'landscaping', 'salon', 'cleaning' => 'emerald',
            'technology' => 'void',
            'hotel', 'travel' => 'sapphire',
            'lawyer', 'finance' => 'obsidian',
            default => 'midnight',
        };

        return [
            'primary' => $primary,
            'secondary' => 'white',
            'tertiary' => 'stone',
            'auto' => true,
        ];
    }
    private function promptRequestsThemeChange(string $prompt): bool
    {
        $p = Str::lower($prompt);

        return Str::contains($p, [
            'change the theme', 'change theme', 'new theme', 'different theme',
            'change the color', 'change color', 'colour palette', 'color palette',
            'new palette', 'different palette', 'make it green', 'make it blue',
            'make it purple', 'make it violet', 'make it terracotta', 'make it dark',
            'emerald theme', 'navy theme', 'indigo theme', 'midnight theme',
            'terracotta theme', 'violet theme', 'ocean theme', 'forest theme',
        ]);
    }

}
