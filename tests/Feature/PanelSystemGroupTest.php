<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelSystemGroupTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(Role::findByName($role, 'web'));
        $this->actingAs($user);

        return $user;
    }

    // ---- Staff Users ----

    public function test_staff_list_renders_for_super_admin(): void
    {
        $admin = $this->actingAsRole('Super Admin');
        Livewire::test('panel.staff.index')
            ->assertOk()
            ->assertSee('Staff Users')
            ->assertSee($admin->email);
    }

    public function test_staff_list_denied_for_non_super_admin(): void
    {
        $this->actingAsRole('Finance Manager');
        Livewire::test('panel.staff.index')->assertForbidden();
    }

    public function test_super_admin_can_create_staff_with_role(): void
    {
        $this->actingAsRole('Super Admin');
        $role = Role::findByName('Finance Manager', 'web');

        Livewire::test('panel.staff.form')
            ->set('name', 'New Finance')
            ->set('email', 'finance.new@example.com')
            ->set('status', 'active')
            ->set('role_id', $role->id)
            ->set('password', 'secret123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'finance.new@example.com', 'status' => 'active']);
        $created = User::where('email', 'finance.new@example.com')->first();
        $this->assertTrue($created->hasRole('Finance Manager'));
    }

    public function test_non_super_admin_cannot_assign_super_admin_role(): void
    {
        // Give a user 'manage staff' without Super Admin, to test the role-assignment guard.
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['status' => 'active']);
        $managerRole = Role::create(['name' => 'Staff Manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo('manage staff');
        $manager->assignRole($managerRole);
        $this->actingAs($manager);

        $superAdminRole = Role::findByName('Super Admin', 'web');

        Livewire::test('panel.staff.form')
            ->set('name', 'Sneaky')
            ->set('email', 'sneaky@example.com')
            ->set('status', 'active')
            ->set('role_id', $superAdminRole->id)
            ->set('password', 'secret123')
            ->call('save')
            ->assertHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_staff_cannot_edit_themselves(): void
    {
        $admin = $this->actingAsRole('Super Admin');

        // Policy forbids updating yourself.
        Livewire::test('panel.staff.form', ['staff' => $admin->id])->assertForbidden();
    }

    // ---- Reports ----

    public function test_reports_render_for_finance_manager_with_financials(): void
    {
        $this->actingAsRole('Finance Manager');
        Payment::factory()->create(['status' => 'captured', 'amount' => 5000]);

        Livewire::test('panel.reports.index')
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Captured amount');
    }

    public function test_reports_scope_hides_customers_for_finance_manager(): void
    {
        $this->actingAsRole('Finance Manager');
        // Finance Manager lacks 'view customers' -> Customers card not shown.
        Livewire::test('panel.reports.index')->assertOk()->assertDontSee('>Customers<', false);
    }

    public function test_reports_denied_for_role_without_report_permissions(): void
    {
        $this->actingAsRole('Inspection Staff');
        Livewire::test('panel.reports.index')->assertForbidden();
    }

    // ---- Notification Logs ----

    public function test_notification_log_renders_for_customer_support(): void
    {
        $this->actingAsRole('Customer Support'); // has 'manage notifications'
        $customer = Customer::factory()->create();
        NotificationLog::query()->create([
            'customer_id' => $customer->id, 'type' => 'auction', 'channel' => 'email',
            'event' => 'auction.won', 'status' => 'sent',
        ]);

        Livewire::test('panel.notifications.index')
            ->assertOk()
            ->assertSee('Notification Log')
            ->assertSee('auction.won')
            ->set('status', 'sent')
            ->assertSee('auction.won');
    }

    public function test_notification_log_denied_for_inspection_staff(): void
    {
        $this->actingAsRole('Inspection Staff');
        Livewire::test('panel.notifications.index')->assertForbidden();
    }

    public function test_notification_log_component_is_read_only(): void
    {
        $this->actingAsRole('Customer Support');
        $component = Livewire::test('panel.notifications.index');
        $this->assertFalse(method_exists($component->instance(), 'send'));
        $this->assertFalse(method_exists($component->instance(), 'retry'));
        $this->assertFalse(method_exists($component->instance(), 'delete'));
    }

    // ---- Audit Logs ----

    public function test_audit_log_renders_for_super_admin_with_search(): void
    {
        $this->actingAsRole('Super Admin');
        AuditLog::query()->create([
            'user_id' => null, 'action' => 'kyc.approved', 'module' => 'customer_kyc',
            'entity_type' => 'App\\Models\\CustomerKyc', 'entity_id' => 1,
            'ip_address' => '127.0.0.1', 'created_at' => now(),
        ]);

        Livewire::test('panel.audit-logs.index')
            ->assertOk()
            ->assertSee('Audit Log')
            ->assertSee('kyc.approved')
            ->set('search', 'kyc.approved')
            ->assertSee('kyc.approved');
    }

    public function test_audit_log_denied_for_role_without_permission(): void
    {
        // Finance Manager lacks 'view audit logs'.
        $this->actingAsRole('Finance Manager');
        Livewire::test('panel.audit-logs.index')->assertForbidden();
    }

    public function test_audit_log_component_is_read_only(): void
    {
        $this->actingAsRole('Super Admin');
        $component = Livewire::test('panel.audit-logs.index');
        $this->assertFalse(method_exists($component->instance(), 'delete'));
        $this->assertFalse(method_exists($component->instance(), 'update'));
    }
}
