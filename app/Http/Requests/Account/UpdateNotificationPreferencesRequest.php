<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
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
            'bookingUpdates' => ['sometimes', 'boolean'],
            'seatHoldAlerts' => ['sometimes', 'boolean'],
            'departureReminders' => ['sometimes', 'boolean'],
            'tripChanges' => ['sometimes', 'boolean'],
            'tipsAnnouncements' => ['sometimes', 'boolean'],
        ];
    }
}
