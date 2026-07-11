<?php

namespace App\Actions\Account;

use App\Models\User;

class DeleteNextOfKinAction
{
    public function handle(User $user): void
    {
        $user->nextOfKin?->delete();
    }
}
