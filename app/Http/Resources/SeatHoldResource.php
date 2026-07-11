<?php

namespace App\Http\Resources;

use App\Models\SeatHold;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeatHold
 */
class SeatHoldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vehicle = $this->relationLoaded('seat') ? $this->seat->vehicle : null;
        $fare = Money::fromNaira($vehicle?->fare ?? 0);

        return [
            'id' => $this->id,
            'vehicleId' => $this->relationLoaded('seat') ? $this->seat->vehicle_id : null,
            'seatId' => $this->seat_id,
            'seatLabel' => $this->relationLoaded('seat') ? (string) $this->seat->seat_number : null,
            'expiresAt' => $this->expires_at,
            'secondsRemaining' => max(0, $this->expires_at->getTimestamp() - now()->getTimestamp()),
            'fareKobo' => $fare['kobo'],
            'fareDisplay' => $fare['display'],
        ];
    }
}
