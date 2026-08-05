<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use App\Support\MonitoringContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        $this->app->singleton(MonitoringContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Website::class, WebsitePolicy::class);

        if ((bool) config('cosmic-monitoring.enabled', true)) {
            DB::listen(function ($query): void {
                $threshold = (float) config('cosmic-monitoring.slow_query_ms', 750);

                if ((float) $query->time < $threshold) {
                    return;
                }

                Log::channel('cosmic_performance')->warning('Slow database query.', [
                    'duration_ms' => (float) $query->time,
                    'connection' => $query->connectionName,
                    'sql_fingerprint' => hash('sha256', preg_replace('/\s+/', ' ', trim((string) $query->sql))),
                    'binding_count' => count($query->bindings),
                ]);
            });
        }

        $key = static function (Request $request, string $scope): string {
            $identity = $request->user()?->getAuthIdentifier()
                ?? strtolower((string) $request->input('email'))
                ?: $request->ip();

            return $scope.'|'.sha1((string) $identity);
        };

        RateLimiter::for('cosmic-login', fn (Request $request) =>
            Limit::perMinute((int) config('cosmic-rate-limits.auth.login_per_minute', 5))
                ->by($key($request, 'login'))
        );

        RateLimiter::for('cosmic-register', fn (Request $request) =>
            Limit::perHour((int) config('cosmic-rate-limits.auth.register_per_hour', 8))
                ->by($key($request, 'register'))
        );

        RateLimiter::for('cosmic-password-reset', fn (Request $request) =>
            Limit::perHour((int) config('cosmic-rate-limits.auth.password_reset_per_hour', 5))
                ->by($key($request, 'password-reset'))
        );

        RateLimiter::for('cosmic-ai', function (Request $request) use ($key) {
            return [
                Limit::perMinute((int) config('cosmic-rate-limits.ai.per_minute', 12))->by($key($request, 'ai-minute')),
                Limit::perHour((int) config('cosmic-rate-limits.ai.per_hour', 120))->by($key($request, 'ai-hour')),
            ];
        });

        RateLimiter::for('cosmic-upload', function (Request $request) use ($key) {
            return [
                Limit::perMinute((int) config('cosmic-rate-limits.uploads.per_minute', 20))->by($key($request, 'upload-minute')),
                Limit::perHour((int) config('cosmic-rate-limits.uploads.per_hour', 200))->by($key($request, 'upload-hour')),
            ];
        });

        RateLimiter::for('cosmic-workspace-write', fn (Request $request) =>
            Limit::perMinute((int) config('cosmic-rate-limits.workspace_writes.per_minute', 30))
                ->by($key($request, 'workspace-write'))
        );

        RateLimiter::for('cosmic-public-preview', fn (Request $request) =>
            Limit::perMinute((int) config('cosmic-rate-limits.public_preview.per_minute', 120))
                ->by('preview|'.sha1($request->ip().'|'.$request->route('token')))
        );

        RateLimiter::for('cosmic-contact', function (Request $request) {
            $website = $request->route('website');
            $websiteId = is_object($website) ? $website->getKey() : $website;

            return [
                Limit::perMinute((int) config('cosmic-rate-limits.forms.per_minute', 10))->by('contact-minute|'.$websiteId.'|'.$request->ip()),
                Limit::perHour((int) config('cosmic-rate-limits.forms.per_hour', 60))->by('contact-hour|'.$websiteId.'|'.$request->ip()),
            ];
        });

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
