<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records security- and account-relevant events for later review.
 */
class AuditLogService
{
    public function __construct(protected Request $request) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $event, ?User $user = null, ?Model $subject = null, array $metadata = []): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
