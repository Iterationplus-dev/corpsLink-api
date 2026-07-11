<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaystackGateway implements PaymentGatewayContract
{
    public function __construct(
        protected string $url,
        protected string $secretKey,
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
            ])
            ->throw();

        return [
            'authorization_url' => $response->json('data.authorization_url'),
            'gateway_reference' => $response->json('data.reference'),
        ];
    }

    public function verify(string $reference): PaymentVerificationResult
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->url}/transaction/verify/{$reference}")
            ->throw();

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
