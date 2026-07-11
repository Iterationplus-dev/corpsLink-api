<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\ChangePasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function update(ChangePasswordRequest $request, ChangePasswordAction $action): JsonResponse
    {
        $action->handle($request->user(), $request->validated('newPassword'));

        return $this->success(['changed' => true]);
    }
}
