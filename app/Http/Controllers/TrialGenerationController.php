<?php

namespace App\Http\Controllers;

use App\AI\Generators\ContentGenerator;
use App\AI\Generators\ImageGenerator;
use App\AI\Layouts\LayoutEngine;
use App\Models\TrialGeneration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use OpenAI\Exceptions\TransporterException;

class TrialGenerationController extends Controller
{
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
                'navigation' => $this->navigationForIndustry($trial->industry),
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
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $ipKey = 'trial-generation:ip:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($ipKey, 2)) {
            return back()->withErrors(['prompt' => 'You have already generated two drafts today. Please try again tomorrow.']);
        }

        RateLimiter::hit($ipKey, 86400);

        $profile = $this->profileFromPrompt($validated['prompt']);
        $prompt = $this->buildPrompt($profile);
        $folder = self::INDUSTRY_FOLDERS[$profile['industry']];
        $sections = LayoutEngine::random($folder, $prompt);

        $trial = TrialGeneration::create([
            ...$profile,
            'token' => (string) Str::uuid(),
            'prompt' => $validated['prompt'],
            'sections' => $sections,
            'status' => 'generating',
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        try {
            set_time_limit(240);
            $content = (new ContentGenerator())->generate($prompt, $sections);
            $images = new ImageGenerator();
            $blocks = $content['blocks'];

            foreach ($blocks as &$block) {
                if (($block['type'] ?? null) === 'hero_video_background') {
                    $block['poster_image_url'] = $images->generate($folder, $block);
                } elseif (isset($block['image_url'])) {
                    $block['image_url'] = $images->generate($folder, $block);
                }
            }
            unset($block);

            $trial->update(['status' => 'ready', 'generated_blocks' => $blocks]);
        } catch (TransporterException $exception) {
            Log::warning('Public trial generation unavailable', ['trial' => $trial->id, 'message' => $exception->getMessage()]);
            $trial->update(['status' => 'failed', 'error_message' => 'Cosmic AI is temporarily unavailable. Please try again later.']);
        } catch (\Throwable $exception) {
            Log::error('Public trial generation failed', ['trial' => $trial->id, 'message' => $exception->getMessage()]);
            $trial->update(['status' => 'failed', 'error_message' => 'We could not generate this draft. Please try again later.']);
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

    /**
     * Navigation is preview-only for an unclaimed trial. It gives the visitor
     * an industry-appropriate picture of the website without creating pages
     * or promising those pages have already been generated.
     */
    private function navigationForIndustry(?string $industry): array
    {
        $menus = config('trial-navigation', []);
        $folder = self::INDUSTRY_FOLDERS[$industry ?? ''] ?? 'default';

        return $menus[$folder] ?? $menus['default'] ?? ['Home', 'About', 'Services', 'Contact'];
    }
}
