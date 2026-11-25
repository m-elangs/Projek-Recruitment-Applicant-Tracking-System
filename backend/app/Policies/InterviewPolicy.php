<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\User;

class InterviewPolicy
{
    public function view(User $user, Interview $interview): bool
    {
        if (! $user->hasPermission('interviews.view')) {
            return false;
        }

        if ($user->isAdmin() || $user->isRecruiter()) {
            return true;
        }

        if ($user->isCandidate()) {
            return $interview->application?->candidate?->user_id === $user->id;
        }

        return $interview->application?->job?->hiring_manager_id === $user->id
            || $interview->interviewerAssignments()->where('user_id', $user->id)->exists();
    }

    public function manage(User $user): bool
    {
        return ! $user->isCandidate() && $user->hasPermission('interviews.manage');
    }

    public function evaluate(User $user, Interview $interview): bool
    {
        if ($user->isCandidate() || ! $user->hasPermission('evaluations.submit')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $interview->interviewerAssignments()->where('user_id', $user->id)->exists();
    }
}
