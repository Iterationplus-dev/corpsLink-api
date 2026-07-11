<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UnregisterDeviceTokenAction
{
    public function handle(User $user, int $deviceTokenId): void
    {
        $deviceToken = $user->deviceTokens()->where('id', $deviceTokenId)->first();

        if (! $deviceToken) {
            throw new ModelNotFoundException('Device token not found.');
        }

        $deviceToken->delete();
    }
}
