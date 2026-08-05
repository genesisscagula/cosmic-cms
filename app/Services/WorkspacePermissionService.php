<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePermissionService
{
    public function roles(): array
    {
        return config('workspace_roles.roles', []);
    }

    public function roleExists(string $role): bool
    {
        return array_key_exists($role, $this->roles());
    }

    public function permissionsForRole(string $role): array
    {
        return data_get($this->roles(), $role.'.permissions', []);
    }

    public function allowsRole(string $role, string $permission): bool
    {
        $permissions = $this->permissionsForRole($role);
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function allows(User $user, Workspace $workspace, string $permission): bool
    {
        $role = $workspace->roleFor($user);
        return $role !== null && $this->allowsRole($role, $permission);
    }

    public function catalog(): array
    {
        $labels = config('workspace_roles.permission_labels', []);

        return collect($this->roles())->map(function (array $definition, string $key) use ($labels) {
            $permissions = $definition['permissions'] ?? [];
            $effective = in_array('*', $permissions, true) ? array_keys($labels) : $permissions;

            return [
                'key' => $key,
                'label' => $definition['label'] ?? ucfirst($key),
                'description' => $definition['description'] ?? '',
                'permissions' => collect($effective)->map(fn (string $permission) => [
                    'key' => $permission,
                    'label' => $labels[$permission] ?? $permission,
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
