<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Interview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'application_id',
        'scheduled_by',
        'title',
        'round_number',
        'interview_type',
        'mode',
        'scheduled_at',
        'duration_minutes',
        'timezone',
        'meeting_url',
        'location',
        'candidate_instructions',
        'internal_agenda',
        'status',
        'cancellation_reason',
        'reminder_sent_at',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'reminder_sent_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function interviewerAssignments(): HasMany
    {
        return $this->hasMany(Interviewer::class);
    }

    public function panelUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'interviewers')
            ->withPivot(['is_lead', 'rsvp_status', 'assigned_at']);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(InterviewEvaluation::class);
    }
}
