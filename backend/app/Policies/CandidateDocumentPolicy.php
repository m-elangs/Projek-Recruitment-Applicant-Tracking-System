<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\User;

class CandidateDocumentPolicy
{
    public function view(User $user, CandidateDocument $document): bool
    {
        if (! $user->hasPermission('documents.view')) {
            return false;
        }

        if ($user->isAdmin() || $user->isRecruiter()) {
            return true;
        }

        $candidate = $document->candidate;
        if (! $candidate) {
            return false;
        }

        if ($user->isCandidate()) {
            return $candidate->user_id === $user->id;
        }

        if ($user->isHiringManager()) {
            return $candidate->applications()
                ->whereHas('job', fn ($q) => $q->where('hiring_manager_id', $user->id))
                ->exists();
        }

        return false;
    }

    public function upload(User $user, Candidate $candidate): bool
    {
        if (! $user->hasPermission('documents.upload')) {
            return false;
        }

        if ($user->isAdmin() || $user->isRecruiter()) {
            return true;
        }

        return $user->isCandidate() && $candidate->user_id === $user->id;
    }

    public function delete(User $user, CandidateDocument $document): bool
    {
        if (! $user->hasPermission('documents.upload')) {
            return false;
        }

        if ($user->isAdmin() || $user->isRecruiter()) {
            return true;
        }

        return $user->isCandidate() && $document->candidate?->user_id === $user->id;
    }
}
