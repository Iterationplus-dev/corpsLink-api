<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amount = Money::fromNaira($this->amount);

        return [
            'id' => $this->id,
            'bookingId' => $this->relationLoaded('booking') ? $this->booking?->id : null,
            'gateway' => $this->gateway?->value,
            'reference' => $this->reference,
            'amountKobo' => $amount['kobo'],
            'amountDisplay' => $amount['display'],
            'currency' => $this->currency,
            'status' => $this->status->value,
            'failureReason' => $this->failureReason(),
            'paidAt' => $this->paid_at,
        ];
    }

    protected function failureReason(): ?string
    {
        if ($this->status !== PaymentStatus::Failed) {
            return null;
        }

        return is_array($this->gateway_response)
            ? ($this->gateway_response['message'] ?? 'Payment failed.')
            : 'Payment failed.';
    }
}
