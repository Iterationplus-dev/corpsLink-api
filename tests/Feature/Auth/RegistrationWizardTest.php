<?php

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\RegistrationOtpNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function otpCodeFor(string $email): string
    {
        $code = null;

        Notification::assertSentOnDemand(
            RegistrationOtpNotification::class,
            function ($notification, $channels, $notifiable) use ($email, &$code) {
                if ($notifiable->routes['mail'] !== $email) {
                    return false;
                }

                $code = $notification->code;

                return true;
            },
        );

        return $code;
    }

    public function test_full_registration_wizard_happy_path(): void
    {
        Notification::fake();

        $institution = Institution::query()->first();

        $start = $this->postJson('/api/v1/auth/register/start', [
            'fullName' => 'Adaeze Okafor',
            'email' => 'adaeze.o@example.com',
            'phone' => '08034128890',
            'acceptedTerms' => true,
        ]);

        $start->assertCreated();
        $registrationId = $start->json('registrationId');
        $this->assertNotEmpty($registrationId);

        $code = $this->otpCodeFor('adaeze.o@example.com');

        $this->postJson('/api/v1/auth/otp/verify', [
            'registrationId' => $registrationId,
            'code' => $code,
        ])->assertOk();

        $this->patchJson("/api/v1/auth/register/{$registrationId}/school", [
            'institutionId' => $institution->id,
            'callUpNumber' => 'NYSC/UNILAG/2026/74812',
            'batch' => 'B',
            'stream' => '1',
        ])->assertOk();

        $this->patchJson("/api/v1/auth/register/{$registrationId}/next-of-kin", [
            'emergencyContact' => [
                'fullName' => 'Chinedu Okafor',
                'relationship' => 'Brother',
                'phone' => '08052210374',
                'address' => '14 Adeola Close, Surulere, Lagos',
            ],
        ])->assertOk();

        Notification::fake();

        $complete = $this->postJson("/api/v1/auth/register/{$registrationId}/complete", [
            'password' => 'Passw0rd!123',
        ]);

        $complete->assertCreated();
        $complete->assertJsonPath('user.email', 'adaeze.o@example.com');
        $complete->assertJsonPath('user.emailVerified', true);
        $this->assertNotEmpty($complete->json('tokens.accessToken'));

        $user = User::query()->where('email', 'adaeze.o@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('corps_member'));
        $this->assertNotNull($user->nextOfKin);
        $this->assertSame(0, PendingRegistration::query()->count());

        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_start_rejects_duplicate_email(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register/start', [
            'fullName' => 'Someone',
            'email' => 'taken@example.com',
            'phone' => '08011112222',
            'acceptedTerms' => true,
        ]);

        $this->assertValidationError($response, 'email');
    }

    public function test_start_rejects_when_terms_not_accepted(): void
    {
        $response = $this->postJson('/api/v1/auth/register/start', [
            'fullName' => 'No Terms',
            'email' => 'noterms@example.com',
            'phone' => '08055556666',
        ]);

        $this->assertValidationError($response, 'acceptedTerms');
    }

    public function test_verify_email_rejects_wrong_code(): void
    {
        Notification::fake();

        $start = $this->postJson('/api/v1/auth/register/start', [
            'fullName' => 'Wrong Coder',
            'email' => 'wrongcode@example.com',
            'phone' => '08022223333',
            'acceptedTerms' => true,
        ]);

        $registrationId = $start->json('registrationId');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'registrationId' => $registrationId,
            'code' => '0000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'verification_code_incorrect');
    }

    public function test_verify_email_locks_after_max_attempts(): void
    {
        Notification::fake();

        $start = $this->postJson('/api/v1/auth/register/start', [
            'fullName' => 'Max Attempts',
            'email' => 'maxattempts@example.com',
            'phone' => '08033334444',
            'acceptedTerms' => true,
        ]);

        $registrationId = $start->json('registrationId');

        for ($i = 0; $i < config('corpslink.otp.max_attempts'); $i++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'registrationId' => $registrationId,
                'code' => '0000',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'registrationId' => $registrationId,
            'code' => '0000',
        ]);

        $response->assertStatus(429);
        $response->assertJsonPath('error.code', 'verification_code_locked');
    }

    public function test_unknown_registration_token_returns_410(): void
    {
        $this->postJson('/api/v1/auth/otp/verify', [
            'registrationId' => '00000000-0000-0000-0000-000000000000',
            'code' => '1234',
        ])->assertStatus(410)->assertJsonPath('error.code', 'registration_expired');
    }

    public function test_expired_registration_token_returns_410(): void
    {
        $pending = PendingRegistration::factory()->expired()->create();

        $this->postJson('/api/v1/auth/otp/verify', [
            'registrationId' => $pending->registration_token,
            'code' => '1234',
        ])->assertStatus(410)->assertJsonPath('error.code', 'registration_expired');
    }

    public function test_school_step_rejects_inactive_institution(): void
    {
        $inactive = Institution::factory()->inactive()->create();
        $pending = PendingRegistration::factory()->emailVerified()->create();

        $response = $this->patchJson("/api/v1/auth/register/{$pending->registration_token}/school", [
            'institutionId' => $inactive->id,
            'callUpNumber' => 'NYSC/TEST/2026/00001',
            'batch' => 'B',
            'stream' => '1',
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'institution_inactive');
    }

    public function test_school_step_requires_verified_email(): void
    {
        $pending = PendingRegistration::factory()->create();
        $institution = Institution::query()->first();

        $response = $this->patchJson("/api/v1/auth/register/{$pending->registration_token}/school", [
            'institutionId' => $institution->id,
            'callUpNumber' => 'NYSC/TEST/2026/00002',
            'batch' => 'B',
            'stream' => '1',
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'email_not_verified');
    }

    public function test_school_step_rejects_duplicate_call_up_number_within_institution(): void
    {
        $institution = Institution::query()->first();

        User::factory()->create([
            'institution_id' => $institution->id,
            'call_up_number' => 'NYSC/DUP/2026/11111',
        ]);

        $pending = PendingRegistration::factory()->emailVerified()->create();

        $response = $this->patchJson("/api/v1/auth/register/{$pending->registration_token}/school", [
            'institutionId' => $institution->id,
            'callUpNumber' => 'NYSC/DUP/2026/11111',
            'batch' => 'B',
            'stream' => '1',
        ]);

        $this->assertValidationError($response, 'callUpNumber');
    }

    public function test_complete_rejects_weak_password(): void
    {
        $pending = PendingRegistration::factory()
            ->emailVerified()
            ->create([
                'institution_id' => Institution::query()->first()->id,
                'call_up_number' => 'NYSC/WEAK/2026/00001',
                'batch' => 'B',
                'stream' => '1',
                'nok_full_name' => 'Some Kin',
                'nok_relationship' => 'Sister',
                'nok_phone' => '08099998888',
                'nok_address' => 'Somewhere',
            ]);

        $response = $this->postJson("/api/v1/auth/register/{$pending->registration_token}/complete", [
            'password' => 'password',
        ]);

        $this->assertValidationError($response, 'password');
    }

    public function test_complete_rejects_incomplete_registration(): void
    {
        $pending = PendingRegistration::factory()->emailVerified()->create();

        $response = $this->postJson("/api/v1/auth/register/{$pending->registration_token}/complete", [
            'password' => 'Passw0rd!123',
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'registration_incomplete');
    }

    public function test_resend_otp_is_rate_limited(): void
    {
        Notification::fake();

        $start = $this->postJson('/api/v1/auth/register/start', [
            'fullName' => 'Rate Limited',
            'email' => 'ratelimited@example.com',
            'phone' => '08044445555',
            'acceptedTerms' => true,
        ]);

        $registrationId = $start->json('registrationId');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/resend', ['context' => 'register', 'registrationId' => $registrationId]);
        }

        $this->postJson('/api/v1/auth/otp/resend', ['context' => 'register', 'registrationId' => $registrationId])
            ->assertStatus(429);
    }
}
