<?php

namespace App\Http\Controllers\Api\V1\Vehicles;

use App\Actions\Vehicles\HoldSeatAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeatHoldResource;
use App\Http\Resources\SeatResource;
use App\Models\Seat;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatController extends Controller
{
    /**
     * Deliberately not cached — holds are second-to-second dynamic and a
     * stale seat map directly breaks the booking UX.
     */
    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $seats = $vehicle->seats()
            ->with(['activeHold', 'confirmedBooking'])
            ->orderBy('seat_number')
            ->get();

        return $this->success(SeatResource::collection($seats));
    }

    public function hold(Request $request, Vehicle $vehicle, Seat $seat, HoldSeatAction $action): JsonResponse
    {
        $hold = $action->handle($request->user(), $seat);

        return $this->created(SeatHoldResource::make($hold->load('seat.vehicle')));
    }
}
