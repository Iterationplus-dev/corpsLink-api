<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();

        return [
            'user_id' => User::factory(),
            'seat_id' => $seat->id,
            'vehicle_id' => $vehicle->id,
            'gateway' => fake()->randomElement(PaymentGateway::cases()),
            'reference' => 'CL-PAY-'.Str::upper(Str::random(12)),
            'gateway_reference' => null,
            'amount' => $vehicle->fare,
            'currency' => 'NGN',
            'status' => PaymentStatus::Pending,
            'gateway_response' => null,
            'paid_at' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Successful,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Failed]);
    }
}
