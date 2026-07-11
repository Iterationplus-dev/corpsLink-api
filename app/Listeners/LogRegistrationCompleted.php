<?php

namespace App\Listeners;

use App\Events\RegistrationCompleted;
use App\Services\AuditLogService;

class LogRegistrationCompleted
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function handle(RegistrationCompleted $event): void
    {
        $this->auditLog->record('registration_completed', $event->user, $event->user);
    }
}
