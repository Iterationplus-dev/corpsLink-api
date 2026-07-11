<?php

namespace App\Actions\Account;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RevokeSessionAction
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function handle(User $user, int $tokenId): void
    {
        $token = $user->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            throw new ModelNotFoundException('Session not found.');
        }

        $token->delete();

        $this->auditLog->record('session_revoked', $user, $user, ['token_id' => $tokenId]);
    }
}
