<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SaveRegistrationNextOfKinRequest extends FormRequest
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
            'emergencyContact' => ['required', 'array'],
            'emergencyContact.fullName' => ['required', 'string', 'max:255'],
            'emergencyContact.relationship' => ['required', 'string', 'max:100'],
            'emergencyContact.phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{10,15}$/'],
            'emergencyContact.alternatePhone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{10,15}$/'],
            'emergencyContact.address' => ['required', 'string', 'max:500'],
            'emergencyContact.applyToAllBookings' => ['sometimes', 'boolean'],
        ];
    }
}
