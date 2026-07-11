<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Enums\PaymentGateway as PaymentGatewayEnum;

class PaymentGatewayResolver
{
    public function resolve(PaymentGatewayEnum $gateway): PaymentGatewayContract
    {
        return match ($gateway) {
            PaymentGatewayEnum::Paystack => new PaystackGateway(
                config('services.paystack.url'),
                config('services.paystack.secret_key'),
            ),
            PaymentGatewayEnum::Flutterwave => new FlutterwaveGateway(
                config('services.flutterwave.url'),
                config('services.flutterwave.secret_key'),
                config('services.flutterwave.webhook_hash'),
                config('services.flutterwave.redirect_url'),
            ),
        };
    }
}
