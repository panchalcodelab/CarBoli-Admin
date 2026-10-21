<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Inspection;
use App\Models\User;
use App\Services\InspectionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_manager_can_schedule_an_inspection_for_active_inspection_staff(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Car / Inventory Manager', 'web'));
        $inspector = $this->createInspector();
        $car = Car::factory()->create();
        $this->actingAs($manager);

        $inspection = (new InspectionService)->schedule($this->inspectionData($car, $inspector));

        $this->assertSame($car->id, $inspection->car_id);
        $this->assertSame($inspector->id, $inspection->assigned_staff_id);
        $this->assertSame('scheduled', $inspection->status);
        $this->assertDatabaseHas('inspections', ['id' => $inspection->id]);
    }

    public function test_overlapping_inspections_for_the_same_staff_are_rejected(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Car / Inventory Manager', 'web'));
        $inspector = $this->createInspector();
        $this->actingAs($manager);
        $service = new InspectionService;

        $service->schedule($this->inspectionData(Car::factory()->create(), $inspector));

        $overlappingData = $this->inspectionData(Car::factory()->create(), $inspector);
        $overlappingData['start_time'] = '10:30';
        $overlappingData['end_time'] = '11:30';

        try {
            $service->schedule($overlappingData);
            $this->fail('An inspector must not be double-booked during overlapping times.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inspections', 1);
        }
    }

    public function test_inspection_staff_can_update_only_assigned_status_and_upload_documents(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Storage::disk('local')->put('inspections/documents/report.pdf', 'inspection report', ['visibility' => 'private']);

        $inspector = $this->createInspector();
        $otherInspector = $this->createInspector();
        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Car / Inventory Manager', 'web'));
        $service = new InspectionService;
        $this->actingAs($manager);
        $inspectionData = $this->inspectionData(Car::factory()->create(), $inspector);
        $inspectionData['status'] = 'assigned';
        $inspection = $service->schedule($inspectionData);
        $otherInspectionData = $this->inspectionData(Car::factory()->create(), $otherInspector);
        $otherInspectionData['status'] = 'assigned';
        $otherInspection = $service->schedule($otherInspectionData);

        $this->actingAs($inspector);

        $this->assertTrue(Gate::allows('view', $inspection));
        $this->assertFalse(Gate::allows('view', $otherInspection));
        // Assigned-only staff see only their own inspection in the /panel list.
        Livewire::test('panel.inspections.index')
            ->assertSee($inspection->car->stock_inventory_id)
            ->assertDontSee($otherInspection->car->stock_inventory_id);

        $updatedInspection = $service->updateStatus($inspection, 'in_progress', 'Inspection started.');
        $completedInspection = $service->updateStatus($updatedInspection, 'completed', 'Inspection complete.');
        $document = $service->addDocument($completedInspection, [
            'path' => 'inspections/documents/report.pdf',
            'file_name' => 'inspection-report.pdf',
            'type' => 'report',
        ]);

        $this->assertSame('completed', $completedInspection->status);
        $this->assertSame('Inspection complete.', $completedInspection->notes);
        $this->assertSame($completedInspection->id, $document->inspection_id);
        $this->assertTrue(Storage::disk('local')->exists($document->path));

        $this->expectException(AuthorizationException::class);
        $service->updateStatus($otherInspection, 'in_progress', 'Not assigned.');
    }

    public function test_inspection_schedule_rejects_end_time_before_start_time(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Car / Inventory Manager', 'web'));
        $inspector = $this->createInspector();
        $this->actingAs($manager);
        $invalidData = $this->inspectionData(Car::factory()->create(), $inspector);
        $invalidData['start_time'] = '12:00';
        $invalidData['end_time'] = '11:00';

        $this->expectException(ValidationException::class);
        (new InspectionService)->schedule($invalidData);
    }

    public function test_customer_support_can_view_inspections_without_scheduling_them(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Car / Inventory Manager', 'web'));
        $this->actingAs($manager);
        $inspection = (new InspectionService)->schedule(
            $this->inspectionData(Car::factory()->create(), $this->createInspector()),
        );

        $support = User::factory()->create(['status' => 'active']);
        $support->assignRole(Role::findByName('Customer Support', 'web'));
        $this->actingAs($support);

        $this->assertTrue(Gate::allows('view', $inspection));
        $this->assertFalse(Gate::allows('create', Inspection::class));
        Livewire::test('panel.inspections.index')
            ->assertOk()
            ->assertSee($inspection->car->stock_inventory_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectionData(Car $car, User $inspector): array
    {
        return [
            'car_id' => $car->id,
            'assigned_staff_id' => $inspector->id,
            'inspection_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'location' => 'Main Yard',
            'status' => 'scheduled',
        ];
    }

    private function createInspector(): User
    {
        $inspector = User::factory()->create(['status' => 'active']);
        $inspector->assignRole(Role::findByName('Inspection Staff', 'web'));

        return $inspector;
    }
}
