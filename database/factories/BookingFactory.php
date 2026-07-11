<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payment = Payment::factory()->successful()->create();

        return [
            'user_id' => $payment->user_id,
            'seat_id' => $payment->seat_id,
            'vehicle_id' => $payment->vehicle_id,
            'payment_id' => $payment->id,
            'reference' => null,
            'fare' => $payment->amount,
            'status' => BookingStatus::Confirmed,
            'booked_at' => now(),
        ];
    }
}
