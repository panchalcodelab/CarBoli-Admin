<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage customers',
            'view customers',
            'view customer kyc',
            'approve kyc',
            'manage cars',
            'manage inspections',
            'view inspections',
            'update assigned inspections',
            'manage auctions',
            'manage auction participants',
            'create auctions',
            'edit auctions',
            'publish auctions',
            'manage payments',
            'view financial reports',
            'manage staff',
            'manage roles',
            'manage notifications',
            'view audit logs',
            'manage settings',
            'manage auction results',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $rolePermissions = [
            'Super Admin' => $permissions,
            'Auction Manager' => [
                'manage auctions',
                'manage auction participants',
                'create auctions',
                'edit auctions',
                'publish auctions',
                'manage auction results',
            ],
            'Car / Inventory Manager' => [
                'manage cars',
                'manage inspections',
            ],
            'Inspection Staff' => [
                'view inspections',
                'update assigned inspections',
            ],
            'Finance Manager' => [
                'manage payments',
                'view financial reports',
            ],
            'Customer Support' => [
                'view customers',
                'view customer kyc',
                'view inspections',
                'manage notifications',
            ],
        ];

        foreach ($rolePermissions as $roleName => $rolePermissionNames) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissionNames);
        }
    }
}
