<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $filled = (int) ($this->filled_seats_count ?? 0);
        $held = (int) ($this->held_seats_count ?? 0);
        $fare = Money::fromNaira($this->fare);

        return [
            'id' => $this->id,
            'institutionId' => $this->institution_id,
            'institution' => InstitutionResource::make($this->whenLoaded('institution')),
            'name' => $this->name,
            'departureTime' => $this->departure_at->format('D j M · g:i A'),
            'departureDate' => $this->departure_at,
            'route' => "{$this->pickup_point} → {$this->destination}",
            'pickupPoint' => $this->pickup_point,
            'destination' => $this->destination,
            'totalSeats' => $this->capacity,
            'filledSeats' => $filled,
            'heldSeats' => $held,
            'remainingSeats' => max(0, $this->capacity - $filled - $held),
            'fareKobo' => $fare['kobo'],
            'fareDisplay' => $fare['display'],
            'status' => $this->badge($filled),
            'isActive' => $this->is_active,
        ];
    }
}
