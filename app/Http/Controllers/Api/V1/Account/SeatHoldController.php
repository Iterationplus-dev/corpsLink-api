<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\ReleaseSeatHoldAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeatHoldResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatHoldController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $hold = $request->user()->activeSeatHold()->with('seat.vehicle')->first();

        return $this->success($hold ? SeatHoldResource::make($hold) : null);
    }

    public function destroy(Request $request, ReleaseSeatHoldAction $action): JsonResponse
    {
        $action->handle($request->user());

        return $this->success();
    }

    /**
     * DELETE /seat-holds/{holdId} — the frontend's documented path, an
     * alias over the same one-active-hold-per-user model as destroy()
     * above. Only releases if the id actually belongs to the requesting
     * user's active hold.
     */
    public function destroyById(Request $request, int $holdId, ReleaseSeatHoldAction $action): JsonResponse
    {
        $hold = $request->user()->activeSeatHold;

        if (! $hold || $hold->id !== $holdId) {
            throw new ModelNotFoundException;
        }

        $action->handle($request->user());

        return $this->success();
    }
}
