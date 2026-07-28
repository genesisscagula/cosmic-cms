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
            'email' => ['required', 'email', 'max:254'],
            'business_name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', 'in:'.implode(',', array_keys(self::INDUSTRY_FOLDERS))],
            'location' => ['required', 'string', 'max:255'],
            'business_description' => ['required', 'string', 'max:2000'],
            'prompt' => ['nullable', 'string', 'max:2000'],
        ]);

        $emailKey = 'trial-generation:email:'.sha1(Str::lower($validated['email']));
        $ipKey = 'trial-generation:ip:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($emailKey, 1) || RateLimiter::tooManyAttempts($ipKey, 2)) {
            return back()->withErrors(['email' => 'This email has already generated a draft today. Please check your inbox or try again tomorrow.']);
        }

        RateLimiter::hit($emailKey, 86400);
        RateLimiter::hit($ipKey, 86400);

        $prompt = $this->buildPrompt($validated);
        $folder = self::INDUSTRY_FOLDERS[$validated['industry']];
        $sections = LayoutEngine::random($folder, $prompt);

        $trial = TrialGeneration::create([
            ...$validated,
            'token' => (string) Str::uuid(),
            'prompt' => $validated['prompt'] ?: null,
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
}
