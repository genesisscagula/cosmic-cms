<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingOnboarding;
use App\Models\TrialGeneration;
use App\Models\User;
use App\Models\Website;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(Request $request): Response
    {
        $trial = null;

        if ($request->filled('trial')) {
            $trial = TrialGeneration::query()
                ->where('token', $request->string('trial'))
                ->where('status', 'ready')
                ->whereNull('claimed_at')
                ->first();
        }

        return Inertia::render('Auth/Register', [
            'trialToken' => $trial?->token,
            'trialEmail' => $trial?->email,
            'trialPlan' => $trial?->selected_plan,
        ]);
    }

    /**
     * Store the account and business setup as a resumable pending onboarding.
     * Paid access, credits, workspace creation, and trial claiming happen only
     * after verified payment in the following onboarding patches.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'trial_token' => ['nullable', 'uuid'],
            'selected_plan' => ['required', Rule::in(['starter', 'growth', 'pro'])],
            'website_name' => ['required', 'string', 'max:255'],
            'website_url' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pending_onboardings', 'website_slug'),
            ],
            'industry' => ['required', 'string', 'max:120'],
            'business_description' => ['required', 'string', 'min:20', 'max:1000'],
            'location' => ['required', 'string', 'max:255'],
        ], [
            'website_url.regex' => 'Use lowercase letters, numbers, and single hyphens only.',
            'website_url.unique' => 'That website address is already reserved. Choose another one.',
        ]);

        $domainAlreadyUsed = Website::query()
            ->where('domain', 'like', '%://'.$validated['website_url'].'.%')
            ->exists();

        if ($domainAlreadyUsed) {
            throw ValidationException::withMessages([
                'website_url' => 'That website address is already in use. Choose another one.',
            ]);
        }

        $user = DB::transaction(function () use ($validated) {
            $trial = null;

            if (! empty($validated['trial_token'])) {
                $trial = TrialGeneration::query()
                    ->where('token', $validated['trial_token'])
                    ->where('status', 'ready')
                    ->whereNull('claimed_at')
                    ->lockForUpdate()
                    ->first();

                if (! $trial) {
                    throw ValidationException::withMessages([
                        'trial_token' => 'This draft is no longer available. Please generate a new one.',
                    ]);
                }

                if ($trial->email && Str::lower($trial->email) !== Str::lower($validated['email'])) {
                    throw ValidationException::withMessages([
                        'email' => 'Use the same email address used to save this draft.',
                    ]);
                }
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'account_type' => 'customer',
                'onboarding_status' => 'pending_payment',
                'credits' => 0,
                'plan_key' => null,
                'plan_status' => 'pending_payment',
                'plan_provider' => 'paypal',
            ]);

            PendingOnboarding::create([
                'user_id' => $user->id,
                'trial_generation_id' => $trial?->id,
                'selected_plan' => $validated['selected_plan'],
                'website_name' => trim($validated['website_name']),
                'website_slug' => $validated['website_url'],
                'industry' => $validated['industry'],
                'business_description' => trim($validated['business_description']),
                'location' => trim($validated['location']),
                'status' => 'pending_payment',
                'expires_at' => now()->addDays(7),
                'metadata' => [
                    'trial_token_present' => (bool) $trial,
                    'created_from_ip_hash' => hash('sha256', request()->ip().'|'.config('app.key')),
                ],
            ]);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('onboarding.pending')
            ->with('status', 'Your account and business details were saved. Complete payment to activate your plan.');
    }

    public function pending(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_status !== 'pending_payment') {
            return redirect()->route('dashboard');
        }

        $onboarding = PendingOnboarding::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $plan = config('payments.plans.'.$onboarding->selected_plan, []);

        $pendingOrder = $user->paymentOrders()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->where('product_key', $onboarding->selected_plan)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        return Inertia::render('Onboarding/Pending', [
            'onboarding' => [
                'plan_key' => $onboarding->selected_plan,
                'plan_name' => $plan['label'] ?? Str::headline($onboarding->selected_plan),
                'price' => '$'.number_format((float) ($plan['price_usd'] ?? 0), 0),
                'website_name' => $onboarding->website_name,
                'website_slug' => $onboarding->website_slug,
                'industry' => $onboarding->industry,
                'location' => $onboarding->location,
                'expires_at' => $onboarding->expires_at?->toIso8601String(),
                'is_expired' => (bool) ($onboarding->expires_at?->isPast()),
                'status' => $onboarding->status,
                'has_pending_checkout' => (bool) $pendingOrder,
            ],
            'status' => session('status'),
            'paymentError' => session('payment_error'),
        ]);
    }

    public function success(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $onboarding = PendingOnboarding::query()
            ->with('website')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (! $onboarding || $onboarding->status !== 'completed' || ! $onboarding->website_id) {
            return redirect()->route('onboarding.pending')
                ->with('status', 'We are still finishing your workspace. You can safely resume from here.');
        }

        $plan = config('payments.plans.'.$onboarding->selected_plan, []);

        return Inertia::render('Onboarding/Success', [
            'onboarding' => [
                'plan_name' => $plan['label'] ?? Str::headline($onboarding->selected_plan),
                'website_name' => $onboarding->website_name,
                'website_url' => $onboarding->website?->domain,
                'website_id' => $onboarding->website_id,
                'trial_transferred' => (bool) data_get($onboarding->metadata, 'trial_transferred', false),
                'completed_at' => $onboarding->completed_at?->toIso8601String(),
            ],
            'status' => session('status'),
        ]);
    }

}
