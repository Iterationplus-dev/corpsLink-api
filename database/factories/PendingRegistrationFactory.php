<?php

namespace Database\Factories;

use App\Models\PendingRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PendingRegistration>
 */
class PendingRegistrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_token' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('080########'),
            'email_verified_at' => null,
            'expires_at' => now()->addHours(config('corpslink.registration.ttl_hours')),
        ];
    }

    public function emailVerified(): static
    {
        return $this->state(fn () => ['email_verified_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }
}
