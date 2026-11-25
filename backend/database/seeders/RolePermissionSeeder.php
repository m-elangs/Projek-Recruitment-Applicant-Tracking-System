<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $roles = [
            [
                'slug' => 'system_admin',
                'name' => 'System Administrator',
                'description' => 'Full platform administration, RBAC governance, company settings, and audit logs.',
                'is_system' => true,
            ],
            [
                'slug' => 'recruiter',
                'name' => 'Recruiter',
                'description' => 'Manages job vacancies, candidates, pipelines, interview scheduling, offers, and hiring reports.',
                'is_system' => true,
            ],
            [
                'slug' => 'hiring_manager',
                'name' => 'Hiring Manager',
                'description' => 'Submits hiring requisitions, reviews assigned candidates, submits interview evaluations, and approves offers.',
                'is_system' => true,
            ],
            [
                'slug' => 'candidate',
                'name' => 'Candidate',
                'description' => 'Manages personal profile, applies to published jobs, tracks applications, and responds to job offers.',
                'is_system' => true,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                array_merge($role, ['updated_at' => $now, 'created_at' => $now])
            );
        }

        $permissions = [
            // Profile
            ['slug' => 'profile.manage_own', 'module' => 'auth', 'name' => 'Manage Own Profile'],

            // Dashboard & Governance
            ['slug' => 'dashboard.view_internal', 'module' => 'dashboard', 'name' => 'View Internal Dashboard'],
            ['slug' => 'users.view', 'module' => 'admin', 'name' => 'View Users'],
            ['slug' => 'users.manage', 'module' => 'admin', 'name' => 'Create & Manage Users'],
            ['slug' => 'roles.manage', 'module' => 'admin', 'name' => 'Manage Roles & Permissions'],
            ['slug' => 'departments.view', 'module' => 'admin', 'name' => 'View Departments'],
            ['slug' => 'departments.manage', 'module' => 'admin', 'name' => 'Manage Departments'],
            ['slug' => 'settings.manage', 'module' => 'admin', 'name' => 'Manage Company Settings'],
            ['slug' => 'audit_logs.view', 'module' => 'admin', 'name' => 'View Audit Logs'],

            // Requisitions & Jobs
            ['slug' => 'requisitions.view', 'module' => 'jobs', 'name' => 'View Job Requisitions'],
            ['slug' => 'requisitions.create', 'module' => 'jobs', 'name' => 'Submit Job Requisitions'],
            ['slug' => 'requisitions.approve', 'module' => 'jobs', 'name' => 'Approve or Reject Job Requisitions'],
            ['slug' => 'jobs.view_internal', 'module' => 'jobs', 'name' => 'View Internal Job Vacancies'],
            ['slug' => 'jobs.manage', 'module' => 'jobs', 'name' => 'Create, Edit, Publish, Pause & Close Jobs'],
            ['slug' => 'jobs.delete_draft', 'module' => 'jobs', 'name' => 'Delete Draft Job Vacancies'],

            // Candidates & Documents
            ['slug' => 'candidates.view', 'module' => 'candidates', 'name' => 'View Candidate Directory & Profiles'],
            ['slug' => 'candidates.manage', 'module' => 'candidates', 'name' => 'Create & Update Candidate Profiles'],
            ['slug' => 'documents.view', 'module' => 'candidates', 'name' => 'Access Private Candidate Documents'],
            ['slug' => 'documents.upload', 'module' => 'candidates', 'name' => 'Upload Candidate Documents'],

            // Applications & Pipeline
            ['slug' => 'applications.submit', 'module' => 'applications', 'name' => 'Submit Job Applications'],
            ['slug' => 'applications.view', 'module' => 'applications', 'name' => 'View Applications'],
            ['slug' => 'applications.note', 'module' => 'applications', 'name' => 'Write Internal Application Notes'],
            ['slug' => 'applications.transition', 'module' => 'applications', 'name' => 'Transition Pipeline Stages'],
            ['slug' => 'applications.withdraw_own', 'module' => 'applications', 'name' => 'Withdraw Own Application'],
            ['slug' => 'applications.recommend', 'module' => 'applications', 'name' => 'Submit Hiring Manager Recommendation'],

            // Interviews & Evaluations
            ['slug' => 'interviews.view', 'module' => 'interviews', 'name' => 'View Interview Schedules'],
            ['slug' => 'interviews.manage', 'module' => 'interviews', 'name' => 'Schedule, Reschedule & Cancel Interviews'],
            ['slug' => 'evaluations.view', 'module' => 'interviews', 'name' => 'View Interview Evaluations'],
            ['slug' => 'evaluations.submit', 'module' => 'interviews', 'name' => 'Submit Interview Evaluations'],

            // Job Offers & Reports
            ['slug' => 'offers.view', 'module' => 'offers', 'name' => 'View Job Offers'],
            ['slug' => 'offers.manage', 'module' => 'offers', 'name' => 'Create, Send & Revoke Job Offers'],
            ['slug' => 'offers.approve', 'module' => 'offers', 'name' => 'Approve Outgoing Job Offers'],
            ['slug' => 'offers.respond', 'module' => 'offers', 'name' => 'Accept or Decline Own Job Offer'],
            ['slug' => 'reports.view', 'module' => 'reports', 'name' => 'View Recruitment Analytics & Reports'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                array_merge($permission, ['updated_at' => $now, 'created_at' => $now])
            );
        }

        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');

        $matrix = [
            'system_admin' => array_diff(array_keys($permissionIds->all()), ['applications.withdraw_own', 'offers.respond']),
            'recruiter' => [
                'profile.manage_own',
                'dashboard.view_internal',
                'departments.view',
                'requisitions.view',
                'requisitions.create',
                'jobs.view_internal',
                'jobs.manage',
                'jobs.delete_draft',
                'candidates.view',
                'candidates.manage',
                'documents.view',
                'documents.upload',
                'applications.submit',
                'applications.view',
                'applications.note',
                'applications.transition',
                'interviews.view',
                'interviews.manage',
                'evaluations.view',
                'evaluations.submit',
                'offers.view',
                'offers.manage',
                'reports.view',
            ],
            'hiring_manager' => [
                'profile.manage_own',
                'dashboard.view_internal',
                'departments.view',
                'requisitions.view',
                'requisitions.create',
                'jobs.view_internal',
                'candidates.view',
                'documents.view',
                'applications.view',
                'applications.note',
                'applications.recommend',
                'interviews.view',
                'evaluations.view',
                'evaluations.submit',
                'offers.view',
                'offers.approve',
                'reports.view',
            ],
            'candidate' => [
                'profile.manage_own',
                'documents.view',
                'documents.upload',
                'applications.submit',
                'applications.view',
                'applications.withdraw_own',
                'interviews.view',
                'offers.view',
                'offers.respond',
            ],
        ];

        foreach ($matrix as $roleSlug => $slugs) {
            $roleId = $roleIds[$roleSlug];
            DB::table('role_permissions')->where('role_id', $roleId)->delete();

            $rows = [];
            foreach ($slugs as $permSlug) {
                $rows[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds[$permSlug],
                    'created_at' => $now,
                ];
            }
            DB::table('role_permissions')->insert($rows);
        }
    }
}
