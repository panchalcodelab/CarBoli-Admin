<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Inspection;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelOperationsGroupTest extends TestCase
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

    // ---- Payments (read-only) ----

    public function test_payments_list_renders_for_finance_manager(): void
    {
        $this->actingAsRole('Finance Manager');
        $payment = Payment::factory()->create(['status' => 'captured']);

        Livewire::test('panel.payments.index')
            ->assertOk()
            ->assertSee('Payments')
            ->set('status', 'captured')
            ->assertSee($payment->customer->full_name);
    }

    public function test_payments_denied_for_inspection_staff(): void
    {
        $this->actingAsRole('Inspection Staff');
        Livewire::test('panel.payments.index')->assertForbidden();
    }

    public function test_payment_detail_is_read_only_and_authorized(): void
    {
        $this->actingAsRole('Finance Manager');
        $payment = Payment::factory()->create();

        Livewire::test('panel.payments.show', ['payment' => $payment->id])
            ->assertOk()
            ->assertSee('read-only financial record');

        // No capture/fail/release/refund methods are exposed on the component.
        $component = Livewire::test('panel.payments.show', ['payment' => $payment->id]);
        $this->assertFalse(method_exists($component->instance(), 'capture'));
        $this->assertFalse(method_exists($component->instance(), 'refund'));
    }

    // ---- Inspections ----

    public function test_inspections_list_renders_and_is_staff_scoped(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole(Role::findByName('Inspection Staff', 'web'));

        $mine = Inspection::factory()->create(['assigned_staff_id' => $staff->id, 'status' => 'assigned']);
        $other = Inspection::factory()->create(['status' => 'assigned']);

        $this->actingAs($staff);

        Livewire::test('panel.inspections.index')
            ->assertOk()
            ->assertSee($mine->car->stock_inventory_id)
            ->assertDontSee($other->car->stock_inventory_id);
    }

    public function test_inspection_manager_can_schedule_via_livewire(): void
    {
        $this->actingAsRole('Car / Inventory Manager');
        $car = Car::factory()->create();

        Livewire::test('panel.inspections.form')
            ->set('car_id', $car->id)
            ->set('inspection_date', now()->addDay()->format('Y-m-d'))
            ->set('start_time', '10:00')
            ->set('end_time', '11:00')
            ->set('status', 'scheduled')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('inspections', ['car_id' => $car->id, 'status' => 'scheduled']);
    }

    public function test_inspection_status_transition_is_validated_server_side(): void
    {
        $this->actingAsRole('Car / Inventory Manager');
        $inspection = Inspection::factory()->create(['status' => 'scheduled']);

        // scheduled -> completed is NOT an allowed direct transition.
        Livewire::test('panel.inspections.show', ['inspection' => $inspection->id])
            ->set('newStatus', 'completed')
            ->call('updateStatus')
            ->assertHasErrors('statusAction');
        $this->assertSame('scheduled', $inspection->fresh()->status);

        // scheduled -> confirmed IS allowed.
        Livewire::test('panel.inspections.show', ['inspection' => $inspection->id])
            ->set('newStatus', 'confirmed')
            ->call('updateStatus')
            ->assertHasNoErrors('statusAction');
        $this->assertSame('confirmed', $inspection->fresh()->status);
    }

    public function test_inspections_denied_for_finance_manager(): void
    {
        $this->actingAsRole('Finance Manager');
        Livewire::test('panel.inspections.index')->assertForbidden();
    }

    // ---- Locations ----

    public function test_locations_list_and_create_for_inventory_manager(): void
    {
        $this->actingAsRole('Car / Inventory Manager');

        Livewire::test('panel.locations.index')->assertOk()->assertSee('Locations');

        Livewire::test('panel.locations.form')
            ->set('name', 'North Yard')
            ->set('city', 'Delhi')
            ->set('latitude', 28.61)
            ->set('longitude', 77.20)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('locations', ['name' => 'North Yard', 'city' => 'Delhi']);
    }

    public function test_location_validation_rejects_bad_coordinates(): void
    {
        $this->actingAsRole('Car / Inventory Manager');

        Livewire::test('panel.locations.form')
            ->set('name', 'Bad Yard')
            ->set('latitude', 999)
            ->call('save')
            ->assertHasErrors('latitude');

        $this->assertDatabaseMissing('locations', ['name' => 'Bad Yard']);
    }

    public function test_locations_denied_for_finance_manager(): void
    {
        $this->actingAsRole('Finance Manager');
        Livewire::test('panel.locations.index')->assertForbidden();
        Livewire::test('panel.locations.form')->assertForbidden();
    }
}
