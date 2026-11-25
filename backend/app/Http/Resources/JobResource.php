<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Job $job */
        $job = $this->resource;
        $viewer = $request->user();
        $isInternal = $viewer && ! $viewer->isCandidate();

        $showSalary = $isInternal || $job->is_salary_visible;

        return [
            'id' => $job->id,
            'code' => $job->code,
            'slug' => $job->slug,
            'requisition_id' => $isInternal ? $job->requisition_id : null,
            'title' => $job->title,
            'department' => $job->relationLoaded('department') && $job->department ? [
                'id' => $job->department->id,
                'code' => $job->department->code,
                'name' => $job->department->name,
            ] : null,
            'recruiter' => $isInternal && $job->relationLoaded('recruiter') && $job->recruiter ? [
                'id' => $job->recruiter->id,
                'name' => $job->recruiter->name,
                'email' => $job->recruiter->email,
            ] : null,
            'hiring_manager' => $isInternal && $job->relationLoaded('hiringManager') && $job->hiringManager ? [
                'id' => $job->hiringManager->id,
                'name' => $job->hiringManager->name,
                'email' => $job->hiringManager->email,
            ] : null,
            'employment_type' => $job->employment_type,
            'work_arrangement' => $job->work_arrangement,
            'location' => $job->location,
            'experience_level' => $job->experience_level,
            'description' => $job->description,
            'requirements' => $job->requirements,
            'responsibilities' => $job->responsibilities,
            'benefits' => $job->benefits,
            'salary_min' => $showSalary ? ($job->salary_min !== null ? (float) $job->salary_min : null) : null,
            'salary_max' => $showSalary ? ($job->salary_max !== null ? (float) $job->salary_max : null) : null,
            'salary_currency' => $job->salary_currency,
            'is_salary_visible' => $job->is_salary_visible,
            'headcount' => $job->headcount,
            'hired_count' => $isInternal ? $job->hired_count : null,
            'status' => $job->status,
            'is_accepting_applications' => $job->isAcceptingApplications(),
            'skills' => $job->relationLoaded('skills') ? $job->skills->map(fn ($s) => [
                'id' => $s->id,
                'skill_name' => $s->skill_name,
                'is_required' => $s->is_required,
            ])->values() : [],
            'applications_count' => $job->applications_count ?? null,
            'active_applications_count' => $job->active_applications_count ?? null,
            'published_at' => $job->published_at?->toIso8601String(),
            'closes_at' => $job->closes_at?->toIso8601String(),
            'closed_at' => $job->closed_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
        ];
    }
}
