<?php

namespace App\Services;

use App\Policies\RolePolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Centralizes role creation, permission synchronization and deletion.
 *
 * All authorization is enforced here on the server, independent of the UI, so
 * direct Livewire or controller calls cannot bypass it. Spatie is treated as
 * the source of truth: roles use the configured guard and permissions are
 * synced via Spatie's supported APIs.
 */
class RoleService
{
    private string $guard;

    public function __construct()
    {
        $this->guard = config('auth.defaults.guard', 'web');
    }

    /**
     * @param  array<int, int|string>  $permissionIds
     */
    public function create(string $name, array $permissionIds): Role
    {
        Gate::authorize('create', Role::class);

        $name = trim($name);
        $this->validateName($name, null);
        $permissions = $this->resolvePermissions($permissionIds);

        return DB::transaction(function () use ($name, $permissions): Role {
            $role = Role::create([
                'name' => $name,
                'guard_name' => $this->guard,
            ]);

            $role->syncPermissions($permissions);

            $this->forgetCache();

            app(AuditLogService::class)->record(
                'roles',
                'role.created',
                $role,
                null,
                ['name' => $role->name, 'permissions' => $permissions->pluck('name')->values()->all()],
            );

            return $role;
        });
    }

    /**
     * @param  array<int, int|string>  $permissionIds
     */
    public function update(Role $role, string $name, array $permissionIds): Role
    {
        Gate::authorize('update', $role);

        $name = trim($name);
        $this->validateName($name, $role);
        $permissions = $this->resolvePermissions($permissionIds);

        $before = [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
        ];

        return DB::transaction(function () use ($role, $name, $permissions, $before): Role {
            // Never rename a protected system role through the ordinary flow.
            if (! $this->isProtected($role)) {
                $role->name = $name;
                $role->save();
            }

            $role->syncPermissions($permissions);

            $this->forgetCache();

            $role->refresh();

            app(AuditLogService::class)->record(
                'roles',
                'role.updated',
                $role,
                $before,
                [
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                ],
            );

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        Gate::authorize('delete', $role);

        if ($this->isProtected($role)) {
            throw ValidationException::withMessages([
                'role' => 'This is a protected system role and cannot be deleted.',
            ]);
        }

        $assignedCount = $role->users()->count();
        if ($assignedCount > 0) {
            throw ValidationException::withMessages([
                'role' => "This role is assigned to {$assignedCount} staff ".
                    ($assignedCount === 1 ? 'member' : 'members').
                    '. Reassign them before deleting the role.',
            ]);
        }

        $snapshot = [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
        ];

        DB::transaction(function () use ($role, $snapshot): void {
            $role->syncPermissions([]);
            $role->delete();

            $this->forgetCache();

            app(AuditLogService::class)->record(
                'roles',
                'role.deleted',
                null,
                $snapshot,
                null,
            );
        });
    }

    private function validateName(string $name, ?Role $existing): void
    {
        $unique = Rule::unique('roles', 'name')->where('guard_name', $this->guard);
        if ($existing !== null) {
            $unique = $unique->ignore($existing->getKey());
        }

        validator(
            ['name' => $name],
            ['name' => ['required', 'string', 'max:255', $unique]],
            ['name.unique' => 'A role with this name already exists.'],
        )->validate();
    }

    /**
     * Resolve and validate submitted permission identifiers against the
     * database and configured guard. Unknown or wrong-guard ids are rejected.
     *
     * @param  array<int, int|string>  $permissionIds
     * @return Collection<int, Permission>
     */
    private function resolvePermissions(array $permissionIds): Collection
    {
        $ids = collect($permissionIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $permissions = Permission::query()
            ->whereIn('id', $ids->all())
            ->where('guard_name', $this->guard)
            ->get();

        if ($permissions->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more selected permissions are invalid.',
            ]);
        }

        return $permissions;
    }

    private function isProtected(Role $role): bool
    {
        return in_array($role->name, RolePolicy::PROTECTED_ROLES, true);
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
