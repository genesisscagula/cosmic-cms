<?php

namespace App\Http\Middleware;

use App\Services\CreditWalletService;
use App\Services\PlanEntitlementService;
use App\Services\PlanRegistry;
use App\Services\WebsiteTemplateCatalog;
use App\Services\SparkCatalog;
use App\Cosmic\Capabilities\CapabilityEngine;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $creditBalance = $user ? app(CreditWalletService::class)->balance($user) : 0;
        $planEntitlements = $user ? app(PlanEntitlementService::class)->summary($user) : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'accountType' => $request->user()?->account_type,
                'isPlatformOwner' => $request->user()?->isPlatformOwner() ?? false,
                'isClient' => $request->user()?->isClient() ?? false,
                'creditBalance' => $creditBalance,
                'plan' => $planEntitlements,
                'planCapabilities' => $user ? app(CapabilityEngine::class)->forClient($user) : null,
                'planChangeMatrix' => $user ? app(PlanEntitlementService::class)->changeMatrix($user->plan_key) : [],
                'appearance' => $user?->appearance_preference ?? 'light',
            ],
            'cosmicPlans' => fn () => app(PlanRegistry::class)->forClient(),
            'cosmicSparks' => fn () => SparkCatalog::forClient(),
            'cosmicTemplates' => fn () => [
                'access_levels' => app(WebsiteTemplateCatalog::class)->accessLevels(),
                'agency_collections' => $user
                    ? app(WebsiteTemplateCatalog::class)->agencyCollectionsForUser($user, app(PlanEntitlementService::class))
                    : array_values(app(WebsiteTemplateCatalog::class)->agencyCollections()),
                'items' => $user
                    ? app(WebsiteTemplateCatalog::class)->forUser($user, app(PlanEntitlementService::class))
                    : app(WebsiteTemplateCatalog::class)->forClient(),
            ],
        ];
    }
}
