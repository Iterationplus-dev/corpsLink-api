<?php

namespace Tests\Feature\Vehicles;

use App\Models\SeatHold;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldSeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_hold_an_available_seat(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/seats/{$seat->id}/hold");

        $response->assertCreated();
        $this->assertDatabaseHas('seat_holds', [
            'seat_id' => $seat->id,
            'user_id' => $user->id,
            'released_at' => null,
        ]);

        $expiresAt = SeatHold::query()->where('seat_id', $seat->id)->value('expires_at');
        $this->assertEqualsWithDelta(
            now()->addMinutes(config('corpslink.seat_hold.duration_minutes'))->timestamp,
            Carbon::parse($expiresAt)->timestamp,
            5,
        );
    }

    public function test_holding_a_new_seat_releases_the_users_previous_hold(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        [$seatA, $seatB] = $vehicle->seats()->orderBy('seat_number')->take(2)->get();

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/vehicles/{$vehicle->id}/seats/{$seatA->id}/hold");
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/vehicles/{$vehicle->id}/seats/{$seatB->id}/hold");

        $this->assertDatabaseHas('seat_holds', ['seat_id' => $seatB->id, 'user_id' => $user->id, 'released_at' => null]);

        $holdA = SeatHold::query()->where('seat_id', $seatA->id)->first();
        $this->assertNotNull($holdA->released_at);
    }

    public function test_cannot_hold_a_seat_already_held_by_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();

        SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/seats/{$seat->id}/hold");

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'seat_unavailable');
        $this->assertNotNull($response->json('error.suggested_seat_number'));
    }

    public function test_an_expired_hold_does_not_block_a_new_claim(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();

        SeatHold::factory()->expired()->create(['seat_id' => $seat->id, 'user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/seats/{$seat->id}/hold");

        $response->assertCreated();
        $this->assertDatabaseHas('seat_holds', ['seat_id' => $seat->id, 'user_id' => $user->id, 'released_at' => null]);
    }

    public function test_seat_must_belong_to_the_given_vehicle(): void
    {
        $user = User::factory()->create();
        $vehicleA = Vehicle::factory()->create();
        $vehicleB = Vehicle::factory()->create();
        $seatOnB = $vehicleB->seats()->first();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/vehicles/{$vehicleA->id}/seats/{$seatOnB->id}/hold");

        $response->assertStatus(404);
    }
}
