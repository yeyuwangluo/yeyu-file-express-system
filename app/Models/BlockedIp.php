<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function matches(?string $ip, string $scope): bool
    {
        if (! $ip) {
            return false;
        }

        return static::query()
            ->where('ip', $ip)
            ->whereIn('scope', ['all', $scope])
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
