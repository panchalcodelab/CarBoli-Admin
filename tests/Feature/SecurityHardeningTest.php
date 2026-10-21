<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Car;
use App\Models\CustomerKyc;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuctionLifecycleService;
use App\Services\AuditLogService;
use App\Services\PaymentWorkflowService;
use App\Services\StaffUserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Unauthenticated access
    // -------------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_access_admin_panel(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_unauthenticated_user_cannot_access_report_export(): void
    {
        $this->get('/admin/reports/export?type=payments')->assertRedirect();
    }

    public function test_unauthenticated_user_cannot_access_kyc_document(): void
    {
        $kyc = CustomerKyc::factory()->create(['document_path' => 'kyc/doc.pdf']);

        $this->get(route('admin.kyc.documents', [$kyc, 'identity']))->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // Role-based access — wrong role cannot reach protected resources
    // -------------------------------------------------------------------------

    public function test_inspection_staff_cannot_access_auction_resource(): void
    {
        $this->actingAs($this->userWithRole('Inspection Staff'));

        $this->assertFalse(Gate::allows('viewAny', Auction::class));
    }

    public function test_finance_manager_cannot_manage_staff(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));

        $this->assertFalse(Gate::allows('create', User::class));
        $this->assertFalse(Gate::allows('viewAny', User::class));
    }

    public function test_auction_manager_cannot_update_or_delete_payments(): void
    {
        $this->actingAs($this->userWithRole('Auction Manager'));
        $payment = Payment::factory()->create();

        $this->assertFalse(Gate::allows('update', $payment));
        $this->assertFalse(Gate::allows('delete', $payment));
    }

    public function test_customer_support_cannot_manage_cars(): void
    {
        $this->actingAs($this->userWithRole('Customer Support'));
        $car = Car::factory()->create();

        $this->assertFalse(Gate::allows('create', Car::class));
        $this->assertFalse(Gate::allows('update', $car));
        $this->assertFalse(Gate::allows('delete', $car));
    }

    public function test_car_inventory_manager_cannot_view_auctions(): void
    {
        $this->actingAs($this->userWithRole('Car / Inventory Manager'));

        $this->assertFalse(Gate::allows('viewAny', Auction::class));
    }

    // -------------------------------------------------------------------------
    // Payment — no direct mutation; only service-controlled transitions
    // -------------------------------------------------------------------------

    public function test_payment_cannot_be_updated_or_deleted_by_any_role(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));
        $payment = Payment::factory()->create();

        $this->assertFalse(Gate::allows('update', $payment));
        $this->assertFalse(Gate::allows('delete', $payment));
        $this->assertFalse(Gate::allows('deleteAny', Payment::class));
    }

    public function test_payment_status_cannot_be_changed_by_unprivileged_user(): void
    {
        $this->actingAs(User::factory()->create());
        $payment = Payment::factory()->create(['status' => 'pending']);

        $this->expectException(AuthorizationException::class);
        app(PaymentWorkflowService::class)->capture($payment);
    }

    public function test_payment_cannot_skip_from_pending_to_refunded(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));
        $payment = Payment::factory()->create(['status' => 'pending', 'payment_type' => 'entry_fee']);

        $this->expectException(ValidationException::class);
        app(PaymentWorkflowService::class)->refund($payment);
    }

    // -------------------------------------------------------------------------
    // Auction — no direct mutation of live/completed auctions
    // -------------------------------------------------------------------------

    public function test_published_auction_cannot_be_updated_via_service(): void
    {
        $this->actingAs($this->userWithRole('Auction Manager'));
        $auction = Auction::factory()->create(['status' => 'scheduled', 'published' => true]);

        $this->expectException(AuthorizationException::class);
        app(AuctionLifecycleService::class)->update($auction, [
            'car_id' => $auction->car_id,
            'title' => 'Tampered title',
            'starting_price' => 1,
            'minimum_bid_increment' => 1,
        ]);
    }

    public function test_completed_auction_cannot_be_cancelled(): void
    {
        $this->actingAs($this->userWithRole('Auction Manager'));
        $auction = Auction::factory()->create(['status' => 'completed', 'published' => true]);

        $this->expectException(ValidationException::class);
        app(AuctionLifecycleService::class)->cancel($auction);
    }

    public function test_auction_cannot_be_deleted_by_any_role(): void
    {
        $this->actingAs($this->userWithRole('Auction Manager'));
        $auction = Auction::factory()->create();

        $this->assertFalse(Gate::allows('delete', $auction));
        $this->assertFalse(Gate::allows('deleteAny', Auction::class));
    }

    // -------------------------------------------------------------------------
    // Bid — read-only; no create/update/delete via policy
    // -------------------------------------------------------------------------

    public function test_bid_cannot_be_created_updated_or_deleted_via_policy(): void
    {
        $this->actingAs($this->userWithRole('Auction Manager'));
        $bid = Bid::factory()->create();

        $this->assertFalse(Gate::allows('create', Bid::class));
        $this->assertFalse(Gate::allows('update', $bid));
        $this->assertFalse(Gate::allows('delete', $bid));
        $this->assertFalse(Gate::allows('deleteAny', Bid::class));
    }

    // -------------------------------------------------------------------------
    // Audit log — append-only
    // -------------------------------------------------------------------------

    public function test_audit_log_cannot_be_updated_or_deleted_by_any_role(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        $log = AuditLog::factory()->create();

        $this->assertFalse(Gate::allows('update', $log));
        $this->assertFalse(Gate::allows('delete', $log));
        $this->assertFalse(Gate::allows('deleteAny', AuditLog::class));
    }

    public function test_audit_log_service_throws_on_delete(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(\LogicException::class);
        app(AuditLogService::class)->delete($log);
    }

    // -------------------------------------------------------------------------
    // Protected file access — path traversal blocked
    // -------------------------------------------------------------------------

    public function test_kyc_document_route_blocks_path_traversal_attempt(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        $kyc = CustomerKyc::factory()->create(['document_path' => '../../.env']);

        $this->get(route('admin.kyc.documents', [$kyc, 'identity']))->assertNotFound();
    }

    public function test_kyc_document_route_returns_404_for_missing_file(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        $kyc = CustomerKyc::factory()->create(['document_path' => 'kyc/nonexistent.pdf']);

        $this->get(route('admin.kyc.documents', [$kyc, 'identity']))->assertNotFound();
    }

    public function test_kyc_document_route_requires_kyc_view_permission(): void
    {
        $this->actingAs($this->userWithRole('Auction Manager'));
        $kyc = CustomerKyc::factory()->create(['document_path' => 'kyc/doc.pdf']);

        $this->get(route('admin.kyc.documents', [$kyc, 'identity']))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Report export — invalid payload rejected server-side
    // -------------------------------------------------------------------------

    public function test_report_export_rejects_invalid_type(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));

        $this->get('/admin/reports/export?type=users')->assertSessionHasErrors('type');
    }

    public function test_report_export_rejects_invalid_date_format(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));

        $this->get('/admin/reports/export?type=payments&from=not-a-date')->assertSessionHasErrors('from');
    }

    public function test_report_export_rejects_to_date_before_from_date(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));

        $this->get('/admin/reports/export?type=payments&from=2025-12-31&to=2025-01-01')
            ->assertSessionHasErrors('to');
    }

    public function test_customer_support_cannot_export_financial_data(): void
    {
        $this->actingAs($this->userWithRole('Customer Support'));

        $this->get('/admin/reports/export?type=payments')->assertForbidden();
        $this->get('/admin/reports/export?type=auctions')->assertForbidden();
    }

    public function test_finance_manager_cannot_export_customer_data(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));

        $this->get('/admin/reports/export?type=customers')->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Super Admin role escalation guard
    // -------------------------------------------------------------------------

    public function test_non_super_admin_cannot_assign_super_admin_role(): void
    {
        $actor = $this->userWithRole('Auction Manager');
        $this->actingAs($actor);

        $target = User::factory()->create();
        $superAdminRole = Role::findByName('Super Admin', 'web');

        $this->expectException(AuthorizationException::class);
        app(StaffUserService::class)->update($target, [
            'name' => $target->name,
            'email' => $target->email,
            'mobile' => null,
            'status' => 'active',
            'role_id' => $superAdminRole->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Inactive/suspended staff blocked from panel
    // -------------------------------------------------------------------------

    public function test_suspended_staff_cannot_access_admin_panel(): void
    {
        $user = $this->userWithRole('Auction Manager');
        $user->update(['status' => 'suspended']);

        $this->actingAs($user->fresh())->get('/admin')->assertForbidden();
    }

    public function test_inactive_staff_cannot_access_admin_panel(): void
    {
        $user = $this->userWithRole('Finance Manager');
        $user->update(['status' => 'inactive']);

        $this->actingAs($user->fresh())->get('/admin')->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function userWithRole(string $roleName): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(Role::findByName($roleName, 'web'));

        return $user;
    }
}
