<?php

namespace App\Actions\Account;

use App\Models\User;
use App\Services\AuditLogService;

class RevokeAllSessionsAction
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function handle(User $user, bool $keepCurrentSession = false): void
    {
        $currentTokenId = $keepCurrentSession ? $user->currentAccessToken()?->id : null;

        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        $this->auditLog->record('all_sessions_revoked', $user, $user, ['kept_current' => $keepCurrentSession]);
    }
}
