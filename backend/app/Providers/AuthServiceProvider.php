<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobOffer;
use App\Policies\ApplicationPolicy;
use App\Policies\CandidateDocumentPolicy;
use App\Policies\CandidatePolicy;
use App\Policies\InterviewPolicy;
use App\Policies\JobOfferPolicy;
use App\Policies\JobPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Job::class => JobPolicy::class,
        Candidate::class => CandidatePolicy::class,
        CandidateDocument::class => CandidateDocumentPolicy::class,
        Application::class => ApplicationPolicy::class,
        Interview::class => InterviewPolicy::class,
        JobOffer::class => JobOfferPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
