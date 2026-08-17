<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $request->user()?->forceFill(['last_login_at' => now()])->save();

        $user = $request->user();

        if ($user?->hasManualPlanEntitlement()) {
            if ($user->onboarding_status !== 'complete') {
                $user->forceFill(['onboarding_status' => 'complete'])->save();
            }

            return redirect()->intended(route('dashboard', absolute: false));
        }

        if ($user?->onboarding_status === 'pending_payment') {
            return redirect()->route('onboarding.pending');
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
