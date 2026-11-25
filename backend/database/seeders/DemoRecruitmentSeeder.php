<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Department;
use App\Models\Interview;
use App\Models\InterviewEvaluation;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\JobRequisition;
use App\Models\RecruitmentStage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoRecruitmentSeeder extends Seeder
{
    public function run(): void
    {
        if (Job::query()->count() > 0) {
            return;
        }

        $admin = User::query()->where('email', 'admin@talentflow.local')->firstOrFail();
        $recruiter = User::query()->where('email', 'recruiter@talentflow.local')->firstOrFail();
        $hmEng = User::query()->where('email', 'hm.engineering@talentflow.local')->firstOrFail();
        $hmProd = User::query()->where('email', 'hm.product@talentflow.local')->firstOrFail();

        $engDept = Department::query()->where('code', 'ENG')->firstOrFail();
        $prodDept = Department::query()->where('code', 'PROD')->firstOrFail();
        $stages = RecruitmentStage::query()->pluck('id', 'slug');

        // 1. Job Requisitions
        $req1 = JobRequisition::query()->create([
            'requisition_code' => 'REQ-2026-0001',
            'department_id' => $engDept->id,
            'requested_by' => $hmEng->id,
            'approved_by' => $admin->id,
            'title' => 'Senior Full Stack Engineer',
            'headcount' => 2,
            'employment_type' => 'full_time',
            'work_arrangement' => 'hybrid',
            'location' => 'Jakarta, Indonesia',
            'budget_min' => 90000,
            'budget_max' => 125000,
            'currency' => 'USD',
            'priority' => 'high',
            'target_start_date' => now()->addDays(45)->toDateString(),
            'justification' => 'Core platform expansion for Q4 enterprise clients.',
            'requirements' => '5+ years with React, TypeScript, PHP/Laravel, and MySQL.',
            'status' => 'approved',
            'approved_at' => now()->subDays(20),
        ]);

        JobRequisition::query()->create([
            'requisition_code' => 'REQ-2026-0002',
            'department_id' => $prodDept->id,
            'requested_by' => $hmProd->id,
            'title' => 'Staff Product Designer',
            'headcount' => 1,
            'employment_type' => 'full_time',
            'work_arrangement' => 'remote',
            'location' => 'Remote (APAC)',
            'budget_min' => 85000,
            'budget_max' => 110000,
            'currency' => 'USD',
            'priority' => 'medium',
            'target_start_date' => now()->addDays(60)->toDateString(),
            'justification' => 'Design system ownership across enterprise HR suite.',
            'status' => 'pending_approval',
        ]);

        // 2. Job Vacancies
        $job1 = Job::query()->create([
            'code' => 'JOB-2026-0001',
            'slug' => 'senior-full-stack-engineer-job-2026-0001',
            'requisition_id' => $req1->id,
            'department_id' => $engDept->id,
            'recruiter_id' => $recruiter->id,
            'hiring_manager_id' => $hmEng->id,
            'title' => 'Senior Full Stack Engineer',
            'employment_type' => 'full_time',
            'work_arrangement' => 'hybrid',
            'location' => 'Jakarta, Indonesia',
            'experience_level' => 'senior',
            'description' => "We are looking for a Senior Full Stack Engineer to architect and ship mission-critical workflow features across TalentFlow.\n\nYou will collaborate closely with Product, Design, and Platform teams to deliver fast, reliable, and accessible web software.",
            'requirements' => "- 5+ years production experience with React, TypeScript, and modern REST APIs.\n- Strong proficiency in PHP 8+ (Laravel) and relational schema optimization in MySQL.\n- Deep understanding of RBAC security, automated testing, and transactional integrity.",
            'responsibilities' => "- Own end-to-end architecture from MySQL schema design to React UI.\n- Mentor engineers through thoughtful code reviews and system design specs.",
            'benefits' => 'Competitive base & performance bonus, flexible hybrid schedule, comprehensive health coverage, annual learning budget.',
            'salary_min' => 95000,
            'salary_max' => 125000,
            'salary_currency' => 'USD',
            'is_salary_visible' => true,
            'headcount' => 2,
            'hired_count' => 1,
            'status' => 'published',
            'published_at' => now()->subDays(18),
            'closes_at' => now()->addDays(30),
        ]);
        $job1->skills()->insert([
            ['job_id' => $job1->id, 'skill_name' => 'React', 'is_required' => true, 'created_at' => now()],
            ['job_id' => $job1->id, 'skill_name' => 'TypeScript', 'is_required' => true, 'created_at' => now()],
            ['job_id' => $job1->id, 'skill_name' => 'Laravel', 'is_required' => true, 'created_at' => now()],
            ['job_id' => $job1->id, 'skill_name' => 'MySQL', 'is_required' => true, 'created_at' => now()],
        ]);

        $job2 = Job::query()->create([
            'code' => 'JOB-2026-0002',
            'slug' => 'product-manager-core-workflows-job-2026-0002',
            'department_id' => $prodDept->id,
            'recruiter_id' => $recruiter->id,
            'hiring_manager_id' => $hmProd->id,
            'title' => 'Product Manager — Core Workflows',
            'employment_type' => 'full_time',
            'work_arrangement' => 'remote',
            'location' => 'Singapore / Remote',
            'experience_level' => 'mid',
            'description' => 'Lead product discovery, roadmap prioritization, and execution for TalentFlow pipeline automation and analytics.',
            'requirements' => '- 3+ years B2B SaaS Product Management experience.\n- Strong analytical fluency with SQL and funnel metrics.',
            'salary_min' => 80000,
            'salary_max' => 105000,
            'salary_currency' => 'USD',
            'is_salary_visible' => true,
            'headcount' => 1,
            'hired_count' => 0,
            'status' => 'published',
            'published_at' => now()->subDays(12),
            'closes_at' => now()->addDays(25),
        ]);
        $job2->skills()->insert([
            ['job_id' => $job2->id, 'skill_name' => 'Product Strategy', 'is_required' => true, 'created_at' => now()],
            ['job_id' => $job2->id, 'skill_name' => 'B2B SaaS', 'is_required' => true, 'created_at' => now()],
            ['job_id' => $job2->id, 'skill_name' => 'SQL Analytics', 'is_required' => false, 'created_at' => now()],
        ]);

        Job::query()->create([
            'code' => 'JOB-2026-0003',
            'slug' => 'backend-platform-engineer-job-2026-0003',
            'department_id' => $engDept->id,
            'recruiter_id' => $recruiter->id,
            'hiring_manager_id' => $hmEng->id,
            'title' => 'Backend Platform Engineer',
            'employment_type' => 'full_time',
            'work_arrangement' => 'onsite',
            'location' => 'Jakarta, Indonesia',
            'experience_level' => 'mid',
            'description' => 'Scale queue workers, search indexing, and API reliability across our recruitment infrastructure.',
            'requirements' => '- 3+ years PHP/Laravel or Go experience.\n- Strong relational database tuning skills.',
            'salary_min' => 70000,
            'salary_max' => 92000,
            'salary_currency' => 'USD',
            'is_salary_visible' => false,
            'headcount' => 1,
            'status' => 'draft',
        ]);

        // 3. Candidates & Private Sample CVs
        $alya = Candidate::query()->where('email', 'candidate@talentflow.local')->firstOrFail();
        $alya->skills()->insert([
            ['candidate_id' => $alya->id, 'skill_name' => 'React', 'proficiency' => 'expert', 'created_at' => now()],
            ['candidate_id' => $alya->id, 'skill_name' => 'TypeScript', 'proficiency' => 'advanced', 'created_at' => now()],
            ['candidate_id' => $alya->id, 'skill_name' => 'Laravel', 'proficiency' => 'expert', 'created_at' => now()],
            ['candidate_id' => $alya->id, 'skill_name' => 'MySQL', 'proficiency' => 'advanced', 'created_at' => now()],
        ]);
        $alya->experiences()->create([
            'company_name' => 'Nusantara Digital',
            'job_title' => 'Software Engineer',
            'employment_type' => 'Full-time',
            'location' => 'Jakarta',
            'start_date' => '2022-03-01',
            'is_current' => true,
            'description' => 'Built multi-tenant B2B web platforms using React, TypeScript, and Laravel REST APIs.',
        ]);
        $alya->educations()->create([
            'institution' => 'Institut Teknologi Bandung',
            'degree' => 'B.Sc.',
            'field_of_study' => 'Informatics Engineering',
            'start_date' => '2017-08-01',
            'end_date' => '2021-07-15',
            'grade' => '3.78 / 4.00',
        ]);

        Storage::disk('private')->put("candidates/{$alya->id}/alya-cv.pdf", "%PDF-1.4\n% TalentFlow Demo CV - Alya Kusuma\n");
        $alyaCv = CandidateDocument::query()->create([
            'candidate_id' => $alya->id,
            'uploaded_by' => $alya->user_id,
            'document_type' => 'cv',
            'original_name' => 'Alya_Kusuma_CV_2026.pdf',
            'storage_disk' => 'private',
            'file_path' => "candidates/{$alya->id}/alya-cv.pdf",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 48210,
            'checksum_sha256' => hash('sha256', 'alya-cv'),
            'is_primary_cv' => true,
        ]);

        $reza = Candidate::query()->create([
            'first_name' => 'Reza',
            'last_name' => 'Mahendra',
            'email' => 'reza.mahendra@example.com',
            'phone' => '+6281398765432',
            'location' => 'Bandung, Indonesia',
            'headline' => 'Principal Backend & Distributed Systems Engineer',
            'current_company' => 'CloudScale Asia',
            'current_title' => 'Senior Software Engineer',
            'years_of_experience' => 6.5,
            'expected_salary' => 115000,
            'salary_currency' => 'USD',
            'source' => 'linkedin',
        ]);
        $reza->skills()->insert([
            ['candidate_id' => $reza->id, 'skill_name' => 'Laravel', 'proficiency' => 'expert', 'created_at' => now()],
            ['candidate_id' => $reza->id, 'skill_name' => 'MySQL', 'proficiency' => 'expert', 'created_at' => now()],
            ['candidate_id' => $reza->id, 'skill_name' => 'React', 'proficiency' => 'advanced', 'created_at' => now()],
        ]);

        $kevin = Candidate::query()->create([
            'first_name' => 'Kevin',
            'last_name' => 'Tanuwijaya',
            'email' => 'kevin.tan@example.com',
            'phone' => '+6281911223344',
            'location' => 'Surabaya, Indonesia',
            'headline' => 'Full Stack Developer',
            'current_company' => 'FinTech Karya',
            'current_title' => 'Full Stack Developer',
            'years_of_experience' => 3.0,
            'source' => 'referral',
        ]);

        $maya = Candidate::query()->create([
            'first_name' => 'Maya',
            'last_name' => 'Lestari',
            'email' => 'maya.lestari@example.com',
            'phone' => '+6591234567',
            'location' => 'Singapore',
            'headline' => 'Senior SaaS Product Manager',
            'current_company' => 'WorkGrid',
            'current_title' => 'Product Manager',
            'years_of_experience' => 5.0,
            'source' => 'careers_portal',
        ]);

        // 4. Applications across realistic pipeline stages
        // App 1: Alya -> Offered (has completed interview + evaluation + pending sent offer so Candidate Portal demo is rich!)
        $appAlya = Application::query()->create([
            'application_number' => 'APP-2026-00001',
            'job_id' => $job1->id,
            'candidate_id' => $alya->id,
            'cv_document_id' => $alyaCv->id,
            'current_stage_id' => $stages['offered'],
            'source' => 'careers_portal',
            'cover_letter' => 'Excited to contribute to TalentFlow engineering!',
            'expected_salary' => 110000,
            'hiring_manager_recommendation' => 'strong_yes',
            'hiring_manager_notes' => 'Strong system design thinking and clean code practices.',
            'is_active' => true,
            'applied_at' => now()->subDays(14),
            'stage_updated_at' => now()->subDays(1),
        ]);
        $appAlya->histories()->insert([
            ['application_id' => $appAlya->id, 'from_stage_id' => null, 'to_stage_id' => $stages['applied'], 'changed_by' => $alya->user_id, 'reason' => 'Initial application', 'time_in_previous_stage_seconds' => 0, 'created_at' => now()->subDays(14)],
            ['application_id' => $appAlya->id, 'from_stage_id' => $stages['applied'], 'to_stage_id' => $stages['shortlisted'], 'changed_by' => $recruiter->id, 'reason' => 'Strong full-stack background', 'time_in_previous_stage_seconds' => 86400, 'created_at' => now()->subDays(12)],
            ['application_id' => $appAlya->id, 'from_stage_id' => $stages['shortlisted'], 'to_stage_id' => $stages['interview'], 'changed_by' => $recruiter->id, 'reason' => 'Technical panel scheduled', 'time_in_previous_stage_seconds' => 172800, 'created_at' => now()->subDays(8)],
            ['application_id' => $appAlya->id, 'from_stage_id' => $stages['interview'], 'to_stage_id' => $stages['offered'], 'changed_by' => $recruiter->id, 'reason' => 'Offer extended following unanimous panel feedback', 'time_in_previous_stage_seconds' => 432000, 'created_at' => now()->subDays(1)],
        ]);
        $appAlya->notes()->create([
            'author_user_id' => $recruiter->id,
            'body' => 'Candidate demonstrated deep familiarity with Laravel service architecture and TypeScript state management.',
            'is_internal' => true,
        ]);

        $ivAlya = Interview::query()->create([
            'application_id' => $appAlya->id,
            'scheduled_by' => $recruiter->id,
            'title' => 'Round 1 — System Architecture & Full-Stack Deep Dive',
            'round_number' => 1,
            'interview_type' => 'technical',
            'mode' => 'online',
            'scheduled_at' => now()->subDays(5)->setTime(10, 0),
            'duration_minutes' => 60,
            'timezone' => 'Asia/Jakarta',
            'meeting_url' => 'https://meet.google.com/tfl-arch-demo',
            'candidate_instructions' => 'Please be ready to walk through a relational schema and API design you built recently.',
            'status' => 'completed',
        ]);
        $ivAlya->interviewerAssignments()->create([
            'user_id' => $hmEng->id,
            'is_lead' => true,
            'rsvp_status' => 'accepted',
            'assigned_at' => now()->subDays(8),
        ]);
        InterviewEvaluation::query()->create([
            'interview_id' => $ivAlya->id,
            'interviewer_user_id' => $hmEng->id,
            'technical_skills_rating' => 5,
            'communication_rating' => 5,
            'problem_solving_rating' => 5,
            'relevant_experience_rating' => 4,
            'cultural_fit_rating' => 5,
            'strengths' => 'Excellent MySQL normalization instincts, crisp API boundary design, and clear communication.',
            'weaknesses' => 'Limited exposure to mobile native tooling, which is non-critical for this web platform role.',
            'overall_feedback' => 'Top-tier full-stack candidate. Strongly recommend extending an offer immediately.',
            'recommendation' => 'strong_yes',
            'submitted_at' => now()->subDays(5)->setTime(12, 0),
        ]);

        JobOffer::query()->create([
            'offer_number' => 'OFR-2026-0001',
            'application_id' => $appAlya->id,
            'created_by' => $recruiter->id,
            'approved_by' => $hmEng->id,
            'department_id' => $engDept->id,
            'position_title' => 'Senior Full Stack Engineer',
            'employment_type' => 'full_time',
            'base_salary' => 112000,
            'bonus_amount' => 10000,
            'salary_currency' => 'USD',
            'pay_frequency' => 'annual',
            'benefits_summary' => 'Executive health plan, remote/hybrid stipend, 20 days PTO.',
            'proposed_start_date' => now()->addDays(30)->toDateString(),
            'expires_at' => now()->addDays(10),
            'status' => 'sent',
            'approved_at' => now()->subDays(1),
            'sent_at' => now()->subDays(1),
        ]);

        // App 2: Reza -> Hired (powers Time-to-Hire & Conversion analytics!)
        $appReza = Application::query()->create([
            'application_number' => 'APP-2026-00002',
            'job_id' => $job1->id,
            'candidate_id' => $reza->id,
            'current_stage_id' => $stages['hired'],
            'source' => 'linkedin',
            'hiring_manager_recommendation' => 'strong_yes',
            'is_active' => false,
            'applied_at' => now()->subDays(17),
            'stage_updated_at' => now()->subDays(3),
            'hired_at' => now()->subDays(3),
        ]);
        JobOffer::query()->create([
            'offer_number' => 'OFR-2026-0002',
            'application_id' => $appReza->id,
            'created_by' => $recruiter->id,
            'approved_by' => $hmEng->id,
            'department_id' => $engDept->id,
            'position_title' => 'Senior Full Stack Engineer',
            'employment_type' => 'full_time',
            'base_salary' => 118000,
            'salary_currency' => 'USD',
            'pay_frequency' => 'annual',
            'proposed_start_date' => now()->addDays(14)->toDateString(),
            'expires_at' => now()->addDays(5),
            'status' => 'accepted',
            'sent_at' => now()->subDays(6),
            'responded_at' => now()->subDays(3),
        ]);

        // App 3: Kevin -> Rejected
        Application::query()->create([
            'application_number' => 'APP-2026-00003',
            'job_id' => $job1->id,
            'candidate_id' => $kevin->id,
            'current_stage_id' => $stages['rejected'],
            'source' => 'referral',
            'rejection_reason' => 'Experience level below senior architecture requirements',
            'is_active' => false,
            'applied_at' => now()->subDays(10),
            'stage_updated_at' => now()->subDays(7),
            'rejected_at' => now()->subDays(7),
        ]);

        // App 4: Maya -> Interview Scheduled (upcoming interview on calendar!)
        $appMaya = Application::query()->create([
            'application_number' => 'APP-2026-00004',
            'job_id' => $job2->id,
            'candidate_id' => $maya->id,
            'current_stage_id' => $stages['interview'],
            'source' => 'careers_portal',
            'is_active' => true,
            'applied_at' => now()->subDays(6),
            'stage_updated_at' => now()->subDays(2),
        ]);
        $ivMaya = Interview::query()->create([
            'application_id' => $appMaya->id,
            'scheduled_by' => $recruiter->id,
            'title' => 'Product Case Study & Stakeholder Interview',
            'round_number' => 1,
            'interview_type' => 'panel',
            'mode' => 'online',
            'scheduled_at' => now()->addDays(2)->setTime(14, 0),
            'duration_minutes' => 60,
            'timezone' => 'Asia/Jakarta',
            'meeting_url' => 'https://meet.google.com/tfl-pm-case',
            'status' => 'scheduled',
        ]);
        $ivMaya->interviewerAssignments()->create([
            'user_id' => $hmProd->id,
            'is_lead' => true,
            'rsvp_status' => 'accepted',
            'assigned_at' => now()->subDays(2),
        ]);

        ActivityLog::query()->create([
            'actor_user_id' => $recruiter->id,
            'actor_role' => 'recruiter',
            'event_type' => 'job.published',
            'auditable_type' => Job::class,
            'auditable_id' => $job1->id,
            'description' => "Job vacancy {$job1->code} ({$job1->title}) published to Careers Portal",
            'created_at' => now()->subDays(18),
        ]);
        ActivityLog::query()->create([
            'actor_user_id' => $recruiter->id,
            'actor_role' => 'recruiter',
            'event_type' => 'offer.sent',
            'auditable_type' => JobOffer::class,
            'auditable_id' => 1,
            'description' => 'Job offer OFR-2026-0001 sent to Alya Kusuma',
            'created_at' => now()->subDay(),
        ]);
    }
}
