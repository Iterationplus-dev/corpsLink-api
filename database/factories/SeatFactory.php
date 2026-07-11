<?php

namespace Database\Factories;

use App\Models\Seat;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rarely used directly — `Vehicle::created` auto-generates a vehicle's
 * seats from its capacity. Prefer pulling from `$vehicle->seats` in tests
 * over calling this factory, which would otherwise collide with those
 * auto-generated rows on the (vehicle_id, seat_number) unique constraint.
 *
 * @extends Factory<Seat>
 */
class SeatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'seat_number' => fake()->unique()->numberBetween(1, 40),
        ];
    }
}
