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
            // Ignored for Paystack/Flutterwave. For Monnify/Opay, a
            // native-SDK charge (bypassing our own initialize() checkout)
            // produces a reference our backend never otherwise learns —
            // see ConfirmPaymentAction::handle(). Harmless to send for the
            // hosted-checkout flow too; it's only trusted when it differs
            // from the Payment's own reference.
            'reference' => ['sometimes', 'string'],
        ];
    }
}
