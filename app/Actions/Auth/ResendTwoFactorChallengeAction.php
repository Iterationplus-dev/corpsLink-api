<?php

namespace App\Actions\Auth;

use App\Enums\VerificationPurpose;
use App\Exceptions\TwoFactorChallengeExpiredException;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\VerificationCodeService;
use Illuminate\Support\Facades\Cache;

class ResendTwoFactorChallengeAction
{
    public function __construct(protected VerificationCodeService $codes) {}

    /**
     * Reuses the existing challenge token — the client already has it and
     * shouldn't need to restart the login flow to get a fresh code.
     */
    public function handle(string $challengeToken): void
    {
        $userId = Cache::get("2fa_challenge:{$challengeToken}");

        if (! $userId) {
            throw TwoFactorChallengeExpiredException::make();
        }

        $user = User::query()->findOrFail($userId);

        $issued = $this->codes->generate($user->email, VerificationPurpose::TwoFactorLogin, $user);

        $user->notify(new TwoFactorCodeNotification($issued['code'], config('corpslink.otp.expiry_minutes')));
    }
}
