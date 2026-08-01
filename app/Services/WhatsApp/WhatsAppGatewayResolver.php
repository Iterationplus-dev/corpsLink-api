<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGatewayContract;
use App\Enums\WhatsAppGateway;

class WhatsAppGatewayResolver
{
    public function resolve(WhatsAppGateway $gateway): WhatsAppGatewayContract
    {
        return match ($gateway) {
            WhatsAppGateway::Meta => new MetaWhatsAppGateway(
                config('services.whatsapp.url'),
                config('services.whatsapp.api_version'),
                config('services.whatsapp.phone_number_id'),
                config('services.whatsapp.access_token'),
                config('services.whatsapp.otp_template'),
                config('services.whatsapp.otp_template_language'),
            ),
            WhatsAppGateway::Twilio => new TwilioWhatsAppGateway(
                config('services.twilio.url'),
                config('services.twilio.account_sid'),
                config('services.twilio.auth_token'),
                config('services.twilio.whatsapp_from'),
                config('services.twilio.whatsapp_content_sid'),
            ),
        };
    }
}
