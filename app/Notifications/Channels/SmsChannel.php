<?php

namespace App\Notifications\Channels;

use App\Enums\SmsGateway;
use App\Notifications\Channels\Concerns\NormalizesNigerianPhoneNumber;
use App\Services\Sms\SmsGatewayResolver;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Tries each provider in `corpslink.sms.providers` order (default: Termii,
 * then Twilio) until one sends successfully. A provider missing its
 * credentials is skipped, not counted as a failure; only when every
 * configured provider actually rejects/fails the message does this throw —
 * matching TermiiChannel's original single-provider behavior, just widened
 * to cover an all-providers-down outage instead of a single one.
 */
class SmsChannel
{
    use NormalizesNigerianPhoneNumber;

    public function __construct(protected SmsGatewayResolver $resolver) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $this->resolvePhone($notifiable, $notification);

        if (! $phone) {
            return;
        }

        $to = $this->normalize($phone);
        $message = $notification->toSms($notifiable);
        $failures = [];

        foreach ($this->configuredProviders() as $provider) {
            $gateway = $this->resolver->resolve($provider);

            if (! $gateway->isConfigured()) {
                continue;
            }

            try {
                $gateway->send($to, $message);

                return;
            } catch (RuntimeException $e) {
                $failures[$provider->value] = $e->getMessage();
                Log::warning("SMS via {$provider->value} failed, trying next provider.", ['phone' => $to]);
            }
        }

        if (! $failures) {
            Log::debug('SMS skipped — no SMS provider configured.', ['phone' => $to]);

            return;
        }

        throw new RuntimeException('All SMS providers failed: '.json_encode($failures));
    }

    /**
     * @return array<int, SmsGateway>
     */
    protected function configuredProviders(): array
    {
        return collect(config('corpslink.sms.providers', []))
            ->map(fn (string $name) => SmsGateway::tryFrom($name))
            ->filter()
            ->values()
            ->all();
    }
}
