<?php

namespace App\Services;

use App\Enums\VerificationPurpose;
use App\Exceptions\InvalidVerificationCodeException;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;

/**
 * Generates and verifies one-time codes shared by every OTP-driven flow:
 * registration email verification, password reset, and email change.
 */
class VerificationCodeService
{
    /**
     * Issue a fresh code for the given email + purpose, invalidating any
     * still-active code previously issued for the same pair.
     *
     * @return array{code: string, model: VerificationCode}
     */
    public function generate(
        string $email,
        VerificationPurpose $purpose,
        ?User $user = null,
        ?string $newEmail = null,
    ): array {
        VerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->active()
            ->update(['consumed_at' => now()]);

        $length = (int) config('corpslink.otp.length');
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $model = VerificationCode::query()->create([
            'email' => $email,
            'user_id' => $user?->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'new_email' => $newEmail,
            'max_attempts' => config('corpslink.otp.max_attempts'),
            'expires_at' => now()->addMinutes(config('corpslink.otp.expiry_minutes')),
        ]);

        return ['code' => $code, 'model' => $model];
    }

    /**
     * Verify a submitted code, consuming it on success.
     *
     * @throws InvalidVerificationCodeException
     */
    public function verify(string $email, VerificationPurpose $purpose, string $code): VerificationCode
    {
        $record = VerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->active()
            ->latest('id')
            ->first();

        if (! $record) {
            throw InvalidVerificationCodeException::notFound();
        }

        if ($record->attemptsExhausted()) {
            throw InvalidVerificationCodeException::attemptsExhausted();
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            $remaining = $record->max_attempts - $record->attempts;

            throw $remaining <= 0
                ? InvalidVerificationCodeException::attemptsExhausted()
                : InvalidVerificationCodeException::incorrect($remaining);
        }

        $record->update(['consumed_at' => now()]);

        return $record;
    }
}
