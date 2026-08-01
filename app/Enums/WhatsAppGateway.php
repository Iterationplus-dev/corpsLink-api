<?php

namespace App\Enums;

enum WhatsAppGateway: string
{
    case Meta = 'meta';
    case Twilio = 'twilio';
}
