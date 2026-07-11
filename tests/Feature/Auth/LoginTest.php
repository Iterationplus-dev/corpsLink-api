<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Passw0rd!123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Passw0rd!123',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('tokens.accessToken'));
        $response->assertJsonPath('user.id', $user->id);
    }

    public function test_user_can_login_with_call_up_number(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Passw0rd!123'),
            'call_up_number' => 'NYSC/LOGIN/2026/00001',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'NYSC/LOGIN/2026/00001',
            'password' => 'Passw0rd!123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.id', $user->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Passw0rd!123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertValidationError($response, 'identifier');
    }

    public function test_login_fails_with_unknown_identifier(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'nobody@example.com',
            'password' => 'whatever123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_login_locks_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Passw0rd!123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Passw0rd!123',
        ]);

        $response->assertStatus(429);
        $response->assertJsonPath('error.code', 'account_locked');
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }
}
