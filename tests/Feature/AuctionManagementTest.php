<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Car;
use App\Models\User;
use App\Services\AuctionLifecycleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuctionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_auction_manager_can_create_a_draft_auction(): void
    {
        $manager = $this->auctionManager();
        $auction = app(AuctionLifecycleService::class)->create($this->auctionData(Car::factory()->create()));

        $this->assertSame('draft', $auction->status);
        $this->assertFalse($auction->published);
        $this->assertSame($manager->id, $auction->created_by);
        $this->assertDatabaseHas('auctions', ['id' => $auction->id, 'status' => 'draft']);
    }

    public function test_auction_creation_rejects_end_date_before_start_date(): void
    {
        $this->auctionManager();
        $data = $this->auctionData(Car::factory()->create());
        $data['end_at'] = now()->addHours(2);

        $this->expectException(ValidationException::class);
        app(AuctionLifecycleService::class)->create($data);
    }

    public function test_publishing_requires_a_complete_schedule(): void
    {
        $this->auctionManager();
        $data = $this->auctionData(Car::factory()->create());
        $data['start_at'] = null;
        $data['end_at'] = null;
        $auction = app(AuctionLifecycleService::class)->create($data);

        $this->expectException(ValidationException::class);
        app(AuctionLifecycleService::class)->publish($auction);
    }

    public function test_auction_manager_can_publish_a_valid_scheduled_auction(): void
    {
        $manager = $this->auctionManager();
        $auction = app(AuctionLifecycleService::class)->create($this->auctionData(Car::factory()->create(['status' => 'available'])));

        $publishedAuction = app(AuctionLifecycleService::class)->publish($auction);

        $this->assertTrue($publishedAuction->published);
        $this->assertSame('scheduled', $publishedAuction->status);
        $this->assertSame($manager->id, $publishedAuction->published_by);
        $this->assertNotNull($publishedAuction->published_at);
    }

    public function test_published_auction_cannot_be_edited(): void
    {
        $this->auctionManager();
        $service = app(AuctionLifecycleService::class);
        $auction = $service->create($this->auctionData(Car::factory()->create(['status' => 'available'])));
        $auction = $service->publish($auction);

        $this->expectException(AuthorizationException::class);
        $service->update($auction, $this->auctionData($auction->car));
    }

    public function test_scheduled_auction_can_be_cancelled(): void
    {
        $this->auctionManager();
        $service = app(AuctionLifecycleService::class);
        $auction = $service->create($this->auctionData(Car::factory()->create(['status' => 'available'])));
        $auction = $service->publish($auction);

        $cancelledAuction = $service->cancel($auction);

        $this->assertSame('cancelled', $cancelledAuction->status);
    }

    public function test_auction_can_only_be_closed_after_its_end_date(): void
    {
        $this->auctionManager();
        $auction = Auction::factory()->create([
            'status' => 'live',
            'published' => true,
            'end_at' => now()->addHour(),
        ]);

        try {
            app(AuctionLifecycleService::class)->close($auction);
            $this->fail('An auction should not close before its end date.');
        } catch (ValidationException) {
            $this->assertSame('live', $auction->fresh()->status);
        }

        $auction->update(['end_at' => now()->subMinute()]);

        $closedAuction = app(AuctionLifecycleService::class)->close($auction);

        $this->assertSame('completed', $closedAuction->status);
    }

    public function test_publishing_is_rejected_when_car_has_an_existing_active_auction(): void
    {
        $this->auctionManager();
        $service = app(AuctionLifecycleService::class);
        $car = Car::factory()->create(['status' => 'available']);

        $firstAuction = $service->create($this->auctionData($car));
        $service->publish($firstAuction);
        $this->assertContains($firstAuction->fresh()->status, ['scheduled', 'live']);

        $secondAuction = $service->create($this->auctionData($car));

        try {
            $service->publish($secondAuction);
            $this->fail('A second auction for a car with an active auction must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('car_id', $exception->errors());
            $this->assertSame('draft', $secondAuction->fresh()->status);
            $this->assertFalse($secondAuction->fresh()->published);
        }
    }

    public function test_publishing_is_allowed_after_a_prior_auction_for_the_car_is_cancelled(): void
    {
        $this->auctionManager();
        $service = app(AuctionLifecycleService::class);
        $car = Car::factory()->create();

        $car->update(['status' => 'available']);
        $firstAuction = $service->publish($service->create($this->auctionData($car)));
        $service->cancel($firstAuction);

        $secondAuction = $service->publish($service->create($this->auctionData($car)));

        $this->assertTrue($secondAuction->published);
        $this->assertContains($secondAuction->status, ['scheduled', 'live']);
    }

    public function test_publishing_is_rejected_for_an_ineligible_car_status(): void
    {
        $this->auctionManager();
        $service = app(AuctionLifecycleService::class);
        $car = Car::factory()->create(['status' => 'sold']);
        $auction = $service->create($this->auctionData($car));

        try {
            $service->publish($auction);
            $this->fail('Publishing must be rejected for a sold car.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('car_id', $exception->errors());
            $this->assertSame('draft', $auction->fresh()->status);
            $this->assertFalse($auction->fresh()->published);
        }
    }

    public function test_publishing_succeeds_for_an_eligible_car_without_conflicts(): void
    {
        $this->auctionManager();
        $service = app(AuctionLifecycleService::class);
        $car = Car::factory()->create(['status' => 'available']);

        $auction = $service->publish($service->create($this->auctionData($car)));

        $this->assertTrue($auction->published);
        $this->assertContains($auction->status, ['scheduled', 'live']);
    }

    public function test_only_one_of_two_auctions_for_the_same_car_can_be_published(): void
    {
        $this->auctionManager();
        $service = app(AuctionLifecycleService::class);
        $car = Car::factory()->create(['status' => 'available']);

        $first = $service->create($this->auctionData($car));
        $second = $service->create($this->auctionData($car));

        $service->publish($first);

        try {
            $service->publish($second);
            $this->fail('Two auctions for the same car must not both publish.');
        } catch (ValidationException) {
            // Expected: the car is already claimed by the first auction.
        }

        $publishedCount = Auction::query()
            ->where('car_id', $car->id)
            ->where('published', true)
            ->whereIn('status', ['scheduled', 'live'])
            ->count();

        $this->assertSame(1, $publishedCount);
    }

    public function test_users_without_auction_permissions_cannot_create_auctions(): void
    {
        $this->actingAs(User::factory()->create());

        $this->expectException(AuthorizationException::class);
        app(AuctionLifecycleService::class)->create($this->auctionData(Car::factory()->create()));
    }

    public function test_auction_manager_can_access_auction_management(): void
    {
        $this->auctionManager();

        $this->assertTrue(Gate::allows('viewAny', Auction::class));
    }

    private function auctionManager(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Auction Manager', 'web'));
        $this->actingAs($manager);

        return $manager;
    }

    /**
     * @return array<string, mixed>
     */
    private function auctionData(Car $car): array
    {
        return [
            'car_id' => $car->id,
            'title' => 'Weekend vehicle auction',
            'description' => 'Auction listing for a test vehicle.',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'starting_price' => 100000,
            'minimum_bid_increment' => 1000,
            'reserve_price' => 150000,
            'participation_fee' => 5000,
        ];
    }
}
