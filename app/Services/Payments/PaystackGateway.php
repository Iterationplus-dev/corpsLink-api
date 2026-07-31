<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackGateway implements PaymentGatewayContract
{
    public function __construct(
        protected string $url,
        protected string $secretKey,
        protected string $callbackUrl,
    ) {}

    /**
     * @return array{authorization_url: ?string, gateway_reference: ?string}
     */
    public function initialize(Payment $payment): array
    {
        // Paystack rejects init-transaction outright ("Duplicate Transaction
        // Reference") if `reference` was ever used before — even for a prior
        // attempt that expired/failed. A retried checkout (session timed
        // out, user backs out and tries again) reuses the same Payment row,
        // so a fresh suffix per attempt is required here — same fix already
        // applied to Monnify (see MonnifyGateway::initialize()).
        $attemptReference = "{$payment->reference}_".Str::random(8);

        $response = Http::withToken($this->secretKey)
            ->post("{$this->url}/transaction/initialize", [
                'email' => $payment->user->email,
                'amount' => (int) round(((float) $payment->amount) * 100), // kobo
                'currency' => $payment->currency,
                'reference' => $attemptReference,
                'callback_url' => $this->callbackUrl,
            ])
            ->throw();

        return [
            'authorization_url' => $response->json('data.authorization_url'),
            'gateway_reference' => $response->json('data.reference'),
        ];
    }

    public function verify(string $reference): PaymentVerificationResult
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->url}/transaction/verify/{$reference}")
                ->throw();
        } catch (RequestException|ConnectionException $e) {
            // Gateway rejected/couldn't find the reference (e.g. checkout was
            // never completed) or was unreachable — surface as a normal
            // failed-verification outcome rather than an unhandled 500, so
            // ConfirmPaymentAction's existing graceful-failure path handles it.
            return new PaymentVerificationResult(
                successful: false,
                amount: 0,
                currency: 'NGN',
                gatewayReference: null,
                raw: ['error' => $e->getMessage()],
            );
        }

        $data = $response->json('data', []);

        return new PaymentVerificationResult(
            successful: ($data['status'] ?? null) === 'success',
            amount: ($data['amount'] ?? 0) / 100,
            currency: $data['currency'] ?? 'NGN',
            gatewayReference: $data['reference'] ?? null,
            raw: $data,
        );
    }

    public function validateWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Paystack-Signature');

        if (! $signature) {
            return false;
        }

        return hash_equals(
            hash_hmac('sha512', $request->getContent(), $this->secretKey),
            $signature,
        );
    }

    /**
     * data.reference here is our attempt-suffixed value
     * ("{payment->reference}_{random}") — strip the suffix so the webhook
     * controller's plain Payment::where('reference', ...) lookup still
     * matches. Our own reference format never contains an underscore.
     */
    public function referenceFromWebhook(Request $request): ?string
    {
        $reference = $request->input('data.reference');

        return $reference ? explode('_', $reference)[0] : null;
    }
}
