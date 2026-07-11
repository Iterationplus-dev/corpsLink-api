<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_issues_a_code_for_known_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, PasswordResetOtpNotification::class);
    }

    public function test_forgot_password_is_silent_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertOk();
        Notification::assertNothingSent();
    }

    public function test_reset_password_with_valid_code(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

        $code = null;
        Notification::assertSentTo($user, PasswordResetOtpNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        Notification::fake();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'newPassword' => 'NewPassw0rd!123',
        ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('NewPassw0rd!123', $user->fresh()->password));
        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    public function test_reset_password_revokes_existing_sessions(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->createToken('old-device');
        $this->assertSame(1, $user->tokens()->count());

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

        $code = null;
        Notification::assertSentTo($user, PasswordResetOtpNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'newPassword' => 'NewPassw0rd!123',
        ]);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_password_rejects_invalid_code(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => '0000',
            'newPassword' => 'NewPassw0rd!123',
        ]);

        $response->assertStatus(422);
    }
}
