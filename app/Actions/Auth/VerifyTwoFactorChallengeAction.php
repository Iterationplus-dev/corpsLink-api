<?php

namespace App\Actions\Auth;

use App\Enums\VerificationPurpose;
use App\Exceptions\TwoFactorChallengeExpiredException;
use App\Models\User;
use App\Services\VerificationCodeService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;

class VerifyTwoFactorChallengeAction
{
    public function __construct(protected VerificationCodeService $codes) {}

    /**
     * @return array{user: User, token: string}
     */
    public function handle(string $challengeToken, string $code, ?string $deviceName): array
    {
        $userId = Cache::get("2fa_challenge:{$challengeToken}");

        if (! $userId) {
            throw TwoFactorChallengeExpiredException::make();
        }

        $user = User::query()->findOrFail($userId);

        $this->codes->verify($user->email, VerificationPurpose::TwoFactorLogin, $code);

        Cache::forget("2fa_challenge:{$challengeToken}");

        $token = $user->createToken($deviceName ?: 'API Token')->plainTextToken;

        event(new Login('sanctum', $user, false));

        return ['user' => $user, 'token' => $token];
    }
}
