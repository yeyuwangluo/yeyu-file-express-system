<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LanSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'files_json' => 'array',
        'receiver_joined' => 'boolean',
        'delivered_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function signals(): HasMany
    {
        return $this->hasMany(LanSignal::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
