<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\UpdateNotificationPreferencesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateNotificationPreferencesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Stored (and read by the notification-gating logic in every
     * Notification::via()) as snake_case — only the API boundary is
     * camelCase, translated both ways here.
     */
    protected const array KEY_MAP = [
        'bookingUpdates' => 'booking_updates',
        'seatHoldAlerts' => 'seat_hold_alerts',
        'departureReminders' => 'departure_reminders',
        'tripChanges' => 'trip_changes',
        'tipsAnnouncements' => 'tips_announcements',
    ];

    public function show(Request $request): JsonResponse
    {
        return $this->success($this->toCamel($request->user()->notification_preferences));
    }

    public function update(UpdateNotificationPreferencesRequest $request, UpdateNotificationPreferencesAction $action): JsonResponse
    {
        $snakeData = [];
        foreach (self::KEY_MAP as $camel => $snake) {
            if ($request->has($camel)) {
                $snakeData[$snake] = $request->boolean($camel);
            }
        }

        $user = $action->handle($request->user(), $snakeData);

        return $this->success($this->toCamel($user->notification_preferences));
    }

    /**
     * @param  array<string, bool>  $preferences
     * @return array<string, bool>
     */
    protected function toCamel(array $preferences): array
    {
        $camel = [];
        foreach (self::KEY_MAP as $camelKey => $snakeKey) {
            $camel[$camelKey] = $preferences[$snakeKey] ?? true;
        }

        return $camel;
    }
}
