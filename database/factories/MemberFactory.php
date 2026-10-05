<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Enums\MemberTitle;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->randomElement(array_column(MemberTitle::cases(), 'value')),
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional()->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'phone' => $this->faker->optional()->phoneNumber(),
            'address' => $this->faker->optional()->address(),
            'date_joined' => $this->faker->date(),
            'status' => MemberStatus::Active->value,
            'notes' => null,
        ];
    }

    public function deceased(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MemberStatus::Deceased->value,
        ]);
    }
}
