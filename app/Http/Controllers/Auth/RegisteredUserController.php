<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Models\PendingOnboarding;
use App\Models\TrialGeneration;
use App\Models\User;
use App\Models\Website;
use App\Models\WorkspaceProvisioning;
use App\Services\WorkspaceProvisioningService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
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
    public function create(Request $request): Response|RedirectResponse
    {
        $trial = null;
        $validPlans = ['starter', 'growth', 'pro', 'agency_starter', 'agency_growth', 'agency_pro'];

        if ($request->filled('trial')) {
            $trial = TrialGeneration::query()
                ->where('token', $request->string('trial'))
                ->where('status', 'ready')
                ->whereNull('claimed_at')
                ->first();
        }

        $selectedPlan = $trial?->selected_plan ?: $request->string('plan')->toString();

        if (! in_array($selectedPlan, $validPlans, true)) {
            return redirect()->route('pricing');
        }

        return Inertia::render('Auth/Register', [
            'trialToken' => $trial?->token,
            'trialEmail' => $trial?->email,
            'trialPlan' => $selectedPlan,
        ]);
    }

    /**
     * Store the account and business setup as a resumable pending onboarding.
     * Paid access, credits, workspace creation, and trial claiming happen only
     * after verified payment in the following onboarding patches.
     */
    public function store(Request $request)
    {
        // A new registration must never inherit checkout state from a previous
        // account in the same browser session.
        $request->session()->forget([
            'selected_plan',
            'checkout_uuid',
            'pending_checkout_id',
            'paypal_subscription_id',
            'paypal_order_id',
            'paypal_request_id',
            'paypal_approval_url',
            'approval_url',
            'onboarding',
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'trial_token' => ['nullable', 'uuid'],
            'selected_plan' => ['required', Rule::in(['starter', 'growth', 'pro', 'agency_starter', 'agency_growth', 'agency_pro'])],
            'website_name' => ['required', 'string', 'max:255'],
            'website_url' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'industry' => ['required', 'string', 'max:120'],
            'business_description' => ['required', 'string', 'min:20', 'max:1000'],
            'location' => ['required', 'string', 'max:255'],
        ], [
            'website_url.regex' => 'Use lowercase letters, numbers, and single hyphens only.',
        ]);

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

            // Never block checkout because a previous test or abandoned onboarding
            // reserved the preferred address. Keep the requested base and append the
            // first available numeric suffix (example: cosmic-cms-2).
            $websiteSlug = $this->resolveAvailableWebsiteSlug($validated['website_url']);

            PendingOnboarding::create([
                'user_id' => $user->id,
                'trial_generation_id' => $trial?->id,
                'selected_plan' => $validated['selected_plan'],
                'website_name' => trim($validated['website_name']),
                'website_slug' => $websiteSlug,
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

        // Complete the server-side account creation first, then land on an
        // internal Inertia page. That page opens PayPal with a native browser
        // navigation, avoiding external redirects being swallowed after a
        // logout/new-registration cycle.
        $pendingUrl = route('onboarding.pending', ['checkout' => 'auto']);

        if ($request->expectsJson()) {
            return response()->json([
                'created' => true,
                'redirect_url' => $pendingUrl,
            ], 201);
        }

        return redirect()->to($pendingUrl, 303);
    }

    private function resolveAvailableWebsiteSlug(string $preferred): string
    {
        $base = Str::slug($preferred) ?: 'website';
        $base = Str::limit($base, 60, '');
        $candidate = $base;
        $suffix = 2;

        while ($this->websiteSlugIsReserved($candidate)) {
            $suffixText = '-'.$suffix++;
            $candidate = Str::limit($base, 60 - strlen($suffixText), '').$suffixText;
        }

        return $candidate;
    }

    private function websiteSlugIsReserved(string $slug): bool
    {
        if (PendingOnboarding::query()->where('website_slug', $slug)->exists()) {
            return true;
        }

        return Website::query()
            ->where(function ($query) use ($slug) {
                $query
                    ->where('domain', 'like', '%://'.$slug.'.%')
                    ->orWhere('domain', 'like', $slug.'.%')
                    ->orWhere('domain', $slug);
            })
            ->exists();
    }

    public function pending(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $onboarding = PendingOnboarding::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $provisioning = WorkspaceProvisioning::query()
            ->where('pending_onboarding_id', $onboarding->id)
            ->latest('id')
            ->first();

        // The provisioning record is the final source of truth. PayPal return,
        // webhook and queue requests can complete in a different process, so the
        // user/payment flags may briefly be stale even though the workspace exists.
        $workspaceId = (int) ($onboarding->workspace_id ?: $provisioning?->workspace_id ?: 0);
        $websiteId = (int) ($onboarding->website_id ?: $provisioning?->website_id ?: 0);
        $provisioningComplete = $provisioning?->status === WorkspaceProvisioning::STATUS_COMPLETED;
        $onboardingComplete = $onboarding->status === 'completed';
        $workspaceReady = $workspaceId > 0
            && $websiteId > 0
            && ($provisioningComplete || $onboardingComplete);

        if ($workspaceReady) {
            if ($onboarding->status !== 'completed'
                || (int) $onboarding->workspace_id !== $workspaceId
                || (int) $onboarding->website_id !== $websiteId) {
                $onboarding->forceFill([
                    'status' => 'completed',
                    'workspace_id' => $workspaceId,
                    'website_id' => $websiteId,
                    'completed_at' => $onboarding->completed_at ?? now(),
                ])->save();
            }

            if ($user->onboarding_status !== 'complete' || $user->plan_status !== SubscriptionStatus::ACTIVE) {
                $user->forceFill([
                    'onboarding_status' => 'complete',
                    'plan_status' => SubscriptionStatus::ACTIVE,
                ])->save();
            }

            return redirect()->route('dashboard');
        }

        $plan = config('payments.plans.'.$onboarding->selected_plan, []);

        $pendingOrder = $user->paymentOrders()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->where('product_key', $onboarding->selected_plan)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subDay())
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
                'has_pending_checkout' => (bool) ($pendingOrder && data_get($pendingOrder->metadata, 'checkout_url')),
                'workspace_ready' => $workspaceReady,
                'redirect_url' => route('dashboard'),
                'provisioning_status' => $provisioning?->status,
                'provisioning_error' => $provisioning?->last_error,
                'can_retry_provisioning' => (bool) ($provisioning && in_array($provisioning->status, ['failed', 'processing'], true)),
                'next_retry_at' => $provisioning?->next_retry_at?->toIso8601String(),
            ],
            'status' => session('status'),
            'paymentError' => session('payment_error'),
            'autoCheckout' => $request->string('checkout')->toString() === 'auto',
        ]);
    }

    public function recoverProvisioning(Request $request, WorkspaceProvisioningService $service): RedirectResponse
    {
        $onboarding = PendingOnboarding::query()->where('user_id', $request->user()->id)->latest('id')->firstOrFail();
        $provisioning = WorkspaceProvisioning::query()->where('pending_onboarding_id', $onboarding->id)->latest('id')->firstOrFail();

        try {
            $service->recover($provisioning, 'customer_manual');
            return redirect()->route('onboarding.pending')->with('status', 'Provisioning recovery completed successfully.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('onboarding.pending')->with('payment_error', $e->getMessage() ?: 'Provisioning recovery failed.');
        }
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
