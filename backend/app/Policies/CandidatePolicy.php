<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\User;

class CandidatePolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isCandidate() && $user->hasPermission('candidates.view');
    }

    public function view(User $user, Candidate $candidate): bool
    {
        if ($user->isAdmin() || $user->isRecruiter()) {
            return $user->hasPermission('candidates.view');
        }

        if ($user->isCandidate()) {
            return $candidate->user_id === $user->id;
        }

        if ($user->isHiringManager() && $user->hasPermission('candidates.view')) {
            return $candidate->applications()
                ->whereHas('job', fn ($q) => $q->where('hiring_manager_id', $user->id))
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return ! $user->isCandidate() && $user->hasPermission('candidates.manage');
    }

    public function update(User $user, Candidate $candidate): bool
    {
        if ($user->isCandidate()) {
            return $candidate->user_id === $user->id && $user->hasPermission('profile.manage_own');
        }

        return $user->hasPermission('candidates.manage');
    }
}
