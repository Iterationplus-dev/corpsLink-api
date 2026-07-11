<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FlutterwaveGateway implements PaymentGatewayContract
{
    public function __construct(
        protected string $url,
        protected string $secretKey,
        protected ?string $webhookHash,
        protected string $redirectUrl,
    ) {}

    /**
     * @return array{authorization_url: ?string, gateway_reference: ?string}
     */
    public function initialize(Payment $payment): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->url}/payments", [
                'tx_ref' => $payment->reference,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'redirect_url' => $this->redirectUrl,
                'customer' => [
                    'email' => $payment->user->email,
                    'name' => $payment->user->name,
                ],
            ])
            ->throw();

        return [
            'authorization_url' => $response->json('data.link'),
            'gateway_reference' => $payment->reference,
        ];
    }

    public function verify(string $reference): PaymentVerificationResult
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->url}/transactions/verify_by_reference", ['tx_ref' => $reference])
            ->throw();

        $data = $response->json('data', []);

        return new PaymentVerificationResult(
            successful: ($data['status'] ?? null) === 'successful',
            amount: (float) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'NGN',
            gatewayReference: isset($data['id']) ? (string) $data['id'] : null,
            raw: $data,
        );
    }

    public function validateWebhookSignature(Request $request): bool
    {
        $signature = $request->header('verif-hash');

        if (! $signature || ! $this->webhookHash) {
            return false;
        }

        return hash_equals($this->webhookHash, $signature);
    }

    public function referenceFromWebhook(Request $request): ?string
    {
        return $request->input('data.tx_ref');
    }
}
