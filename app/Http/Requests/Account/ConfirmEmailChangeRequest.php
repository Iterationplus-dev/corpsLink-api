<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmEmailChangeRequest extends FormRequest
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
            'newEmail' => ['required', 'string', 'email:rfc', 'max:255'],
            'code' => ['required', 'digits:'.config('corpslink.otp.length')],
        ];
    }
}
