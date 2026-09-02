<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\PendingOnboarding;
use App\Services\MarketplaceCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        $marketplaceTemplate = preg_match('/^[a-z0-9-]+$/', (string) $request->query('marketplace_template', ''))
            ? (string) $request->query('marketplace_template')
            : '';

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'marketplaceTemplate' => $marketplaceTemplate,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, MarketplaceCheckoutService $marketplaceCheckouts): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $request->user()?->forceFill(['last_login_at' => now()])->save();

        $user = $request->user();
        $marketplaceSlug = preg_match('/^[a-z0-9-]+$/', $request->string('marketplace_template')->toString())
            ? $request->string('marketplace_template')->toString()
            : '';

        if ($marketplaceSlug !== '' && $user) {
            try {
                $template = $marketplaceCheckouts->publishedTemplate($marketplaceSlug);
                $pending = PendingOnboarding::query()
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['pending_payment', 'payment_cancelled'])
                    ->latest('id')
                    ->first();

                if ($pending) {
                    $marketplaceCheckouts->selectForPendingOnboarding($user, $pending, $template);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($user?->hasManualPlanEntitlement()) {
            if ($user->onboarding_status !== 'complete') {
                $user->forceFill(['onboarding_status' => 'complete'])->save();
            }

            return redirect()->intended(route('dashboard', absolute: false));
        }

        if ($user?->onboarding_status === 'pending_payment') {
            return redirect()->route('onboarding.pending');
        }

        if ($marketplaceSlug !== '') {
            $marketplaceBase = app()->environment('production')
                ? rtrim((string) config('cosmic_marketplace.scheme', 'https').'://'.config('cosmic_marketplace.domain', 'marketplace.cosmiccms.com'), '/')
                : rtrim((string) config('cosmic_marketplace.core_url', config('app.url')), '/').'/'.trim((string) config('cosmic_marketplace.local_prefix', 'marketplace'), '/');

            return redirect()->away($marketplaceBase.'/checkout/'.rawurlencode($marketplaceSlug));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

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

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
