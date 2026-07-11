<?php

namespace App\Actions\Account;

use App\Models\NextOfKin;
use App\Models\User;

class UpsertNextOfKinAction
{
    /**
     * @param  array{full_name: string, relationship: string, phone: string, alternate_phone: ?string, address: string, apply_to_all_bookings?: bool}  $data
     */
    public function handle(User $user, array $data): NextOfKin
    {
        return $user->nextOfKin()->updateOrCreate([], $data);
    }
}
