<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class StaffUserService
{
    /**
     * The protected system role that must never be granted or revoked through
     * the ordinary staff create/edit flow.
     */
    public const PROTECTED_ROLE = 'Super Admin';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        Gate::authorize('create', User::class);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(User::STAFF_STATUSES)],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ])->validate();

        $role = Role::query()->findOrFail($validated['role_id']);
        $this->authorizeSuperAdminAssignment($role);

        return DB::transaction(function () use ($validated, $role): User {
            unset($validated['role_id']);

            $staff = User::query()->create($validated);
            $staff->syncRoles($role);

            return $staff;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $staff, array $data): User
    {
        Gate::authorize('update', $staff);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff)],
            'mobile' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(User::STAFF_STATUSES)],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ])->validate();

        $role = Role::query()->findOrFail($validated['role_id']);
        $this->authorizeSuperAdminAssignment($role);

        return DB::transaction(function () use ($staff, $validated, $role): User {
            unset($validated['role_id']);

            if (blank($validated['password'] ?? null)) {
                unset($validated['password']);
            }

            $staff->update($validated);
            $staff->syncRoles($role);

            return $staff->refresh();
        });
    }

    /**
     * Permanently delete a staff account. Authorization (not self, not a
     * protected Super Admin) is enforced by the policy. The account's audit
     * trail is preserved because audit_logs.user_id is null-on-delete, and
     * other references to the user null out via their foreign keys.
     */
    public function delete(User $staff): void
    {
        Gate::authorize('delete', $staff);

        $snapshot = [
            'id' => $staff->getKey(),
            'name' => $staff->name,
            'email' => $staff->email,
            'status' => $staff->status,
            'roles' => $staff->getRoleNames()->values()->all(),
        ];

        DB::transaction(function () use ($staff, $snapshot): void {
            $staff->syncRoles([]);
            $staff->delete();

            app(AuditLogService::class)->record(
                'staff',
                'staff.deleted',
                null,
                $snapshot,
                null,
            );
        });
    }

    /**
     * The Super Admin role may be assigned only by an acting Super Admin. Any
     * other user attempting to grant it — including via a crafted request —
     * is rejected, which prevents privilege escalation by ordinary staff
     * managers. Non-protected roles are assignable by any authorized manager.
     */
    private function authorizeSuperAdminAssignment(Role $role): void
    {
        if ($role->name !== self::PROTECTED_ROLE) {
            return;
        }

        $actor = Auth::user();

        if (! $actor instanceof User || ! $actor->hasRole(self::PROTECTED_ROLE)) {
            throw ValidationException::withMessages([
                'role_id' => 'Only a Super Admin may assign the Super Admin role.',
            ]);
        }
    }
}
