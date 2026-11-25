<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateSkill extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'candidate_id',
        'skill_name',
        'proficiency',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
