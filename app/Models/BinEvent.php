<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BinEvent extends Model
{
    protected $fillable = [
        'bin_id',
        'location_x',
        'location_y',
        'type',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'occurred_at' => 'datetime',
        'location_x'  => 'float',
        'location_y'  => 'float',
    ];
}
