<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @mixin PersonalAccessToken
 */
class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            'id' => $this->id,
            'deviceName' => $this->name,
            'lastUsedAt' => $this->last_used_at,
            'createdAt' => $this->created_at,
            'current' => $user?->currentAccessToken()?->id === $this->id,
        ];
    }
}
