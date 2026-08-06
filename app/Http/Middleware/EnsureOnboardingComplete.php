<?php

namespace App\Http\Middleware;

use App\Models\PaymentOrder;
use App\Support\SubscriptionStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (string) $user->account_type !== 'customer') {
            return $next($request);
        }

        $hasPaidPlan = PaymentOrder::query()
            ->where('user_id', $user->id)
            ->where('product_type', 'plan')
            ->where('status', 'paid')
            ->whereNotNull('fulfilled_at')
            ->exists();

        $isFullyActivated = $user->onboarding_status === 'complete'
            && SubscriptionStatus::normalize((string) $user->plan_status) === SubscriptionStatus::ACTIVE
            && $hasPaidPlan;

        if (! $isFullyActivated) {
            if ($user->onboarding_status === 'complete' && ! $hasPaidPlan) {
                $user->forceFill([
                    'onboarding_status' => 'pending_payment',
                    'plan_key' => null,
                    'plan_status' => 'pending_payment',
                    'plan_renews_at' => null,
                ])->save();
            }

            return redirect()->route('onboarding.pending');
        }

        return $next($request);
    }
}
