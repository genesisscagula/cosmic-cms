<?php

namespace App\Http\Controllers;

use App\AI\Registries\IndustryMenuRegistry;
use App\Jobs\SendTrialAccessLinkJob;
use App\Models\Page;
use App\Models\TrialGeneration;
use App\Models\Website;
use App\Services\AiPageGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use OpenAI\Exceptions\TransporterException;

class TrialGenerationController extends Controller
{
    private const DEMO_WEBSITE_ID = 14;

    private const PLANS = ['starter', 'growth', 'pro'];

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
        private readonly AiPageGenerationService $pageGenerationService
    ) {
    }

    public function create(Request $request)
    {
        $trial = null;
        if ($request->filled('trial')) {
            $trial = TrialGeneration::where('token', $request->string('trial'))->first();
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

        $trial = TrialGeneration::create([
            ...$profile,
            'token' => (string) Str::uuid(),
            'prompt' => $validated['prompt'],
            'status' => 'generating',
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'menu_structure' => IndustryMenuRegistry::for($profile['industry']),
            'preview_theme' => $this->previewThemeForIndustry($profile['industry']),
        ]);

        try {
            $generated = $this->pageGenerationService->generatePage($generationPrompt);

            $page = DB::transaction(function () use ($trial, $profile, $generated) {
                $website = Website::query()->findOrFail(self::DEMO_WEBSITE_ID);

                $website->update([
                    'industry' => self::INDUSTRY_FOLDERS[$profile['industry']] ?? 'default',
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

            return redirect()->route('pages.builder', [
                'page' => $page,
                'token' => $trial->token,
            ]);
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

        return redirect()->route('start', ['trial' => $trial->token]);
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

        $trial->update([
            'email' => Str::lower($validated['email']),
            'email_captured_at' => now(),
            'last_saved_at' => now(),
        ]);

        $this->sendTrialAccessEmail($trial, false);

        return response()->json([
            'message' => 'Your private editing link has been sent to your email.',
            'email' => $trial->email,
        ]);
    }

    public function regenerate(Request $request, TrialGeneration $trial)
    {
        set_time_limit(240);
        abort_unless($trial->status === 'ready' && ! $trial->claimed_at && $trial->page_id, 404);
        abort_if(blank($trial->email), 422, 'Save your page with an email address before regenerating.');

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $windowStart = now()->subDays(7);
        $used = DB::table('trial_regenerations')
            ->where('email', Str::lower($trial->email))
            ->where('created_at', '>=', $windowStart)
            ->count();

        abort_if($used >= 2, 429, 'You have used your two free regenerations for this 7-day period. Create an account to keep generating.');

        $profile = [
            'business_name' => $trial->business_name,
            'industry' => $trial->industry,
            'location' => $trial->location,
            'business_description' => $trial->business_description,
            'prompt' => $validated['prompt'],
        ];

        try {
            $generated = $this->pageGenerationService->generatePage($this->buildPrompt($profile));
            $newToken = (string) Str::uuid();

            DB::transaction(function () use ($trial, $generated, $validated, $newToken) {
                $trial->page()->lockForUpdate()->firstOrFail()->update([
                    'blocks' => $generated['blocks'],
                    'status' => 'draft',
                    'publish_error' => null,
                ]);

                DB::table('trial_regenerations')->insert([
                    'trial_generation_id' => $trial->id,
                    'email' => Str::lower($trial->email),
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
            $this->sendTrialAccessEmail($trial, true);

            return response()->json([
                'message' => 'Your landing page was regenerated successfully.',
                'redirect_url' => route('pages.builder', ['page' => $trial->page_id, 'token' => $trial->token]),
                'token' => $trial->token,
                'remaining' => max(0, 1 - $used),
                'resets_at' => now()->addDays(7)->toIso8601String(),
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
        $normalized = Str::lower($prompt);
        $industry = 'Technology';

        foreach (self::INDUSTRY_KEYWORDS as $candidate => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    $industry = $candidate;
                    break 2;
                }
            }
        }

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
