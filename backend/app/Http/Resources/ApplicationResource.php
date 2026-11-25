<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Application $app */
        $app = $this->resource;
        $viewer = $request->user();
        $isInternal = $viewer && ! $viewer->isCandidate();

        return [
            'id' => $app->id,
            'application_number' => $app->application_number,
            'job_id' => $app->job_id,
            'candidate_id' => $app->candidate_id,
            'source' => $app->source,
            'cover_letter' => $app->cover_letter,
            'expected_salary' => $app->expected_salary !== null ? (float) $app->expected_salary : null,
            'available_from' => $app->available_from?->format('Y-m-d'),
            'is_active' => $app->is_active,
            'rejection_reason' => $app->rejection_reason,
            'withdrawal_reason' => $app->withdrawal_reason,
            'hiring_manager_recommendation' => $isInternal ? $app->hiring_manager_recommendation : null,
            'hiring_manager_notes' => $isInternal ? $app->hiring_manager_notes : null,
            'applied_at' => $app->applied_at?->toIso8601String(),
            'stage_updated_at' => $app->stage_updated_at?->toIso8601String(),
            'hired_at' => $app->hired_at?->toIso8601String(),
            'rejected_at' => $app->rejected_at?->toIso8601String(),
            'withdrawn_at' => $app->withdrawn_at?->toIso8601String(),
            'current_stage' => $app->relationLoaded('currentStage') && $app->currentStage ? [
                'id' => $app->currentStage->id,
                'slug' => $app->currentStage->slug,
                'name' => $app->currentStage->name,
                'display_order' => $app->currentStage->display_order,
                'is_terminal' => $app->currentStage->is_terminal,
            ] : null,
            'job' => $app->relationLoaded('job') && $app->job ? [
                'id' => $app->job->id,
                'code' => $app->job->code,
                'slug' => $app->job->slug ?? null,
                'title' => $app->job->title,
                'location' => $app->job->location,
                'employment_type' => $app->job->employment_type,
                'work_arrangement' => $app->job->work_arrangement ?? null,
                'department' => $app->job->relationLoaded('department') && $app->job->department ? [
                    'id' => $app->job->department->id,
                    'name' => $app->job->department->name,
                ] : null,
            ] : null,
            'candidate' => $app->relationLoaded('candidate') && $app->candidate ? [
                'id' => $app->candidate->id,
                'full_name' => $app->candidate->full_name,
                'first_name' => $app->candidate->first_name,
                'last_name' => $app->candidate->last_name,
                'email' => $app->candidate->email,
                'phone' => $app->candidate->phone,
                'location' => $app->candidate->location,
                'current_title' => $app->candidate->current_title,
                'current_company' => $app->candidate->current_company,
                'years_of_experience' => $app->candidate->years_of_experience,
                'skills' => $app->candidate->relationLoaded('skills') ? $app->candidate->skills : [],
                'experiences' => $app->candidate->relationLoaded('experiences') ? $app->candidate->experiences : [],
                'educations' => $app->candidate->relationLoaded('educations') ? $app->candidate->educations : [],
            ] : null,
            'cv_document' => $app->relationLoaded('cvDocument') && $app->cvDocument ? [
                'id' => $app->cvDocument->id,
                'original_name' => $app->cvDocument->original_name,
                'mime_type' => $app->cvDocument->mime_type,
                'file_size_bytes' => $app->cvDocument->file_size_bytes,
            ] : null,
            'histories' => $app->relationLoaded('histories') ? $app->histories->map(fn ($h) => [
                'id' => $h->id,
                'from_stage' => $h->fromStage ? ['slug' => $h->fromStage->slug, 'name' => $h->fromStage->name] : null,
                'to_stage' => $h->toStage ? ['slug' => $h->toStage->slug, 'name' => $h->toStage->name] : null,
                'actor' => $isInternal && $h->actor ? ['id' => $h->actor->id, 'name' => $h->actor->name] : null,
                'reason' => $h->reason,
                'notes' => $isInternal ? $h->notes : null,
                'time_in_previous_stage_seconds' => $h->time_in_previous_stage_seconds,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values() : [],
            'interviews' => $app->relationLoaded('interviews') ? $app->interviews->map(fn ($iv) => [
                'id' => $iv->id,
                'title' => $iv->title,
                'round_number' => $iv->round_number,
                'interview_type' => $iv->interview_type,
                'mode' => $iv->mode,
                'scheduled_at' => $iv->scheduled_at?->toIso8601String(),
                'duration_minutes' => $iv->duration_minutes,
                'timezone' => $iv->timezone,
                'meeting_url' => $iv->meeting_url,
                'location' => $iv->location,
                'candidate_instructions' => $iv->candidate_instructions,
                'status' => $iv->status,
                'panel_users' => $iv->relationLoaded('panelUsers') ? $iv->panelUsers->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'job_title' => $u->job_title,
                    'is_lead' => (bool) $u->pivot->is_lead,
                ])->values() : [],
                'evaluations' => $isInternal && $iv->relationLoaded('evaluations') ? $iv->evaluations : [],
            ])->values() : [],
            'offers' => $app->relationLoaded('offers') ? $app->offers
                ->filter(fn ($o) => $isInternal || in_array($o->status, ['sent', 'accepted', 'declined', 'expired'], true))
                ->values() : [],
            'notes' => $isInternal && $app->relationLoaded('notes') ? $app->notes->map(fn ($n) => [
                'id' => $n->id,
                'body' => $n->body,
                'author' => $n->author ? ['id' => $n->author->id, 'name' => $n->author->name, 'job_title' => $n->author->job_title] : null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values() : [],
        ];
    }
}
