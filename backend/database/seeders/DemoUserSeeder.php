<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $engineering = Department::query()->updateOrCreate(
            ['code' => 'ENG'],
            ['name' => 'Software Engineering', 'description' => 'Product & Platform Engineering', 'is_active' => true]
        );

        $product = Department::query()->updateOrCreate(
            ['code' => 'PROD'],
            ['name' => 'Product & Design', 'description' => 'Product Management & UI/UX Design', 'is_active' => true]
        );

        Department::query()->updateOrCreate(
            ['code' => 'PPL'],
            ['name' => 'People & Talent', 'description' => 'Talent Acquisition & People Operations', 'is_active' => true]
        );

        $roles = Role::query()->pluck('id', 'slug');

        User::query()->updateOrCreate(
            ['email' => 'admin@talentflow.local'],
            [
                'role_id' => $roles[RoleSlug::SYSTEM_ADMIN->value],
                'name' => 'Nadia Pratama',
                'password' => 'Password123!',
                'job_title' => 'Head of IT & Systems',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'recruiter@talentflow.local'],
            [
                'role_id' => $roles[RoleSlug::RECRUITER->value],
                'name' => 'Raka Wijaya',
                'password' => 'Password123!',
                'job_title' => 'Senior Technical Recruiter',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $hm = User::query()->updateOrCreate(
            ['email' => 'hm.engineering@talentflow.local'],
            [
                'role_id' => $roles[RoleSlug::HIRING_MANAGER->value],
                'department_id' => $engineering->id,
                'name' => 'Dimas Santoso',
                'password' => 'Password123!',
                'job_title' => 'VP of Engineering',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $engineering->update(['head_user_id' => $hm->id]);

        User::query()->updateOrCreate(
            ['email' => 'hm.product@talentflow.local'],
            [
                'role_id' => $roles[RoleSlug::HIRING_MANAGER->value],
                'department_id' => $product->id,
                'name' => 'Sinta Maharani',
                'password' => 'Password123!',
                'job_title' => 'Director of Product',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $candidateUser = User::query()->updateOrCreate(
            ['email' => 'candidate@talentflow.local'],
            [
                'role_id' => $roles[RoleSlug::CANDIDATE->value],
                'name' => 'Alya Kusuma',
                'password' => 'Password123!',
                'phone' => '+6281234567890',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        Candidate::query()->updateOrCreate(
            ['email' => 'candidate@talentflow.local'],
            [
                'user_id' => $candidateUser->id,
                'first_name' => 'Alya',
                'last_name' => 'Kusuma',
                'phone' => '+6281234567890',
                'location' => 'Jakarta, Indonesia',
                'headline' => 'Full Stack Software Engineer (React & Laravel)',
                'current_company' => 'Nusantara Digital',
                'current_title' => 'Software Engineer',
                'years_of_experience' => 4.5,
                'source' => 'careers_portal',
            ]
        );
    }
}
