<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\ConfirmEmailChangeAction;
use App\Actions\Account\RequestEmailChangeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmEmailChangeRequest;
use App\Http\Requests\Account\RequestEmailChangeRequest;
use Illuminate\Http\JsonResponse;

class EmailController extends Controller
{
    public function requestChange(RequestEmailChangeRequest $request, RequestEmailChangeAction $action): JsonResponse
    {
        $action->handle($request->user(), $request->validated('newEmail'));

        return $this->success(['otpExpiresAt' => now()->addMinutes(config('corpslink.otp.expiry_minutes'))]);
    }

    public function confirmChange(ConfirmEmailChangeRequest $request, ConfirmEmailChangeAction $action): JsonResponse
    {
        $user = $action->handle($request->user(), $request->validated('code'));

        return $this->success(['email' => $user->email]);
    }
}
