<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Car;
use App\Models\Location;
use App\Models\Make;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Services\AuctionLifecycleService;
use App\Services\CarApprovalService;
use App\Services\CarInventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CarWizardTest extends TestCase
{
    use RefreshDatabase;

    private function inventoryManager(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(Role::findByName('Car / Inventory Manager', 'web'));
        $this->actingAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardPayload(): array
    {
        $location = Location::factory()->create();
        $category = VehicleCategory::query()->create(['name' => 'SUV', 'slug' => 'suv', 'is_active' => true]);
        $make = Make::query()->create(['name' => 'Hyundai', 'slug' => 'hyundai', 'is_active' => true]);

        return [
            'category_id' => $category->id,
            'make_id' => $make->id,
            'stock_inventory_id' => 'CB-W-001',
            'make' => 'Hyundai',
            'model' => 'Creta',
            'variant' => 'SX(O)',
            'manufacturing_year' => 2023,
            'registration_date' => '2023-02-01',
            'number_of_owners' => 1,
            'fuel_type' => 'petrol',
            'transmission' => 'automatic',
            'engine_capacity_cc' => 1497,
            'kilometer' => 24000,
            'color' => 'Pearl white',
            'seating_capacity' => 5,
            'vin' => 'VIN123',
            'engine_number' => 'ENG123',
            'registration_number' => 'GJ01AB1234',
            'detailed_description' => 'Clean, single owner.',
            'location_id' => $location->id,
            'assigned_manager_id' => null,
            'seller_type' => 'individual',
            'seller_name' => 'Ravi Patel',
            'seller_mobile' => '9876543210',
            'seller_email' => 'ravi@example.com',
            'seller_external_reference' => 'CUST-55',
            'seller_customer_id' => null,
            'seller_address' => '12 MG Road',
            'seller_city' => 'Ahmedabad',
            'seller_state' => 'Gujarat',
            'seller_pincode' => '380001',
            'inspection_status' => 'completed',
            'assigned_inspector_id' => null,
            'exterior_condition' => 'good',
            'interior_condition' => 'good',
            'engine_condition' => 'excellent',
            'transmission_condition' => 'good',
            'tyre_condition' => 'fair',
            'battery_condition' => 'good',
            'accident_history' => 'none',
            'flood_damage' => 'no',
            'inspection_score' => 88,
            'inspector_remarks' => 'Minor wear.',
            'expected_price' => 1500000,
            'reserve_price' => 1200000,
            'starting_price' => 1000000,
            'minimum_bid_increment' => 5000,
            'auction_type' => 'standard',
            'auction_start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'auction_end_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'bidder_eligibility' => 'all_verified',
            'listing_visibility' => 'public',
            'buyer_premium_percent' => 2,
            'seller_commission_percent' => 1.5,
            'payment_deadline_days' => 3,
        ];
    }

    public function test_wizard_persists_car_seller_inspection_and_draft_auction(): void
    {
        $this->inventoryManager();

        $car = app(CarInventoryService::class)->saveWizard($this->wizardPayload());

        $this->assertDatabaseHas('cars', [
            'id' => $car->id,
            'stock_inventory_id' => 'CB-W-001',
            'status' => 'draft',
            'seating_capacity' => 5,
            'engine_capacity_cc' => 1497,
        ]);
        $this->assertDatabaseHas('car_sellers', [
            'car_id' => $car->id,
            'seller_type' => 'individual',
            'name' => 'Ravi Patel',
            'city' => 'Ahmedabad',
        ]);
        $this->assertDatabaseHas('inspections', [
            'car_id' => $car->id,
            'status' => 'completed',
            'exterior_condition' => 'good',
            'inspection_score' => 88,
        ]);
        $this->assertDatabaseHas('auctions', [
            'car_id' => $car->id,
            'status' => 'draft',
            'published' => false,
            'auction_type' => 'standard',
            'listing_visibility' => 'public',
        ]);
    }

    public function test_wizard_rejects_invalid_payload(): void
    {
        $this->inventoryManager();
        $payload = $this->wizardPayload();
        $payload['manufacturing_year'] = 1700; // out of range

        $this->expectException(ValidationException::class);
        app(CarInventoryService::class)->saveWizard($payload);
    }

    public function test_unauthorized_user_cannot_run_wizard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(Role::findByName('Finance Manager', 'web'));
        $this->actingAs($user);

        $this->expectException(AuthorizationException::class);
        app(CarInventoryService::class)->saveWizard($this->wizardPayload());
    }

    public function test_approval_workflow_moves_car_through_states(): void
    {
        $this->inventoryManager();
        $car = app(CarInventoryService::class)->saveWizard($this->wizardPayload());
        $approval = app(CarApprovalService::class);

        $approval->submitForReview($car);
        $this->assertSame('under_review', $car->fresh()->status);

        // Cannot approve before documents are approved.
        try {
            $approval->approve($car->fresh());
            $this->fail('Approval should require document approval first.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('documents', $e->errors());
        }

        $approval->approveDocuments($car->fresh());
        $approved = $approval->approve($car->fresh());
        $this->assertSame('available', $approved->status);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_rejection_returns_car_to_draft_with_reason(): void
    {
        $this->inventoryManager();
        $car = app(CarInventoryService::class)->saveWizard($this->wizardPayload());
        $approval = app(CarApprovalService::class);
        $approval->submitForReview($car);

        $rejected = $approval->reject($car->fresh(), 'Blurry documents.');

        $this->assertSame('draft', $rejected->status);
        $this->assertSame('Blurry documents.', $rejected->rejection_reason);
    }

    public function test_auction_cannot_publish_until_car_is_approved(): void
    {
        $manager = $this->inventoryManager();
        $car = app(CarInventoryService::class)->saveWizard($this->wizardPayload());

        // Grant auction permissions to publish.
        $manager->assignRole(Role::findByName('Auction Manager', 'web'));

        $auction = Auction::query()->where('car_id', $car->id)->firstOrFail();
        $auction->update([
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'starting_price' => 1000000,
            'minimum_bid_increment' => 5000,
        ]);

        try {
            app(AuctionLifecycleService::class)->publish($auction);
            $this->fail('Draft car auction must not publish.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('car_id', $e->errors());
        }

        // Approve the car, then publishing succeeds.
        $approval = app(CarApprovalService::class);
        $approval->submitForReview($car);
        $approval->approveDocuments($car->fresh());
        $approval->approve($car->fresh());

        $published = app(AuctionLifecycleService::class)->publish($auction->fresh());
        $this->assertTrue($published->published);
    }

    public function test_legacy_available_car_can_still_publish(): void
    {
        $manager = $this->inventoryManager();
        $manager->assignRole(Role::findByName('Auction Manager', 'web'));

        // A legacy car that predates the approval workflow: available, no approved_at.
        $car = Car::factory()->create(['status' => 'available']);
        $auction = app(AuctionLifecycleService::class)->create([
            'car_id' => $car->id,
            'title' => 'Legacy auction',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'starting_price' => 100000,
            'minimum_bid_increment' => 1000,
            'participation_fee' => 0,
        ]);

        $published = app(AuctionLifecycleService::class)->publish($auction);
        $this->assertTrue($published->published);
    }

    public function test_cars_index_livewire_lists_records_with_search_and_filter(): void
    {
        $this->inventoryManager();
        Car::factory()->create(['make' => 'Hyundai', 'model' => 'Creta', 'status' => 'available']);
        Car::factory()->create(['make' => 'Tata', 'model' => 'Nexon', 'status' => 'draft']);

        Livewire::test('panel.cars.index')
            ->assertOk()
            ->assertSee('Car Management')
            ->assertSee('Hyundai Creta')
            ->set('search', 'Creta')
            ->assertSee('Hyundai Creta')
            ->assertDontSee('Tata Nexon')
            ->set('search', '')
            ->set('status', 'draft')
            ->assertSee('Tata Nexon')
            ->assertDontSee('Hyundai Creta');
    }

    public function test_wizard_livewire_blocks_advancing_past_invalid_step(): void
    {
        $this->inventoryManager();

        Livewire::test('panel.cars.wizard')
            ->assertOk()
            ->assertSet('step', 1)
            ->call('nextStep')
            ->assertHasErrors(['make', 'model', 'registration_number'])
            ->assertSet('step', 1);
    }

    public function test_wizard_livewire_advances_when_step_valid(): void
    {
        $this->inventoryManager();
        $payload = $this->wizardPayload();

        Livewire::test('panel.cars.wizard')
            ->set('registration_number', $payload['registration_number'])
            ->set('make', $payload['make'])
            ->set('model', $payload['model'])
            ->set('variant', $payload['variant'])
            ->set('manufacturing_year', $payload['manufacturing_year'])
            ->set('fuel_type', $payload['fuel_type'])
            ->set('transmission', $payload['transmission'])
            ->set('kilometer', $payload['kilometer'])
            ->set('color', $payload['color'])
            ->set('stock_inventory_id', $payload['stock_inventory_id'])
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 2);
    }
}
