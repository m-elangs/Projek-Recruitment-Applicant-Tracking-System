<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_talentflow_domain_tables_are_created(): void
    {
        $expectedTables = [
            'roles',
            'permissions',
            'role_permissions',
            'departments',
            'company_settings',
            'users',
            'job_requisitions',
            'jobs',
            'job_skills',
            'candidates',
            'candidate_skills',
            'candidate_experiences',
            'candidate_educations',
            'candidate_documents',
            'recruitment_stages',
            'applications',
            'application_histories',
            'application_notes',
            'interviews',
            'interviewers',
            'interview_evaluations',
            'job_offers',
            'notifications',
            'activity_logs',
            'queue_jobs',
            'personal_access_tokens',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }
    }

    public function test_core_roles_permissions_stages_and_settings_seed_correctly(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, DB::table('roles')->count());
        $this->assertSame(9, DB::table('recruitment_stages')->count());
        $this->assertGreaterThanOrEqual(30, DB::table('permissions')->count());
        $this->assertGreaterThan(50, DB::table('role_permissions')->count());
        $this->assertGreaterThanOrEqual(7, DB::table('company_settings')->count());

        $orderedStageSlugs = DB::table('recruitment_stages')
            ->orderBy('display_order')
            ->pluck('slug')
            ->all();

        $this->assertSame([
            'applied',
            'screening',
            'shortlisted',
            'interview',
            'assessment',
            'offered',
            'hired',
            'rejected',
            'withdrawn',
        ], $orderedStageSlugs);
    }
}
