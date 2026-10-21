<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RoleService;
use App\Services\StaffUserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function permission(string $name): Permission
    {
        return Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $role->givePermissionTo($this->permission('manage roles'));
        $role->givePermissionTo($this->permission('manage staff'));

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function rolesManager(): User
    {
        $role = Role::firstOrCreate(['name' => 'Access Manager', 'guard_name' => 'web']);
        $role->givePermissionTo($this->permission('manage roles'));

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    public function test_authorized_admin_can_create_a_role_with_permissions(): void
    {
        $this->actingAs($this->rolesManager());
        $p1 = $this->permission('manage cars');
        $p2 = $this->permission('view customers');

        $role = app(RoleService::class)->create('Yard Supervisor', [$p1->id, $p2->id]);

        $this->assertSame('web', $role->guard_name);
        $this->assertTrue($role->hasPermissionTo('manage cars'));
        $this->assertTrue($role->hasPermissionTo('view customers'));
        $this->assertDatabaseHas('audit_logs', ['module' => 'roles', 'action' => 'role.created']);
    }

    public function test_role_permissions_persist_and_sync_on_edit(): void
    {
        $this->actingAs($this->rolesManager());
        $cars = $this->permission('manage cars');
        $customers = $this->permission('view customers');
        $payments = $this->permission('manage payments');

        $role = app(RoleService::class)->create('Ops', [$cars->id, $customers->id]);

        // Edit: drop customers, add payments.
        app(RoleService::class)->update($role->fresh(), 'Ops', [$cars->id, $payments->id]);

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('manage cars'));
        $this->assertTrue($role->hasPermissionTo('manage payments'));
        $this->assertFalse($role->hasPermissionTo('view customers'));
        $this->assertDatabaseHas('audit_logs', ['module' => 'roles', 'action' => 'role.updated']);
    }

    public function test_duplicate_role_name_within_guard_is_rejected(): void
    {
        $this->actingAs($this->rolesManager());
        app(RoleService::class)->create('Duplicate', []);

        $this->expectException(ValidationException::class);
        app(RoleService::class)->create('Duplicate', []);
    }

    public function test_invalid_permission_id_is_rejected(): void
    {
        $this->actingAs($this->rolesManager());

        $this->expectException(ValidationException::class);
        app(RoleService::class)->create('Bad', [999999]);
    }

    public function test_unauthorized_user_cannot_create_a_role(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(Role::firstOrCreate(['name' => 'Nobody', 'guard_name' => 'web']));
        $this->actingAs($user);

        $this->expectException(AuthorizationException::class);
        app(RoleService::class)->create('Should Fail', []);
    }

    public function test_role_still_assigned_to_staff_cannot_be_deleted(): void
    {
        $this->actingAs($this->rolesManager());
        $role = app(RoleService::class)->create('In Use', []);

        $member = User::factory()->create(['status' => 'active']);
        $member->assignRole($role);

        try {
            app(RoleService::class)->delete($role->fresh());
            $this->fail('Expected ValidationException for an assigned role.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('assigned', collect($e->errors())->flatten()->first());
        }

        $this->assertDatabaseHas('roles', ['name' => 'In Use']);
    }

    public function test_unassigned_role_can_be_deleted(): void
    {
        $this->actingAs($this->rolesManager());
        $role = app(RoleService::class)->create('Disposable', []);

        app(RoleService::class)->delete($role->fresh());

        $this->assertDatabaseMissing('roles', ['name' => 'Disposable']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'roles', 'action' => 'role.deleted']);
    }

    public function test_protected_super_admin_role_cannot_be_deleted(): void
    {
        $actor = $this->superAdmin();
        $this->actingAs($actor);
        $superAdminRole = Role::where('name', 'Super Admin')->first();

        $this->assertFalse(Gate::forUser($actor)->allows('delete', $superAdminRole));

        $this->expectException(AuthorizationException::class);
        app(RoleService::class)->delete($superAdminRole);
    }

    public function test_protected_super_admin_role_name_cannot_be_renamed_via_update(): void
    {
        $this->actingAs($this->superAdmin());
        $superAdminRole = Role::where('name', 'Super Admin')->first();

        app(RoleService::class)->update($superAdminRole, 'Hacked Name', $superAdminRole->permissions->pluck('id')->all());

        $this->assertDatabaseHas('roles', ['name' => 'Super Admin']);
        $this->assertDatabaseMissing('roles', ['name' => 'Hacked Name']);
    }

    public function test_normal_staff_flow_cannot_promote_account_to_super_admin(): void
    {
        $this->actingAs($this->superAdmin());
        $superAdminRole = Role::where('name', 'Super Admin')->first();

        $this->expectException(ValidationException::class);
        app(StaffUserService::class)->create([
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'status' => 'active',
            'password' => 'StrongPassword123!',
            'role_id' => $superAdminRole->id,
        ]);
    }

    public function test_super_admin_target_is_not_downgraded_through_ordinary_staff_edit(): void
    {
        $this->actingAs($this->superAdmin());
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $ordinaryRole = app(RoleService::class)->create('Plain Role', []);

        $target = User::factory()->create(['status' => 'active']);
        $target->assignRole($superAdminRole);

        // Crafted request attempts to assign a lower role to a Super Admin.
        app(StaffUserService::class)->update($target, [
            'name' => $target->name,
            'email' => $target->email,
            'status' => 'active',
            'role_id' => $ordinaryRole->id,
        ]);

        $this->assertTrue($target->fresh()->hasRole('Super Admin'));
        $this->assertFalse($target->fresh()->hasRole('Plain Role'));
    }

    public function test_unauthorized_user_cannot_reach_roles_page_by_url(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(Role::firstOrCreate(['name' => 'Auction Manager', 'guard_name' => 'web']));
        $this->actingAs($user);

        $this->get('/admin/roles')->assertForbidden();
    }

    public function test_authorized_admin_can_open_roles_page(): void
    {
        $this->actingAs($this->rolesManager());

        $this->get('/admin/roles')->assertOk();
    }

    public function test_role_create_page_renders(): void
    {
        $this->actingAs($this->rolesManager());
        $this->permission('manage cars');
        $this->permission('view customers');

        $this->get('/admin/roles/create')->assertOk()->assertSee('Permissions');
    }

    public function test_role_edit_page_renders_with_current_permissions(): void
    {
        $this->actingAs($this->rolesManager());
        $cars = $this->permission('manage cars');
        $role = app(RoleService::class)->create('Editable', [$cars->id]);

        $this->get('/admin/roles/'.$role->id.'/edit')->assertOk()->assertSee('Editable');
    }
}
