<?php

namespace App\Actions\Auth;

use App\Exceptions\ApiException;
use App\Exceptions\RegistrationExpiredException;
use App\Models\Institution;
use App\Models\PendingRegistration;

class SaveRegistrationSchoolInfoAction
{
    /**
     * @param  array{institution_id: int, call_up_number: string, state_code: ?string, batch: string, stream: string}  $data
     */
    public function handle(string $registrationToken, array $data): PendingRegistration
    {
        $pending = PendingRegistration::query()
            ->where('registration_token', $registrationToken)
            ->first();

        if (! $pending || $pending->isExpired()) {
            throw RegistrationExpiredException::make();
        }

        if (! $pending->isEmailVerified()) {
            throw new ApiException('Please verify your email before continuing.', status: 422, errorCode: 'email_not_verified');
        }

        $institution = Institution::query()->active()->find($data['institution_id']);

        if (! $institution) {
            throw new ApiException('This institution is not currently accepting registrations.', status: 422, errorCode: 'institution_inactive');
        }

        $pending->update([
            'institution_id' => $data['institution_id'],
            'call_up_number' => $data['call_up_number'],
            'state_code' => $data['state_code'] ?? null,
            'batch' => $data['batch'],
            'stream' => $data['stream'],
        ]);

        return $pending;
    }
}
