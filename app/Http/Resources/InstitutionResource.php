<?php

namespace App\Http\Resources;

use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Institution
 */
class InstitutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'abbreviation' => $this->abbreviation,
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'state' => $this->state,
            // No per-vehicle fill data is eager-loaded here (would mean an
            // N+1 across every institution in a list) — a coarse active/
            // inactive heuristic stands in for the frontend's aggregate
            // open|filling|full badge.
            'status' => $this->is_active ? 'open' : 'full',
            'campDestination' => $this->relationLoaded('vehicles') ? $this->vehicles->first()?->destination : null,
            'vehicleCount' => $this->vehicles_count ?? 0,
            'verified' => $this->is_active,
            'logoUrl' => $this->logoUrl(),
            'isActive' => $this->is_active,
            'supportPhone' => $this->support_phone,
            'supportEmail' => $this->support_email,
            'supportHours' => $this->support_hours,
        ];
    }
}
