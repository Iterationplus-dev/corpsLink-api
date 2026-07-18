<?php

namespace App\Notifications\Channels;

use App\Notifications\Channels\Concerns\NormalizesNigerianPhoneNumber;
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
    use NormalizesNigerianPhoneNumber;

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTermii')) {
            return;
        }

        $phone = $this->resolvePhone($notifiable, $notification);

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
}
