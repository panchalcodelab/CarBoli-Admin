<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerAccountService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_support_can_view_customers_but_cannot_block_them(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $support = User::factory()->create(['status' => 'active']);
        $support->assignRole(Role::findByName('Customer Support', 'web'));
        $customer = Customer::factory()->create();

        $this->assertTrue(Gate::forUser($support)->allows('viewAny', Customer::class));
        $this->assertTrue(Gate::forUser($support)->allows('view', $customer));
        $this->assertFalse(Gate::forUser($support)->allows('block', $customer));

        $this->actingAs($support);

        $this->assertTrue(Gate::allows('viewAny', Customer::class));
        $this->assertFalse(Gate::allows('create', Customer::class));
        $this->assertFalse(Gate::allows('update', $customer));
        $this->get('/admin/customers')->assertOk();

        try {
            app(CustomerAccountService::class)->block($customer);
            $this->fail('Customer Support must not block customer accounts.');
        } catch (AuthorizationException) {
            $this->assertFalse($customer->fresh()->is_blocked);
        }
    }

    public function test_super_admin_can_block_and_unblock_customer_accounts(): void
    {
        $permission = Permission::create([
            'name' => 'manage customers',
            'guard_name' => 'web',
        ]);
        $role = Role::create([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $admin = User::factory()->create();
        $admin->assignRole($role);
        $this->actingAs($admin);

        $customer = Customer::factory()->create();
        $service = app(CustomerAccountService::class);

        $service->block($customer);

        $this->assertSame('blocked', $customer->fresh()->account_status);
        $this->assertTrue($customer->fresh()->is_blocked);

        $service->unblock($customer->fresh());

        $this->assertSame('active', $customer->fresh()->account_status);
        $this->assertFalse($customer->fresh()->is_blocked);
    }

    public function test_blocking_a_customer_records_a_single_audit_log(): void
    {
        $admin = $this->superAdminActingAs();
        $customer = Customer::factory()->create(['account_status' => 'active', 'is_blocked' => false]);

        app(CustomerAccountService::class)->block($customer);

        $logs = AuditLog::query()
            ->where('module', 'customers')
            ->where('action', 'customer.blocked')
            ->where('entity_type', $customer->getMorphClass())
            ->where('entity_id', $customer->getKey())
            ->get();

        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertSame($admin->getKey(), $log->user_id);
        $this->assertSame('active', $log->old_values['account_status']);
        $this->assertFalse($log->old_values['is_blocked']);
        $this->assertSame('blocked', $log->new_values['account_status']);
        $this->assertTrue($log->new_values['is_blocked']);
    }

    public function test_unblocking_a_customer_records_a_single_audit_log(): void
    {
        $admin = $this->superAdminActingAs();
        $customer = Customer::factory()->create(['account_status' => 'blocked', 'is_blocked' => true]);

        app(CustomerAccountService::class)->unblock($customer);

        $logs = AuditLog::query()
            ->where('module', 'customers')
            ->where('action', 'customer.unblocked')
            ->where('entity_type', $customer->getMorphClass())
            ->where('entity_id', $customer->getKey())
            ->get();

        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertSame($admin->getKey(), $log->user_id);
        $this->assertSame('blocked', $log->old_values['account_status']);
        $this->assertTrue($log->old_values['is_blocked']);
        $this->assertSame('active', $log->new_values['account_status']);
        $this->assertFalse($log->new_values['is_blocked']);
    }

    public function test_blocking_an_already_blocked_customer_does_not_record_an_audit_log(): void
    {
        $this->superAdminActingAs();
        $customer = Customer::factory()->create(['account_status' => 'blocked', 'is_blocked' => true]);

        app(CustomerAccountService::class)->block($customer);

        $this->assertSame(0, AuditLog::query()
            ->where('module', 'customers')
            ->where('action', 'customer.blocked')
            ->count());
    }

    private function superAdminActingAs(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'manage customers', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $admin = User::factory()->create();
        $admin->assignRole($role);
        $this->actingAs($admin);

        return $admin;
    }
}
