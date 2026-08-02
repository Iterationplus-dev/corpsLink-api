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
 * Opay's Cashier (international) API. Two different auth schemes apply
 * depending on sensitivity: /cashier/create (starting a checkout, low risk)
 * accepts the public key directly as a bearer token; /cashier/status
 * (reading a transaction's real status) and webhook verification require an
 * HMAC-SHA512 signature of the alphabetically-key-sorted JSON body, signed
 * with the secret key.
 *
 * @see https://documentation.opaycheckout.com/cashier-create
 * @see https://documentation.opaycheckout.com/query-payment-status
 * @see https://documentation.opaycheckout.com/api-signature
 */
class OpayGateway implements PaymentGatewayContract
{
    public function __construct(
        protected string $url,
        protected string $publicKey,
        protected string $secretKey,
        protected string $merchantId,
        protected string $country,
        protected string $callbackUrl,
        protected string $returnUrl,
    ) {}

    /**
     * @return array{authorization_url: ?string, gateway_reference: ?string}
     */
    public function initialize(Payment $payment): array
    {
        // Opay rejects cashier/create outright if `reference` was ever used
        // before — even for a prior attempt that expired/failed. A retried
        // checkout (session timed out, user backs out and tries again)
        // reuses the same Payment row, so a fresh suffix per attempt is
        // required here — same fix already applied to Paystack/Monnify (see
        // PaystackGateway::initialize()). Opay's response echoes back
        // exactly the reference we sent (unlike its own orderNo), so that
        // echoed value is what gets stored as gateway_reference.
        $attemptReference = "{$payment->reference}_".Str::random(8);

        $response = Http::withToken($this->publicKey)
            ->withHeaders(['MerchantId' => $this->merchantId])
            ->post("{$this->url}/api/v1/international/cashier/create", [
                'reference' => $attemptReference,
                'country' => $this->country,
                'amount' => [
                    'total' => (int) round(((float) $payment->amount) * 100), // kobo
                    'currency' => $payment->currency,
                ],
                'returnUrl' => $this->returnUrl,
                'callbackUrl' => $this->callbackUrl,
                'product' => [
                    'name' => 'Seat booking',
                    'description' => 'CorpsLink seat booking',
                ],
                'userInfo' => [
                    'userEmail' => $payment->user->email,
                    'userName' => $payment->user->name,
                ],
            ])
            ->throw();

        return [
            'authorization_url' => $response->json('data.cashierUrl'),
            'gateway_reference' => $response->json('data.reference'),
        ];
    }

    /**
     * $reference is either our own merchant reference (the suffixed
     * attempt reference from initialize(), stored as gateway_reference —
     * the hosted-checkout path) or, for a native-SDK charge that never
     * went through our own cashier/create call, Opay's own orderNo handed
     * back by the client (see ConfirmPaymentAction::verificationReference()).
     * cashier/status accepts either as an alternative lookup key; a value
     * that doesn't carry our own Payment::REFERENCE_PREFIX can't be one of
     * ours, so it must be the latter.
     */
    public function verify(string $reference): PaymentVerificationResult
    {
        $lookupKey = str_starts_with($reference, Payment::REFERENCE_PREFIX) ? 'reference' : 'orderNo';
        $body = [$lookupKey => $reference, 'country' => $this->country];

        try {
            $response = Http::withToken($this->sign($body))
                ->withHeaders(['MerchantId' => $this->merchantId])
                ->post("{$this->url}/api/v1/international/cashier/status", $body)
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
            successful: ($data['status'] ?? null) === 'SUCCESS',
            amount: ($data['amount']['total'] ?? 0) / 100,
            currency: $data['amount']['currency'] ?? 'NGN',
            gatewayReference: $data['orderNo'] ?? null,
            raw: $data,
        );
    }

    public function validateWebhookSignature(Request $request): bool
    {
        $signature = $request->input('sha512');
        $payload = $request->input('payload');

        if (! $signature || ! is_array($payload)) {
            return false;
        }

        return hash_equals($this->sign($payload), $signature);
    }

    /**
     * payload.reference here is our attempt-suffixed value
     * ("{payment->reference}_{random}") — strip the suffix so the webhook
     * controller's plain Payment::where('reference', ...) lookup still
     * matches. Our own reference format never contains an underscore.
     */
    public function referenceFromWebhook(Request $request): ?string
    {
        $reference = $request->input('payload.reference');

        return $reference ? explode('_', $reference)[0] : null;
    }

    /**
     * Opay's request/callback signature: JSON-encode the payload with its
     * keys (recursively) sorted alphabetically, then HMAC-SHA512 the result
     * with the secret key.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function sign(array $payload): string
    {
        $sorted = $this->sortKeysRecursively($payload);

        return hash_hmac('sha512', json_encode($sorted, JSON_UNESCAPED_SLASHES), $this->secretKey);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sortKeysRecursively(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sortKeysRecursively($value);
            }
        }

        ksort($data);

        return $data;
    }
}
