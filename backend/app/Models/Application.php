<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'application_number',
        'job_id',
        'candidate_id',
        'cv_document_id',
        'current_stage_id',
        'source',
        'cover_letter',
        'expected_salary',
        'available_from',
        'hiring_manager_recommendation',
        'hiring_manager_notes',
        'rejection_reason',
        'withdrawal_reason',
        'is_active',
        'applied_at',
        'stage_updated_at',
        'hired_at',
        'rejected_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'expected_salary' => 'decimal:2',
        'available_from' => 'date',
        'is_active' => 'boolean',
        'applied_at' => 'datetime',
        'stage_updated_at' => 'datetime',
        'hired_at' => 'datetime',
        'rejected_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Application $application) {
            if ($application->getConnection()->getDriverName() !== 'mysql') {
                $application->setAttribute(
                    'active_job_lock',
                    ($application->is_active && $application->deleted_at === null) ? $application->job_id : null
                );
            }
        });
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function cvDocument(): BelongsTo
    {
        return $this->belongsTo(CandidateDocument::class, 'cv_document_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentStage::class, 'current_stage_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ApplicationHistory::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class)->orderByDesc('created_at');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class)->orderBy('round_number')->orderBy('scheduled_at');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(JobOffer::class)->orderByDesc('created_at');
    }

    public function latestOffer(): HasOne
    {
        return $this->hasOne(JobOffer::class)->latestOfMany();
    }
}
