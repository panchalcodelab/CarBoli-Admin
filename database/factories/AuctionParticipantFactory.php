<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuctionParticipant>
 */
class AuctionParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'customer_id' => Customer::factory(),
            'registration_status' => 'requested',
            'entry_fee_amount' => 0,
            'payment_status' => 'pending',
            'kyc_status' => 'pending',
            'participation_date' => now(),
            'eligibility_status' => 'pending',
        ];
    }
}
