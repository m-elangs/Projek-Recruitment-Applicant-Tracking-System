<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOffer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'offer_number',
        'application_id',
        'created_by',
        'approved_by',
        'department_id',
        'position_title',
        'employment_type',
        'base_salary',
        'bonus_amount',
        'salary_currency',
        'pay_frequency',
        'benefits_summary',
        'proposed_start_date',
        'expires_at',
        'offer_letter_path',
        'status',
        'sent_at',
        'approved_at',
        'responded_at',
        'decline_reason',
        'candidate_notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'proposed_start_date' => 'date',
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
