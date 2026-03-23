<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_key',
        'user_id',
        'plan_type',
        'price',
        'duration_days',
        'expires_at',
        'is_active',
        'is_lifetime',
        'features',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_lifetime' => 'boolean',
        'features' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateLicenseKey()
    {
        do {
            $key = strtoupper(bin2hex(random_bytes(16)));
        } while (self::where('license_key', $key)->exists());

        return $key;
    }
}
