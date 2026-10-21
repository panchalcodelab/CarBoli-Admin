<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bid>
 */
class BidFactory extends Factory
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
            'bid_amount' => 10000,
            'bid_sequence' => 1,
            'status' => 'valid',
            'device_metadata' => null,
        ];
    }
}
