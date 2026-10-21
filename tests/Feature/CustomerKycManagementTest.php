<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\CustomerKycDocumentController;
use App\Models\CustomerKyc;
use App\Models\User;
use App\Services\KycReviewService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class CustomerKycManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_support_can_view_kyc_but_cannot_review_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $support = User::factory()->create();
        $support->assignRole(Role::findByName('Customer Support', 'web'));
        $customerKyc = CustomerKyc::factory()->create();

        $this->assertTrue(Gate::forUser($support)->allows('view', $customerKyc));
        $this->assertFalse(Gate::forUser($support)->allows('review', $customerKyc));
    }

    public function test_super_admin_can_approve_kyc_and_the_change_is_audited(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::findByName('Super Admin', 'web'));
        $this->actingAs($reviewer);

        $customerKyc = CustomerKyc::factory()->create();
        app(KycReviewService::class)->markUnderReview($customerKyc);

        $reviewedKyc = app(KycReviewService::class)->approve($customerKyc->fresh());

        $this->assertSame('approved', $reviewedKyc->status);
        $this->assertSame($reviewer->id, $reviewedKyc->reviewer_id);
        $this->assertNotNull($reviewedKyc->reviewed_at);
        $this->assertSame('approved', $reviewedKyc->customer->fresh()->kyc_status);
        $this->assertNotNull($reviewedKyc->customer->fresh()->kyc_approved_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $reviewer->id,
            'action' => 'kyc.approved',
            'module' => 'customer_kyc',
            'entity_type' => CustomerKyc::class,
            'entity_id' => $customerKyc->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $reviewer->id,
            'action' => 'kyc.under_review',
            'module' => 'customer_kyc',
            'entity_type' => CustomerKyc::class,
            'entity_id' => $customerKyc->id,
        ]);

        try {
            app(KycReviewService::class)->approve($reviewedKyc);
            $this->fail('A finalized KYC record must not be finalized a second time.');
        } catch (ValidationException) {
            $this->assertSame(2, $reviewedKyc->auditLogs()->count());
        }
    }

    public function test_rejection_requires_a_reason_and_records_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::findByName('Super Admin', 'web'));
        $this->actingAs($reviewer);

        $customerKyc = CustomerKyc::factory()->create(['status' => 'under_review']);
        $service = app(KycReviewService::class);

        try {
            $service->reject($customerKyc, '');
            $this->fail('A rejection reason is required.');
        } catch (ValidationException) {
            $this->assertSame('under_review', $customerKyc->fresh()->status);
        }

        $rejectedKyc = $service->reject($customerKyc, 'Document is unreadable.');

        $this->assertSame('rejected', $rejectedKyc->status);
        $this->assertSame('Document is unreadable.', $rejectedKyc->rejection_reason);
        $this->assertSame('rejected', $rejectedKyc->customer->fresh()->kyc_status);
        $this->assertSame('Document is unreadable.', $rejectedKyc->customer->fresh()->kyc_rejection_reason);
    }

    public function test_kyc_document_download_uses_private_storage_and_requires_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Storage::disk('local')->put('kyc/id-proof.pdf', 'private document');

        $customerKyc = CustomerKyc::factory()->create([
            'document_path' => 'kyc/id-proof.pdf',
        ]);
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::findByName('Super Admin', 'web'));
        $this->actingAs($reviewer);

        $response = app(CustomerKycDocumentController::class)($customerKyc, 'identity');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);

        $auctionManager = User::factory()->create();
        $auctionManager->assignRole(Role::findByName('Auction Manager', 'web'));
        $this->actingAs($auctionManager);

        $this->expectException(AuthorizationException::class);
        app(CustomerKycDocumentController::class)($customerKyc, 'identity');
    }

    public function test_private_document_download_rejects_path_traversal(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::findByName('Super Admin', 'web'));
        $this->actingAs($reviewer);

        $customerKyc = CustomerKyc::factory()->create([
            'document_path' => '../../.env',
        ]);

        $this->expectException(NotFoundHttpException::class);
        app(CustomerKycDocumentController::class)($customerKyc, 'identity');
    }
}
