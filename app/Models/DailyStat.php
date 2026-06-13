<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyStat extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];
}
