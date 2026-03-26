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
        'status',
        'plan_type',
        'duration_days',
        'expires_at',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateLicenseKey(): string
    {
        do {
            $key = 'INFI-' . strtoupper(bin2hex(random_bytes(16)));
        } while (self::where('license_key', $key)->exists());

        return $key;
    }
}
