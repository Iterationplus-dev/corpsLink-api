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

        $query = function () use ($institution) {
            $resolved = VehicleResource::collection(
                $institution->vehicles()
                    ->active()
                    ->withCount([
                        'seats as filled_seats_count' => fn ($query) => $query
                            ->whereHas('booking', fn ($q) => $q->where('status', BookingStatus::Confirmed)),
                        'seats as held_seats_count' => fn ($query) => $query->whereHas('activeHold'),
                    ])
                    ->orderBy('departure_at')
                    ->get(),
            )->resolve();

            // A resolved resource array still holds live objects for any
            // field that isn't a plain scalar (e.g. `departureDate` is a
            // Carbon instance) — those carry mangled private/protected
            // property names (embedded null bytes) that don't round-trip
            // cleanly through the Redis/Predis client. Forcing a JSON
            // round-trip collapses everything to plain scalars/arrays
            // before it ever reaches the cache, matching exactly what a
            // real HTTP response would have serialized it to anyway.
            return json_decode(json_encode($resolved), true);
        };

        try {
            $vehicles = Cache::remember($cacheKey, now()->addSeconds(config('corpslink.cache.vehicles_ttl_seconds')), $query);
        } catch (\Throwable $e) {
            // Belt and suspenders — a cache layer should never be able to
            // take an endpoint down, regardless of the cause.
            report($e);
            Cache::forget($cacheKey);
            $vehicles = $query();
        }

        return $this->success($vehicles);
    }
}
