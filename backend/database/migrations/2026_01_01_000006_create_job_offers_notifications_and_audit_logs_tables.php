<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_number', 30)->unique();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('position_title', 180);
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'internship']);
            $table->decimal('base_salary', 12, 2);
            $table->decimal('bonus_amount', 12, 2)->nullable();
            $table->char('salary_currency', 3)->default('USD');
            $table->enum('pay_frequency', ['monthly', 'annual'])->default('annual');
            $table->text('benefits_summary')->nullable();
            $table->date('proposed_start_date');
            $table->dateTime('expires_at');
            $table->string('offer_letter_path', 500)->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'sent', 'accepted', 'declined', 'expired', 'revoked'])
                ->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('decline_reason', 255)->nullable();
            $table->text('candidate_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at'], 'idx_offers_status_expiry');
            $table->index(['application_id', 'status'], 'idx_offers_app');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE job_offers ADD CONSTRAINT chk_job_offers_salary CHECK (base_salary > 0)');
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 191);
            $table->string('notifiable_type', 191);
            $table->unsignedBigInteger('notifiable_id');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifications_lookup');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 50)->nullable();
            $table->string('event_type', 100);
            $table->string('auditable_type', 150);
            $table->unsignedBigInteger('auditable_id');
            $table->string('description', 255);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'idx_audit_entity');
            $table->index(['actor_user_id', 'created_at'], 'idx_audit_actor');
            $table->index(['event_type', 'created_at'], 'idx_audit_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('job_offers');
    }
};
