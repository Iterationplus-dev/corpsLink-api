<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verification Codes (OTP)
    |--------------------------------------------------------------------------
    |
    | Settings shared by every OTP-driven flow: registration email
    | verification, password reset, and email change.
    |
    */

    'otp' => [
        'length' => env('CORPSLINK_OTP_LENGTH', 4),
        'expiry_minutes' => env('CORPSLINK_OTP_EXPIRY_MINUTES', 10),
        'max_attempts' => env('CORPSLINK_OTP_MAX_ATTEMPTS', 5),
        'resend_throttle_seconds' => env('CORPSLINK_OTP_RESEND_THROTTLE_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration Wizard
    |--------------------------------------------------------------------------
    |
    | The registration wizard is resumable via a registration_token. Draft
    | rows (and their still-unconsumed OTPs) older than this TTL are pruned.
    |
    */

    'registration' => [
        'ttl_hours' => env('CORPSLINK_REGISTRATION_TTL_HOURS', 48),
    ],

    /*
    |--------------------------------------------------------------------------
    | Avatar Uploads
    |--------------------------------------------------------------------------
    |
    | Avatars are stored on Cloudinary. `folder` is the Cloudinary folder
    | prefix (the user id is appended per-upload); `transformation` is the
    | on-the-fly transformation preset applied when building the served URL.
    |
    */

    'avatar' => [
        'folder' => env('CORPSLINK_AVATAR_FOLDER', 'corpslink/avatars'),
        'max_kilobytes' => env('CORPSLINK_AVATAR_MAX_KB', 2048),
        'mimes' => ['jpeg', 'jpg', 'png', 'webp'],
        'transformation' => ['w' => 200, 'h' => 200, 'c' => 'fill', 'g' => 'face', 'q' => 'auto', 'f' => 'auto'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Institution Logos
    |--------------------------------------------------------------------------
    |
    | Logos are also stored on Cloudinary (via logo_path storing the
    | public_id), though no upload endpoint exists yet — this transformation
    | preset is ready for whenever that feature lands.
    |
    */

    'institution' => [
        'logo_transformation' => ['w' => 400, 'h' => 400, 'c' => 'fit', 'q' => 'auto', 'f' => 'auto'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Seat Holds
    |--------------------------------------------------------------------------
    |
    | How long a corps member's seat selection is reserved before it's
    | released back to the pool if payment (Phase 3) isn't completed.
    |
    */

    'seat_hold' => [
        'duration_minutes' => env('CORPSLINK_SEAT_HOLD_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Read-Heavy Cache TTLs
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'institutions_ttl_seconds' => env('CORPSLINK_CACHE_INSTITUTIONS_TTL', 60),
        'vehicles_ttl_seconds' => env('CORPSLINK_CACHE_VEHICLES_TTL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    'payments' => [
        'default_gateway' => env('CORPSLINK_DEFAULT_GATEWAY', 'paystack'),
        'currency' => env('CORPSLINK_PAYMENTS_CURRENCY', 'NGN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Login
    |--------------------------------------------------------------------------
    |
    | How long a login's SMS-2FA challenge token stays redeemable. Stored
    | in cache (not a table) — it's a short-lived login artifact, not
    | durable state.
    |
    */

    'two_factor' => [
        'challenge_ttl_minutes' => env('CORPSLINK_2FA_CHALLENGE_TTL_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    */

    'reminders' => [
        'seat_hold_warning_minutes' => env('CORPSLINK_SEAT_HOLD_WARNING_MINUTES', 5),
        'departure_hours' => [24, 1],
    ],

];
