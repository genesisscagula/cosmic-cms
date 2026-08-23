<?php

namespace App\Http\Controllers;

use App\Models\PendingOnboarding;
use App\Models\TrialGeneration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        // Defense-in-depth: keep the owner CRM private even if route middleware changes later.
        abort_unless($request->user()?->isPlatformOwner(), 403);

        $registered = PendingOnboarding::query()
            ->with([
                'user:id,name,email,plan_key,plan_status,onboarding_status,created_at',
                'trialGeneration:id,token,page_id,website_id,email,business_name,industry,location,selected_plan,status,bundle_status,bundle_manifest,claimed_at,email_captured_at,created_at',
            ])
            ->latest('id')
            ->limit(500)
            ->get();

        $registeredEmails = $registered
            ->pluck('user.email')
            ->filter()
            ->map(fn ($email) => Str::lower((string) $email))
            ->flip();

        $signupRows = $registered->map(function (PendingOnboarding $onboarding) {
            $user = $onboarding->user;
            $trial = $onboarding->trialGeneration;
            $source = $trial ? 'start' : 'pricing';
            $status = $this->signupStatus($onboarding, $user);

            return [
                'key' => 'signup-'.$onboarding->id,
                'kind' => 'signup',
                'source' => $source,
                'name' => $user?->name ?: '—',
                'email' => $user?->email ?: '—',
                'business_name' => $onboarding->website_name ?: ($trial?->business_name ?: '—'),
                'industry' => $onboarding->industry ?: ($trial?->industry ?: '—'),
                'location' => $onboarding->location ?: ($trial?->location ?: '—'),
                'selected_plan' => $onboarding->selected_plan ?: $trial?->selected_plan,
                'status' => $status,
                'registered' => true,
                'created_at' => $user?->created_at?->toIso8601String() ?: $onboarding->created_at?->toIso8601String(),
                'created_at_label' => ($user?->created_at ?: $onboarding->created_at)?->diffForHumans(),
                'bundle_status' => $trial?->bundle_status,
                'page_count' => $trial ? max(1, count((array) data_get($trial->bundle_manifest, 'pages', []))) : null,
                'trial_url' => $trial?->page_id && ! $trial->claimed_at ? route('pages.builder', [
                    'page' => $trial->page_id,
                    'token' => $trial->token,
                ]) : null,
            ];
        });

        // Every generated Luna staging website belongs in the owner CRM even
        // before the visitor provides an email address. The token + page id are
        // the durable staging link; regeneration rotates the token and this view
        // automatically resolves the fresh link on its next request.
        $trialLeadRows = TrialGeneration::query()
            ->whereNotNull('page_id')
            ->where('status', 'ready')
            ->whereNull('claimed_at')
            ->latest('id')
            ->limit(500)
            ->get()
            ->reject(fn (TrialGeneration $trial) => filled($trial->email)
                && $registeredEmails->has(Str::lower((string) $trial->email)))
            ->unique(fn (TrialGeneration $trial) => filled($trial->email)
                ? 'email:'.Str::lower((string) $trial->email)
                : 'trial:'.$trial->id)
            ->map(fn (TrialGeneration $trial) => [
                'key' => 'trial-'.$trial->id,
                'kind' => filled($trial->email) ? 'lead' : 'generated_trial',
                'source' => 'start',
                'name' => '—',
                'email' => $trial->email ?: 'Not captured yet',
                'business_name' => $trial->business_name ?: 'Untitled demo',
                'industry' => $trial->industry ?: 'General Business',
                'location' => $trial->location ?: 'Location not specified',
                'selected_plan' => $trial->selected_plan,
                'status' => $trial->selected_plan
                    ? 'plan_selected'
                    : match ($trial->bundle_status) {
                        'ready' => 'generated_trial',
                        'queued', 'building' => 'building_trial',
                        'partial', 'failed' => 'partial_trial',
                        default => 'saved_trial',
                    },
                'registered' => false,
                'created_at' => ($trial->email_captured_at ?: $trial->created_at)?->toIso8601String(),
                'created_at_label' => ($trial->email_captured_at ?: $trial->created_at)?->diffForHumans(),
                'bundle_status' => $trial->bundle_status,
                'page_count' => max(1, count((array) data_get($trial->bundle_manifest, 'pages', []))),
                'trial_url' => $trial->page_id ? route('pages.builder', [
                    'page' => $trial->page_id,
                    'token' => $trial->token,
                ]) : null,
            ]);

        /** @var Collection<int, array<string, mixed>> $leads */
        $leads = $signupRows
            ->concat($trialLeadRows)
            ->sortByDesc('created_at')
            ->values();

        $leadStats = [
            'total' => $leads->count(),
            'start' => $leads->where('source', 'start')->count(),
            'pricing' => $leads->where('source', 'pricing')->count(),
            'registered' => $leads->where('registered', true)->count(),
            'paid' => $leads->where('status', 'active')->count(),
        ];

        return Inertia::render('Sales/Index', [
            'leads' => $leads,
            'leadStats' => $leadStats,
        ]);
    }

    private function signupStatus(PendingOnboarding $onboarding, ?User $user): string
    {
        if ($onboarding->status === 'completed' || $user?->plan_status === 'active') {
            return 'active';
        }

        if ($onboarding->status === 'failed') {
            return 'failed';
        }

        if ($onboarding->expires_at?->isPast()) {
            return 'expired';
        }

        return $onboarding->status ?: ($user?->plan_status ?: 'pending_payment');
    }
}
