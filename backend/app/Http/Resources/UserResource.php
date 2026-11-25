<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = $this->resource;
        $user->loadMissing(['role.permissions', 'department']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'is_active' => $user->is_active,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'slug' => $user->role->slug,
                'name' => $user->role->name,
            ] : null,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'code' => $user->department->code,
                'name' => $user->department->name,
            ] : null,
            'permissions' => $user->permissionSlugs(),
            'redirect_to' => $user->defaultRedirectPath(),
            'candidate_id' => $user->isCandidate() ? $user->candidate?->id : null,
            'unread_notifications_count' => $user->unreadNotifications()->count(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
