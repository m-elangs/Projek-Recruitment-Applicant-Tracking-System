<?php

namespace App\Models;

use App\Enums\RoleSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected ?array $cachedPermissionSlugs = null;

    protected $fillable = [
        'role_id',
        'department_id',
        'name',
        'email',
        'password',
        'phone',
        'job_title',
        'avatar_path',
        'is_active',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function candidate(): HasOne
    {
        return $this->hasOne(Candidate::class);
    }

    public function managedJobs(): HasMany
    {
        return $this->hasMany(Job::class, 'hiring_manager_id');
    }

    public function recruitedJobs(): HasMany
    {
        return $this->hasMany(Job::class, 'recruiter_id');
    }

    public function roleSlug(): string
    {
        return (string) ($this->role?->slug ?? '');
    }

    public function hasRole(string|array $slugs): bool
    {
        $targets = (array) $slugs;

        return in_array($this->roleSlug(), $targets, true);
    }

    /**
     * @return array<int, string>
     */
    public function permissionSlugs(): array
    {
        if ($this->cachedPermissionSlugs === null) {
            $this->loadMissing('role.permissions');
            $this->cachedPermissionSlugs = $this->role?->permissions
                ?->pluck('slug')
                ->values()
                ->all() ?? [];
        }

        return $this->cachedPermissionSlugs;
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isAdmin() && ! in_array($permissionSlug, ['applications.withdraw_own', 'offers.respond'], true)) {
            return true;
        }

        return in_array($permissionSlug, $this->permissionSlugs(), true);
    }

    public function isAdmin(): bool
    {
        return $this->roleSlug() === RoleSlug::SYSTEM_ADMIN->value;
    }

    public function isRecruiter(): bool
    {
        return $this->roleSlug() === RoleSlug::RECRUITER->value;
    }

    public function isHiringManager(): bool
    {
        return $this->roleSlug() === RoleSlug::HIRING_MANAGER->value;
    }

    public function isCandidate(): bool
    {
        return $this->roleSlug() === RoleSlug::CANDIDATE->value;
    }

    public function defaultRedirectPath(): string
    {
        return RoleSlug::tryFrom($this->roleSlug())?->defaultRedirectPath() ?? '/dashboard';
    }
}
