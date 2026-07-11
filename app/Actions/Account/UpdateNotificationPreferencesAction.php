<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateNotificationPreferencesAction
{
    /**
     * @param  array<string, bool>  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->update(['notification_preferences' => array_merge($user->notification_preferences, $data)]);

        return $user;
    }
}
