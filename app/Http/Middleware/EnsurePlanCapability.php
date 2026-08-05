<?php

namespace App\Http\Middleware;

use App\Cosmic\Capabilities\CapabilityEngine;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlanCapability
{
    public function __construct(private readonly CapabilityEngine $capabilities)
    {
    }

    public function handle(Request $request, Closure $next, string $capability, ?string $expected = null): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $required = $this->castExpected($expected);
        $decision = $this->capabilities->decide($user, $capability, $required);

        if (! $decision->allowed) {
            abort(403, $decision->upgradeMessage ?? $decision->reason ?? 'This feature is not available on your plan.');
        }

        return $next($request);
    }

    private function castExpected(?string $expected): mixed
    {
        if ($expected === null || $expected === '') {
            return true;
        }

        return match (strtolower($expected)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => is_numeric($expected) ? (int) $expected : $expected,
        };
    }
}
