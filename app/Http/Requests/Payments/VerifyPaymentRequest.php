<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Accepted for contract parity with the frontend, but not
            // strictly needed — the Payment is already resolved via the
            // route-bound {payment} id, which is what's actually verified.
            'reference' => ['sometimes', 'string'],
        ];
    }
}
