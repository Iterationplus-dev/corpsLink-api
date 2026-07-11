<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Powers the "Trip details" / "Receipt" screens and the booking-creation
 * response.
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fare = Money::fromNaira($this->fare);
        $vehicle = $this->relationLoaded('vehicle') ? $this->vehicle : null;
        $seat = $this->relationLoaded('seat') ? $this->seat : null;
        $user = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'institution' => $vehicle?->relationLoaded('institution') ? [
                'id' => $vehicle->institution->id,
                'name' => $vehicle->institution->name,
            ] : null,
            'vehicle' => $vehicle ? [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'route' => "{$vehicle->pickup_point} → {$vehicle->destination}",
                'pickupPoint' => $vehicle->pickup_point,
            ] : null,
            'seat' => $seat ? [
                'id' => $seat->id,
                'label' => (string) $seat->seat_number,
                'position' => $seat->isWindow() ? 'window' : 'aisle',
            ] : null,
            'departureAt' => $vehicle?->departure_at,
            'fareKobo' => $fare['kobo'],
            'fareDisplay' => $fare['display'],
            'passengerName' => $user?->name,
            'stateCode' => $user?->state_code,
            'qrPayload' => $this->qrPayload(),
            'payment' => PaymentResource::make($this->whenLoaded('payment')),
            'createdAt' => $this->created_at,
        ];
    }

    protected function qrPayload(): string
    {
        $institutionId = $this->relationLoaded('vehicle') && $this->vehicle->relationLoaded('institution')
            ? $this->vehicle->institution->id
            : '';
        $seatLabel = $this->relationLoaded('seat') ? $this->seat->seat_number : '';
        $stateCode = $this->relationLoaded('user') ? ($this->user->state_code ?? '') : '';

        return "{$this->reference}|{$institutionId}|SEAT{$seatLabel}|{$stateCode}";
    }
}
