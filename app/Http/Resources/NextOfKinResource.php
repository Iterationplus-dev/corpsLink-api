<?php

namespace App\Http\Resources;

use App\Models\NextOfKin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NextOfKin
 */
class NextOfKinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->full_name,
            'relationship' => $this->relationship,
            'phone' => $this->phone,
            'alternatePhone' => $this->alternate_phone,
            'address' => $this->address,
            'applyToAllBookings' => $this->apply_to_all_bookings,
        ];
    }
}
