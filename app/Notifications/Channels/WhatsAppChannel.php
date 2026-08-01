<?php

namespace App\Notifications\Channels;

use App\Enums\WhatsAppGateway;
use App\Notifications\Channels\Concerns\NormalizesNigerianPhoneNumber;
use App\Services\WhatsApp\WhatsAppGatewayResolver;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Tries each provider in `corpslink.whatsapp.providers` order (default:
 * Meta, then Twilio) until one sends successfully. A provider missing its
 * credentials is skipped, not counted as a failure; only when every
 * configured provider actually rejects/fails the message does this throw —
 * same fallback behavior as SmsChannel.
 */
class WhatsAppChannel
{
    use NormalizesNigerianPhoneNumber;

    public function __construct(protected WhatsAppGatewayResolver $resolver) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = $this->resolvePhone($notifiable, $notification);

        if (! $phone) {
            return;
        }

        $to = $this->normalize($phone);
        $params = $notification->toWhatsApp($notifiable);
        $failures = [];

        foreach ($this->configuredProviders() as $provider) {
            $gateway = $this->resolver->resolve($provider);

            if (! $gateway->isConfigured()) {
                continue;
            }

            try {
                $gateway->send($to, $params);

                return;
            } catch (RuntimeException $e) {
                $failures[$provider->value] = $e->getMessage();
                Log::warning("WhatsApp message via {$provider->value} failed, trying next provider.", ['phone' => $to]);
            }
        }

        if (! $failures) {
            Log::debug('WhatsApp message skipped — no WhatsApp provider configured.', ['phone' => $to]);

            return;
        }

        throw new RuntimeException('All WhatsApp providers failed: '.json_encode($failures));
    }

    /**
     * @return array<int, WhatsAppGateway>
     */
    protected function configuredProviders(): array
    {
        return collect(config('corpslink.whatsapp.providers', []))
            ->map(fn (string $name) => WhatsAppGateway::tryFrom($name))
            ->filter()
            ->values()
            ->all();
    }
}
