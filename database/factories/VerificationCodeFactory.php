<?php

namespace Database\Factories;

use App\Enums\VerificationPurpose;
use App\Models\VerificationCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<VerificationCode>
 */
class VerificationCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'user_id' => null,
            'purpose' => VerificationPurpose::RegistrationEmail,
            'code_hash' => Hash::make('1234'),
            'new_email' => null,
            'attempts' => 0,
            'max_attempts' => config('corpslink.otp.max_attempts'),
            'expires_at' => now()->addMinutes(config('corpslink.otp.expiry_minutes')),
            'consumed_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function consumed(): static
    {
        return $this->state(fn () => ['consumed_at' => now()]);
    }
}
