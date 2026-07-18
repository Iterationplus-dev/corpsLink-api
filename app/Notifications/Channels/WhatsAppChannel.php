<?php

namespace App\Notifications\Channels;

use App\Notifications\Channels\Concerns\NormalizesNigerianPhoneNumber;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends template messages through Meta's WhatsApp Cloud API. Same pattern
 * as TermiiChannel — a plain HTTP call, no SDK.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
class WhatsAppChannel
{
    use NormalizesNigerianPhoneNumber;

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = $this->resolvePhone($notifiable, $notification);

        if (! $phone) {
            return;
        }

        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');

        if (! $phoneNumberId || ! $accessToken) {
            Log::debug('WhatsApp message skipped — no Cloud API credentials configured.', ['phone' => $phone]);

            return;
        }

        $params = $notification->toWhatsApp($notifiable);

        $response = Http::baseUrl(config('services.whatsapp.url'))
            ->withToken($accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->post('/'.config('services.whatsapp.api_version').'/'.$phoneNumberId.'/messages', [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->normalize($phone),
                'type' => 'template',
                'template' => [
                    'name' => config('services.whatsapp.otp_template'),
                    'language' => ['code' => config('services.whatsapp.otp_template_language')],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => array_map(
                                fn (string $value) => ['type' => 'text', 'text' => $value],
                                $params,
                            ),
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp message failed: '.$response->body());
        }
    }
}
