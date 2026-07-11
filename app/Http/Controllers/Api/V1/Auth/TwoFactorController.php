<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ResendTwoFactorChallengeAction;
use App\Actions\Auth\VerifyTwoFactorChallengeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorResendRequest;
use App\Http\Requests\Auth\TwoFactorVerifyRequest;
use App\Http\Resources\UserResource;
use App\Support\AuthTokens;
use Illuminate\Http\JsonResponse;

class TwoFactorController extends Controller
{
    public function verify(TwoFactorVerifyRequest $request, VerifyTwoFactorChallengeAction $action): JsonResponse
    {
        $deviceName = $request->string('deviceName')->value() ?: ($request->userAgent() ?: 'API Token');

        $result = $action->handle(
            $request->validated('challengeToken'),
            $request->validated('code'),
            $deviceName,
        );

        return $this->success([
            'user' => UserResource::make($result['user']->load(['institution', 'nextOfKin'])),
            'tokens' => AuthTokens::fromPlainTextToken($result['token']),
        ]);
    }

    public function resend(TwoFactorResendRequest $request, ResendTwoFactorChallengeAction $action): JsonResponse
    {
        $action->handle($request->validated('challengeToken'));

        return $this->success();
    }
}
