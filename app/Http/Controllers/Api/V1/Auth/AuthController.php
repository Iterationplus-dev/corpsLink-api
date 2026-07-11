<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Account\RevokeAllSessionsAction;
use App\Actions\Auth\LoginAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Support\AuthTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $deviceName = $request->string('deviceName')->value() ?: ($request->userAgent() ?: 'API Token');

        $result = $action->handle(
            $request->validated('identifier'),
            $request->validated('password'),
            $deviceName,
        );

        // The frontend's LoginResponse type has no 2FA branch — 2FA is an
        // opt-in security setting this particular app build doesn't have a
        // challenge screen for yet. Left in place (extra fields, not a
        // breaking shape) rather than removing a working feature; only
        // users who've explicitly enabled it hit this branch.
        if ($result['requires_two_factor']) {
            return $this->success([
                'requiresTwoFactor' => true,
                'challengeToken' => $result['challenge_token'],
                'expiresIn' => $result['expires_in'],
            ]);
        }

        return $this->success([
            'requiresTwoFactor' => false,
            'user' => UserResource::make($result['user']->load(['institution', 'nextOfKin'])),
            'tokens' => AuthTokens::fromPlainTextToken($result['token']),
        ]);
    }

    /**
     * Pragmatic shim, not real token rotation — see App\Support\AuthTokens.
     * Revokes the current token and issues a fresh one.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceName = $request->user()->currentAccessToken()->name ?? 'API Token';

        $user->currentAccessToken()->delete();
        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->success(AuthTokens::fromPlainTextToken($token));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success();
    }

    public function logoutAll(Request $request, RevokeAllSessionsAction $action): JsonResponse
    {
        $action->handle($request->user(), keepCurrentSession: false);

        return $this->success();
    }
}
