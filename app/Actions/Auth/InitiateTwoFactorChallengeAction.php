<?php

namespace App\Actions\Auth;

use App\Enums\VerificationPurpose;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\VerificationCodeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class InitiateTwoFactorChallengeAction
{
    public function __construct(protected VerificationCodeService $codes) {}

    /**
     * @return array{challenge_token: string, expires_in: int}
     */
    public function handle(User $user): array
    {
        $issued = $this->codes->generate($user->email, VerificationPurpose::TwoFactorLogin, $user);

        $ttlMinutes = (int) config('corpslink.two_factor.challenge_ttl_minutes');
        $challengeToken = (string) Str::uuid();

        Cache::put("2fa_challenge:{$challengeToken}", $user->id, now()->addMinutes($ttlMinutes));

        $user->notify(new TwoFactorCodeNotification($issued['code'], config('corpslink.otp.expiry_minutes')));

        return ['challenge_token' => $challengeToken, 'expires_in' => $ttlMinutes * 60];
    }
}
