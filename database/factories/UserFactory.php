<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => fake()->unique()->numerify('080########'),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'institution_id' => Institution::factory(),
            'call_up_number' => fake()->unique()->bothify('NYSC/??/2026/#####'),
            'state_code' => fake()->optional()->bothify('??/26?/####'),
            'batch' => fake()->randomElement(['A', 'B', 'C']),
            'stream' => fake()->randomElement(['1', '2']),
            'notification_preferences' => User::DEFAULT_NOTIFICATION_PREFERENCES,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
