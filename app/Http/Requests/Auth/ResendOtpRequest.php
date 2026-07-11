<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResendOtpRequest extends FormRequest
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
            'context' => ['required', Rule::in(['register', 'reset_password', 'change_email'])],
            'registrationId' => ['required_if:context,register', 'uuid'],
            'email' => ['required_if:context,reset_password,change_email', 'email'],
        ];
    }
}
