<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * Maps each notification class's specific `type` value (set in its own
     * toDatabase(), e.g. `payment_confirmed`) to the coarser bucket the
     * client groups by for icon/color — see NotificationListItem.vue's
     * iconMap, which throws on any category it doesn't recognize, so an
     * unmapped or future `type` falls back to 'promo' rather than crashing
     * the notifications list.
     *
     * @var array<string, string>
     */
    protected const array CATEGORY_MAP = [
        'payment_confirmed' => 'booking',
        'seat_hold_expiring' => 'seat_hold',
        'departure_reminder' => 'departure',
        'welcome' => 'promo',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->data['type'] ?? null;

        return [
            'id' => $this->id,
            'category' => self::CATEGORY_MAP[$type] ?? 'promo',
            'title' => $this->data['title'] ?? null,
            'body' => $this->data['body'] ?? null,
            'bookingId' => $this->data['booking_id'] ?? null,
            'read' => $this->read_at !== null,
            'createdAt' => $this->created_at,
        ];
    }
}
