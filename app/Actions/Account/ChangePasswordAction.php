<?php

namespace App\Actions\Account;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;

class ChangePasswordAction
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function handle(User $user, string $newPassword): User
    {
        $user->forceFill(['password' => Hash::make($newPassword)])->save();

        $currentTokenId = $user->currentAccessToken()?->id;

        $user->tokens()->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))->delete();

        $user->notify(new PasswordChangedNotification);

        $this->auditLog->record('password_changed', $user, $user);

        return $user;
    }
}
