<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThrottleSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'emails_per_minute',
        'sending_start_time',
        'sending_end_time'
    ];

    protected $casts = [
        'emails_per_minute' => 'integer',
        'sending_start_time' => 'string',
        'sending_end_time' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
