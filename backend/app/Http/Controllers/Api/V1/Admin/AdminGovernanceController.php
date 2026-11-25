<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminGovernanceController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    public function users(Request $request): JsonResponse
    {
        $query = User::query()->with(['role', 'department']);

        if (! empty($request->query('role'))) {
            $slug = $request->query('role');
            $query->whereHas('role', fn ($q) => $q->where('slug', $slug));
        }

        if (! empty($request->query('department_id'))) {
            $query->where('department_id', (int) $request->query('department_id'));
        }

        if (! empty($request->query('search'))) {
            $term = '%'.$request->query('search').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        $paginator = $query->orderBy('name')->paginate((int) ($request->query('per_page', 20)));

        return ApiResponse::paginated($paginator, UserResource::class, 'Users retrieved.');
    }

    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = DB::transaction(function () use ($validated, $request) {
            $created = User::query()->create([
                'name' => $validated['name'],
                'email' => strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'role_id' => $validated['role_id'],
                'department_id' => $validated['department_id'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'email_verified_at' => now(),
            ]);

            $this->auditLog->log(
                eventType: 'admin.user_created',
                auditable: $created,
                description: "Internal user {$created->email} created",
                actor: $request->user(),
                newValues: ['email' => $created->email, 'role_id' => $created->role_id]
            );

            return $created->load(['role.permissions', 'department']);
        });

        return ApiResponse::created(new UserResource($user), 'User account created.');
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(8)],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['is_active']) && ! $validated['is_active'] && $user->id === $request->user()->id) {
            throw new BusinessRuleException('CANNOT_DISABLE_SELF', 'You cannot deactivate your own administrator account.', 422);
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $old = $user->only(['role_id', 'department_id', 'is_active']);
        $user->update($validated);

        if (isset($validated['is_active']) && ! $validated['is_active']) {
            $user->tokens()->delete();
        }

        $this->auditLog->log(
            eventType: 'admin.user_updated',
            auditable: $user,
            description: "User {$user->email} updated",
            actor: $request->user(),
            oldValues: $old,
            newValues: $user->only(['role_id', 'department_id', 'is_active'])
        );

        return ApiResponse::success(new UserResource($user->fresh(['role.permissions', 'department'])), 'User updated.');
    }

    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions:id,slug,module,name')
            ->withCount('users')
            ->orderBy('id')
            ->get();

        return ApiResponse::success($roles, 'Roles retrieved.');
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()->orderBy('module')->orderBy('slug')->get();

        return ApiResponse::success($permissions->groupBy('module'), 'Permissions catalog retrieved.');
    }

    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $slug = Str::slug($validated['name'], '_');
        if (Role::query()->where('slug', $slug)->exists()) {
            throw new BusinessRuleException('DUPLICATE_ROLE', 'A role with this name already exists.', 409);
        }

        $role = DB::transaction(function () use ($validated, $slug, $request) {
            $created = Role::query()->create([
                'slug' => $slug,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_system' => false,
            ]);

            if (! empty($validated['permission_ids'])) {
                $created->permissions()->sync($validated['permission_ids']);
            }

            $this->auditLog->log(
                eventType: 'admin.role_created',
                auditable: $created,
                description: "Custom role '{$created->name}' created",
                actor: $request->user()
            );

            return $created->load('permissions');
        });

        return ApiResponse::created($role, 'Role created.');
    }

    public function syncRolePermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        DB::transaction(function () use ($role, $validated, $request) {
            $role->permissions()->sync($validated['permission_ids']);

            $this->auditLog->log(
                eventType: 'admin.role_permissions_updated',
                auditable: $role,
                description: "Permissions updated for role '{$role->name}'",
                actor: $request->user(),
                newValues: ['permission_count' => count($validated['permission_ids'])]
            );
        });

        return ApiResponse::success($role->fresh('permissions'), 'Role permissions updated.');
    }

    public function departments(): JsonResponse
    {
        $departments = Department::query()
            ->with('head:id,name,email,job_title')
            ->withCount(['users', 'jobs'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success($departments, 'Departments retrieved.');
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:120', 'unique:departments,name'],
            'head_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $dept = Department::query()->create($validated);

        $this->auditLog->log(
            eventType: 'admin.department_created',
            auditable: $dept,
            description: "Department '{$dept->name}' ({$dept->code}) created",
            actor: $request->user()
        );

        return ApiResponse::created($dept->load('head:id,name,email'), 'Department created.');
    }

    public function updateDepartment(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department->id)],
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('departments', 'name')->ignore($department->id)],
            'head_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $department->update($validated);

        $this->auditLog->log(
            eventType: 'admin.department_updated',
            auditable: $department,
            description: "Department '{$department->name}' updated",
            actor: $request->user()
        );

        return ApiResponse::success($department->fresh('head:id,name,email'), 'Department updated.');
    }

    public function settings(): JsonResponse
    {
        $settings = CompanySetting::query()
            ->pluck('setting_value', 'setting_key');

        return ApiResponse::success($settings, 'Company settings retrieved.');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            foreach ($validated['settings'] as $key => $value) {
                $setting = CompanySetting::query()->updateOrCreate(
                    ['setting_key' => (string) $key],
                    ['setting_value' => $value, 'updated_by' => $request->user()->id]
                );
            }

            if (isset($setting)) {
                $this->auditLog->log(
                    eventType: 'admin.settings_updated',
                    auditable: $setting,
                    description: 'Global company settings updated',
                    actor: $request->user(),
                    newValues: array_keys($validated['settings'])
                );
            }
        });

        return ApiResponse::success(
            CompanySetting::query()->pluck('setting_value', 'setting_key'),
            'Company settings saved.'
        );
    }

    public function activityLogs(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->with('actor:id,name,email');

        if (! empty($request->query('event_type'))) {
            $query->where('event_type', 'like', $request->query('event_type').'%');
        }

        if (! empty($request->query('actor_user_id'))) {
            $query->where('actor_user_id', (int) $request->query('actor_user_id'));
        }

        if (! empty($request->query('search'))) {
            $term = '%'.$request->query('search').'%';
            $query->where(fn ($q) => $q->where('description', 'like', $term)->orWhere('event_type', 'like', $term));
        }

        if (! empty($request->query('from'))) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if (! empty($request->query('to'))) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $paginator = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($request->query('per_page', 25)));

        return ApiResponse::paginated($paginator, null, 'Audit activity logs retrieved.');
    }
}
