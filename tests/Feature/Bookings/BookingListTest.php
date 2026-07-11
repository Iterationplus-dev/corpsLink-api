<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_own_bookings(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);
        Booking::factory()->create(); // another user's booking

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/bookings');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertSame([$booking->id], $ids->all());
    }

    public function test_bookings_include_a_reference_and_nested_seat_and_vehicle(): void
    {
        $user = User::factory()->create();
        Booking::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/bookings');

        $response->assertOk();
        $first = $response->json('0');
        $this->assertNotEmpty($first['reference']);
        $this->assertArrayHasKey('label', $first['seat']);
        $this->assertArrayHasKey('name', $first['vehicle']);
    }
}
