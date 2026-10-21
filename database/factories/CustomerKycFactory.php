<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerKyc;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerKyc>
 */
class CustomerKycFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'pan' => strtoupper(fake()->bothify('?????#####?')),
            'identity_document_type' => 'identity_card',
            'identity_document_number' => fake()->numerify('############'),
            'status' => 'pending',
        ];
    }
}
