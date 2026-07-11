<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => 'Coaster Bus '.fake()->randomLetter(),
            'pickup_point' => fake()->streetName().' Main Gate',
            'destination' => 'Camp',
            'departure_at' => fake()->dateTimeBetween('+1 day', '+3 weeks'),
            'fare' => fake()->randomElement([1000, 1500, 2000, 2500]),
            // Must stay a multiple of 4 — layout is a fixed 2 | aisle | 2 grid.
            'capacity' => 40,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function departed(): static
    {
        return $this->state(fn () => ['departure_at' => now()->subDay()]);
    }
}
