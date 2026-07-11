<?php

namespace App\Actions\Auth;

use App\Events\RegistrationCompleted;
use App\Exceptions\ApiException;
use App\Exceptions\RegistrationExpiredException;
use App\Models\NextOfKin;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompleteRegistrationAction
{
    /**
     * @return array{user: User, token: string}
     */
    public function handle(string $registrationToken, string $password, string $deviceName): array
    {
        $pending = PendingRegistration::query()
            ->where('registration_token', $registrationToken)
            ->first();

        if (! $pending || $pending->isExpired()) {
            throw RegistrationExpiredException::make();
        }

        if (! $pending->isEmailVerified() || ! $pending->hasSchoolInfo() || ! $pending->hasNextOfKin()) {
            throw new ApiException('Please complete every registration step before creating your account.', status: 422, errorCode: 'registration_incomplete');
        }

        [$user, $token] = DB::transaction(function () use ($pending, $password, $deviceName) {
            $user = User::query()->create([
                'name' => $pending->name,
                'email' => $pending->email,
                'phone' => $pending->phone,
                'password' => $password,
                'institution_id' => $pending->institution_id,
                'call_up_number' => $pending->call_up_number,
                'state_code' => $pending->state_code,
                'batch' => $pending->batch,
                'stream' => $pending->stream,
                'email_verified_at' => now(),
                'notification_preferences' => User::DEFAULT_NOTIFICATION_PREFERENCES,
            ]);

            $user->assignRole('corps_member');

            NextOfKin::query()->create([
                'user_id' => $user->id,
                'full_name' => $pending->nok_full_name,
                'relationship' => $pending->nok_relationship,
                'phone' => $pending->nok_phone,
                'alternate_phone' => $pending->nok_alternate_phone,
                'address' => $pending->nok_address,
                'apply_to_all_bookings' => $pending->nok_apply_all,
            ]);

            $pending->delete();

            $token = $user->createToken($deviceName)->plainTextToken;

            return [$user, $token];
        });

        event(new RegistrationCompleted($user));

        return ['user' => $user, 'token' => $token];
    }
}
