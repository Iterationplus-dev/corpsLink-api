<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Actions\Payments\ConfirmPaymentAction;
use App\Actions\Payments\InitializePaymentAction;
use App\Enums\PaymentGateway;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\InitializePaymentRequest;
use App\Http\Requests\Payments\VerifyPaymentRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function show(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        return $this->success(PaymentResource::make($payment->loadMissing('booking')));
    }

    public function initialize(InitializePaymentRequest $request, Payment $payment, InitializePaymentAction $action): JsonResponse
    {
        $this->authorize('view', $payment);

        $gateway = PaymentGateway::from($request->validated('gateway'));

        $result = $action->handle($payment, $gateway);

        return $this->success([
            'authorizationUrl' => $result['authorization_url'],
            'reference' => $result['payment']->reference,
        ]);
    }

    public function verify(VerifyPaymentRequest $request, Payment $payment, ConfirmPaymentAction $action): JsonResponse
    {
        $this->authorize('view', $payment);

        $booking = $action->handle($payment);

        return $this->success(BookingResource::make($booking));
    }

    /**
     * Public endpoint (no auth:sanctum — gateways can't authenticate as a
     * user). Its own security gate is the per-gateway signature check.
     */
    public function webhook(
        Request $request,
        string $gateway,
        PaymentGatewayResolver $resolver,
        ConfirmPaymentAction $action,
    ): JsonResponse {
        $gatewayEnum = PaymentGateway::tryFrom($gateway);

        if (! $gatewayEnum) {
            throw InvalidWebhookSignatureException::make();
        }

        $service = $resolver->resolve($gatewayEnum);

        if (! $service->validateWebhookSignature($request)) {
            throw InvalidWebhookSignatureException::make();
        }

        $reference = $service->referenceFromWebhook($request);
        $payment = $reference ? Payment::query()->where('reference', $reference)->first() : null;

        if ($payment) {
            $action->handle($payment);
        }

        return $this->success(['ok' => true]);
    }
}
