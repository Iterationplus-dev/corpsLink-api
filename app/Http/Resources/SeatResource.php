<?php

namespace App\Http\Resources;

use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Seat
 */
class SeatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // confirmedBooking and activeHold must be eager loaded by the
        // caller to avoid N+1.
        $hold = $this->activeHold;

        $status = match (true) {
            $this->confirmedBooking !== null => 'occupied',
            $hold !== null => $hold->user_id === $request->user()?->id ? 'held_by_you' : 'held',
            default => 'available',
        };

        return [
            'id' => $this->id,
            'vehicleId' => $this->vehicle_id,
            'label' => (string) $this->seat_number,
            'row' => $this->row(),
            'col' => $this->col(),
            'position' => $this->isWindow() ? 'window' : 'aisle',
            'status' => $status,
            'holdExpiresAt' => $status === 'held_by_you' ? $hold->expires_at : null,
        ];
    }
}
