<?php

namespace Database\Factories;

use App\Enums\CondolenceStatus;
use App\Models\Condolence;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Condolence>
 */
class CondolenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Condolence Levy – '.$this->faker->name(),
            'deceased_member_id' => Member::factory()->deceased(),
            'amount_per_member' => 5000,
            'date_announced' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'status' => CondolenceStatus::Open->value,
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
