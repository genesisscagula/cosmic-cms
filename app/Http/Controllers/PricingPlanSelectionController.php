<?php

namespace App\Http\Controllers;

use App\Models\PendingOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PricingPlanSelectionController extends Controller
{
    /**
     * Validate and persist the plan selected on the public pricing page.
     *
     * Guests continue to registration. Customers with an unfinished onboarding
     * can safely change the pending plan without creating another account.
     * Fully onboarded customers are sent to the authenticated pricing screen.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => [
                'required',
                'string',
                Rule::in([
                    'starter',
                    'growth',
                    'pro',
                    'agency_starter',
                    'agency_growth',
                    'agency_pro',
                ]),
            ],
        ]);

        $plan = $validated['plan'];
        $request->session()->put('checkout.selected_plan', $plan);

        if (! $request->user()) {
            return redirect()->route('register', ['plan' => $plan]);
        }

        if (in_array($request->user()->onboarding_status, ['pending_payment', 'provisioning'], true)) {
            PendingOnboarding::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('status', ['pending_payment', 'payment_failed'])
                ->latest('id')
                ->first()?->update([
                    'selected_plan' => $plan,
                    'status' => 'pending_payment',
                    'expires_at' => now()->addDays(7),
                ]);

            return redirect()->route('onboarding.pending')
                ->with('status', 'Your selected plan has been updated. Continue to payment when ready.');
        }

        return redirect()->route('cosmic-pricing.index', ['plan' => $plan]);
    }
}
