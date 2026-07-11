<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\PasswordReset;

class LogPasswordReset
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function handle(PasswordReset $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $this->auditLog->record('password_reset', $user, $user);
    }
}
