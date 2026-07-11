<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\UpdateSecurityPreferencesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateSecurityPreferencesRequest;
use Illuminate\Http\JsonResponse;

class SecurityController extends Controller
{
    public function update(UpdateSecurityPreferencesRequest $request, UpdateSecurityPreferencesAction $action): JsonResponse
    {
        $user = $action->handle($request->user(), $request->boolean('twoFactorEnabled'));

        return $this->success(['twoFactorEnabled' => $user->two_factor_enabled]);
    }
}
