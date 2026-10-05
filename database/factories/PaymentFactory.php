<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
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
            'amount' => 5000,
            'paid_at' => now()->toDateString(),
            'payment_method' => PaymentMethod::Cash->value,
            'reference' => null,
            'notes' => null,
        ];
    }
}
