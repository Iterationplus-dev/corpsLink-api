<?php

namespace Database\Factories;

use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatHold>
 */
class SeatHoldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seat_id' => Seat::factory(),
            'user_id' => User::factory(),
            'expires_at' => now()->addMinutes(config('corpslink.seat_hold.duration_minutes')),
            'released_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function released(): static
    {
        return $this->state(fn () => ['released_at' => now()]);
    }
}
