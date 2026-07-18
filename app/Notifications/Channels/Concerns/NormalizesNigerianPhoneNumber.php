<?php

namespace App\Notifications\Channels\Concerns;

use Illuminate\Notifications\Notification;

trait NormalizesNigerianPhoneNumber
{
    /**
     * Reads an on-demand route (e.g. for a PendingRegistration, which has
     * no Notifiable trait) if one was set, otherwise falls back to the
     * notifiable's own `phone` attribute (e.g. a User).
     */
    protected function resolvePhone(object $notifiable, Notification $notification): ?string
    {
        $routed = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor(static::class, $notification)
            : null;

        return $routed ?? $notifiable->phone ?? null;
    }

    /**
     * Local Nigerian numbers (080XXXXXXXX, how phone numbers are stored) to
     * the international format (234XXXXXXXXXX) providers expect.
     */
    protected function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? $phone;

        if (str_starts_with($digits, '0')) {
            return '234'.substr($digits, 1);
        }

        if (str_starts_with($digits, '234')) {
            return $digits;
        }

        return '234'.$digits;
    }
}
