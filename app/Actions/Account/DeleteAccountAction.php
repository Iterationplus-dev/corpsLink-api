<?php

namespace App\Actions\Account;

use App\Contracts\ImageStorageContract;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteAccountAction
{
    public function __construct(
        protected ImageStorageContract $imageStorage,
        protected AuditLogService $auditLog,
    ) {}

    public function handle(User $user): void
    {
        // Cloudinary cleanup happens outside the transaction — an external
        // HTTP call shouldn't hold a DB transaction open, and a Cloudinary
        // hiccup must never block account deletion.
        if ($user->avatar_path) {
            $this->deleteAvatarQuietly($user->avatar_path);
        }

        DB::transaction(function () use ($user) {
            $this->auditLog->record('account_deletion_requested', $user, $user);

            $user->tokens()->delete();

            $user->delete();
        });
    }

    protected function deleteAvatarQuietly(string $publicId): void
    {
        try {
            $this->imageStorage->delete($publicId);
        } catch (Throwable $e) {
            Log::warning('Failed to delete Cloudinary avatar during account deletion.', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
