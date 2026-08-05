<?php

namespace App\Http\Middleware;

use App\Support\MonitoringContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AttachMonitoringContext
{
    public function __construct(private readonly MonitoringContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->context->requestId($request);
        $request->attributes->set('cosmic_request_id', $requestId);

        $context = $this->context->safeRequestContext($request, $requestId);
        Log::shareContext($context);

        $startedAt = hrtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            Log::channel('cosmic_errors')->error('Unhandled request exception.', [
                ...$context,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            throw $exception;
        }

        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);
        $response->headers->set(
            (string) config('cosmic-monitoring.correlation_header', 'X-Cosmic-Request-ID'),
            $requestId
        );

        if ($response->getStatusCode() >= 500) {
            Log::channel('cosmic_errors')->error('Server error response.', [
                ...$context,
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
            ]);
        } elseif ($durationMs >= (float) config('cosmic-monitoring.slow_query_ms', 750) * 2) {
            Log::channel('cosmic_performance')->warning('Slow HTTP request.', [
                ...$context,
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
            ]);
        }

        return $response;
    }
}
