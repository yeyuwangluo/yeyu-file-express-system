<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIScanLog extends Model
{
    use HasFactory;

    protected $table = 'ai_scan_logs';

    protected $fillable = [
        'file_id',
        'filename',
        'is_malicious',
        'threat_type',
        'confidence',
        'reason',
        'suspicious_code',
        'model',
        'scanner',
        'skipped',
    ];

    protected $casts = [
        'is_malicious' => 'boolean',
        'skipped' => 'boolean',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
