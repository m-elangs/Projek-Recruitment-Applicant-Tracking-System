<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'interview_id',
        'interviewer_user_id',
        'technical_skills_rating',
        'communication_rating',
        'problem_solving_rating',
        'relevant_experience_rating',
        'cultural_fit_rating',
        'interview_notes',
        'strengths',
        'weaknesses',
        'overall_feedback',
        'recommendation',
        'submitted_at',
    ];

    protected $casts = [
        'technical_skills_rating' => 'integer',
        'communication_rating' => 'integer',
        'problem_solving_rating' => 'integer',
        'relevant_experience_rating' => 'integer',
        'cultural_fit_rating' => 'integer',
        'average_rating' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (InterviewEvaluation $eval) {
            if ($eval->getConnection()->getDriverName() !== 'mysql') {
                $sum = (int) $eval->technical_skills_rating
                    + (int) $eval->communication_rating
                    + (int) $eval->problem_solving_rating
                    + (int) $eval->relevant_experience_rating
                    + (int) $eval->cultural_fit_rating;
                $eval->setAttribute('average_rating', round($sum / 5, 2));
            }
        });
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_user_id');
    }
}
