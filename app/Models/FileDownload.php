<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileDownload extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'success' => 'boolean',
        'bytes' => 'integer',
        'created_at' => 'datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(SharedFile::class, 'file_id');
    }
}
