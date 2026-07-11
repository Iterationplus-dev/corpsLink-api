<?php

namespace App\Http\Requests\Auth;

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeRegistrationEmailRequest extends FormRequest
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
            'registration_token' => ['required', 'uuid'],
            'email' => [
                'required', 'string', 'email:rfc', 'max:255',
                Rule::unique(User::class, 'email'),
                Rule::unique(PendingRegistration::class, 'email')
                    ->where(fn ($query) => $query->where('registration_token', '!=', $this->string('registration_token'))),
            ],
        ];
    }
}
