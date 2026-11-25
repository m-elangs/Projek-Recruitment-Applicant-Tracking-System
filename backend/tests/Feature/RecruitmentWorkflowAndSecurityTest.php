<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Job;
use App\Models\User;
use Database\Seeders\CompanySettingSeeder;
use Database\Seeders\RecruitmentStageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentWorkflowAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Department $engDept;
    private User $admin;
    private User $recruiter;
    private User $hiringManager;
    private User $otherHiringManager;
    private User $candidateUser;
    private Candidate $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->seed([
            RolePermissionSeeder::class,
            RecruitmentStageSeeder::class,
            CompanySettingSeeder::class,
        ]);

        $this->engDept = Department::query()->create([
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->withRole(RoleSlug::SYSTEM_ADMIN)->create();
        $this->recruiter = User::factory()->withRole(RoleSlug::RECRUITER)->create();
        $this->hiringManager = User::factory()->withRole(RoleSlug::HIRING_MANAGER)->create([
            'department_id' => $this->engDept->id,
        ]);
        $this->otherHiringManager = User::factory()->withRole(RoleSlug::HIRING_MANAGER)->create();
        $this->candidateUser = User::factory()->withRole(RoleSlug::CANDIDATE)->create([
            'email' => 'applicant@example.com',
        ]);
        $this->candidate = Candidate::query()->create([
            'user_id' => $this->candidateUser->id,
            'first_name' => 'Rina',
            'last_name' => 'Putri',
            'email' => 'applicant@example.com',
            'source' => 'careers_portal',
        ]);
    }

    public function test_job_lifecycle_and_draft_only_deletion_rules(): void
    {
        $createRes = $this->actingAs($this->recruiter, 'sanctum')
            ->postJson('/api/v1/jobs', [
                'department_id' => $this->engDept->id,
                'hiring_manager_id' => $this->hiringManager->id,
                'title' => 'Senior Backend Engineer',
                'employment_type' => 'full_time',
                'work_arrangement' => 'hybrid',
                'location' => 'Jakarta',
                'description' => 'Design and build scalable recruitment backend APIs.',
                'requirements' => '5+ years PHP, Laravel, and MySQL experience.',
                'salary_min' => 80000,
                'salary_max' => 110000,
                'skills' => ['Laravel', 'MySQL'],
            ])
            ->assertCreated();

        $jobId = $createRes->json('data.id');
        $this->assertSame('draft', $createRes->json('data.status'));

        // Publish job -> deleting published job must fail with 422 ONLY_DRAFT_DELETABLE
        $this->actingAs($this->recruiter, 'sanctum')
            ->patchJson("/api/v1/jobs/{$jobId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->actingAs($this->recruiter, 'sanctum')
            ->deleteJson("/api/v1/jobs/{$jobId}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ONLY_DRAFT_DELETABLE');

        // Draft job can be deleted cleanly
        $draftJob = Job::query()->create([
            'code' => 'JOB-DRAFT-99',
            'slug' => 'draft-job-99',
            'department_id' => $this->engDept->id,
            'recruiter_id' => $this->recruiter->id,
            'hiring_manager_id' => $this->hiringManager->id,
            'title' => 'Temporary Draft Vacancy',
            'employment_type' => 'full_time',
            'work_arrangement' => 'remote',
            'location' => 'Remote',
            'description' => 'Draft description long enough for validation.',
            'requirements' => 'Draft requirements.',
            'status' => 'draft',
        ]);

        $this->actingAs($this->recruiter, 'sanctum')
            ->deleteJson("/api/v1/jobs/{$draftJob->id}")
            ->assertOk();

        $this->assertSoftDeleted('jobs', ['id' => $draftJob->id]);
    }

    public function test_closed_or_draft_jobs_reject_applications_and_duplicate_active_applications_are_blocked(): void
    {
        // Upload primary CV for candidate
        $this->actingAs($this->candidateUser, 'sanctum')
            ->postJson('/api/v1/portal/documents', [
                'file' => UploadedFile::fake()->create('rina_cv.pdf', 120, 'application/pdf'),
                'document_type' => 'cv',
                'is_primary_cv' => true,
            ])
            ->assertCreated();

        $draftJob = Job::query()->create([
            'code' => 'JOB-2026-0100',
            'slug' => 'draft-engineer-0100',
            'department_id' => $this->engDept->id,
            'recruiter_id' => $this->recruiter->id,
            'hiring_manager_id' => $this->hiringManager->id,
            'title' => 'Unpublished Engineer',
            'employment_type' => 'full_time',
            'work_arrangement' => 'onsite',
            'location' => 'Jakarta',
            'description' => 'Unpublished job description text.',
            'requirements' => 'Requirements text.',
            'status' => 'draft',
        ]);

        // Applying to draft job -> 422
        $this->actingAs($this->candidateUser, 'sanctum')
            ->postJson('/api/v1/portal/applications', ['job_id' => $draftJob->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'JOB_NOT_ACCEPTING_APPLICATIONS');

        // Publish job -> First application succeeds, second active application fails with 409
        $draftJob->update(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->candidateUser, 'sanctum')
            ->postJson('/api/v1/portal/applications', ['job_id' => $draftJob->id])
            ->assertCreated();

        $this->actingAs($this->candidateUser, 'sanctum')
            ->postJson('/api/v1/portal/applications', ['job_id' => $draftJob->id])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DUPLICATE_ACTIVE_APPLICATION');
    }

    public function test_private_document_and_scoping_prevent_unauthorized_access(): void
    {
        $uploadRes = $this->actingAs($this->candidateUser, 'sanctum')
            ->postJson('/api/v1/portal/documents', [
                'file' => UploadedFile::fake()->create('private_cv.pdf', 100, 'application/pdf'),
                'document_type' => 'cv',
            ])
            ->assertCreated();

        $docId = $uploadRes->json('data.id');

        // Another candidate cannot download Rina's CV
        $otherCandidateUser = User::factory()->withRole(RoleSlug::CANDIDATE)->create(['email' => 'other@example.com']);
        Candidate::query()->create([
            'user_id' => $otherCandidateUser->id,
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => 'other@example.com',
        ]);

        $this->actingAs($otherCandidateUser, 'sanctum')
            ->get("/api/v1/documents/{$docId}/download")
            ->assertForbidden();

        // Unassigned Hiring Manager cannot download Rina's CV
        $this->actingAs($this->otherHiringManager, 'sanctum')
            ->get("/api/v1/documents/{$docId}/download")
            ->assertForbidden();

        // Owner candidate & Recruiter CAN download Rina's CV
        $this->actingAs($this->candidateUser, 'sanctum')
            ->get("/api/v1/documents/{$docId}/download")
            ->assertOk();

        $this->actingAs($this->recruiter, 'sanctum')
            ->get("/api/v1/documents/{$docId}/download")
            ->assertOk();
    }

    public function test_complete_end_to_end_hiring_workflow_from_application_to_hired(): void
    {
        // 1. Candidate uploads CV & applies to published job
        $this->actingAs($this->candidateUser, 'sanctum')
            ->postJson('/api/v1/portal/documents', [
                'file' => UploadedFile::fake()->create('cv.pdf', 150, 'application/pdf'),
                'document_type' => 'cv',
            ])
            ->assertCreated();

        $job = Job::query()->create([
            'code' => 'JOB-2026-0777',
            'slug' => 'principal-engineer-0777',
            'department_id' => $this->engDept->id,
            'recruiter_id' => $this->recruiter->id,
            'hiring_manager_id' => $this->hiringManager->id,
            'title' => 'Principal Engineer',
            'employment_type' => 'full_time',
            'work_arrangement' => 'hybrid',
            'location' => 'Jakarta',
            'description' => 'Lead core platform engineering initiatives.',
            'requirements' => '7+ years engineering leadership.',
            'headcount' => 1,
            'hired_count' => 0,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $appRes = $this->actingAs($this->candidateUser, 'sanctum')
            ->postJson('/api/v1/portal/applications', [
                'job_id' => $job->id,
                'cover_letter' => 'Looking forward to joining!',
                'expected_salary' => 120000,
            ])
            ->assertCreated();

        $appId = $appRes->json('data.id');
        $this->assertSame('applied', $appRes->json('data.current_stage.slug'));

        // 2. Invalid direct jump from Applied -> Hired is blocked!
        $this->actingAs($this->recruiter, 'sanctum')
            ->patchJson("/api/v1/applications/{$appId}/stage", ['stage_slug' => 'hired'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STAGE_TRANSITION');

        // Rejection without reason is blocked!
        $this->actingAs($this->recruiter, 'sanctum')
            ->patchJson("/api/v1/applications/{$appId}/stage", ['stage_slug' => 'rejected'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REJECTION_REASON_REQUIRED');

        // 3. Recruiter progresses Applied -> Screening -> Shortlisted & adds internal note
        $this->actingAs($this->recruiter, 'sanctum')
            ->patchJson("/api/v1/applications/{$appId}/stage", ['stage_slug' => 'screening'])
            ->assertOk();

        $this->actingAs($this->recruiter, 'sanctum')
            ->patchJson("/api/v1/applications/{$appId}/stage", ['stage_slug' => 'shortlisted'])
            ->assertOk();

        $this->actingAs($this->recruiter, 'sanctum')
            ->postJson("/api/v1/applications/{$appId}/notes", ['body' => 'Confidential internal screening note'])
            ->assertCreated();

        // Candidate viewing their own application MUST NOT see internal notes!
        $portalView = $this->actingAs($this->candidateUser, 'sanctum')
            ->getJson("/api/v1/portal/applications/{$appId}")
            ->assertOk();
        $this->assertEmpty($portalView->json('data.notes'));

        // 4. Recruiter schedules interview & assigns Hiring Manager -> auto-moves to Interview stage
        $ivRes = $this->actingAs($this->recruiter, 'sanctum')
            ->postJson('/api/v1/interviews', [
                'application_id' => $appId,
                'title' => 'Technical System Design Interview',
                'mode' => 'online',
                'scheduled_at' => now()->addDays(2)->toIso8601String(),
                'duration_minutes' => 60,
                'meeting_url' => 'https://meet.google.com/abc-defg-hij',
                'interviewer_ids' => [$this->hiringManager->id],
            ])
            ->assertCreated();

        $interviewId = $ivRes->json('data.id');

        // 5. Unassigned Hiring Manager is blocked from submitting evaluation (403)
        $this->actingAs($this->otherHiringManager, 'sanctum')
            ->postJson("/api/v1/interviews/{$interviewId}/evaluations", [
                'technical_skills_rating' => 4,
                'communication_rating' => 4,
                'problem_solving_rating' => 4,
                'relevant_experience_rating' => 4,
                'cultural_fit_rating' => 4,
                'strengths' => 'Good skills',
                'weaknesses' => 'Minor gaps',
                'overall_feedback' => 'Solid performance overall.',
                'recommendation' => 'yes',
            ])
            ->assertForbidden();

        // Assigned Hiring Manager submits evaluation -> succeeds & computes average_rating = 4.60
        $evalRes = $this->actingAs($this->hiringManager, 'sanctum')
            ->postJson("/api/v1/interviews/{$interviewId}/evaluations", [
                'technical_skills_rating' => 5,
                'communication_rating' => 5,
                'problem_solving_rating' => 4,
                'relevant_experience_rating' => 5,
                'cultural_fit_rating' => 4,
                'strengths' => 'Deep architectural clarity and database rigor.',
                'weaknesses' => 'None significant for this level.',
                'overall_feedback' => 'Strong hire for Principal Engineer.',
                'recommendation' => 'strong_yes',
            ])
            ->assertCreated();

        $this->assertEquals(4.60, (float) $evalRes->json('data.average_rating'));

        // 6. Recruiter creates & sends Job Offer -> Pipeline moves to Offered
        $offerRes = $this->actingAs($this->recruiter, 'sanctum')
            ->postJson('/api/v1/job-offers', [
                'application_id' => $appId,
                'base_salary' => 125000,
                'bonus_amount' => 15000,
                'salary_currency' => 'USD',
                'pay_frequency' => 'annual',
                'proposed_start_date' => now()->addDays(30)->toDateString(),
                'expires_at' => now()->addDays(7)->toIso8601String(),
                'send_immediately' => true,
            ])
            ->assertCreated();

        $offerId = $offerRes->json('data.id');
        $this->assertSame('sent', $offerRes->json('data.status'));

        // 7. Candidate accepts Job Offer -> Application reaches Hired & job.hired_count increments!
        $this->actingAs($this->candidateUser, 'sanctum')
            ->patchJson("/api/v1/portal/offers/{$offerId}/respond", [
                'decision' => 'accepted',
                'candidate_notes' => 'Thrilled to accept the offer!',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $finalApp = $this->actingAs($this->recruiter, 'sanctum')
            ->getJson("/api/v1/applications/{$appId}")
            ->assertOk();

        $this->assertSame('hired', $finalApp->json('data.current_stage.slug'));
        $this->assertFalse($finalApp->json('data.is_active'));
        $this->assertSame(1, $job->fresh()->hired_count);

        // 8. Dashboard, Reports & Audit Logs reflect real database state
        $dashboard = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk();

        $this->assertSame(1, $dashboard->json('data.kpis.hired_candidates'));
        $this->assertGreaterThanOrEqual(5, count($finalApp->json('data.histories')));

        $reports = $this->actingAs($this->recruiter, 'sanctum')
            ->getJson('/api/v1/reports')
            ->assertOk();

        $this->assertSame(1, $reports->json('data.overview.hired_candidates'));
        $this->assertEquals(100.0, (float) $reports->json('data.overview.hiring_conversion_rate'));

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/activity-logs')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
