<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the "manage roles" permission (used by the Role Management module) and
 * grants it to the existing Super Admin role. This keeps already-deployed
 * databases in sync without re-running the full roles/permissions seeder, and
 * does not touch any other role or permission.
 */
return new class extends Migration
{
    private const PERMISSION = 'manage roles';

    private const GUARD = 'web';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => self::PERMISSION,
            'guard_name' => self::GUARD,
        ]);

        $superAdmin = Role::where('name', 'Super Admin')
            ->where('guard_name', self::GUARD)
            ->first();

        $superAdmin?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)
            ->where('guard_name', self::GUARD)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
