<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\RegisterDeviceTokenAction;
use App\Actions\Account\UnregisterDeviceTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\RegisterDeviceTokenRequest;
use App\Http\Resources\DeviceTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(RegisterDeviceTokenRequest $request, RegisterDeviceTokenAction $action): JsonResponse
    {
        $deviceToken = $action->handle(
            $request->user(),
            $request->string('token')->value(),
            $request->string('platform')->value(),
        );

        return $this->success(DeviceTokenResource::make($deviceToken));
    }

    public function destroy(Request $request, int $deviceToken, UnregisterDeviceTokenAction $action): JsonResponse
    {
        $action->handle($request->user(), $deviceToken);

        return $this->success();
    }
}
