<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorVerifyRequest extends FormRequest
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
            'challengeToken' => ['required', 'uuid'],
            'code' => ['required', 'digits:'.config('corpslink.otp.length')],
            'deviceName' => ['nullable', 'string', 'max:255'],
        ];
    }
}
