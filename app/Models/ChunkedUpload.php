<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChunkedUpload extends Model
{
    protected $guarded = [];

    protected $casts = [
        'has_extract_code' => 'boolean',
        'total_size' => 'integer',
        'chunk_size' => 'integer',
        'total_chunks' => 'integer',
        'received_chunks' => 'integer',
        'received_bytes' => 'integer',
        'expire_days' => 'integer',
        'risk_score' => 'integer',
        'risk_reasons_json' => 'array',
        'expires_at' => 'datetime',
    ];

    public function completedFile(): BelongsTo
    {
        return $this->belongsTo(SharedFile::class, 'completed_file_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
