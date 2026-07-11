<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitializePaymentRequest extends FormRequest
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
            'gateway' => ['required', Rule::enum(PaymentGateway::class)],
        ];
    }
}
