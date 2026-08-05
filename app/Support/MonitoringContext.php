<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MonitoringContext
{
    public function requestId(Request $request): string
    {
        $header = (string) config('cosmic-monitoring.correlation_header', 'X-Cosmic-Request-ID');
        $candidate = trim((string) $request->headers->get($header));

        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $candidate)) {
            return $candidate;
        }

        return (string) Str::uuid();
    }

    public function safeRequestContext(Request $request, string $requestId): array
    {
        return [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'workspace_id' => $request->user()?->currentWorkspace?->getKey(),
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ];
    }

    public function redact(array $context): array
    {
        $redactedKeys = array_map('strtolower', config('cosmic-monitoring.redacted_keys', []));

        return $this->walk($context, $redactedKeys);
    }

    private function walk(array $values, array $redactedKeys): array
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), $redactedKeys, true)) {
                $values[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->walk($value, $redactedKeys);
            }
        }

        return $values;
    }
}
