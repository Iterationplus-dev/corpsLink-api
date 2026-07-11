<?php

namespace App\Http\Requests\Auth;

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartRegistrationRequest extends FormRequest
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
            'fullName' => ['required', 'string', 'max:255'],
            'acceptedTerms' => ['required', 'accepted'],
            'email' => [
                'required', 'string', 'email:rfc', 'max:255',
                Rule::unique(User::class, 'email'),
                Rule::unique(PendingRegistration::class, 'email'),
            ],
            'phone' => [
                'required', 'string', 'max:20', 'regex:/^\+?[0-9]{10,15}$/',
                Rule::unique(User::class, 'phone'),
                Rule::unique(PendingRegistration::class, 'phone'),
            ],
        ];
    }
}
