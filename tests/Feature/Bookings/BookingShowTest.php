<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_booking_detail(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/bookings/{$booking->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $booking->id);
        $response->assertJsonPath('reference', $booking->reference);
        $response->assertJsonPath('payment.gateway', $booking->payment->gateway->value);
    }

    public function test_another_user_cannot_view_the_booking(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/v1/bookings/{$booking->id}")
            ->assertStatus(403);
    }

    public function test_guest_cannot_view_a_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->getJson("/api/v1/bookings/{$booking->id}")->assertStatus(401);
    }
}
