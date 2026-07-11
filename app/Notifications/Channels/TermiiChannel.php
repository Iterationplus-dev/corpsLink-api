<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends SMS through Termii's HTTP API. Same pattern as ZeptomailTransport —
 * a plain HTTP call, no SDK.
 *
 * @see https://developers.termii.com/messaging
 */
class TermiiChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTermii')) {
            return;
        }

        $phone = $notifiable->phone ?? null;

        if (! $phone) {
            return;
        }

        $apiKey = config('services.termii.api_key');

        if (! $apiKey) {
            Log::debug('Termii SMS skipped — no API key configured.', ['phone' => $phone]);

            return;
        }

        $message = $notification->toTermii($notifiable);

        $response = Http::post(config('services.termii.url').'/api/sms/send', [
            'api_key' => $apiKey,
            'to' => $this->normalize($phone),
            'from' => config('services.termii.sender_id'),
            'sms' => $message,
            'type' => 'plain',
            'channel' => 'generic',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Termii SMS failed: '.$response->body());
        }
    }

    /**
     * Local Nigerian numbers (080XXXXXXXX, how users.phone is stored) to
     * the international format (234XXXXXXXXXX) Termii expects.
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
