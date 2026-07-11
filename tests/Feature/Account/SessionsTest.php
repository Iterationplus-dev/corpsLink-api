<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_active_sessions(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Chrome on Windows')->plainTextToken;
        $user->createToken('Tecno Camon 20');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile/sessions');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_user_can_revoke_a_single_session(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current')->plainTextToken;
        $other = $user->createToken('other-device');

        $this->withHeader('Authorization', "Bearer {$current}")
            ->deleteJson("/api/v1/profile/sessions/{$other->accessToken->id}")
            ->assertOk();

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherToken = $other->createToken('their-device');

        $token = $user->createToken('mine')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/profile/sessions/{$otherToken->accessToken->id}")
            ->assertStatus(404);

        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_user_can_sign_out_of_all_devices(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current')->plainTextToken;
        $user->createToken('another-device');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/profile/sessions')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
