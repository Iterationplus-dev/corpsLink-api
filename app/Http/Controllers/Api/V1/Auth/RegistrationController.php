<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ChangeRegistrationEmailAction;
use App\Actions\Auth\CompleteRegistrationAction;
use App\Actions\Auth\SaveRegistrationNextOfKinAction;
use App\Actions\Auth\SaveRegistrationSchoolInfoAction;
use App\Actions\Auth\StartRegistrationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeRegistrationEmailRequest;
use App\Http\Requests\Auth\CompleteRegistrationRequest;
use App\Http\Requests\Auth\SaveRegistrationNextOfKinRequest;
use App\Http\Requests\Auth\SaveRegistrationSchoolInfoRequest;
use App\Http\Requests\Auth\StartRegistrationRequest;
use App\Http\Resources\UserResource;
use App\Support\AuthTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function start(StartRegistrationRequest $request, StartRegistrationAction $action): JsonResponse
    {
        $pending = $action->handle([
            'name' => $request->validated('fullName'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
        ]);

        return $this->created([
            'registrationId' => $pending->registration_token,
            'otpExpiresAt' => $pending->expires_at,
        ]);
    }

    public function changeEmail(ChangeRegistrationEmailRequest $request, ChangeRegistrationEmailAction $action): JsonResponse
    {
        $action->handle(
            $request->string('registration_token')->value(),
            $request->string('email')->value(),
        );

        return $this->success();
    }

    public function school(string $registrationId, SaveRegistrationSchoolInfoRequest $request, SaveRegistrationSchoolInfoAction $action): JsonResponse
    {
        $action->handle($registrationId, [
            'institution_id' => $request->validated('institutionId'),
            'call_up_number' => $request->validated('callUpNumber'),
            'state_code' => $request->validated('stateCode'),
            'batch' => $request->validated('batch'),
            'stream' => $request->validated('stream'),
        ]);

        return $this->success(['callUpNumberVerified' => true]);
    }

    public function nextOfKin(string $registrationId, SaveRegistrationNextOfKinRequest $request, SaveRegistrationNextOfKinAction $action): JsonResponse
    {
        $contact = $request->validated('emergencyContact');

        $action->handle($registrationId, [
            'full_name' => $contact['fullName'],
            'relationship' => $contact['relationship'],
            'phone' => $contact['phone'],
            'alternate_phone' => $contact['alternatePhone'] ?? null,
            'address' => $contact['address'],
            'apply_to_all_bookings' => $contact['applyToAllBookings'] ?? true,
        ]);

        return $this->success(['accepted' => true]);
    }

    public function complete(string $registrationId, CompleteRegistrationRequest $request, CompleteRegistrationAction $action): JsonResponse
    {
        $result = $action->handle(
            $registrationId,
            $request->string('password')->value(),
            $this->deviceName($request),
        );

        return $this->created([
            'user' => UserResource::make($result['user']->load(['institution', 'nextOfKin'])),
            'tokens' => AuthTokens::fromPlainTextToken($result['token']),
        ]);
    }

    protected function deviceName(Request $request): string
    {
        return $request->string('device_name')->value() ?: ($request->userAgent() ?: 'API Token');
    }
}
