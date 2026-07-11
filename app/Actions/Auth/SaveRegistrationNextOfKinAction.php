<?php

namespace App\Actions\Auth;

use App\Exceptions\ApiException;
use App\Exceptions\RegistrationExpiredException;
use App\Models\PendingRegistration;

class SaveRegistrationNextOfKinAction
{
    /**
     * @param  array{full_name: string, relationship: string, phone: string, alternate_phone: ?string, address: string, apply_to_all_bookings?: bool}  $data
     */
    public function handle(string $registrationToken, array $data): PendingRegistration
    {
        $pending = PendingRegistration::query()
            ->where('registration_token', $registrationToken)
            ->first();

        if (! $pending || $pending->isExpired()) {
            throw RegistrationExpiredException::make();
        }

        if (! $pending->hasSchoolInfo()) {
            throw new ApiException('Please complete your school information before continuing.', status: 422, errorCode: 'school_info_incomplete');
        }

        $pending->update([
            'nok_full_name' => $data['full_name'],
            'nok_relationship' => $data['relationship'],
            'nok_phone' => $data['phone'],
            'nok_alternate_phone' => $data['alternate_phone'] ?? null,
            'nok_address' => $data['address'],
            'nok_apply_all' => $data['apply_to_all_bookings'] ?? true,
        ]);

        return $pending;
    }
}
