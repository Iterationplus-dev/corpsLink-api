<?php

namespace Tests\Feature\Account;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_a_device_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/account/device-tokens', [
            'token' => 'fcm-token-123',
            'platform' => 'android',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-123',
            'platform' => 'android',
        ]);
    }

    public function test_registering_the_same_token_again_reassigns_it_instead_of_erroring(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser, 'sanctum')->postJson('/api/v1/account/device-tokens', [
            'token' => 'shared-device-token',
            'platform' => 'ios',
        ])->assertOk();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/account/device-tokens', [
            'token' => 'shared-device-token',
            'platform' => 'ios',
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'shared-device-token',
        ]);
    }

    public function test_user_can_unregister_a_device_token(): void
    {
        $user = User::factory()->create();
        $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/account/device-tokens/{$deviceToken->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('device_tokens', ['id' => $deviceToken->id]);
    }

    public function test_user_cannot_unregister_another_users_device_token(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $deviceToken = DeviceToken::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/account/device-tokens/{$deviceToken->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('device_tokens', ['id' => $deviceToken->id]);
    }
}
