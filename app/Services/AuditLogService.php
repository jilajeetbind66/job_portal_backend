<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Create an audit log entry.
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $meta = null,
        ?int $partnerId = null,
    ): AuditLog {
        $actor = Auth::user();

        if ($partnerId === null && $actor?->partner_id) {
            $partnerId = $actor->partner_id;
        }

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'partner_id' => $partnerId,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}