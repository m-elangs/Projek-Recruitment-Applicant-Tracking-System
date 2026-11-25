<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('jobs.view_internal');
    }

    public function view(User $user, Job $job): bool
    {
        if (! $user->hasPermission('jobs.view_internal')) {
            return false;
        }

        if ($user->isAdmin() || $user->isRecruiter()) {
            return true;
        }

        return $job->hiring_manager_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('jobs.manage');
    }

    public function update(User $user, Job $job): bool
    {
        return $user->hasPermission('jobs.manage');
    }

    public function delete(User $user, Job $job): bool
    {
        return $user->hasPermission('jobs.delete_draft') && $job->status === 'draft';
    }
}
