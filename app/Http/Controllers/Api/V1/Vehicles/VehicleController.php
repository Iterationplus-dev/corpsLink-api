<?php

namespace App\Http\Controllers\Api\V1\Vehicles;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class VehicleController extends Controller
{
    public function show(Vehicle $vehicle): JsonResponse
    {
        $vehicle = Cache::remember(
            "vehicles:{$vehicle->id}:v2",
            now()->addSeconds(config('corpslink.cache.vehicles_ttl_seconds')),
            fn () => $vehicle->loadMissing('institution')
                ->loadCount([
                    'seats as filled_seats_count' => fn ($query) => $query
                        ->whereHas('booking', fn ($q) => $q->where('status', BookingStatus::Confirmed)),
                    'seats as held_seats_count' => fn ($query) => $query->whereHas('activeHold'),
                ]),
        );

        return $this->success(VehicleResource::make($vehicle));
    }
}
