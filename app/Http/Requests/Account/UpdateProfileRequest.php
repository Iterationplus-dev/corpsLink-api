<?php

namespace App\Http\Requests\Account;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'fullName' => ['sometimes', 'string', 'max:255'],
            'phone' => [
                'sometimes', 'string', 'max:20', 'regex:/^\+?[0-9]{10,15}$/',
                Rule::unique(User::class, 'phone')->ignore($this->user()),
            ],
            'stateCode' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
