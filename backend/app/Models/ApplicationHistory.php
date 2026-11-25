<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'from_stage_id',
        'to_stage_id',
        'changed_by',
        'reason',
        'notes',
        'time_in_previous_stage_seconds',
        'created_at',
    ];

    protected $casts = [
        'time_in_previous_stage_seconds' => 'integer',
        'created_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentStage::class, 'to_stage_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
