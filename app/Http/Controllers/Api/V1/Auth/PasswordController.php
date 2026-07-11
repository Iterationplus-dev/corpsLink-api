<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ForgotPasswordAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function forgot(ForgotPasswordRequest $request, ForgotPasswordAction $action): JsonResponse
    {
        $action->handle($request->validated('email'));

        return $this->success(['otpExpiresAt' => now()->addMinutes(config('corpslink.otp.expiry_minutes'))]);
    }

    public function reset(ResetPasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        $action->handle(
            $request->validated('email'),
            $request->validated('code'),
            $request->validated('newPassword'),
        );

        return $this->success(['reset' => true]);
    }
}
