<?php

namespace App\Policies;

use App\Models\JobOffer;
use App\Models\User;

class JobOfferPolicy
{
    public function view(User $user, JobOffer $offer): bool
    {
        if (! $user->hasPermission('offers.view')) {
            return false;
        }

        if ($user->isAdmin() || $user->isRecruiter()) {
            return true;
        }

        if ($user->isCandidate()) {
            return $offer->application?->candidate?->user_id === $user->id
                && in_array($offer->status, ['sent', 'accepted', 'declined', 'expired'], true);
        }

        return $offer->application?->job?->hiring_manager_id === $user->id;
    }

    public function manage(User $user): bool
    {
        return ! $user->isCandidate() && $user->hasPermission('offers.manage');
    }

    public function approve(User $user, JobOffer $offer): bool
    {
        if (! $user->hasPermission('offers.approve')) {
            return false;
        }

        return $user->isAdmin() || $offer->application?->job?->hiring_manager_id === $user->id;
    }

    public function respond(User $user, JobOffer $offer): bool
    {
        return $user->isCandidate()
            && $user->hasPermission('offers.respond')
            && $offer->application?->candidate?->user_id === $user->id;
    }
}
