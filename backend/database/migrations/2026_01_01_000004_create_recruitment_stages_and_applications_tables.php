<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_stages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 60);
            $table->unsignedTinyInteger('display_order')->unique();
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 30)->unique();
            $table->foreignId('job_id')->constrained('jobs')->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->restrictOnDelete();
            $table->foreignId('cv_document_id')->nullable()->constrained('candidate_documents')->nullOnDelete();
            $table->foreignId('current_stage_id')->constrained('recruitment_stages')->restrictOnDelete();
            $table->enum('source', ['careers_portal', 'linkedin', 'referral', 'agency', 'direct_sourced', 'other'])
                ->default('careers_portal');
            $table->text('cover_letter')->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->date('available_from')->nullable();
            $table->enum('hiring_manager_recommendation', ['pending', 'strong_yes', 'yes', 'neutral', 'no', 'strong_no'])
                ->default('pending');
            $table->text('hiring_manager_notes')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->string('withdrawal_reason', 255)->nullable();
            $table->boolean('is_active')->default(true);

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->unsignedBigInteger('active_job_lock')
                    ->virtualAs('CASE WHEN is_active = 1 AND deleted_at IS NULL THEN job_id ELSE NULL END')
                    ->nullable();
            } else {
                $table->unsignedBigInteger('active_job_lock')->nullable();
            }

            $table->timestamp('applied_at')->useCurrent();
            $table->timestamp('stage_updated_at')->useCurrent();
            $table->timestamp('hired_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['candidate_id', 'active_job_lock'], 'uq_candidate_active_job');
            $table->index(['job_id', 'current_stage_id', 'stage_updated_at'], 'idx_apps_pipeline');
            $table->index(['candidate_id', 'applied_at'], 'idx_apps_candidate');
            $table->index(['current_stage_id', 'applied_at', 'hired_at'], 'idx_apps_reporting');
        });

        Schema::create('application_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('recruitment_stages')->restrictOnDelete();
            $table->foreignId('to_stage_id')->constrained('recruitment_stages')->restrictOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('time_in_previous_stage_seconds')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'created_at'], 'idx_app_hist_timeline');
        });

        Schema::create('application_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['application_id', 'created_at'], 'idx_app_notes_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_notes');
        Schema::dropIfExists('application_histories');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('recruitment_stages');
    }
};
