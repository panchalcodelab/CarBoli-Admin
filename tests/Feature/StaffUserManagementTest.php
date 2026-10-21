<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StaffUserService;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_account_is_created_with_a_role_and_hashed_password(): void
    {
        $this->actingAs($this->createSuperAdmin());

        $role = Role::create([
            'name' => 'Auction Manager',
            'guard_name' => 'web',
        ]);

        $staff = app(StaffUserService::class)->create([
            'name' => 'Auction Operator',
            'email' => 'operator@example.com',
            'mobile' => '9876543210',
            'status' => 'active',
            'password' => 'StrongPassword123!',
            'role_id' => $role->id,
        ]);

        $this->assertTrue($staff->hasRole('Auction Manager'));
        $this->assertTrue(Hash::check('StrongPassword123!', $staff->password));
        $this->assertSame('9876543210', $staff->mobile);
        $this->assertSame('active', $staff->status);
    }

    public function test_staff_account_can_be_updated_without_replacing_its_password(): void
    {
        $this->actingAs($this->createSuperAdmin());

        $role = Role::create([
            'name' => 'Auction Manager',
            'guard_name' => 'web',
        ]);
        $staff = User::factory()->create([
            'name' => 'Original Name',
            'status' => 'active',
            'password' => 'OriginalPassword123!',
        ]);
        $staff->assignRole($role);

        $updated = app(StaffUserService::class)->update($staff, [
            'name' => 'Updated Name',
            'email' => $staff->email,
            'mobile' => '9876543210',
            'status' => 'suspended',
            'role_id' => $role->id,
        ]);

        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('suspended', $updated->status);
        $this->assertTrue(Hash::check('OriginalPassword123!', $updated->password));
    }

    public function test_inactive_and_suspended_staff_cannot_access_admin_panel(): void
    {
        $role = Role::create([
            'name' => 'Auction Manager',
            'guard_name' => 'web',
        ]);

        $staff = User::factory()->create(['status' => 'inactive']);
        $staff->assignRole($role);

        $this->assertFalse($staff->canAccessAdminPanel());
        $this->actingAs($staff)->get('/admin')->assertForbidden();

        $staff->update(['status' => 'suspended']);

        $this->assertFalse($staff->fresh()->canAccessAdminPanel());
        $this->actingAs($staff->fresh())->get('/admin')->assertForbidden();
    }

    public function test_active_staff_without_a_role_cannot_access_admin_panel(): void
    {
        $staff = User::factory()->create(['status' => 'active']);

        $this->assertFalse($staff->canAccessAdminPanel());
        $this->actingAs($staff)->get('/admin')->assertForbidden();
    }

    public function test_login_event_updates_last_login_timestamp(): void
    {
        $staff = User::factory()->create(['status' => 'active']);

        Event::dispatch(new Login('web', $staff, false));

        $this->assertNotNull($staff->fresh()->last_login_at);
    }

    public function test_super_admin_can_open_staff_user_resource(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->assertTrue($superAdmin->canAccessAdminPanel());
        $this->assertTrue($superAdmin->can('manage staff'));

        $this->actingAs($superAdmin);

        $this->assertTrue(Gate::allows('viewAny', User::class));
        $this->get('/admin/staff')->assertOk();
    }

    public function test_users_without_staff_permission_cannot_open_staff_user_resource(): void
    {
        $role = Role::create([
            'name' => 'Auction Manager',
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        $this->actingAs($user);

        $this->assertFalse(Gate::allows('viewAny', User::class));
        $this->get('/admin/staff')->assertForbidden();
    }

    private function createSuperAdmin(): User
    {
        $permission = Permission::create([
            'name' => 'manage staff',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }
}
