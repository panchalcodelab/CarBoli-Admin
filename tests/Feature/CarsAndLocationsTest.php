<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Location;
use App\Models\User;
use App\Services\CarInventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CarsAndLocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_manager_can_manage_cars_and_locations(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole(Role::findByName('Car / Inventory Manager', 'web'));

        $this->assertTrue(Gate::forUser($manager)->allows('viewAny', Car::class));
        $this->assertTrue(Gate::forUser($manager)->allows('create', Car::class));
        $this->assertTrue(Gate::forUser($manager)->allows('viewAny', Location::class));
        $this->assertTrue(Gate::forUser($manager)->allows('create', Location::class));

        $this->actingAs($manager);

        $this->assertTrue(Gate::allows('viewAny', Car::class));
        $this->assertTrue(Gate::allows('viewAny', Location::class));
        $this->get('/admin/cars')->assertOk();
        $this->get('/admin/locations')->assertOk();
    }

    public function test_auction_manager_cannot_create_inventory_records(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Auction Manager', 'web'));

        $this->assertFalse(Gate::forUser($manager)->allows('create', Car::class));
        $this->assertFalse(Gate::forUser($manager)->allows('create', Location::class));
    }

    public function test_car_creation_stores_its_location(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Car / Inventory Manager', 'web'));
        $this->actingAs($manager);

        $location = Location::query()->create([
            'name' => 'North Yard',
            'city' => 'Delhi',
            'state' => 'Delhi',
        ]);

        $car = app(CarInventoryService::class)->create($this->carData($location));

        $this->assertSame($location->id, $car->location_id);
        $this->assertSame('CB-2026-001', $car->stock_inventory_id);
        $this->assertSame('available', $car->status);

    }

    public function test_car_creation_rejects_negative_prices(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Car / Inventory Manager', 'web'));
        $this->actingAs($manager);

        $location = Location::query()->create(['name' => 'East Yard']);
        $invalidData = $this->carData($location);
        $invalidData['stock_inventory_id'] = 'CB-2026-002';
        $invalidData['expected_price'] = -1;

        try {
            app(CarInventoryService::class)->create($invalidData);
            $this->fail('Negative car prices must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('cars', ['stock_inventory_id' => 'CB-2026-002']);
        }
    }

    public function test_car_can_be_updated_without_violating_its_unique_inventory_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Car / Inventory Manager', 'web'));
        $this->actingAs($manager);

        $location = Location::query()->create(['name' => 'West Yard']);
        $service = app(CarInventoryService::class);
        $car = $service->create($this->carData($location));
        $updatedData = $this->carData($location);
        $updatedData['make'] = 'Updated Make';
        $updatedData['status'] = 'archived';

        $updatedCar = $service->update($car, $updatedData);

        $this->assertSame('Updated Make', $updatedCar->make);
        $this->assertSame('archived', $updatedCar->status);
    }

    public function test_unprivileged_user_cannot_create_car_even_with_valid_data(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $auctionManager = User::factory()->create();
        $auctionManager->assignRole(Role::findByName('Auction Manager', 'web'));
        $this->actingAs($auctionManager);

        $location = Location::query()->create(['name' => 'South Yard']);

        $this->expectException(AuthorizationException::class);
        app(CarInventoryService::class)->create($this->carData($location));
    }

    /**
     * @return array<string, mixed>
     */
    private function carData(Location $location): array
    {
        return [
            'location_id' => $location->id,
            'stock_inventory_id' => 'CB-2026-001',
            'make' => 'Toyota',
            'model' => 'Innova',
            'variant' => 'ZX',
            'manufacturing_year' => 2020,
            'registration_year' => 2021,
            'fuel_type' => 'diesel',
            'transmission' => 'automatic',
            'body_type' => 'suv',
            'color' => 'silver',
            'kilometer' => 40000,
            'number_of_owners' => 1,
            'registration_number' => 'DL01AB1234',
            'expected_price' => 1200000,
            'reserve_price' => 900000,
            'starting_auction_price' => 500000,
            'status' => 'available',
            'short_description' => 'Well-maintained SUV',
            'detailed_description' => 'Service records are available.',
            'specifications' => 'ABS, airbags, reverse camera',
        ];
    }
}
