<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobRequisition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'requisition_code',
        'department_id',
        'requested_by',
        'approved_by',
        'title',
        'headcount',
        'employment_type',
        'work_arrangement',
        'location',
        'budget_min',
        'budget_max',
        'currency',
        'priority',
        'target_start_date',
        'justification',
        'requirements',
        'status',
        'rejection_reason',
        'approved_at',
    ];

    protected $casts = [
        'headcount' => 'integer',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'target_start_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'requisition_id');
    }
}
