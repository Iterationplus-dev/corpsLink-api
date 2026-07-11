<?php

namespace App\Actions\Account;

use App\Models\User;

class ReleaseSeatHoldAction
{
    /**
     * No-ops if the user has no active hold.
     */
    public function handle(User $user): void
    {
        $user->activeSeatHold?->update(['released_at' => now()]);
    }
}
