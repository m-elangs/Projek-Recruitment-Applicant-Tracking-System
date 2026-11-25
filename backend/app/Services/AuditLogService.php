<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function log(
        string $eventType,
        Model $auditable,
        string $description,
        ?User $actor = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ActivityLog {
        $resolvedActor = $actor ?? auth()->user();

        return ActivityLog::query()->create([
            'actor_user_id' => $resolvedActor?->id,
            'actor_role' => $resolvedActor?->roleSlug() ?: null,
            'event_type' => $eventType,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent() ? substr((string) request()->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }
}
