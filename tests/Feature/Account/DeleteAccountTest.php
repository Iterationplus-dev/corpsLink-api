<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deletion_requires_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Passw0rd!123')]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/profile', [
            'password' => 'wrong-password',
        ]);

        $this->assertValidationError($response, 'password');
        $this->assertNotSoftDeleted($user);
    }

    public function test_account_is_soft_deleted_and_tokens_revoked(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Passw0rd!123')]);
        $user->createToken('device');

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/profile', [
            'password' => 'Passw0rd!123',
        ])->assertOk();

        $this->assertSoftDeleted($user);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_deleted_account_cannot_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Passw0rd!123')]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/profile', [
            'password' => 'Passw0rd!123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Passw0rd!123',
        ])->assertUnprocessable();
    }
}
