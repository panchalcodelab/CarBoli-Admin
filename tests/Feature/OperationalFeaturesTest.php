<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\ReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_log_tracks_queued_sent_and_failed_events(): void
    {
        $customer = Customer::factory()->create();
        $service = app(NotificationService::class);
        $queuedLog = $service->queue([
            'customer_id' => $customer->id,
            'type' => 'auction',
            'channel' => 'email',
            'event' => 'auction.won',
            'template_identifier' => 'auction.winner',
        ]);

        $this->assertSame('queued', $queuedLog->status);

        $sentLog = $service->markSent($queuedLog, 'provider-123');
        $this->assertSame('sent', $sentLog->status);
        $this->assertSame('provider-123', $sentLog->provider_reference);
        $this->assertNotNull($sentLog->sent_at);

        $failedLog = $service->queue([
            'type' => 'payment',
            'channel' => 'sms',
            'event' => 'payment.failed',
        ]);
        $failedLog = $service->markFailed($failedLog, 'Provider unavailable.');
        $this->assertSame('failed', $failedLog->status);
        $this->assertSame('Provider unavailable.', $failedLog->failure_reason);

        $this->expectException(ValidationException::class);
        $service->markFailed($sentLog, 'Cannot overwrite sent outcome.');
    }

    public function test_audit_service_records_actor_and_policy_keeps_logs_append_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = $this->userWithRole('Super Admin');
        $this->actingAs($superAdmin);
        $customer = Customer::factory()->create();

        $auditLog = app(AuditLogService::class)->record(
            'customers',
            'customer.blocked',
            $customer,
            ['account_status' => 'active'],
            ['account_status' => 'blocked'],
        );

        $this->assertSame($superAdmin->id, $auditLog->user_id);
        $this->assertSame(Customer::class, $auditLog->entity_type);
        $this->assertSame(['account_status' => 'active'], $auditLog->old_values);
        $this->assertSame(['account_status' => 'blocked'], $auditLog->new_values);
        $this->assertTrue(Gate::allows('viewAny', AuditLog::class));
        $this->assertFalse(Gate::allows('update', $auditLog));
        $this->assertFalse(Gate::allows('delete', $auditLog));
    }

    public function test_report_csv_export_requires_role_permission_and_uses_fixed_download_name(): void
    {
        $financeManager = $this->userWithRole('Finance Manager');
        $this->actingAs($financeManager);
        $this->assertTrue($financeManager->can('view financial reports'));
        $this->assertTrue($financeManager->canAccessAdminPanel());
        $this->get('/admin/reports')->assertOk();

        $this->get('/admin/reports/export?type=payments')
            ->assertOk()
            ->assertDownload('car-boli-payments-report.csv');

        $this->get('/admin/reports/export?type=customers')
            ->assertForbidden();

        $this->get('/admin/reports/export?type=payments&from=not-a-date')
            ->assertSessionHasErrors('from');
    }

    public function test_customer_support_can_export_customer_data_but_not_financial_data(): void
    {
        $this->actingAs($this->userWithRole('Customer Support'));

        $this->get('/admin/reports/export?type=customers')
            ->assertOk()
            ->assertDownload('car-boli-customers-report.csv');

        $this->get('/admin/reports/export?type=payments')
            ->assertForbidden();
    }

    public function test_customer_csv_export_neutralizes_spreadsheet_formulas(): void
    {
        $this->actingAs($this->userWithRole('Customer Support'));
        Customer::factory()->create([
            'full_name' => '=1+1',
            'email' => 'formula@example.com',
        ]);

        $response = $this->get('/admin/reports/export?type=customers');

        $response->assertDownload('car-boli-customers-report.csv');
        $this->assertStringContainsString("'=1+1", $response->streamedContent());
    }

    public function test_report_page_shows_only_role_permitted_sections(): void
    {
        $financeManager = $this->userWithRole('Finance Manager');
        $this->actingAs($financeManager);

        Livewire::test('panel.reports.index')
            ->assertOk()
            ->assertSee('Captured amount')
            ->assertSee('Payments CSV')
            ->assertDontSee('Customers CSV');

        $customerSupport = $this->userWithRole('Customer Support');
        $this->actingAs($customerSupport);

        Livewire::test('panel.reports.index')
            ->assertOk()
            ->assertSee('Customers CSV')
            ->assertDontSee('Captured amount');
    }

    public function test_unassigned_active_user_cannot_access_admin_panel(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertForbidden();
    }

    public function test_report_summary_and_dashboard_stats_are_permission_scoped(): void
    {
        $financeManager = $this->userWithRole('Finance Manager');
        $this->actingAs($financeManager);
        $customer = Customer::factory()->create();

        $summary = app(ReportService::class)->summary($financeManager);

        $this->assertNotNull($summary['payment_count']);
        $this->assertNull($summary['customer_count']);
        // A finance manager sees the dashboard financial stat card.
        Livewire::test('panel.dashboard')->assertOk()->assertSee('Auction value this month');

        $unprivilegedUser = User::factory()->create();
        $this->actingAs($unprivilegedUser);

        $restrictedSummary = app(ReportService::class)->summary($unprivilegedUser);

        $this->assertNull($restrictedSummary['payment_count']);
        $this->assertNull($restrictedSummary['captured_payment_total']);
        $this->assertNull($restrictedSummary['customer_count']);
        // An unprivileged user cannot access the admin panel at all.
        $this->assertFalse($unprivilegedUser->canAccessAdminPanel());
        $this->assertNotNull($customer->id);
    }

    public function test_report_summary_respects_date_filters(): void
    {
        $financeManager = $this->userWithRole('Finance Manager');
        $summary = app(ReportService::class)->summary($financeManager, '2000-01-01', '2000-12-31');

        $this->assertSame(0, $summary['payment_count']);
        $this->assertEquals(0, $summary['captured_payment_total']);
    }

    private function userWithRole(string $roleName): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(Role::findByName($roleName, 'web'));

        return $user;
    }
}
