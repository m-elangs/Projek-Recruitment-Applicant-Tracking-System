<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        if (! $user->hasPermission('applications.view')) {
            return false;
        }

        if ($user->isAdmin() || $user->isRecruiter()) {
            return true;
        }

        if ($user->isCandidate()) {
            return $application->candidate?->user_id === $user->id;
        }

        if ($user->isHiringManager()) {
            return $application->job?->hiring_manager_id === $user->id
                || $application->interviews()
                    ->whereHas('interviewerAssignments', fn ($q) => $q->where('user_id', $user->id))
                    ->exists();
        }

        return false;
    }

    public function transition(User $user, Application $application): bool
    {
        return ! $user->isCandidate() && $user->hasPermission('applications.transition');
    }

    public function withdraw(User $user, Application $application): bool
    {
        if ($user->isCandidate()) {
            return $user->hasPermission('applications.withdraw_own')
                && $application->candidate?->user_id === $user->id;
        }

        return $user->hasPermission('applications.transition');
    }

    public function note(User $user, Application $application): bool
    {
        if ($user->isCandidate() || ! $user->hasPermission('applications.note')) {
            return false;
        }

        if ($user->isAdmin() || $user->isRecruiter()) {
            return true;
        }

        return $application->job?->hiring_manager_id === $user->id;
    }

    public function recommend(User $user, Application $application): bool
    {
        if (! $user->hasPermission('applications.recommend')) {
            return false;
        }

        return $user->isAdmin() || $application->job?->hiring_manager_id === $user->id;
    }
}
