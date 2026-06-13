<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthCheck extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'checked_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
