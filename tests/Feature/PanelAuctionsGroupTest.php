<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Bid;
use App\Models\Car;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelAuctionsGroupTest extends TestCase
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

    private function eligibleCustomer(): Customer
    {
        return Customer::factory()->create([
            'account_status' => 'active',
            'is_blocked' => false,
            'kyc_status' => 'approved',
        ]);
    }

    // ---- Auctions list + lifecycle ----

    public function test_auctions_list_renders_with_search_and_filter(): void
    {
        $this->actingAsRole('Auction Manager');
        $car = Car::factory()->create(['make' => 'Hyundai', 'model' => 'Creta']);
        $auction = Auction::factory()->create(['car_id' => $car->id, 'title' => 'Weekend Sale', 'status' => 'draft']);

        Livewire::test('panel.auctions.index')
            ->assertOk()
            ->assertSee('Live Auctions')
            ->assertSee('Weekend Sale')
            ->set('status', 'live')
            ->assertDontSee('Weekend Sale')
            ->set('status', 'draft')
            ->assertSee('Weekend Sale');
    }

    public function test_auction_create_via_livewire_persists_draft(): void
    {
        $this->actingAsRole('Auction Manager');
        $car = Car::factory()->create(['status' => 'available']);

        Livewire::test('panel.auctions.form')
            ->set('car_id', $car->id)
            ->set('title', 'Spring Auction')
            ->set('start_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('end_at', now()->addDays(2)->format('Y-m-d\TH:i'))
            ->set('starting_price', 100000)
            ->set('minimum_bid_increment', 1000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('auctions', ['title' => 'Spring Auction', 'status' => 'draft', 'published' => false]);
    }

    public function test_publish_action_is_authorized_server_side_even_if_button_hidden(): void
    {
        // Finance Manager can view neither auctions management nor publish.
        $this->actingAsRole('Finance Manager');
        $car = Car::factory()->create(['status' => 'available']);
        $auction = Auction::factory()->create(['car_id' => $car->id, 'status' => 'draft', 'published' => false]);

        // Finance Manager cannot even view the auctions list.
        Livewire::test('panel.auctions.index')->assertForbidden();
    }

    public function test_publish_blocked_for_unapproved_car_through_livewire(): void
    {
        $this->actingAsRole('Auction Manager');
        $car = Car::factory()->create(['status' => 'draft']); // not approved
        $auction = Auction::factory()->create([
            'car_id' => $car->id,
            'status' => 'draft',
            'published' => false,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'minimum_bid_increment' => 1000,
        ]);

        Livewire::test('panel.auctions.index')
            ->call('publish', $auction->id)
            ->assertHasErrors('action');

        $this->assertFalse($auction->fresh()->published);
    }

    public function test_finalize_is_idempotent_and_does_not_duplicate_results(): void
    {
        $manager = $this->actingAsRole('Auction Manager');
        $car = Car::factory()->create(['status' => 'available']);
        $winner = $this->eligibleCustomer();
        $auction = Auction::factory()->create([
            'car_id' => $car->id,
            'status' => 'completed',
            'published' => true,
            'start_at' => now()->subDays(2),
            'end_at' => now()->subMinute(),
        ]);
        AuctionParticipant::factory()->create([
            'auction_id' => $auction->id, 'customer_id' => $winner->id,
            'registration_status' => 'approved', 'eligibility_status' => 'eligible',
            'payment_status' => 'captured', 'kyc_status' => 'approved',
        ]);
        Bid::query()->create([
            'auction_id' => $auction->id, 'customer_id' => $winner->id,
            'bid_amount' => 120000, 'bid_sequence' => 1, 'status' => 'valid',
        ]);

        Livewire::test('panel.auctions.index')->call('finalize', $auction->id);
        Livewire::test('panel.auctions.index')->call('finalize', $auction->id); // repeat

        $this->assertSame(1, DB::table('auction_results')->where('auction_id', $auction->id)->count());
    }

    // ---- Participants ----

    public function test_participant_registration_is_idempotent_through_livewire(): void
    {
        $this->actingAsRole('Auction Manager');
        $car = Car::factory()->create(['status' => 'available']);
        $customer = $this->eligibleCustomer();
        $auction = Auction::factory()->create([
            'car_id' => $car->id, 'status' => 'live', 'published' => true,
            'start_at' => now()->subMinute(), 'end_at' => now()->addHour(),
            'participation_fee' => 5000,
        ]);

        Livewire::test('panel.participants.index')
            ->set('reg_auction_id', $auction->id)
            ->set('reg_customer_id', $customer->id)
            ->call('register')
            ->assertHasNoErrors();

        Livewire::test('panel.participants.index')
            ->set('reg_auction_id', $auction->id)
            ->set('reg_customer_id', $customer->id)
            ->call('register');

        $this->assertSame(1, AuctionParticipant::query()
            ->where('auction_id', $auction->id)->where('customer_id', $customer->id)->count());
    }

    public function test_participants_list_denied_for_unprivileged_role(): void
    {
        $this->actingAsRole('Inspection Staff');
        Livewire::test('panel.participants.index')->assertForbidden();
    }

    // ---- Bids + Results read-only ----

    public function test_bids_and_results_lists_render_for_authorized_role(): void
    {
        $this->actingAsRole('Auction Manager');
        Livewire::test('panel.bids.index')->assertOk()->assertSee('Bids');
        Livewire::test('panel.results.index')->assertOk()->assertSee('Results');
    }

    public function test_bids_and_results_denied_for_unprivileged_role(): void
    {
        $this->actingAsRole('Inspection Staff');
        Livewire::test('panel.bids.index')->assertForbidden();
        Livewire::test('panel.results.index')->assertForbidden();
    }

    public function test_finance_manager_cannot_view_bids_or_results(): void
    {
        // Finance Manager has neither 'manage auctions' nor 'manage auction results'.
        $this->actingAsRole('Finance Manager');
        Livewire::test('panel.bids.index')->assertForbidden();
        Livewire::test('panel.results.index')->assertForbidden();
    }
}
