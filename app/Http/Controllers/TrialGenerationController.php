<?php

namespace App\Http\Controllers;

use App\AI\Registries\IndustryMenuRegistry;
use App\Jobs\SendTrialAccessLinkJob;
use App\Models\MediaPack;
use App\Models\Page;
use App\Models\TrialGeneration;
use App\Services\TrialCreditService;
use App\Models\Website;
use App\Services\AiPageGenerationService;
use App\Services\IndustryResolver;
use App\Services\MyBrandThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

    public function __construct(
        private readonly AiPageGenerationService $pageGenerationService,
        private readonly IndustryResolver $industryResolver,
        private readonly MyBrandThemeService $myBrandThemes
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
        set_time_limit(240);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $ipKey = 'trial-generation:ip:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($ipKey, 2)) {
            return back()->withErrors([
                'prompt' => 'You have already generated two drafts today. Please try again tomorrow.',
            ]);
        }

        RateLimiter::hit($ipKey, 86400);

        $profile = $this->profileFromPrompt($validated['prompt']);
        $generationPrompt = $this->buildPrompt($profile);

        $initialThemeSettings = $this->myBrandThemes->ensureInSettings(
            $this->previewThemeForIndustry($profile['industry'])
        );

        $trial = TrialGeneration::create([
            ...$profile,
            'token' => (string) Str::uuid(),
            'prompt' => $validated['prompt'],
            'status' => 'generating',
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'menu_structure' => IndustryMenuRegistry::for($profile['industry']),
            'preview_theme' => $initialThemeSettings,
            'guest_credits' => TrialCreditService::STARTING_BALANCE,
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
            Log::info('[TrialGeneration] Starting synchronous trial generation.', ['trial' => $trial->id]);

            $generated = $this->pageGenerationService->generateTrialPage($generationPrompt, $trial->id);

            Log::info('[TrialGeneration] AI generation completed.', [
                'trial' => $trial->id,
                'sections' => count($generated['sections'] ?? []),
                'blocks' => count($generated['blocks'] ?? []),
            ]);

            $remoteImages = array_values($generated['remote_images'] ?? []);
            $targetImageCount = min(10, max(0, (int) ($generated['target_image_count'] ?? 0)));

            $mediaPack->update([
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

            $page = DB::transaction(function () use ($trial, $profile, $generated) {
                $trialWebsiteId = (int) config('cosmic.trial_website_id', 1);

                abort_if($trialWebsiteId < 1, 500, 'TRIAL_WEBSITE_ID must reference a valid website.');

                $website = Website::query()->findOrFail($trialWebsiteId);

                $website->update([
                    'industry' => $this->industryResolver->resolve($profile['industry'], 'default'),
                    'location' => $profile['location'],
                    'business_description' => $profile['business_description'],
                ]);

                $page = $website->pages()->create([
                    'title' => $profile['business_name'],
                    'slug' => $this->uniqueDemoSlug($website, $profile['business_name']),
                    'parent_id' => null,
                    'sort_order' => ((int) $website->pages()->whereNull('parent_id')->max('sort_order')) + 1,
                    'page_type' => 'standard',
                    'blocks' => $generated['blocks'],
                    'status' => 'draft',
                ]);

                $trial->update([
                    'page_id' => $page->id,
                    'sections' => $generated['sections'],
                    'generated_blocks' => $generated['blocks'],
                    'status' => 'ready',
                    'error_message' => null,
                ]);

                return $page;
            });

            Log::info('[TrialGeneration] Trial page persisted and ready.', [
                'trial' => $trial->id,
                'page' => $page->id,
            ]);

            Log::info('[MediaPack] Trial remote preview ready; local download deferred until purchase.', [
                'media_pack_id' => $mediaPack->id,
                'trial' => $trial->id,
                'page' => $page->id,
                'provider' => 'unsplash',
                'remote_image_count' => count($remoteImages),
                'target_image_count' => $targetImageCount,
            ]);

            // Keep the public Start -> Builder hand-off on the browser's current
            // origin. Using route() here creates an absolute URL from APP_URL, which
            // can differ from the actual local/dev origin (localhost vs 127.0.0.1,
            // custom ports, proxies) and make an otherwise successful generation
            // appear to stop after the loading overlay disappears.
            $builderUrl = route('pages.builder', [
                'page' => $page,
                'token' => $trial->token,
            ], false);

            Log::info('[TrialGeneration] Builder redirect prepared.', [
                'trial' => $trial->id,
                'page' => $page->id,
                'builder_url' => $builderUrl,
            ]);

            // The public Start page submits with Axios. Always return a stable JSON
            // contract here instead of relying on content-negotiation, which can be
            // affected by Inertia headers and leave the client without builder_url.
            return response()->json([
                'status' => 'ready',
                'trial_id' => $trial->id,
                'page_id' => $page->id,
                'token' => $trial->token,
                'trial_token' => $trial->token,
                'builder_url' => $builderUrl,
                'redirect_url' => $builderUrl,
                'url' => $builderUrl,
                'media_pack_uuid' => $mediaPack->uuid,
                'media_pack_status' => $mediaPack->fresh()->status,
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

        $email = Str::lower($validated['email']);
        $welcomeAlreadySentToThisEmail = $trial->welcome_email_sent_at
            && Str::lower((string) $trial->welcome_email_address) === $email;

        $trial->update([
            'email' => $email,
            'email_captured_at' => now(),
            'last_saved_at' => now(),
        ]);

        if (! $welcomeAlreadySentToThisEmail) {
            $this->sendTrialAccessEmail($trial, false);
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
        ]);

        $trialCredits->ensureCanSpend($trial, TrialCreditService::REGENERATE_PAGE, 'Page regeneration');

        $profile = [
            'business_name' => $trial->business_name,
            'industry' => $trial->industry,
            'location' => $trial->location,
            'business_description' => $trial->business_description,
            'prompt' => $validated['prompt'],
        ];

        try {
            $currentPage = $trial->page()->firstOrFail();
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
            $newToken = (string) Str::uuid();

            DB::transaction(function () use ($trial, $generated, $validated, $newToken) {
                $trial->page()->lockForUpdate()->firstOrFail()->update([
                    'blocks' => $generated['blocks'],
                    'status' => 'draft',
                    'publish_error' => null,
                ]);

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
                    'prompt' => $validated['prompt'],
                    'sections' => $generated['sections'],
                    'generated_blocks' => $generated['blocks'],
                    'last_saved_at' => now(),
                    'error_message' => null,
                ]);
            });

            $trial->refresh();
            $balance = $trialCredits->consume($trial, TrialCreditService::REGENERATE_PAGE, 'regenerate_page', ['prompt' => $validated['prompt']]);
            $this->sendTrialAccessEmail($trial, true);

            return response()->json([
                'message' => 'Your landing page was regenerated successfully.',
                'cost' => TrialCreditService::REGENERATE_PAGE,
                'credit_balance' => $balance,
                'redirect_url' => route('pages.builder', ['page' => $trial->page_id, 'token' => $trial->token]),
                'token' => $trial->token,
            ]);
        } catch (TransporterException $exception) {
            Log::warning('Trial regeneration unavailable', ['trial' => $trial->id, 'message' => $exception->getMessage()]);
            return response()->json(['message' => 'Cosmic AI took too long to respond. Your existing page was preserved.'], 503);
        } catch (\Throwable $exception) {
            Log::error('Trial regeneration failed', ['trial' => $trial->id, 'message' => $exception->getMessage()]);
            return response()->json(['message' => 'Regeneration failed. Your existing page was preserved.'], 500);
        }
    }

    private function sendTrialAccessEmail(TrialGeneration $trial, bool $regenerated): void
    {
        if (blank($trial->email) || ! $trial->page_id) {
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

    private function previewThemeForIndustry(?string $industry): array
    {
        $primary = match ($industry) {
            'Restaurant', 'Coffee Shop', 'Bakery' => 'terracotta',
            'Automotive', 'Construction', 'Electrician', 'Plumbing', 'Roofing' => 'asphalt',
            'Fitness', 'Real Estate', 'Landscaping', 'Salon & Beauty', 'Cleaning' => 'emerald',
            'Technology' => 'void',
            'Hotel & Resort', 'Travel' => 'sapphire',
            'Law Firm', 'Finance' => 'obsidian',
            default => 'midnight',
        };

        return [
            'primary' => $primary,
            'secondary' => 'white',
            'tertiary' => 'stone',
            'auto' => true,
        ];
    }
}
