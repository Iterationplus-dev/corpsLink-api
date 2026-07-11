<?php

namespace App\Support;

/**
 * Sanctum issues a single opaque token with no expiry in this app
 * (config/sanctum.php: 'expiration' => null) — it has no native
 * refresh-token concept. The frontend contract expects an
 * {accessToken, refreshToken, expiresIn} pair, so both fields carry the
 * same Sanctum token; POST /auth/refresh re-issues a fresh one on demand.
 * Real dual-token rotation is a deliberate non-goal here — this shim
 * satisfies the contract shape without inventing token-family
 * infrastructure this app doesn't otherwise need.
 */
class AuthTokens
{
    public const int EXPIRES_IN_SECONDS = 31536000;

    /**
     * @return array{accessToken: string, refreshToken: string, expiresIn: int}
     */
    public static function fromPlainTextToken(string $token): array
    {
        return [
            'accessToken' => $token,
            'refreshToken' => $token,
            'expiresIn' => self::EXPIRES_IN_SECONDS,
        ];
    }
}
