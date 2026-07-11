<?php

namespace App\Actions\Account;

use App\Contracts\ImageStorageContract;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class UploadAvatarAction
{
    public function __construct(
        protected ImageStorageContract $imageStorage,
        protected AuditLogService $auditLog,
    ) {}

    public function handle(User $user, UploadedFile $file): User
    {
        $oldPublicId = $user->avatar_path;

        $folder = config('corpslink.avatar.folder').'/'.$user->id;
        $uploaded = $this->imageStorage->upload($file, $folder);

        $user->update(['avatar_path' => $uploaded['public_id']]);

        // Only clean up the old asset once the new one is safely stored —
        // a failed upload should never destroy the existing avatar. A
        // failed cleanup is logged, not fatal to the request.
        if ($oldPublicId) {
            $this->deleteQuietly($oldPublicId);
        }

        $this->auditLog->record('avatar_updated', $user, $user);

        return $user;
    }

    protected function deleteQuietly(string $publicId): void
    {
        try {
            $this->imageStorage->delete($publicId);
        } catch (Throwable $e) {
            Log::warning('Failed to delete old Cloudinary avatar.', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
