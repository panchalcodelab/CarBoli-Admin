<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'starting_price' => 10000,
            'minimum_bid_increment' => 100,
            'reserve_price' => null,
            'participation_fee' => 0,
            'status' => 'draft',
            'published' => false,
        ];
    }
}
