<?php

namespace App\Policies;

use App\Models\NextOfKin;
use App\Models\User;

class NextOfKinPolicy
{
    public function view(User $user, NextOfKin $nextOfKin): bool
    {
        return $user->id === $nextOfKin->user_id;
    }

    public function update(User $user, NextOfKin $nextOfKin): bool
    {
        return $user->id === $nextOfKin->user_id;
    }

    public function delete(User $user, NextOfKin $nextOfKin): bool
    {
        return $user->id === $nextOfKin->user_id;
    }
}
