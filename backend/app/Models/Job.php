<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Job extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jobs';

    protected $fillable = [
        'code',
        'slug',
        'requisition_id',
        'department_id',
        'recruiter_id',
        'hiring_manager_id',
        'title',
        'employment_type',
        'work_arrangement',
        'location',
        'experience_level',
        'description',
        'requirements',
        'responsibilities',
        'benefits',
        'salary_min',
        'salary_max',
        'salary_currency',
        'is_salary_visible',
        'headcount',
        'hired_count',
        'status',
        'published_at',
        'closes_at',
        'closed_at',
    ];

    protected $casts = [
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
        'is_salary_visible' => 'boolean',
        'headcount' => 'integer',
        'hired_count' => 'integer',
        'published_at' => 'datetime',
        'closes_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'requisition_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hiring_manager_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(JobSkill::class, 'job_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'job_id');
    }

    public function isAcceptingApplications(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return false;
        }

        return true;
    }
}
