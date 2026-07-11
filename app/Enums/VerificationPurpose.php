<?php

namespace App\Enums;

enum VerificationPurpose: string
{
    case RegistrationEmail = 'registration_email';
    case PasswordReset = 'password_reset';
    case EmailChange = 'email_change';
    case TwoFactorLogin = 'two_factor_login';
}
