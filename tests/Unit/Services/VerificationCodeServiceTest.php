<?php

namespace Tests\Unit\Services;

use App\Enums\VerificationPurpose;
use App\Exceptions\InvalidVerificationCodeException;
use App\Services\VerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected VerificationCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(VerificationCodeService::class);
    }

    public function test_generate_creates_a_hashed_code(): void
    {
        $issued = $this->service->generate('user@example.com', VerificationPurpose::RegistrationEmail);

        $this->assertSame(4, strlen($issued['code']));
        $this->assertTrue(ctype_digit($issued['code']));
        $this->assertNotSame($issued['code'], $issued['model']->code_hash);
    }

    public function test_generate_invalidates_previous_active_code_for_same_purpose(): void
    {
        $first = $this->service->generate('user@example.com', VerificationPurpose::RegistrationEmail);
        $this->service->generate('user@example.com', VerificationPurpose::RegistrationEmail);

        $this->assertNotNull($first['model']->fresh()->consumed_at);
    }

    public function test_verify_succeeds_with_correct_code(): void
    {
        $issued = $this->service->generate('user@example.com', VerificationPurpose::RegistrationEmail);

        $record = $this->service->verify('user@example.com', VerificationPurpose::RegistrationEmail, $issued['code']);

        $this->assertNotNull($record->consumed_at);
    }

    public function test_verify_throws_for_unknown_code(): void
    {
        $this->expectException(InvalidVerificationCodeException::class);

        $this->service->verify('nobody@example.com', VerificationPurpose::RegistrationEmail, '1234');
    }

    public function test_verify_throws_and_increments_attempts_for_wrong_code(): void
    {
        $issued = $this->service->generate('user@example.com', VerificationPurpose::RegistrationEmail);

        try {
            $this->service->verify('user@example.com', VerificationPurpose::RegistrationEmail, '0000');
        } catch (InvalidVerificationCodeException) {
            // expected
        }

        $this->assertSame(1, $issued['model']->fresh()->attempts);
    }

    public function test_verify_throws_once_attempts_exhausted(): void
    {
        $issued = $this->service->generate('user@example.com', VerificationPurpose::RegistrationEmail);

        $max = config('corpslink.otp.max_attempts');

        for ($i = 0; $i < $max; $i++) {
            try {
                $this->service->verify('user@example.com', VerificationPurpose::RegistrationEmail, '0000');
            } catch (InvalidVerificationCodeException) {
                // expected until exhausted
            }
        }

        $this->assertTrue($issued['model']->fresh()->attemptsExhausted());

        $this->expectException(InvalidVerificationCodeException::class);
        $this->service->verify('user@example.com', VerificationPurpose::RegistrationEmail, $issued['code']);
    }

    public function test_verify_throws_for_expired_code(): void
    {
        $issued = $this->service->generate('user@example.com', VerificationPurpose::RegistrationEmail);
        $issued['model']->update(['expires_at' => now()->subMinute()]);

        $this->expectException(InvalidVerificationCodeException::class);
        $this->service->verify('user@example.com', VerificationPurpose::RegistrationEmail, $issued['code']);
    }
}
