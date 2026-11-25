<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
        'updated_by',
    ];

    protected $casts = [
        'setting_value' => 'json',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $record = static::query()->where('setting_key', $key)->first();

        return $record ? $record->setting_value : $default;
    }
}
