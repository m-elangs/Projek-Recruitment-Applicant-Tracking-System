<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name', 150);
            $table->string('email', 191)->unique();
            $table->string('password');
            $table->string('phone', 30)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role_id', 'is_active'], 'idx_users_role_active');
            $table->index(['department_id', 'is_active'], 'idx_users_dept_active');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_user_id', 'fk_departments_head_user')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign('fk_departments_head_user');
        });

        Schema::dropIfExists('users');
    }
};
