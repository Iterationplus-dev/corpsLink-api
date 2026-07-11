<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function codeFor(User $user): string
    {
        $code = null;

        Notification::assertSentTo($user, TwoFactorCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        return $code;
    }

    public function test_login_with_two_factor_enabled_returns_a_challenge_not_a_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('Passw0rd!123'),
            'two_factor_enabled' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Passw0rd!123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('requiresTwoFactor', true);
        $this->assertNotEmpty($response->json('challengeToken'));
        $this->assertArrayNotHasKey('tokens', $response->json());

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }

    public function test_correct_code_completes_login(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('Passw0rd!123'),
            'two_factor_enabled' => true,
        ]);

        $challenge = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Passw0rd!123',
        ])->json();

        $code = $this->codeFor($user);

        $response = $this->postJson('/api/v1/auth/login/2fa-verify', [
            'challengeToken' => $challenge['challengeToken'],
            'code' => $code,
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.id', $user->id);
        $this->assertNotEmpty($response->json('tokens.accessToken'));
    }

    public function test_wrong_code_is_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('Passw0rd!123'),
            'two_factor_enabled' => true,
        ]);

        $challenge = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Passw0rd!123',
        ])->json();

        $response = $this->postJson('/api/v1/auth/login/2fa-verify', [
            'challengeToken' => $challenge['challengeToken'],
            'code' => '0000',
        ]);

        $response->assertStatus(422);
    }

    public function test_expired_or_unknown_challenge_token_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/login/2fa-verify', [
            'challengeToken' => (string) Str::uuid(),
            'code' => '1234',
        ]);

        $response->assertStatus(410);
        $response->assertJsonPath('error.code', 'two_factor_challenge_expired');
    }

    public function test_challenge_token_cannot_be_reused_after_success(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('Passw0rd!123'),
            'two_factor_enabled' => true,
        ]);

        $challenge = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Passw0rd!123',
        ])->json();

        $code = $this->codeFor($user);

        $this->postJson('/api/v1/auth/login/2fa-verify', [
            'challengeToken' => $challenge['challengeToken'],
            'code' => $code,
        ])->assertOk();

        $response = $this->postJson('/api/v1/auth/login/2fa-verify', [
            'challengeToken' => $challenge['challengeToken'],
            'code' => $code,
        ]);

        $response->assertStatus(410);
    }

    public function test_resend_issues_a_new_code_for_the_same_challenge(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('Passw0rd!123'),
            'two_factor_enabled' => true,
        ]);

        $challenge = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Passw0rd!123',
        ])->json();

        $response = $this->postJson('/api/v1/auth/login/2fa-resend', [
            'challengeToken' => $challenge['challengeToken'],
        ]);

        $response->assertOk();

        Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 2);

        $code = $this->codeFor($user);

        $this->postJson('/api/v1/auth/login/2fa-verify', [
            'challengeToken' => $challenge['challengeToken'],
            'code' => $code,
        ])->assertOk();
    }

    public function test_users_without_two_factor_are_unaffected(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('Passw0rd!123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Passw0rd!123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('requiresTwoFactor', false);
        $this->assertNotEmpty($response->json('tokens.accessToken'));

        Notification::assertNothingSentTo($user);
    }
}
