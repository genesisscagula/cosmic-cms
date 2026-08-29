<?php

namespace App\Services;

use RuntimeException;

/**
 * Single source of truth for Luna's executable CMS scope/action contracts.
 *
 * Batch 1 intentionally registers the existing Spark branches in full while
 * exposing the remaining scopes as stable contract shells. Later batches fill
 * those action maps without changing the top-level router again.
 */
final class LunaActionContractRegistry
{
    private array $contract;

    public function __construct()
    {
        $path = resource_path('luna/action_contracts.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($decoded) || ! is_array($decoded['scopes'] ?? null)) {
            throw new RuntimeException('Luna action contract registry is missing or invalid.');
        }

        $this->contract = $decoded;
    }

    /** @return array<int,string> */
    public function scopes(): array
    {
        return array_values(array_keys($this->contract['scopes']));
    }

    public function hasScope(string $scope): bool
    {
        return isset($this->contract['scopes'][$scope]);
    }

    /** @return array<int,string> */
    public function actions(string $scope): array
    {
        $actions = $this->contract['scopes'][$scope]['actions'] ?? [];
        return is_array($actions) ? array_values(array_keys($actions)) : [];
    }

    public function hasAction(string $scope, ?string $action): bool
    {
        return is_string($action) && $action !== '' && in_array($action, $this->actions($scope), true);
    }

    /** @return array<string,mixed> */
    public function scope(string $scope): array
    {
        $value = $this->contract['scopes'][$scope] ?? [];
        return is_array($value) ? $value : [];
    }

    /** @return array<string,mixed> */
    public function action(string $scope, ?string $action): array
    {
        if (! is_string($action) || $action === '') return [];
        $value = $this->contract['scopes'][$scope]['actions'][$action] ?? [];
        return is_array($value) ? $value : [];
    }

    /**
     * Safe router/executor metadata only. This deliberately excludes arbitrary
     * schema payloads and keeps the routing envelope compact.
     *
     * @return array<string,mixed>
     */
    public function routingMetadata(string $scope, ?string $action = null): array
    {
        $scopeContract = $this->scope($scope);
        $actionContract = $this->action($scope, $action);

        return array_filter([
            'contract_version' => (int) ($this->contract['version'] ?? 1),
            'scope' => $scope,
            'action' => $action,
            'department' => $actionContract['department'] ?? ($scopeContract['department'] ?? 'terra'),
            'context' => is_array($scopeContract['context'] ?? null) ? array_values($scopeContract['context']) : [],
            'mutation' => $actionContract['mutation'] ?? null,
            'target' => $actionContract['target'] ?? null,
            'executor' => $actionContract['executor'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
