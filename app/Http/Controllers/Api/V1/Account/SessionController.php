<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\RevokeAllSessionsAction;
use App\Actions\Account\RevokeSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->latest('last_used_at')->get();

        return $this->success(SessionResource::collection($tokens));
    }

    public function destroy(Request $request, int $token, RevokeSessionAction $action): JsonResponse
    {
        $action->handle($request->user(), $token);

        return $this->success();
    }

    public function destroyAll(Request $request, RevokeAllSessionsAction $action): JsonResponse
    {
        $action->handle($request->user(), keepCurrentSession: false);

        return $this->success();
    }
}
