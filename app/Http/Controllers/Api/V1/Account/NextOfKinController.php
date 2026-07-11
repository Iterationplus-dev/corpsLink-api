<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\DeleteNextOfKinAction;
use App\Actions\Account\UpsertNextOfKinAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpsertNextOfKinRequest;
use App\Http\Resources\NextOfKinResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NextOfKinController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->success(NextOfKinResource::make($request->user()->nextOfKin));
    }

    public function update(UpsertNextOfKinRequest $request, UpsertNextOfKinAction $action): JsonResponse
    {
        $data = $request->validated();

        $nok = $action->handle($request->user(), [
            'full_name' => $data['fullName'],
            'relationship' => $data['relationship'],
            'phone' => $data['phone'],
            'alternate_phone' => $data['alternatePhone'] ?? null,
            'address' => $data['address'],
            'apply_to_all_bookings' => $data['applyToAllBookings'] ?? true,
        ]);

        return $this->success(NextOfKinResource::make($nok));
    }

    public function destroy(Request $request, DeleteNextOfKinAction $action): JsonResponse
    {
        $action->handle($request->user());

        return $this->success();
    }
}
