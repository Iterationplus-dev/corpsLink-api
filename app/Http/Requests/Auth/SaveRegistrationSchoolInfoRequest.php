<?php

namespace App\Http\Requests\Auth;

use App\Models\Institution;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRegistrationSchoolInfoRequest extends FormRequest
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
            'institutionId' => ['required', 'integer', Rule::exists(Institution::class, 'id')],
            'callUpNumber' => [
                'required', 'string', 'max:50',
                Rule::unique(User::class, 'call_up_number')->where('institution_id', $this->input('institutionId')),
                Rule::unique(PendingRegistration::class, 'call_up_number')
                    ->where('institution_id', $this->input('institutionId'))
                    ->where(fn ($query) => $query->where('registration_token', '!=', $this->route('registrationId'))),
            ],
            'stateCode' => ['nullable', 'string', 'max:20'],
            'batch' => ['required', 'string', 'max:10'],
            'stream' => ['required', 'string', 'max:10'],
        ];
    }
}
