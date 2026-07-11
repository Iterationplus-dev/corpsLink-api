<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateProfileAction
{
    /**
     * @param  array{name?: string, phone?: string, state_code?: ?string}  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->fill($data)->save();

        return $user;
    }
}
