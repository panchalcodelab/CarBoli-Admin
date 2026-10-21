<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'stock_inventory_id' => fake()->unique()->bothify('CB-########'),
            'make' => fake()->company(),
            'model' => fake()->word(),
            'manufacturing_year' => fake()->numberBetween(2000, now()->year),
            'registration_year' => fake()->numberBetween(2000, now()->year),
            'expected_price' => fake()->numberBetween(100000, 3000000),
            'status' => 'draft',
        ];
    }
}
