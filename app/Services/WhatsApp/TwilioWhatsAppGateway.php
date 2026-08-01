<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGatewayContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends template (Content API) messages through Twilio's WhatsApp channel.
 * Same Messages.json endpoint as TwilioSmsGateway, but To/From carry a
 * "whatsapp:" prefix and the body is a pre-approved Content Template
 * (ContentSid + ContentVariables) rather than free text — WhatsApp only
 * allows free-form replies within an existing 24h conversation, so OTPs
 * (first contact) must use a template, same constraint as Meta's Cloud API.
 *
 * @see https://www.twilio.com/docs/whatsapp/api
 */
class TwilioWhatsAppGateway implements WhatsAppGatewayContract
{
    public function __construct(
        protected string $url,
        protected ?string $accountSid,
        protected ?string $authToken,
        protected ?string $from,
        protected ?string $contentSid,
    ) {}

    public function isConfigured(): bool
    {
        return (bool) ($this->accountSid && $this->authToken && $this->from && $this->contentSid);
    }

    public function send(string $to, array $params): void
    {
        $to = str_starts_with($to, '+') ? $to : "+{$to}";

        $variables = [];
        foreach ($params as $index => $value) {
            $variables[(string) ($index + 1)] = $value;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->accountSid, $this->authToken)
            ->post("{$this->url}/Accounts/{$this->accountSid}/Messages.json", [
                'To' => "whatsapp:{$to}",
                'From' => "whatsapp:{$this->from}",
                'ContentSid' => $this->contentSid,
                'ContentVariables' => json_encode($variables),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp message via Twilio failed: '.$response->body());
        }
    }
}
