<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseActivation extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'device_id',
        'device_name',
        'device_type',
        'device_fingerprint',
        'ip_address',
        'country',
        'city',
        'user_agent',
        'browser',
        'os',
        'platform',
        'last_active_at',
        'is_current',
        'activation_count',
        'metadata'
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'last_active_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get the license that owns the activation.
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * Scope for current activations.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope for device activations.
     */
    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Get activation location.
     */
    public function getLocationAttribute(): string
    {
        if ($this->country && $this->city) {
            return $this->city . ', ' . $this->country;
        } elseif ($this->country) {
            return $this->country;
        } else {
            return 'Unknown';
        }
    }

    /**
     * Get device info.
     */
    public function getDeviceInfoAttribute(): string
    {
        if ($this->device_name) {
            return $this->device_name . ' (' . ($this->device_type ?: 'Unknown') . ')';
        } elseif ($this->device_type) {
            return ucfirst($this->device_type) . ' Device';
        } else {
            return 'Unknown Device';
        }
    }

    /**
     * Check if activation is active (within last 24 hours).
     */
    public function getIsActiveAttribute(): bool
    {
        if (!$this->last_active_at) return false;
        return $this->last_active_at->diffInHours(now()) < 24;
    }

    /**
     * Get days since last activity.
     */
    public function getDaysInactiveAttribute(): ?int
    {
        if (!$this->last_active_at) return null;
        return $this->last_active_at->diffInDays(now());
    }
}
