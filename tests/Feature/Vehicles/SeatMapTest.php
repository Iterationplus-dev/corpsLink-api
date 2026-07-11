<?php

namespace Tests\Feature\Vehicles;

use App\Models\SeatHold;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_seat_statuses_are_correct_from_the_requesting_users_perspective(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $seats = $vehicle->seats()->orderBy('seat_number')->take(3)->get();
        SeatHold::factory()->create(['seat_id' => $seats[0]->id, 'user_id' => $user->id]);
        SeatHold::factory()->create(['seat_id' => $seats[1]->id, 'user_id' => $otherUser->id]);
        // $seats[2] left free.

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$vehicle->id}/seats");

        $response->assertOk();
        $byNumber = collect($response->json())->keyBy('label');

        $this->assertSame('held_by_you', $byNumber[$seats[0]->seat_number]['status']);
        $this->assertNotNull($byNumber[$seats[0]->seat_number]['holdExpiresAt']);

        $this->assertSame('held', $byNumber[$seats[1]->seat_number]['status']);
        $this->assertNull($byNumber[$seats[1]->seat_number]['holdExpiresAt']);

        $this->assertSame('available', $byNumber[$seats[2]->seat_number]['status']);
    }

    public function test_row_and_window_metadata_matches_the_fixed_layout(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$vehicle->id}/seats");

        $byNumber = collect($response->json())->keyBy('label');

        // Row 1: seats 1-4 → window, aisle, aisle, window.
        $this->assertSame(1, $byNumber[1]['row']);
        $this->assertSame('window', $byNumber[1]['position']);
        $this->assertSame('aisle', $byNumber[2]['position']);
        $this->assertSame('aisle', $byNumber[3]['position']);
        $this->assertSame('window', $byNumber[4]['position']);

        // Row 2 starts at seat 5.
        $this->assertSame(2, $byNumber[5]['row']);
        $this->assertSame('window', $byNumber[5]['position']);
    }
}
