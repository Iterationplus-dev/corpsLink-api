<?php

namespace App\Contracts;

interface WhatsAppGatewayContract
{
    /**
     * Whether this provider has the credentials it needs — checked before
     * send() so an unconfigured provider is skipped rather than treated as
     * a delivery failure worth falling back from.
     */
    public function isConfigured(): bool;

    /**
     * @param  string  $to  International format, no leading "+" (e.g. "234801...").
     * @param  array<int, string>  $params  Positional template variables (e.g. [code, expiryMinutes]).
     *
     * @throws \RuntimeException when the provider is unreachable or rejects the message.
     */
    public function send(string $to, array $params): void;
}
