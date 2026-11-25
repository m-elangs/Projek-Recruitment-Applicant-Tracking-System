<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('scheduled_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->unsignedTinyInteger('round_number')->default(1);
            $table->enum('interview_type', ['screening', 'technical', 'behavioral', 'panel', 'assessment_review', 'final'])
                ->default('technical');
            $table->enum('mode', ['online', 'onsite']);
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('timezone', 64)->default('UTC');
            $table->string('meeting_url', 500)->nullable();
            $table->string('location', 255)->nullable();
            $table->text('candidate_instructions')->nullable();
            $table->text('internal_agenda')->nullable();
            $table->enum('status', ['scheduled', 'rescheduled', 'completed', 'cancelled', 'no_show'])
                ->default('scheduled');
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scheduled_at', 'status'], 'idx_interviews_calendar');
            $table->index(['application_id', 'round_number'], 'idx_interviews_app');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE interviews ADD CONSTRAINT chk_interviews_duration CHECK (duration_minutes BETWEEN 15 AND 480)');
        }

        Schema::create('interviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_lead')->default(false);
            $table->enum('rsvp_status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['interview_id', 'user_id'], 'uq_interview_interviewer');
            $table->index(['user_id', 'rsvp_status'], 'idx_interviewer_schedule');
        });

        Schema::create('interview_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->foreignId('interviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('technical_skills_rating');
            $table->unsignedTinyInteger('communication_rating');
            $table->unsignedTinyInteger('problem_solving_rating');
            $table->unsignedTinyInteger('relevant_experience_rating');
            $table->unsignedTinyInteger('cultural_fit_rating');

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->decimal('average_rating', 3, 2)
                    ->storedAs('((technical_skills_rating + communication_rating + problem_solving_rating + relevant_experience_rating + cultural_fit_rating) / 5.0)');
            } else {
                $table->decimal('average_rating', 3, 2)->nullable();
            }

            $table->text('interview_notes')->nullable();
            $table->text('strengths');
            $table->text('weaknesses');
            $table->text('overall_feedback');
            $table->enum('recommendation', ['strong_yes', 'yes', 'neutral', 'no', 'strong_no']);
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['interview_id', 'interviewer_user_id'], 'uq_evaluation_per_interviewer');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE interview_evaluations ADD CONSTRAINT chk_eval_technical CHECK (technical_skills_rating BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE interview_evaluations ADD CONSTRAINT chk_eval_communication CHECK (communication_rating BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE interview_evaluations ADD CONSTRAINT chk_eval_problem_solving CHECK (problem_solving_rating BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE interview_evaluations ADD CONSTRAINT chk_eval_experience CHECK (relevant_experience_rating BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE interview_evaluations ADD CONSTRAINT chk_eval_cultural_fit CHECK (cultural_fit_rating BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_evaluations');
        Schema::dropIfExists('interviewers');
        Schema::dropIfExists('interviews');
    }
};
