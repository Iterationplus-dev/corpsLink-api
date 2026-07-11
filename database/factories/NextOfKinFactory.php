<?php

namespace Database\Factories;

use App\Models\NextOfKin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NextOfKin>
 */
class NextOfKinFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'relationship' => fake()->randomElement(['Brother', 'Sister', 'Mother', 'Father', 'Spouse', 'Friend']),
            'phone' => fake()->numerify('080########'),
            'alternate_phone' => fake()->optional()->numerify('081########'),
            'address' => fake()->address(),
            'apply_to_all_bookings' => true,
        ];
    }
}
