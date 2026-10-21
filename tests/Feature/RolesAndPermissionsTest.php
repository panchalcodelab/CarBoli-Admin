<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_have_roles_and_permissions(): void
    {
        $user = User::factory()->create();

        $role = Role::create(['name' => 'Super Admin']);
        $permission = Permission::create(['name' => 'manage auctions']);

        $user->assignRole($role);
        $user->givePermissionTo($permission);

        $this->assertTrue($user->hasRole('Super Admin'));
        $this->assertTrue($user->can('manage auctions'));
        $this->assertDatabaseHas('roles', ['name' => 'Super Admin']);
        $this->assertDatabaseHas('permissions', ['name' => 'manage auctions']);
    }
}
