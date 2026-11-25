<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\User;
use Database\Seeders\CompanySettingSeeder;
use Database\Seeders\RecruitmentStageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            RecruitmentStageSeeder::class,
            CompanySettingSeeder::class,
        ]);

        Route::middleware(['api', 'auth:sanctum', 'permission:users.manage'])
            ->get('/api/v1/_test/admin-only', fn () => response()->json(['success' => true]));
    }

    public function test_candidate_can_register_and_receives_linked_candidate_profile_and_portal_redirect(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Budi',
            'last_name' => 'Hartono',
            'email' => 'budi.hartono@example.com',
            'phone' => '+628111222333',
            'location' => 'Bandung',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role.slug', 'candidate')
            ->assertJsonPath('data.redirect_to', '/portal/dashboard')
            ->assertJsonStructure(['data' => ['user' => ['id', 'permissions', 'candidate_id'], 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'budi.hartono@example.com']);
        $this->assertDatabaseHas('candidates', [
            'email' => 'budi.hartono@example.com',
            'first_name' => 'Budi',
            'source' => 'careers_portal',
        ]);
        $this->assertDatabaseHas('activity_logs', ['event_type' => 'auth.candidate_registered']);
    }

    public function test_internal_user_login_returns_permissions_and_internal_dashboard_redirect(): void
    {
        $recruiter = User::factory()->withRole(RoleSlug::RECRUITER)->create([
            'email' => 'recruiter.test@example.com',
            'password' => 'SecretPass123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'recruiter.test@example.com',
            'password' => 'SecretPass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $recruiter->id)
            ->assertJsonPath('data.user.role.slug', 'recruiter')
            ->assertJsonPath('data.redirect_to', '/dashboard');

        $permissions = $response->json('data.user.permissions');
        $this->assertContains('jobs.manage', $permissions);
        $this->assertNotContains('users.manage', $permissions);
    }

    public function test_deactivated_user_is_blocked_from_logging_in(): void
    {
        User::factory()->withRole(RoleSlug::RECRUITER)->create([
            'email' => 'inactive@example.com',
            'password' => 'SecretPass123!',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'SecretPass123!',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ACCOUNT_DISABLED');
    }

    public function test_rbac_middleware_blocks_unauthorized_roles_and_allows_system_admin(): void
    {
        $candidate = User::factory()->withRole(RoleSlug::CANDIDATE)->create();
        $recruiter = User::factory()->withRole(RoleSlug::RECRUITER)->create();
        $admin = User::factory()->withRole(RoleSlug::SYSTEM_ADMIN)->create();

        $this->actingAs($candidate, 'sanctum')
            ->getJson('/api/v1/_test/admin-only')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($recruiter, 'sanctum')
            ->getJson('/api/v1/_test/admin-only')
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/_test/admin-only')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
