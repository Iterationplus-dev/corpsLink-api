<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\DeleteAccountAction;
use App\Actions\Account\UpdateProfileAction;
use App\Actions\Account\UploadAvatarAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Requests\Account\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->success(
            UserResource::make($request->user()->load(['institution', 'nextOfKin'])),
        );
    }

    public function update(UpdateProfileRequest $request, UpdateProfileAction $action): JsonResponse
    {
        $data = array_filter([
            'name' => $request->validated('fullName'),
            'phone' => $request->validated('phone'),
            'state_code' => $request->validated('stateCode'),
        ], fn ($value) => $value !== null);

        $user = $action->handle($request->user(), $data);

        return $this->success(UserResource::make($user->load(['institution', 'nextOfKin'])));
    }

    public function avatar(UploadAvatarRequest $request, UploadAvatarAction $action): JsonResponse
    {
        $user = $action->handle($request->user(), $request->file('avatar'));

        return $this->success(UserResource::make($user));
    }

    public function destroy(DeleteAccountRequest $request, DeleteAccountAction $action): JsonResponse
    {
        $action->handle($request->user());

        return $this->success(['deleted' => true]);
    }
}
