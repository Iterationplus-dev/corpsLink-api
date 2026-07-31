<?php

namespace App\Services\Sms;

use App\Contracts\SmsGatewayContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends SMS through Twilio's REST API. Same pattern as TermiiSmsGateway —
 * a plain HTTP call, no SDK.
 *
 * @see https://www.twilio.com/docs/sms/send-messages
 */
class TwilioSmsGateway implements SmsGatewayContract
{
    public function __construct(
        protected string $url,
        protected ?string $accountSid,
        protected ?string $authToken,
        protected ?string $from,
    ) {}

    public function isConfigured(): bool
    {
        return (bool) ($this->accountSid && $this->authToken && $this->from);
    }

    public function send(string $to, string $message): void
    {
        // Twilio requires E.164 (leading "+"); the rest of this app's SMS
        // pipeline normalizes numbers to Termii's "234..." format instead.
        $to = str_starts_with($to, '+') ? $to : "+{$to}";

        $response = Http::asForm()
            ->withBasicAuth($this->accountSid, $this->authToken)
            ->post("{$this->url}/Accounts/{$this->accountSid}/Messages.json", [
                'To' => $to,
                'From' => $this->from,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Twilio SMS failed: '.$response->body());
        }
    }
}
