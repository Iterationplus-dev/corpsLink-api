<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Paystack = 'paystack';
    case Flutterwave = 'flutterwave';
    case Monnify = 'monnify';
}
