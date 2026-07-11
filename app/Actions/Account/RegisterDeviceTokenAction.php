<?php

namespace App\Actions\Account;

use App\Models\DeviceToken;
use App\Models\User;

class RegisterDeviceTokenAction
{
    /**
     * Keyed by token (unique) rather than user — the same physical device
     * re-registering under a different account (shared device, re-login)
     * reassigns the token instead of erroring on a duplicate.
     */
    public function handle(User $user, string $token, string $platform): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            ['token' => $token],
            ['user_id' => $user->id, 'platform' => $platform, 'last_used_at' => now()],
        );
    }
}
