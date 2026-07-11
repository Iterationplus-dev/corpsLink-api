<?php

namespace Tests\Feature\Account;

use App\Models\SeatHold;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_their_current_hold(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/account/seat-hold');

        $response->assertOk();
        $response->assertJsonPath('seatId', $seat->id);
        $response->assertJsonPath('vehicleId', $vehicle->id);
        $this->assertGreaterThan(0, $response->json('secondsRemaining'));
    }

    public function test_returns_null_when_no_active_hold(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/account/seat-hold');

        $response->assertOk();
        $this->assertSame('null', $response->getContent());
    }

    public function test_user_can_release_their_hold(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/account/seat-hold')->assertOk();

        $this->assertNotNull($hold->fresh()->released_at);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/account/seat-hold');
        $this->assertSame('null', $response->getContent());
    }
}
