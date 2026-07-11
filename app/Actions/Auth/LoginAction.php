<?php

namespace App\Actions\Auth;

use App\Exceptions\AccountLockedException;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    protected const int MAX_ATTEMPTS = 5;

    protected const int LOCKOUT_MINUTES = 15;

    public function __construct(protected InitiateTwoFactorChallengeAction $twoFactor) {}

    /**
     * @return array{requires_two_factor: bool, user?: User, token?: string, challenge_token?: string, expires_in?: int}
     */
    public function handle(string $identifier, string $password, ?string $deviceName): array
    {
        $lockoutKey = "login_lockout:{$identifier}";

        if ((int) Cache::get($lockoutKey, 0) >= self::MAX_ATTEMPTS) {
            throw AccountLockedException::tooManyFailedLogins(self::LOCKOUT_MINUTES);
        }

        $user = User::query()->withIdentifier($identifier)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            Cache::put($lockoutKey, (int) Cache::get($lockoutKey, 0) + 1, now()->addMinutes(self::LOCKOUT_MINUTES));

            throw ValidationException::withMessages([
                'identifier' => ['These credentials do not match our records.'],
            ]);
        }

        Cache::forget($lockoutKey);

        if ($user->two_factor_enabled) {
            return ['requires_two_factor' => true, ...$this->twoFactor->handle($user)];
        }

        $token = $user->createToken($deviceName ?: 'API Token')->plainTextToken;

        event(new Login('sanctum', $user, false));

        return ['requires_two_factor' => false, 'user' => $user, 'token' => $token];
    }
}
