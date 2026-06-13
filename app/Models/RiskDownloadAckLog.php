<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskDownloadAckLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'risk_score' => 'integer',
        'signature_expires_at' => 'datetime',
        'scan_checked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(SharedFile::class, 'file_id');
    }
}
