<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelCustomersKycTest extends TestCase
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

    // ---- Customers list ----

    public function test_customers_list_renders_with_search_and_filters(): void
    {
        $this->actingAsRole('Super Admin');
        $a = Customer::factory()->create(['full_name' => 'Ravi Patel', 'account_status' => 'active', 'kyc_status' => 'approved']);
        $b = Customer::factory()->create(['full_name' => 'Sita Shah', 'account_status' => 'blocked', 'kyc_status' => 'pending']);

        Livewire::test('panel.customers.index')
            ->assertOk()
            ->assertSee('User Management')
            ->assertSee('Ravi Patel')
            ->assertSee('Sita Shah')
            ->set('search', 'Ravi')
            ->assertSee('Ravi Patel')
            ->assertDontSee('Sita Shah')
            ->set('search', '')
            ->set('account_status', 'blocked')
            ->assertSee('Sita Shah')
            ->assertDontSee('Ravi Patel');
    }

    public function test_customer_support_can_view_list_but_cannot_block(): void
    {
        $this->actingAsRole('Customer Support');
        $customer = Customer::factory()->create(['account_status' => 'active']);

        // Support can view the list.
        Livewire::test('panel.customers.index')->assertOk()->assertSee($customer->full_name);

        // But block is denied (policy).
        Livewire::test('panel.customers.index')
            ->call('block', $customer->id)
            ->assertForbidden();
        $this->assertSame('active', $customer->fresh()->account_status);
    }

    public function test_super_admin_can_block_and_unblock_from_list_with_audit(): void
    {
        $admin = $this->actingAsRole('Super Admin');
        $customer = Customer::factory()->create(['account_status' => 'active', 'is_blocked' => false]);

        Livewire::test('panel.customers.index')->call('block', $customer->id);
        $this->assertSame('blocked', $customer->fresh()->account_status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id, 'action' => 'customer.blocked', 'entity_id' => $customer->id,
        ]);

        Livewire::test('panel.customers.index')->call('unblock', $customer->id);
        $this->assertSame('active', $customer->fresh()->account_status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id, 'action' => 'customer.unblocked', 'entity_id' => $customer->id,
        ]);
    }

    public function test_customer_detail_hides_kyc_fields_without_permission(): void
    {
        // Auction Manager has neither view-customers nor view-customer-kyc.
        $this->actingAsRole('Auction Manager');
        $customer = Customer::factory()->create();

        Livewire::test('panel.customers.show', ['customer' => $customer->id])
            ->assertForbidden();
    }

    public function test_customer_detail_renders_for_authorized_user(): void
    {
        $this->actingAsRole('Super Admin');
        $customer = Customer::factory()->create(['kyc_rejection_reason' => 'Blurry doc']);

        Livewire::test('panel.customers.show', ['customer' => $customer->id])
            ->assertOk()
            ->assertSee($customer->full_name)
            ->assertSee('Customer profile')
            ->assertSee('Blurry doc');
    }

    // ---- KYC ----

    public function test_kyc_list_renders_with_status_filter(): void
    {
        $this->actingAsRole('Super Admin');
        $pending = CustomerKyc::factory()->create(['status' => 'pending']);
        $approved = CustomerKyc::factory()->create(['status' => 'approved']);

        Livewire::test('panel.kyc.index')
            ->assertOk()
            ->assertSee('KYC Verification')
            ->set('status', 'approved')
            ->assertSee($approved->customer->full_name);
    }

    public function test_customer_support_can_view_kyc_but_review_actions_denied(): void
    {
        $this->actingAsRole('Customer Support');
        $kyc = CustomerKyc::factory()->create(['status' => 'pending']);

        Livewire::test('panel.kyc.show', ['kyc' => $kyc->id])
            ->assertOk()
            ->call('approve')
            ->assertForbidden();
        $this->assertSame('pending', $kyc->fresh()->status);
    }

    public function test_super_admin_can_start_review_and_approve_kyc_with_audit(): void
    {
        $admin = $this->actingAsRole('Super Admin');
        $kyc = CustomerKyc::factory()->create(['status' => 'pending']);

        Livewire::test('panel.kyc.show', ['kyc' => $kyc->id])
            ->call('startReview')
            ->call('approve');

        $this->assertSame('approved', $kyc->fresh()->status);
        $this->assertSame('approved', $kyc->fresh()->customer->kyc_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'kyc.approved', 'entity_id' => $kyc->id, 'user_id' => $admin->id,
        ]);
    }

    public function test_kyc_rejection_requires_reason_and_records_it(): void
    {
        $this->actingAsRole('Super Admin');
        $kyc = CustomerKyc::factory()->create(['status' => 'under_review']);

        // Empty reason blocked by validation.
        Livewire::test('panel.kyc.show', ['kyc' => $kyc->id])
            ->set('rejectReason', '')
            ->call('reject')
            ->assertHasErrors('rejectReason');
        $this->assertSame('under_review', $kyc->fresh()->status);

        // Valid reason persists.
        Livewire::test('panel.kyc.show', ['kyc' => $kyc->id])
            ->set('rejectReason', 'Document unreadable.')
            ->call('reject')
            ->assertHasNoErrors();
        $this->assertSame('rejected', $kyc->fresh()->status);
        $this->assertSame('Document unreadable.', $kyc->fresh()->rejection_reason);
    }

    public function test_unprivileged_user_cannot_access_kyc_or_customers(): void
    {
        $this->actingAsRole('Inspection Staff');

        Livewire::test('panel.customers.index')->assertForbidden();
        Livewire::test('panel.kyc.index')->assertForbidden();
    }
}
