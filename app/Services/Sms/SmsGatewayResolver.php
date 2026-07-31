<?php

namespace App\Services\Sms;

use App\Contracts\SmsGatewayContract;
use App\Enums\SmsGateway;

class SmsGatewayResolver
{
    public function resolve(SmsGateway $gateway): SmsGatewayContract
    {
        return match ($gateway) {
            SmsGateway::Termii => new TermiiSmsGateway(
                config('services.termii.url'),
                config('services.termii.api_key'),
                config('services.termii.sender_id'),
            ),
            SmsGateway::Twilio => new TwilioSmsGateway(
                config('services.twilio.url'),
                config('services.twilio.account_sid'),
                config('services.twilio.auth_token'),
                config('services.twilio.from'),
            ),
        };
    }
}
