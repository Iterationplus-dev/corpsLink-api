<?php

namespace App\Services\Sms;

use App\Contracts\SmsGatewayContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends SMS through Termii's HTTP API. Same pattern as ZeptomailTransport —
 * a plain HTTP call, no SDK.
 *
 * @see https://developers.termii.com/messaging
 */
class TermiiSmsGateway implements SmsGatewayContract
{
    public function __construct(
        protected string $url,
        protected ?string $apiKey,
        protected string $senderId,
    ) {}

    public function isConfigured(): bool
    {
        return (bool) $this->apiKey;
    }

    public function send(string $to, string $message): void
    {
        $response = Http::post("{$this->url}/api/sms/send", [
            'api_key' => $this->apiKey,
            'to' => $to,
            'from' => $this->senderId,
            'sms' => $message,
            'type' => 'plain',
            'channel' => 'generic',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Termii SMS failed: '.$response->body());
        }
    }
}
