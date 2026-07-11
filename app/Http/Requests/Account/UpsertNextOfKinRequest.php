<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpsertNextOfKinRequest extends FormRequest
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
            'relationship' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{10,15}$/'],
            'alternatePhone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{10,15}$/'],
            'address' => ['required', 'string', 'max:500'],
            'applyToAllBookings' => ['sometimes', 'boolean'],
        ];
    }
}
