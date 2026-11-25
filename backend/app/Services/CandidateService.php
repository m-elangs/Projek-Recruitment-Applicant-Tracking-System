<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CandidateService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    public function paginate(array $filters, User $viewer): LengthAwarePaginator
    {
        $query = Candidate::query()
            ->with(['skills', 'primaryCv'])
            ->withCount('applications');

        if ($viewer->isHiringManager()) {
            $query->whereHas('applications.job', fn ($q) => $q->where('hiring_manager_id', $viewer->id));
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['location'])) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }

        if (isset($filters['min_experience']) && $filters['min_experience'] !== '') {
            $query->where('years_of_experience', '>=', (float) $filters['min_experience']);
        }

        if (! empty($filters['skill'])) {
            $skill = $filters['skill'];
            $query->whereHas('skills', fn ($q) => $q->where('skill_name', 'like', '%'.$skill.'%'));
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('current_title', 'like', $term)
                    ->orWhere('current_company', 'like', $term)
                    ->orWhere('headline', 'like', $term);
            });
        }

        return $query->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Detect exact email conflicts and fuzzy phone/name duplicates.
     */
    public function detectDuplicates(
        ?string $email,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?int $ignoreCandidateId = null
    ): array {
        $exactEmailMatch = null;
        if (! blank($email)) {
            $exactQuery = Candidate::query()->where('email', strtolower(trim($email)));
            if ($ignoreCandidateId) {
                $exactQuery->where('id', '!=', $ignoreCandidateId);
            }
            $exactEmailMatch = $exactQuery->first(['id', 'first_name', 'last_name', 'email', 'phone', 'current_company']);
        }

        $potentialMatches = collect();

        $normalizedPhone = $phone ? preg_replace('/[^0-9+]/', '', $phone) : null;
        if ($normalizedPhone && strlen($normalizedPhone) >= 7) {
            $phoneMatches = Candidate::query()
                ->when($ignoreCandidateId, fn ($q) => $q->where('id', '!=', $ignoreCandidateId))
                ->whereNotNull('phone')
                ->where('phone', 'like', '%'.substr($normalizedPhone, -7).'%')
                ->limit(5)
                ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'current_company']);

            $potentialMatches = $potentialMatches->merge($phoneMatches);
        }

        if (! blank($firstName) && ! blank($lastName)) {
            $nameMatches = Candidate::query()
                ->when($ignoreCandidateId, fn ($q) => $q->where('id', '!=', $ignoreCandidateId))
                ->where('first_name', 'like', trim($firstName))
                ->where('last_name', 'like', trim($lastName))
                ->limit(5)
                ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'current_company']);

            $potentialMatches = $potentialMatches->merge($nameMatches);
        }

        return [
            'has_exact_email_duplicate' => $exactEmailMatch !== null,
            'exact_match' => $exactEmailMatch,
            'potential_duplicates' => $potentialMatches->unique('id')->values()->all(),
        ];
    }

    public function create(array $data, User $actor): Candidate
    {
        $duplicateCheck = $this->detectDuplicates(
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['first_name'] ?? null,
            $data['last_name'] ?? null
        );

        if ($duplicateCheck['has_exact_email_duplicate']) {
            throw new BusinessRuleException(
                'DUPLICATE_CANDIDATE_EMAIL',
                'A candidate profile with this email address already exists.',
                409,
                ['existing_candidate' => $duplicateCheck['exact_match']]
            );
        }

        return DB::transaction(function () use ($data, $actor) {
            $skills = $data['skills'] ?? null;
            $experiences = $data['experiences'] ?? null;
            $educations = $data['educations'] ?? null;
            unset($data['skills'], $data['experiences'], $data['educations']);

            $data['email'] = strtolower(trim($data['email']));
            $data['source'] = $data['source'] ?? 'direct_sourced';

            $candidate = Candidate::query()->create($data);

            $this->syncProfileCollections($candidate, $skills, $experiences, $educations);

            $this->auditLog->log(
                eventType: 'candidate.created',
                auditable: $candidate,
                description: "Candidate profile created for {$candidate->full_name} ({$candidate->email})",
                actor: $actor,
                newValues: ['email' => $candidate->email, 'source' => $candidate->source]
            );

            return $candidate->load(['skills', 'experiences', 'educations', 'documents']);
        });
    }

    public function update(Candidate $candidate, array $data, User $actor): Candidate
    {
        if (! empty($data['email'])) {
            $duplicateCheck = $this->detectDuplicates($data['email'], null, null, null, $candidate->id);
            if ($duplicateCheck['has_exact_email_duplicate']) {
                throw new BusinessRuleException(
                    'DUPLICATE_CANDIDATE_EMAIL',
                    'Another candidate profile is already using this email address.',
                    409,
                    ['existing_candidate' => $duplicateCheck['exact_match']]
                );
            }
            $data['email'] = strtolower(trim($data['email']));
        }

        return DB::transaction(function () use ($candidate, $data, $actor) {
            $skills = $data['skills'] ?? null;
            $experiences = $data['experiences'] ?? null;
            $educations = $data['educations'] ?? null;
            unset($data['skills'], $data['experiences'], $data['educations']);

            $old = $candidate->only(['first_name', 'last_name', 'email', 'phone', 'current_title']);
            $candidate->update($data);

            if ($candidate->user && (isset($data['first_name']) || isset($data['last_name']) || isset($data['phone']))) {
                $candidate->user->update([
                    'name' => $candidate->full_name,
                    'phone' => $candidate->phone,
                ]);
            }

            $this->syncProfileCollections($candidate, $skills, $experiences, $educations);

            $this->auditLog->log(
                eventType: 'candidate.updated',
                auditable: $candidate,
                description: "Candidate profile updated for {$candidate->full_name}",
                actor: $actor,
                oldValues: $old,
                newValues: $candidate->only(['first_name', 'last_name', 'email', 'phone', 'current_title'])
            );

            return $candidate->fresh(['skills', 'experiences', 'educations', 'documents', 'primaryCv']);
        });
    }

    private function syncProfileCollections(
        Candidate $candidate,
        ?array $skills,
        ?array $experiences,
        ?array $educations
    ): void {
        if (is_array($skills)) {
            $candidate->skills()->delete();
            $seen = [];
            foreach ($skills as $item) {
                $name = is_array($item) ? trim((string) ($item['skill_name'] ?? '')) : trim((string) $item);
                if ($name === '' || isset($seen[strtolower($name)])) {
                    continue;
                }
                $seen[strtolower($name)] = true;

                $candidate->skills()->create([
                    'skill_name' => $name,
                    'proficiency' => is_array($item) ? ($item['proficiency'] ?? 'intermediate') : 'intermediate',
                    'created_at' => now(),
                ]);
            }
        }

        if (is_array($experiences)) {
            $candidate->experiences()->delete();
            foreach ($experiences as $exp) {
                $candidate->experiences()->create([
                    'company_name' => $exp['company_name'],
                    'job_title' => $exp['job_title'],
                    'employment_type' => $exp['employment_type'] ?? null,
                    'location' => $exp['location'] ?? null,
                    'start_date' => $exp['start_date'],
                    'end_date' => ! empty($exp['is_current']) ? null : ($exp['end_date'] ?? null),
                    'is_current' => (bool) ($exp['is_current'] ?? false),
                    'description' => $exp['description'] ?? null,
                ]);
            }
        }

        if (is_array($educations)) {
            $candidate->educations()->delete();
            foreach ($educations as $edu) {
                $candidate->educations()->create([
                    'institution' => $edu['institution'],
                    'degree' => $edu['degree'],
                    'field_of_study' => $edu['field_of_study'],
                    'start_date' => $edu['start_date'],
                    'end_date' => $edu['end_date'] ?? null,
                    'grade' => $edu['grade'] ?? null,
                    'description' => $edu['description'] ?? null,
                ]);
            }
        }
    }
}
