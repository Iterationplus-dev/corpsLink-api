<?php

namespace App\Actions\Account;

use App\Models\User;
use App\Services\AuditLogService;

class UpdateSecurityPreferencesAction
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function handle(User $user, bool $twoFactorEnabled): User
    {
        $user->update(['two_factor_enabled' => $twoFactorEnabled]);

        $this->auditLog->record('two_factor_toggled', $user, $user, ['enabled' => $twoFactorEnabled]);

        return $user;
    }
}
