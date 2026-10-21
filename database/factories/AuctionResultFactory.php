<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\AuctionResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuctionResult>
 */
class AuctionResultFactory extends Factory
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
            'winner_id' => null,
            'winning_bid_id' => null,
            'status' => 'pending',
            'finalized_by' => null,
            'finalized_at' => null,
            'settlement_status' => 'pending',
        ];
    }
}
