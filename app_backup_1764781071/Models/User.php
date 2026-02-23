<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
    'name',
    'email',
        'google_id',
    'password',
    'google_id',
    'license_key',
    'license_status',
    'license_verified_at',
    'license_expires_at',
    'payment_id',
    'plan'
];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'license_verified_at' => 'datetime',
        'license_expires_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Check if user has active license
public function hasActiveLicense()
{
    // Debug log
    \Log::info('Checking active license for user', [
        'user_id' => $this->id,
        'license_status' => $this->license_status,
        'license_verified_at' => $this->license_verified_at,
        'license_expires_at' => $this->license_expires_at,
        'now' => now()
    ]);
    
    $hasActive = $this->license_status === 'active' 
        && $this->license_verified_at 
        && $this->license_expires_at 
        && $this->license_expires_at > now();
    
    \Log::info('Active license result', [
        'user_id' => $this->id,
        'has_active' => $hasActive
    ]);
    
    return $hasActive;
}

    // Check if license is pending (paid but not verified)
    public function hasPendingLicense()
    {
        return $this->license_status === 'pending' && $this->license_key;
    }

    // Check if can access dashboard
    public function canAccessDashboard()
    {
        return $this->hasActiveLicense();
    }
}
