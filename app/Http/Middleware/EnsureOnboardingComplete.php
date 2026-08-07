<?php

namespace App\Http\Middleware;

use App\Models\PaymentOrder;
use App\Models\PendingOnboarding;
use App\Models\WorkspaceProvisioning;
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

        // A completed provisioning is authoritative: reaching this state means the
        // provisioning service already verified a paid + fulfilled order and created
        // the customer's workspace/site. Trust it even if user/payment flags are a
        // fraction behind because the PayPal return, webhook and browser requests can
        // finish in different processes.
        $completedProvisioning = WorkspaceProvisioning::query()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceProvisioning::STATUS_COMPLETED)
            ->whereNotNull('workspace_id')
            ->whereNotNull('website_id')
            ->latest('id')
            ->first();

        if ($completedProvisioning) {
            $dirty = false;

            if ($user->onboarding_status !== 'complete') {
                $user->onboarding_status = 'complete';
                $dirty = true;
            }

            if (SubscriptionStatus::normalize((string) $user->plan_status) !== SubscriptionStatus::ACTIVE) {
                $user->plan_status = SubscriptionStatus::ACTIVE;
                $dirty = true;
            }

            if ($dirty) {
                $user->save();
            }

            $onboarding = PendingOnboarding::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();

            if ($onboarding && (
                $onboarding->status !== 'completed'
                || (int) $onboarding->workspace_id !== (int) $completedProvisioning->workspace_id
                || (int) $onboarding->website_id !== (int) $completedProvisioning->website_id
            )) {
                $onboarding->forceFill([
                    'status' => 'completed',
                    'workspace_id' => $completedProvisioning->workspace_id,
                    'website_id' => $completedProvisioning->website_id,
                    'completed_at' => $onboarding->completed_at ?? now(),
                ])->save();
            }

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
            // Only roll a stale "complete" account back when there is no completed
            // provisioning and no confirmed fulfilled payment. This prevents the
            // /dashboard <-> /onboarding/pending redirect loop.
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
