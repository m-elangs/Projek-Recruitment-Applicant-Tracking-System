<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CandidateDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'candidate_id',
        'uploaded_by',
        'document_type',
        'original_name',
        'storage_disk',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'is_primary_cv',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'is_primary_cv' => 'boolean',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
