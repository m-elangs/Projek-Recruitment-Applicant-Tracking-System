<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 191)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('location', 150)->nullable();
            $table->string('headline', 180)->nullable();
            $table->text('summary')->nullable();
            $table->string('current_company', 150)->nullable();
            $table->string('current_title', 150)->nullable();
            $table->unsignedDecimal('years_of_experience', 4, 1)->nullable();
            $table->string('linkedin_url', 255)->nullable();
            $table->string('portfolio_url', 255)->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->char('salary_currency', 3)->default('USD');
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->enum('source', ['careers_portal', 'linkedin', 'referral', 'agency', 'direct_sourced', 'other'])
                ->default('careers_portal');
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone', 'idx_candidates_phone');
            $table->index(['last_name', 'first_name'], 'idx_candidates_name');
            $table->index(['source', 'created_at'], 'idx_candidates_source');
        });

        Schema::create('candidate_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('skill_name', 80);
            $table->enum('proficiency', ['beginner', 'intermediate', 'advanced', 'expert'])->default('intermediate');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['candidate_id', 'skill_name'], 'uq_candidate_skill');
            $table->index(['skill_name', 'candidate_id'], 'idx_cand_skills_name');
        });

        Schema::create('candidate_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('company_name', 150);
            $table->string('job_title', 150);
            $table->string('employment_type', 50)->nullable();
            $table->string('location', 150)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'start_date'], 'idx_cand_exp_timeline');
        });

        Schema::create('candidate_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('institution', 180);
            $table->string('degree', 120);
            $table->string('field_of_study', 150);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('grade', 40)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'start_date'], 'idx_cand_edu_timeline');
        });

        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('document_type', ['cv', 'cover_letter', 'portfolio', 'certificate', 'other'])->default('cv');
            $table->string('original_name', 255);
            $table->string('storage_disk', 30)->default('private');
            $table->string('file_path', 500);
            $table->string('mime_type', 120);
            $table->unsignedInteger('file_size_bytes');
            $table->char('checksum_sha256', 64);
            $table->boolean('is_primary_cv')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['candidate_id', 'document_type', 'is_primary_cv'], 'idx_cand_docs_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('candidate_educations');
        Schema::dropIfExists('candidate_experiences');
        Schema::dropIfExists('candidate_skills');
        Schema::dropIfExists('candidates');
    }
};
