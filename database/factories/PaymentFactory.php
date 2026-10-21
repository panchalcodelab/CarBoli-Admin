<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'auction_id' => Auction::factory(),
            'amount' => 5000,
            'currency' => 'INR',
            'status' => 'pending',
            'payment_type' => 'entry_fee',
        ];
    }
}
