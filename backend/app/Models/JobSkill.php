<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSkill extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'job_id',
        'skill_name',
        'is_required',
        'created_at',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }
}
