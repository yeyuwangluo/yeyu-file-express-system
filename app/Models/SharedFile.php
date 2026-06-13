<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SharedFile extends Model
{
    use SoftDeletes;

    protected $table = 'files';

    protected $guarded = [];

    protected $casts = [
        'has_extract_code' => 'boolean',
        'expires_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'deleted_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
        'scan_checked_at' => 'datetime',
        'malware_scan_checked_at' => 'datetime',
        'malware_scan_passed' => 'boolean',
        'malware_scan_details' => 'array',
        'size' => 'integer',
        'download_count' => 'integer',
        'download_bytes' => 'integer',
        'risk_score' => 'integer',
        'risk_reasons_json' => 'array',
    ];

    public function downloads(): HasMany
    {
        return $this->hasMany(FileDownload::class, 'file_id');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(FileUpload::class, 'file_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function publicStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }

    public function isScanPassed(): bool
    {
        return in_array($this->scan_status, ['clean', 'skipped'], true);
    }

    public function isScanPending(): bool
    {
        return $this->scan_status === 'pending';
    }

    public function isScanFailed(): bool
    {
        return in_array($this->scan_status, ['infected', 'error'], true);
    }

    public function getUploadNameAttribute(): string
    {
        return $this->original_name;
    }
}
