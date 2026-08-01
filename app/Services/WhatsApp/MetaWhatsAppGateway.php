<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGatewayContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends template messages through Meta's WhatsApp Cloud API. Same pattern
 * as the SMS gateways — a plain HTTP call, no SDK.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
class MetaWhatsAppGateway implements WhatsAppGatewayContract
{
    public function __construct(
        protected string $url,
        protected string $apiVersion,
        protected ?string $phoneNumberId,
        protected ?string $accessToken,
        protected string $otpTemplate,
        protected string $otpTemplateLanguage,
    ) {}

    public function isConfigured(): bool
    {
        return (bool) ($this->phoneNumberId && $this->accessToken);
    }

    public function send(string $to, array $params): void
    {
        $response = Http::baseUrl($this->url)
            ->withToken($this->accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->post('/'.$this->apiVersion.'/'.$this->phoneNumberId.'/messages', [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $this->otpTemplate,
                    'language' => ['code' => $this->otpTemplateLanguage],
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
            throw new RuntimeException('WhatsApp message via Meta failed: '.$response->body());
        }
    }
}
