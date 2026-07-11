<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Notifications\EmailChangeOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_change_requires_current_password(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('Passw0rd!123')]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/profile/change-email/request', [
            'newEmail' => 'new@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertValidationError($response, 'password');
    }

    public function test_full_email_change_flow(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('Passw0rd!123')]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/profile/change-email/request', [
            'newEmail' => 'new@example.com',
            'password' => 'Passw0rd!123',
        ])->assertOk();

        $code = null;
        Notification::assertSentOnDemand(
            EmailChangeOtpNotification::class,
            function ($notification, $channels, $notifiable) use (&$code) {
                $code = $notification->code;

                return $notifiable->routes['mail'] === 'new@example.com';
            },
        );

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/profile/change-email/confirm', [
            'newEmail' => 'new@example.com',
            'code' => $code,
        ]);

        $response->assertOk();
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_new_email_must_be_unique(): void
    {
        Notification::fake();

        $existing = User::factory()->create();
        $user = User::factory()->create(['password' => Hash::make('Passw0rd!123')]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/profile/change-email/request', [
            'newEmail' => $existing->email,
            'password' => 'Passw0rd!123',
        ]);

        $this->assertValidationError($response, 'newEmail');
    }
}
