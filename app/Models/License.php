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
        'status',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_lifetime' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateLicenseKey(): string
    {
        do {
            $key = 'INFIMAL-' . strtoupper(bin2hex(random_bytes(8)));
        } while (self::where('license_key', $key)->exists());

        return $key;
    }
}
