<?php

namespace App\Http\Controllers\Api\V1\Institutions;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VehicleController extends Controller
{
    public function index(Request $request, Institution $institution): JsonResponse
    {
        $cacheKey = "vehicles:institution:{$institution->id}:v2";

        $vehicles = Cache::remember(
            $cacheKey,
            now()->addSeconds(config('corpslink.cache.vehicles_ttl_seconds')),
            fn () => $institution->vehicles()
                ->active()
                ->withCount([
                    'seats as filled_seats_count' => fn ($query) => $query
                        ->whereHas('booking', fn ($q) => $q->where('status', BookingStatus::Confirmed)),
                    'seats as held_seats_count' => fn ($query) => $query->whereHas('activeHold'),
                ])
                ->orderBy('departure_at')
                ->get(),
        );

        return $this->success(VehicleResource::collection($vehicles));
    }
}
