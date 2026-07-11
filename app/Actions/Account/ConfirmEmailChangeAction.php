<?php

namespace App\Actions\Account;

use App\Enums\VerificationPurpose;
use App\Exceptions\InvalidVerificationCodeException;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\VerificationCodeService;

class ConfirmEmailChangeAction
{
    public function __construct(
        protected VerificationCodeService $codes,
        protected AuditLogService $auditLog,
    ) {}

    public function handle(User $user, string $code): User
    {
        $record = $this->codes->verify($user->email, VerificationPurpose::EmailChange, $code);

        if (! $record->new_email) {
            throw InvalidVerificationCodeException::notFound();
        }

        $oldEmail = $user->email;

        $user->forceFill(['email' => $record->new_email])->save();

        $this->auditLog->record('email_changed', $user, $user, ['from' => $oldEmail, 'to' => $record->new_email]);

        return $user;
    }
}
