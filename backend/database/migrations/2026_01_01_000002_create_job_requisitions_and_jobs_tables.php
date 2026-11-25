<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_code', 30)->unique();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'internship']);
            $table->enum('work_arrangement', ['onsite', 'hybrid', 'remote'])->default('onsite');
            $table->string('location', 150);
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('target_start_date')->nullable();
            $table->text('justification');
            $table->text('requirements')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'cancelled'])
                ->default('pending_approval');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'status'], 'idx_req_dept_status');
            $table->index(['requested_by', 'status'], 'idx_req_requester_status');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('slug', 220)->unique();
            $table->foreignId('requisition_id')->nullable()->constrained('job_requisitions')->nullOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('recruiter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('hiring_manager_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'internship']);
            $table->enum('work_arrangement', ['onsite', 'hybrid', 'remote'])->default('onsite');
            $table->string('location', 150);
            $table->enum('experience_level', ['entry', 'junior', 'mid', 'senior', 'lead', 'executive'])->default('mid');
            $table->longText('description');
            $table->longText('requirements');
            $table->longText('responsibilities')->nullable();
            $table->text('benefits')->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->char('salary_currency', 3)->default('USD');
            $table->boolean('is_salary_visible')->default(true);
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->unsignedSmallInteger('hired_count')->default(0);
            $table->enum('status', ['draft', 'published', 'paused', 'closed'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'closes_at', 'department_id', 'employment_type'], 'idx_jobs_portal');
            $table->index(['hiring_manager_id', 'recruiter_id', 'status'], 'idx_jobs_owners');

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['title', 'location'], 'ft_jobs_search');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE jobs ADD CONSTRAINT chk_jobs_salary_range CHECK (salary_max IS NULL OR salary_min IS NULL OR salary_max >= salary_min)');
            DB::statement('ALTER TABLE jobs ADD CONSTRAINT chk_jobs_headcount CHECK (headcount >= 1)');
        }

        Schema::create('job_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('skill_name', 80);
            $table->boolean('is_required')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['job_id', 'skill_name'], 'uq_job_skill');
            $table->index('skill_name', 'idx_job_skills_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_skills');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_requisitions');
    }
};
