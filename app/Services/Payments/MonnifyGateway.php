<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Unlike Paystack/Flutterwave, Monnify's secret key doesn't authenticate API
 * calls directly — every operation needs a short-lived Bearer token fetched
 * from /auth/login first. No caching of that token: initialize/verify are
 * low-frequency calls (once or twice per booking), so the extra round trip
 * per call isn't worth the complexity of a token cache.
 *
 * @see https://developers.monnify.com/api/
 */
class MonnifyGateway implements PaymentGatewayContract
{
    public function __construct(
        protected string $url,
        protected string $apiKey,
        protected string $secretKey,
        protected string $contractCode,
        protected string $redirectUrl,
    ) {}

    /**
     * @return array{authorization_url: ?string, gateway_reference: ?string}
     */
    public function initialize(Payment $payment): array
    {
        // Monnify rejects init-transaction outright ("Duplicate payment
        // reference") if paymentReference was ever used before — even for a
        // prior attempt that expired/failed. A retried checkout (session
        // timed out, user backs out and tries again) reuses the same
        // Payment row, so a fresh suffix per attempt is required here.
        // transactionReference — Monnify's own always-unique id, returned
        // below as gateway_reference — is what verify() keys off afterwards,
        // not this value, so the suffix never needs to be reconstructed.
        $attemptReference = "{$payment->reference}_".Str::random(8);

        $response = Http::withToken($this->accessToken())
            ->post("{$this->url}/api/v1/merchant/transactions/init-transaction", [
                'amount' => (float) $payment->amount,
                'customerName' => $payment->user->name,
                'customerEmail' => $payment->user->email,
                'paymentReference' => $attemptReference,
                'paymentDescription' => 'CorpsLink seat booking',
                'currencyCode' => $payment->currency,
                'contractCode' => $this->contractCode,
                'redirectUrl' => $this->redirectUrl,
                'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER'],
            ])
            ->throw();

        return [
            'authorization_url' => $response->json('responseBody.checkoutUrl'),
            'gateway_reference' => $response->json('responseBody.transactionReference'),
        ];
    }

    /**
     * $reference here is Monnify's own transactionReference (see
     * ConfirmPaymentAction::verificationReference()), not our paymentReference
     * — the latter varies per attempt now, the former doesn't.
     */
    public function verify(string $reference): PaymentVerificationResult
    {
        try {
            $response = Http::withToken($this->accessToken())
                ->get("{$this->url}/api/v2/merchant/transactions/query", ['transactionReference' => $reference])
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

        $data = $response->json('responseBody', []);

        return new PaymentVerificationResult(
            successful: in_array($data['paymentStatus'] ?? null, ['PAID', 'OVERPAID'], true),
            amount: (float) ($data['amountPaid'] ?? 0),
            currency: $data['currencyCode'] ?? 'NGN',
            gatewayReference: $data['transactionReference'] ?? null,
            raw: $data,
        );
    }

    public function validateWebhookSignature(Request $request): bool
    {
        $signature = $request->header('monnify-signature');

        if (! $signature) {
            return false;
        }

        return hash_equals(
            hash_hmac('sha512', $request->getContent(), $this->secretKey),
            $signature,
        );
    }

    /**
     * eventData.paymentReference is our attempt-suffixed value
     * ("{payment->reference}_{random}") — strip the suffix so the webhook
     * controller's plain Payment::where('reference', ...) lookup still
     * matches. Our own reference format never contains an underscore.
     */
    public function referenceFromWebhook(Request $request): ?string
    {
        $paymentReference = $request->input('eventData.paymentReference');

        return $paymentReference ? explode('_', $paymentReference)[0] : null;
    }

    protected function accessToken(): string
    {
        $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
            ->post("{$this->url}/api/v1/auth/login")
            ->throw();

        return $response->json('responseBody.accessToken');
    }
}
