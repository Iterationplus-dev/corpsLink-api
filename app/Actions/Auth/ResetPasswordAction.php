<?php

namespace App\Actions\Auth;

use App\Enums\VerificationPurpose;
use App\Exceptions\InvalidVerificationCodeException;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Services\VerificationCodeService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;

class ResetPasswordAction
{
    public function __construct(protected VerificationCodeService $codes) {}

    public function handle(string $email, string $code, string $newPassword): User
    {
        $this->codes->verify($email, VerificationPurpose::PasswordReset, $code);

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw InvalidVerificationCodeException::notFound();
        }

        $user->forceFill(['password' => Hash::make($newPassword)])->save();

        $user->tokens()->delete();

        event(new PasswordReset($user));

        $user->notify(new PasswordChangedNotification);

        return $user;
    }
}
