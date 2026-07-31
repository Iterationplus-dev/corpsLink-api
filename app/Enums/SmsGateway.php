<?php

namespace App\Enums;

enum SmsGateway: string
{
    case Termii = 'termii';
    case Twilio = 'twilio';
}
