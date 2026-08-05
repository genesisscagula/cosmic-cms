<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\Website;
use App\Policies\WebsitePolicy;
use App\Cosmic\Capabilities\CapabilityEngine;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Website::class, WebsitePolicy::class);

        // Dynamic plan gates: Gate::allows('plan:api_access') or authorize('plan:white_label_level', 'full').
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! str_starts_with($ability, 'plan:')) {
                return null;
            }

            $capability = substr($ability, 5);
            $expected = $arguments[0] ?? true;

            return app(CapabilityEngine::class)->allows($user, $capability, $expected);
        });

        Vite::prefetch(concurrency: 3);
    }
}
