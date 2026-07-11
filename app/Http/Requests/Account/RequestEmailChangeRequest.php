<?php

namespace App\Http\Requests\Account;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestEmailChangeRequest extends FormRequest
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
            'newEmail' => [
                'required', 'string', 'email:rfc', 'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user()),
            ],
            'password' => ['required', 'string', 'current_password:sanctum'],
        ];
    }
}
