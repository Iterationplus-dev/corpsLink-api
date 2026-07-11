<?php

namespace Tests\Feature\Institutions;

use App\Models\Booking;
use App\Models\Institution;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleListTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_future_vehicles_are_returned(): void
    {
        $user = User::factory()->create();
        $institution = Institution::factory()->create();

        $active = Vehicle::factory()->create(['institution_id' => $institution->id]);
        Vehicle::factory()->inactive()->create(['institution_id' => $institution->id]);
        Vehicle::factory()->departed()->create(['institution_id' => $institution->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/institutions/{$institution->id}/vehicles");

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertEqualsCanonicalizing([$active->id], $ids->all());
    }

    public function test_vehicles_are_ordered_by_departure_time(): void
    {
        $user = User::factory()->create();
        $institution = Institution::factory()->create();

        $later = Vehicle::factory()->create([
            'institution_id' => $institution->id,
            'departure_at' => now()->addDays(10),
        ]);
        $sooner = Vehicle::factory()->create([
            'institution_id' => $institution->id,
            'departure_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/institutions/{$institution->id}/vehicles");

        $ids = collect($response->json())->pluck('id');
        $this->assertSame([$sooner->id, $later->id], $ids->all());
    }

    public function test_badge_reflects_occupancy(): void
    {
        $user = User::factory()->create();
        $institution = Institution::factory()->create();
        $vehicle = Vehicle::factory()->create(['institution_id' => $institution->id, 'capacity' => 40]);

        // Fill 35 of 40 seats with confirmed bookings (remaining 5 <= 20%
        // of 40 → filling_fast).
        $vehicle->seats()->take(35)->get()->each(function ($seat) use ($vehicle) {
            $bookingUser = User::factory()->create();
            $payment = Payment::factory()->successful()->create([
                'user_id' => $bookingUser->id,
                'seat_id' => $seat->id,
                'vehicle_id' => $vehicle->id,
            ]);
            Booking::factory()->create([
                'user_id' => $bookingUser->id,
                'seat_id' => $seat->id,
                'vehicle_id' => $vehicle->id,
                'payment_id' => $payment->id,
            ]);
        });

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/institutions/{$institution->id}/vehicles");

        $response->assertOk();
        $data = collect($response->json())->firstWhere('id', $vehicle->id);
        $this->assertSame(35, $data['filledSeats']);
        $this->assertSame('filling_fast', $data['status']);
    }
}
