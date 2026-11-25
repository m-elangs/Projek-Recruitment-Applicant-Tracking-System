<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'location',
        'headline',
        'summary',
        'current_company',
        'current_title',
        'years_of_experience',
        'linkedin_url',
        'portfolio_url',
        'expected_salary',
        'salary_currency',
        'notice_period_days',
        'source',
    ];

    protected $casts = [
        'years_of_experience' => 'decimal:1',
        'expected_salary' => 'decimal:2',
        'notice_period_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(CandidateSkill::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(CandidateExperience::class)->orderByDesc('is_current')->orderByDesc('start_date');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(CandidateEducation::class)->orderByDesc('start_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CandidateDocument::class);
    }

    public function primaryCv(): HasOne
    {
        return $this->hasOne(CandidateDocument::class)
            ->where('document_type', 'cv')
            ->where('is_primary_cv', true);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
