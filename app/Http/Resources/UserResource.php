<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->name,
            'email' => $this->email,
            'emailVerified' => $this->email_verified_at !== null,
            'phone' => $this->phone,
            'avatarUrl' => $this->avatarUrl(),
            'avatarInitials' => $this->avatarInitials(),
            'institution' => InstitutionResource::make($this->whenLoaded('institution')),
            'callUpNumber' => $this->call_up_number,
            'stateCode' => $this->state_code,
            'batch' => $this->batch,
            'stream' => $this->stream,
            'twoFactorEnabled' => $this->two_factor_enabled,
            'notificationPreferences' => $this->notification_preferences,
            'emergencyContact' => NextOfKinResource::make($this->whenLoaded('nextOfKin')),
            'lastLoginAt' => $this->last_login_at,
            'createdAt' => $this->created_at,
        ];
    }

    protected function avatarInitials(): string
    {
        $words = collect(explode(' ', trim((string) $this->name)))->filter();

        return $words->take(2)->map(fn ($word) => Str::upper($word[0]))->implode('');
    }
}
