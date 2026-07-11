<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ResendOtpAction;
use App\Actions\Auth\VerifyRegistrationEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function verify(VerifyOtpRequest $request, VerifyRegistrationEmailAction $action): JsonResponse
    {
        $action->handle(
            $request->validated('registrationId'),
            $request->validated('code'),
        );

        return $this->success(['verified' => true]);
    }

    public function resend(ResendOtpRequest $request, ResendOtpAction $action): JsonResponse
    {
        $action->handle(
            $request->user('sanctum'),
            $request->validated('context'),
            $request->validated('registrationId'),
            $request->validated('email'),
        );

        return $this->success(['otpExpiresAt' => now()->addMinutes(config('corpslink.otp.expiry_minutes'))]);
    }
}
