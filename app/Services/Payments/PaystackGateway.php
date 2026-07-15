<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
        $response = Http::withToken($this->secretKey)
            ->post("{$this->url}/transaction/initialize", [
                'email' => $payment->user->email,
                'amount' => (int) round(((float) $payment->amount) * 100), // kobo
                'currency' => $payment->currency,
                'reference' => $payment->reference,
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

    public function referenceFromWebhook(Request $request): ?string
    {
        return $request->input('data.reference');
    }
}
