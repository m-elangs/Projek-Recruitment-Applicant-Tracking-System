<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentStage extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'display_order',
        'is_terminal',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_terminal' => 'boolean',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'current_stage_id');
    }
}
