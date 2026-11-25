<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interviewer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'interview_id',
        'user_id',
        'is_lead',
        'rsvp_status',
        'assigned_at',
    ];

    protected $casts = [
        'is_lead' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
